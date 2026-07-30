<?php

declare(strict_types=1);

namespace Phore\Schema\Generator\JsonSchema;

use Phore\Schema\Parser\SchemaParser;
use Phore\Schema\Schema\ClassSchema;
use Phore\Schema\Schema\PropertySchema;
use Phore\Schema\Schema\Type\ArraySchemaType;
use Phore\Schema\Schema\Type\ClassReferenceSchemaType;
use Phore\Schema\Schema\Type\IntersectionSchemaType;
use Phore\Schema\Schema\Type\PrimitiveSchemaType;
use Phore\Schema\Schema\Type\SchemaType;
use Phore\Schema\Schema\Type\UnionSchemaType;

final class JsonClassSchemaGenerator
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $definitions = [];

    private JsonSchemaGeneratorOptions $options;

    /**
     * Erzeugt ein JSON Schema für einen abstrakten SchemaType.
     *
     * ClassSchema-Instanzen können dabei als Root- oder Untertyp verwendet werden. Über
     * JsonSchemaGeneratorOptions wird gesteuert, ob verschachtelte ClassSchemas inline
     * gerendert oder als einzelne Typen unter $defs abgelegt und per $ref referenziert
     * werden. Die Compatibility-Stufe reduziert den Output auf unterstützte JSON-Schema-
     * Features, z.B. für OpenAI Structured Outputs.
     *
     */
    public function generate(SchemaType $schemaType, ?JsonSchemaGeneratorOptions $options = null): JsonSchema
    {
        $this->definitions = [];
        $this->options = $options ?? new JsonSchemaGeneratorOptions();

        $jsonSchema = $this->typeToJsonSchema($schemaType, true, []);

        if ($this->options->shouldEmitSchemaKeyword()) {
            $jsonSchema['$schema'] = 'https://json-schema.org/draft/2020-12/schema';
        }

        if ($schemaType instanceof ClassSchema) {
            $jsonSchema['title'] = $schemaType->shortName;
        }

        if (!$this->options->effectiveMergeSubTypes() && $this->definitions !== []) {
            $jsonSchema['$defs'] = $this->definitions;
        }

        return new JsonSchema($jsonSchema);
    }

    /**
     * @param list<string> $stack
     * @return array<string, mixed>
     */
    private function typeToJsonSchema(SchemaType $type, bool $isRoot, array $stack): array
    {
        if ($type instanceof ClassSchema) {
            return $this->classSchemaToJsonSchema($type, $isRoot, $stack);
        }

        if ($type instanceof PrimitiveSchemaType) {
            return $this->primitiveToJsonSchema($type);
        }

        if ($type instanceof ArraySchemaType) {
            return $this->arrayToJsonSchema($type, $stack);
        }

        if ($type instanceof ClassReferenceSchemaType) {
            if ($this->options->effectiveInlineClassReferences() && class_exists($type->className) && !in_array($type->className, $stack, true)) {
                return $this->classSchemaToObjectSchema(
                    (new SchemaParser())->parseClass($type->className),
                    [...$stack, $type->className],
                );
            }

            return [
                'type' => 'object',
                'phpClass' => $type->className,
            ];
        }

        if ($type instanceof UnionSchemaType) {
            return [
                'anyOf' => array_map(
                    fn (SchemaType $innerType): array => $this->typeToJsonSchema($innerType, false, $stack),
                    $type->types,
                ),
            ];
        }

        if ($type instanceof IntersectionSchemaType) {
            $schemas = array_map(
                fn (SchemaType $innerType): array => $this->typeToJsonSchema($innerType, false, $stack),
                $type->types,
            );

            if ($this->options->supportsAllOf()) {
                return ['allOf' => $schemas];
            }

            return $schemas[0] ?? [];
        }

        return [];
    }

    /**
     * @param list<string> $stack
     * @return array<string, mixed>
     */
    private function classSchemaToJsonSchema(ClassSchema $schema, bool $isRoot, array $stack): array
    {
        $definitionName = $this->definitionName($schema->className);

        if (!$isRoot && !$this->options->effectiveMergeSubTypes()) {
            if (!isset($this->definitions[$definitionName])) {
                $this->definitions[$definitionName] = $this->classSchemaToObjectSchema($schema, [...$stack, $schema->className]);
            }

            return ['$ref' => '#/$defs/' . $definitionName];
        }

        if (in_array($schema->className, $stack, true)) {
            if ($this->options->supportsDefinitions()) {
                return ['$ref' => '#/$defs/' . $definitionName];
            }

            return [
                'type' => 'object',
                'description' => 'Recursive reference to ' . $schema->className,
            ];
        }

        return $this->classSchemaToObjectSchema($schema, [...$stack, $schema->className]);
    }

    /**
     * @param list<string> $stack
     * @return array<string, mixed>
     */
    private function classSchemaToObjectSchema(ClassSchema $schema, array $stack): array
    {
        $properties = [];
        $required = [];

        foreach ($schema->properties as $property) {
            $properties[$property->name] = $this->propertyToJsonSchema($property, $stack);

            if ($this->options->requiresAllPropertiesRequired() || (!$property->hasDefaultValue && !$property->allowsNull)) {
                $required[] = $property->name;
            }
        }

        $jsonSchema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];

        if ($schema->description !== '') {
            $jsonSchema['description'] = $schema->description;
        }

        if ($required !== []) {
            $jsonSchema['required'] = $required;
        }

        return $jsonSchema;
    }

    /**
     * @param list<string> $stack
     * @return array<string, mixed>
     */
    private function propertyToJsonSchema(PropertySchema $property, array $stack): array
    {
        $jsonSchema = $this->typeToJsonSchema($property->type, false, $stack);

        if ($property->description !== '') {
            $jsonSchema['description'] = $property->description;
        }

        if ($property->hasDefaultValue && $this->options->shouldEmitDefault()) {
            $jsonSchema['default'] = $property->defaultValue;
        }

        return $jsonSchema;
    }

    /**
     * @return array<string, mixed>
     */
    private function primitiveToJsonSchema(PrimitiveSchemaType $type): array
    {
        return match ($type->getKind()) {
            PrimitiveSchemaType::STRING, PrimitiveSchemaType::CALLABLE, PrimitiveSchemaType::RESOURCE => ['type' => 'string'],
            PrimitiveSchemaType::INT => ['type' => 'integer'],
            PrimitiveSchemaType::FLOAT => ['type' => 'number'],
            PrimitiveSchemaType::BOOL => ['type' => 'boolean'],
            PrimitiveSchemaType::NULL => ['type' => 'null'],
            PrimitiveSchemaType::OBJECT => ['type' => 'object'],
            PrimitiveSchemaType::ITERABLE => ['type' => 'array'],
            PrimitiveSchemaType::FALSE => ['const' => false],
            PrimitiveSchemaType::TRUE => ['const' => true],
            PrimitiveSchemaType::SCALAR => ['type' => ['string', 'integer', 'number', 'boolean']],
            PrimitiveSchemaType::NUMERIC => $this->numericToJsonSchema(),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function numericToJsonSchema(): array
    {
        if (!$this->options->supportsPattern()) {
            return ['anyOf' => [['type' => 'number'], ['type' => 'string']]];
        }

        return ['anyOf' => [['type' => 'number'], ['type' => 'string', 'pattern' => '^-?\\d+(\\.\\d+)?$']]];
    }

    /**
     * @param list<string> $stack
     * @return array<string, mixed>
     */
    private function arrayToJsonSchema(ArraySchemaType $type, array $stack): array
    {
        $valueSchema = $this->typeToJsonSchema($type->valueType, false, $stack);

        if ($type->arrayKind === ArraySchemaType::KIND_MAP) {
            return [
                'type' => 'object',
                'additionalProperties' => $valueSchema,
            ];
        }

        return [
            'type' => 'array',
            'items' => $valueSchema,
        ];
    }

    private function definitionName(string $className): string
    {
        $className = ltrim($className, '\\');
        if (str_contains($className, '\\')) {
            return str_replace('\\', '.', $className);
        }

        return $className;
    }
}

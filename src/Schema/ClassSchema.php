<?php

declare(strict_types=1);

namespace Phore\Schema\Schema;

use Phore\Schema\Generator\JsonSchema\JsonClassSchemaGenerator;
use Phore\Schema\Generator\JsonSchema\JsonSchema;
use Phore\Schema\Generator\JsonSchema\JsonSchemaGeneratorOptions;
use Phore\Schema\Schema\Type\SchemaType;

final class ClassSchema implements SchemaType
{
    /**
     * @param list<PropertySchema> $properties
     * @param array<string, list<string>> $tags
     */
    public function __construct(
        public readonly string $className,
        public readonly string $shortName,
        public readonly string $description = '',
        public readonly array $properties = [],
        public readonly array $tags = [],
    ) {
    }

    public function getKind(): string
    {
        return 'class';
    }

    /**
     * Erzeugt ein JSON Schema aus diesem ClassSchema.
     *
     * Über die Options werden Compatibility-Stufe und Behandlung von Untertypen gesteuert.
     *
     */
    public function toJsonSchema(?JsonSchemaGeneratorOptions $options = null): JsonSchema
    {
        return (new JsonClassSchemaGenerator())->generate($this, $options);
    }

    public function getProperty(string $name): ?PropertySchema
    {
        foreach ($this->properties as $property) {
            if ($property->name === $name) {
                return $property;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->getKind(),
            'className' => $this->className,
            'shortName' => $this->shortName,
            'description' => $this->description,
            'properties' => array_map(static fn (PropertySchema $property): array => $property->toArray(), $this->properties),
            'tags' => $this->tags,
        ];
    }
}

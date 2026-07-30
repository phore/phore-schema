<?php

declare(strict_types=1);

namespace Phore\Schema\Validator;

use InvalidArgumentException;
use Phore\Schema\Schema\ClassSchema;
use Phore\Schema\Schema\PropertySchema;
use Phore\Schema\Schema\Type\ArraySchemaType;
use Phore\Schema\Schema\Type\ClassReferenceSchemaType;
use Phore\Schema\Schema\Type\IntersectionSchemaType;
use Phore\Schema\Schema\Type\PrimitiveSchemaType;
use Phore\Schema\Schema\Type\SchemaType;
use Phore\Schema\Schema\Type\UnionSchemaType;
use stdClass;

final class Validator
{
    /**
     * @var list<string>
     */
    private array $errors = [];

    public function validate(SchemaType $schemaType, mixed $data): bool
    {
        $this->errors = [];
        $this->validateType($schemaType, $data, '$');

        return $this->errors === [];
    }

    public function assertValid(SchemaType $schemaType, mixed $data): void
    {
        if (!$this->validate($schemaType, $data)) {
            throw new InvalidArgumentException('Schema validation failed: ' . implode('; ', $this->errors));
        }
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function validateType(SchemaType $schemaType, mixed $data, string $path): void
    {
        if ($schemaType instanceof ClassSchema) {
            $this->validateClassSchema($schemaType, $data, $path);
            return;
        }

        if ($schemaType instanceof PrimitiveSchemaType) {
            $this->validatePrimitiveSchemaType($schemaType, $data, $path);
            return;
        }

        if ($schemaType instanceof ArraySchemaType) {
            $this->validateArraySchemaType($schemaType, $data, $path);
            return;
        }

        if ($schemaType instanceof ClassReferenceSchemaType) {
            $this->validateClassReferenceSchemaType($schemaType, $data, $path);
            return;
        }

        if ($schemaType instanceof UnionSchemaType) {
            $this->validateUnionSchemaType($schemaType, $data, $path);
            return;
        }

        if ($schemaType instanceof IntersectionSchemaType) {
            $this->validateIntersectionSchemaType($schemaType, $data, $path);
            return;
        }
    }

    private function validateClassSchema(ClassSchema $schema, mixed $data, string $path): void
    {
        if (!is_array($data) && !is_object($data)) {
            $this->addError($path, 'expected object/array structure');
            return;
        }

        $values = $this->structureToArray($data);

        foreach ($schema->properties as $property) {
            if (!array_key_exists($property->name, $values)) {
                if (!$property->hasDefaultValue && !$property->allowsNull) {
                    $this->addError($path . '.' . $property->name, 'required property is missing');
                }
                continue;
            }

            $value = $values[$property->name];
            if ($value === null && $property->allowsNull) {
                continue;
            }

            $this->validateProperty($property, $value, $path . '.' . $property->name);
        }

        foreach (array_keys($values) as $name) {
            if ($schema->getProperty((string)$name) === null) {
                $this->addError($path . '.' . $name, 'unknown property');
            }
        }
    }

    private function validateProperty(PropertySchema $property, mixed $value, string $path): void
    {
        $this->validateType($property->type, $value, $path);
    }

    private function validatePrimitiveSchemaType(PrimitiveSchemaType $schemaType, mixed $data, string $path): void
    {
        $valid = match ($schemaType->getKind()) {
            PrimitiveSchemaType::STRING => is_string($data),
            PrimitiveSchemaType::INT => is_int($data),
            PrimitiveSchemaType::FLOAT => is_float($data) || is_int($data),
            PrimitiveSchemaType::BOOL => is_bool($data),
            PrimitiveSchemaType::NULL => $data === null,
            PrimitiveSchemaType::MIXED => true,
            PrimitiveSchemaType::OBJECT => is_object($data) || is_array($data),
            PrimitiveSchemaType::CALLABLE => is_callable($data),
            PrimitiveSchemaType::ITERABLE => is_iterable($data),
            PrimitiveSchemaType::RESOURCE => is_resource($data),
            PrimitiveSchemaType::SCALAR => is_scalar($data),
            PrimitiveSchemaType::NUMERIC => is_numeric($data),
            PrimitiveSchemaType::FALSE => $data === false,
            PrimitiveSchemaType::TRUE => $data === true,
            default => true,
        };

        if (!$valid) {
            $this->addError($path, 'expected ' . $schemaType->getKind() . ', got ' . $this->typeName($data));
        }
    }

    private function validateArraySchemaType(ArraySchemaType $schemaType, mixed $data, string $path): void
    {
        if ($schemaType->arrayKind === ArraySchemaType::KIND_MAP && is_object($data)) {
            $data = $this->structureToArray($data);
        }

        if (!is_array($data)) {
            $this->addError($path, 'expected array, got ' . $this->typeName($data));
            return;
        }

        if ($schemaType->arrayKind === ArraySchemaType::KIND_LIST && !array_is_list($data)) {
            $this->addError($path, 'expected list array');
            return;
        }

        if ($schemaType->arrayKind === ArraySchemaType::KIND_MAP) {
            foreach (array_keys($data) as $key) {
                if (!is_string($key)) {
                    $this->addError($path, 'expected map with string keys');
                    return;
                }
            }
        }

        foreach ($data as $key => $value) {
            $this->validateType($schemaType->valueType, $value, $path . '[' . var_export($key, true) . ']');
        }
    }

    private function validateClassReferenceSchemaType(ClassReferenceSchemaType $schemaType, mixed $data, string $path): void
    {
        if ($schemaType->className !== '' && class_exists($schemaType->className) && !$data instanceof $schemaType->className) {
            $this->addError($path, 'expected instance of ' . $schemaType->className);
            return;
        }

        if (!is_object($data) && !is_array($data)) {
            $this->addError($path, 'expected class/object structure ' . $schemaType->className);
        }
    }

    private function validateUnionSchemaType(UnionSchemaType $schemaType, mixed $data, string $path): void
    {
        $errors = [];

        foreach ($schemaType->types as $innerType) {
            $validator = new self();
            if ($validator->validate($innerType, $data)) {
                return;
            }
            $errors = [...$errors, ...$validator->getErrors()];
        }

        $this->addError($path, 'did not match any union type' . ($errors === [] ? '' : ': ' . implode('; ', $errors)));
    }

    private function validateIntersectionSchemaType(IntersectionSchemaType $schemaType, mixed $data, string $path): void
    {
        foreach ($schemaType->types as $innerType) {
            $this->validateType($innerType, $data, $path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function structureToArray(array|object $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if ($data instanceof stdClass) {
            return get_object_vars($data);
        }

        return get_object_vars($data);
    }

    private function addError(string $path, string $message): void
    {
        $this->errors[] = $path . ': ' . $message;
    }

    private function typeName(mixed $data): string
    {
        return is_object($data) ? $data::class : gettype($data);
    }
}

<?php

declare(strict_types=1);

namespace Phore\Schema\Hydrator;

use Phore\Schema\Parser\SchemaParser;
use Phore\Schema\Schema\ClassSchema;
use Phore\Schema\Schema\PropertySchema;
use Phore\Schema\Schema\Type\ArraySchemaType;
use Phore\Schema\Schema\Type\ClassReferenceSchemaType;
use Phore\Schema\Schema\Type\IntersectionSchemaType;
use Phore\Schema\Schema\Type\PrimitiveSchemaType;
use Phore\Schema\Schema\Type\SchemaType;
use Phore\Schema\Schema\Type\UnionSchemaType;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Throwable;

final class Hydrator
{
    public function __construct(
        private readonly SchemaParser $schemaParser = new SchemaParser(),
    ) {
    }

    public function hydrate(SchemaType $schemaType, array|stdClass $data): mixed
    {
        return $this->hydrateType($schemaType, $data, '$');
    }

    private function hydrateType(SchemaType $schemaType, mixed $data, string $path): mixed
    {
        if ($schemaType instanceof ClassSchema) {
            return $this->hydrateClassSchema($schemaType, $data, $path);
        }

        if ($schemaType instanceof ClassReferenceSchemaType) {
            return $this->hydrateClassReferenceSchemaType($schemaType, $data, $path);
        }

        if ($schemaType instanceof ArraySchemaType) {
            return $this->hydrateArraySchemaType($schemaType, $data, $path);
        }

        if ($schemaType instanceof UnionSchemaType) {
            return $this->hydrateUnionSchemaType($schemaType, $data, $path);
        }

        if ($schemaType instanceof IntersectionSchemaType) {
            return $this->hydrateIntersectionSchemaType($schemaType, $data, $path);
        }

        if ($schemaType instanceof PrimitiveSchemaType) {
            return $this->hydratePrimitiveSchemaType($schemaType, $data, $path);
        }

        throw new HydrationException($path, 'unsupported schema type ' . $schemaType::class);
    }

    private function hydrateClassSchema(ClassSchema $schema, mixed $data, string $path): object
    {
        if ($schema->className !== '' && is_object($data) && $data instanceof $schema->className) {
            return $data;
        }

        if (!is_array($data) && !$data instanceof stdClass) {
            throw new HydrationException($path, 'expected object/array structure, got ' . $this->typeName($data));
        }

        if ($schema->className === '' || (!class_exists($schema->className) && !interface_exists($schema->className))) {
            throw new HydrationException($path, 'class does not exist: ' . $schema->className);
        }

        $values = $this->structureToArray($data);
        $this->assertNoUnknownProperties($schema, $values, $path);

        $hydratedValues = [];
        foreach ($schema->properties as $property) {
            if (!array_key_exists($property->name, $values)) {
                if ($property->hasDefaultValue) {
                    $hydratedValues[$property->name] = $property->defaultValue;
                    continue;
                }

                if ($property->allowsNull) {
                    $hydratedValues[$property->name] = null;
                    continue;
                }

                throw new HydrationException($this->propertyPath($path, $property->name), 'required property is missing');
            }

            $value = $values[$property->name];
            $propertyPath = $this->propertyPath($path, $property->name);
            if ($value === null && $property->allowsNull) {
                $hydratedValues[$property->name] = null;
                continue;
            }

            $hydratedValues[$property->name] = $this->hydrateType($property->type, $value, $propertyPath);
        }

        try {
            $reflectionClass = new ReflectionClass($schema->className);
            if (!$reflectionClass->isInstantiable()) {
                throw new HydrationException($path, 'class is not instantiable: ' . $schema->className);
            }

            $constructorParameterNames = [];
            $object = $this->instantiateClass($reflectionClass, $schema, $values, $hydratedValues, $path, $constructorParameterNames);
            $this->writePublicProperties($reflectionClass, $object, $schema, $hydratedValues, $constructorParameterNames, $path);

            return $object;
        } catch (HydrationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new HydrationException($path, 'failed to hydrate ' . $schema->className . ': ' . $throwable->getMessage(), $throwable);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function assertNoUnknownProperties(ClassSchema $schema, array $values, string $path): void
    {
        foreach (array_keys($values) as $name) {
            if ($schema->getProperty((string)$name) === null) {
                throw new HydrationException($this->propertyPath($path, (string)$name), 'unknown property');
            }
        }
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     * @param array<string, mixed> $rawValues
     * @param array<string, mixed> $hydratedValues
     * @param list<string> $constructorParameterNames
     */
    private function instantiateClass(
        ReflectionClass $reflectionClass,
        ClassSchema $schema,
        array $rawValues,
        array $hydratedValues,
        string $path,
        array &$constructorParameterNames,
    ): object {
        $constructor = $reflectionClass->getConstructor();
        if ($constructor === null) {
            return $reflectionClass->newInstanceWithoutConstructor();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $constructorParameterNames[] = $name;

            if (array_key_exists($name, $hydratedValues)) {
                $arguments[] = $hydratedValues[$name];
                continue;
            }

            if (array_key_exists($name, $rawValues)) {
                $arguments[] = $rawValues[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new HydrationException($this->propertyPath($path, $name), 'required constructor parameter is missing');
        }

        try {
            return $reflectionClass->newInstanceArgs($arguments);
        } catch (Throwable $throwable) {
            throw new HydrationException($path, 'constructor failed for ' . $schema->className . ': ' . $throwable->getMessage(), $throwable);
        }
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     * @param array<string, mixed> $hydratedValues
     * @param list<string> $constructorParameterNames
     */
    private function writePublicProperties(
        ReflectionClass $reflectionClass,
        object $object,
        ClassSchema $schema,
        array $hydratedValues,
        array $constructorParameterNames,
        string $path,
    ): void {
        foreach ($schema->properties as $propertySchema) {
            if (!array_key_exists($propertySchema->name, $hydratedValues)) {
                continue;
            }

            if (!$reflectionClass->hasProperty($propertySchema->name)) {
                continue;
            }

            $reflectionProperty = $reflectionClass->getProperty($propertySchema->name);
            if (!$reflectionProperty->isPublic() || $reflectionProperty->isStatic()) {
                continue;
            }

            if ($reflectionProperty->isReadOnly() && $reflectionProperty->isInitialized($object)) {
                continue;
            }

            if (in_array($propertySchema->name, $constructorParameterNames, true) && $reflectionProperty->isInitialized($object)) {
                continue;
            }

            try {
                $reflectionProperty->setValue($object, $hydratedValues[$propertySchema->name]);
            } catch (Throwable $throwable) {
                throw new HydrationException(
                    $this->propertyPath($path, $propertySchema->name),
                    'failed to set property: ' . $throwable->getMessage(),
                    $throwable,
                );
            }
        }
    }

    private function hydrateClassReferenceSchemaType(ClassReferenceSchemaType $schemaType, mixed $data, string $path): object
    {
        if ($schemaType->className !== '' && is_object($data) && $data instanceof $schemaType->className) {
            return $data;
        }

        if ($schemaType->className === '' || (!class_exists($schemaType->className) && !interface_exists($schemaType->className))) {
            throw new HydrationException($path, 'class does not exist: ' . $schemaType->className);
        }

        if (interface_exists($schemaType->className)) {
            throw new HydrationException($path, 'cannot hydrate interface reference: ' . $schemaType->className);
        }

        try {
            return $this->hydrateClassSchema($this->schemaParser->parseClass($schemaType->className), $data, $path);
        } catch (ReflectionException $exception) {
            throw new HydrationException($path, 'failed to parse class schema: ' . $exception->getMessage(), $exception);
        }
    }

    private function hydrateArraySchemaType(ArraySchemaType $schemaType, mixed $data, string $path): array
    {
        if ($schemaType->arrayKind === ArraySchemaType::KIND_MAP && $data instanceof stdClass) {
            $data = $this->structureToArray($data);
        }

        if (!is_array($data)) {
            throw new HydrationException($path, 'expected array, got ' . $this->typeName($data));
        }

        if ($schemaType->arrayKind === ArraySchemaType::KIND_LIST && !array_is_list($data)) {
            throw new HydrationException($path, 'expected list array');
        }

        if ($schemaType->arrayKind === ArraySchemaType::KIND_MAP) {
            foreach (array_keys($data) as $key) {
                if (!is_string($key)) {
                    throw new HydrationException($path, 'expected map with string keys');
                }
            }
        }

        $result = [];
        foreach ($data as $key => $value) {
            $this->hydrateType($schemaType->keyType, $key, $this->arrayKeyPath($path, $key, true));
            $result[$key] = $this->hydrateType($schemaType->valueType, $value, $this->arrayKeyPath($path, $key));
        }

        return $result;
    }

    private function hydrateUnionSchemaType(UnionSchemaType $schemaType, mixed $data, string $path): mixed
    {
        $errors = [];

        foreach ($schemaType->types as $innerType) {
            try {
                return $this->hydrateType($innerType, $data, $path);
            } catch (HydrationException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        throw new HydrationException($path, 'did not match any union type' . ($errors === [] ? '' : ': ' . implode('; ', $errors)));
    }

    private function hydrateIntersectionSchemaType(IntersectionSchemaType $schemaType, mixed $data, string $path): mixed
    {
        if ($schemaType->types === []) {
            return $data;
        }

        $result = $this->hydrateType($schemaType->types[0], $data, $path);
        foreach (array_slice($schemaType->types, 1) as $innerType) {
            $this->assertHydratedValueMatchesType($innerType, $result, $path);
        }

        return $result;
    }

    private function assertHydratedValueMatchesType(SchemaType $schemaType, mixed $value, string $path): void
    {
        if ($schemaType instanceof ClassReferenceSchemaType) {
            if ($schemaType->className !== '' && !$value instanceof $schemaType->className) {
                throw new HydrationException($path, 'expected instance of ' . $schemaType->className);
            }
            return;
        }

        $this->hydrateType($schemaType, $value, $path);
    }

    private function hydratePrimitiveSchemaType(PrimitiveSchemaType $schemaType, mixed $data, string $path): mixed
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
            throw new HydrationException($path, 'expected ' . $schemaType->getKind() . ', got ' . $this->typeName($data));
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function structureToArray(array|stdClass $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        return get_object_vars($data);
    }

    private function propertyPath(string $path, string $property): string
    {
        return $path . '.' . $property;
    }

    private function arrayKeyPath(string $path, int|string $key, bool $isKey = false): string
    {
        $keyPath = is_int($key) ? '[' . $key . ']' : '[' . var_export($key, true) . ']';

        return $isKey ? $path . $keyPath . '<key>' : $path . $keyPath;
    }

    private function typeName(mixed $data): string
    {
        return is_object($data) ? $data::class : gettype($data);
    }
}

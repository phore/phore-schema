<?php

declare(strict_types=1);

namespace Phore\Schema\Parser;

use Phore\Schema\Schema\Type\ArraySchemaType;
use Phore\Schema\Schema\Type\ClassReferenceSchemaType;
use Phore\Schema\Schema\Type\IntersectionSchemaType;
use Phore\Schema\Schema\Type\PrimitiveSchemaType;
use Phore\Schema\Schema\Type\SchemaType;
use Phore\Schema\Schema\Type\UnionSchemaType;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final class TypeParser
{
    public function fromReflectionType(?ReflectionType $type, string $contextClass = ''): SchemaType
    {
        if ($type === null) {
            return new PrimitiveSchemaType(PrimitiveSchemaType::MIXED);
        }

        if ($type instanceof ReflectionNamedType) {
            $schemaType = $this->fromNamedType($type->getName(), $type->isBuiltin(), $contextClass);

            if ($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null') {
                return $this->union([
                    $schemaType,
                    new PrimitiveSchemaType(PrimitiveSchemaType::NULL),
                ]);
            }

            return $schemaType;
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->union(array_map(
                fn (ReflectionType $unionType): SchemaType => $this->fromReflectionType($unionType, $contextClass),
                $type->getTypes(),
            ));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return new IntersectionSchemaType(array_map(
                fn (ReflectionType $intersectionType): SchemaType => $this->fromReflectionType($intersectionType, $contextClass),
                $type->getTypes(),
            ));
        }

        return new PrimitiveSchemaType(PrimitiveSchemaType::MIXED);
    }

    public function fromPhpDocType(string $type, string $contextClass = ''): SchemaType
    {
        $type = trim($type);
        if ($type === '') {
            return new PrimitiveSchemaType(PrimitiveSchemaType::MIXED);
        }

        if (str_starts_with($type, '?')) {
            return $this->union([
                $this->fromPhpDocType(substr($type, 1), $contextClass),
                new PrimitiveSchemaType(PrimitiveSchemaType::NULL),
            ]);
        }

        $unionParts = $this->splitTopLevel($type, '|');
        if (count($unionParts) > 1) {
            return $this->union(array_map(
                fn (string $part): SchemaType => $this->fromPhpDocType($part, $contextClass),
                $unionParts,
            ));
        }

        if (str_ends_with($type, '[]')) {
            return new ArraySchemaType(
                new PrimitiveSchemaType(PrimitiveSchemaType::INT),
                $this->fromPhpDocType(substr($type, 0, -2), $contextClass),
                ArraySchemaType::KIND_LIST,
            );
        }

        if (preg_match('/^([a-zA-Z_\\\\][a-zA-Z0-9_\\\\-]*)\s*<(.+)>$/', $type, $matches) === 1) {
            $genericType = strtolower($matches[1]);
            $genericParts = $this->splitTopLevel($matches[2], ',');

            if ($genericType === 'array' && count($genericParts) === 2) {
                return new ArraySchemaType(
                    $this->fromPhpDocType($genericParts[0], $contextClass),
                    $this->fromPhpDocType($genericParts[1], $contextClass),
                    strtolower($genericParts[0]) === PrimitiveSchemaType::STRING ? ArraySchemaType::KIND_MAP : ArraySchemaType::KIND_ARRAY,
                );
            }

            if (($genericType === 'list' || $genericType === 'iterable') && count($genericParts) === 1) {
                return new ArraySchemaType(
                    new PrimitiveSchemaType(PrimitiveSchemaType::INT),
                    $this->fromPhpDocType($genericParts[0], $contextClass),
                    ArraySchemaType::KIND_LIST,
                );
            }
        }

        return $this->fromNamedType($type, $this->isBuiltInName($type), $contextClass);
    }

    private function fromNamedType(string $name, bool $isBuiltIn, string $contextClass): SchemaType
    {
        $normalized = strtolower($name);

        return match ($normalized) {
            'string' => new PrimitiveSchemaType(PrimitiveSchemaType::STRING),
            'int', 'integer' => new PrimitiveSchemaType(PrimitiveSchemaType::INT),
            'float', 'double' => new PrimitiveSchemaType(PrimitiveSchemaType::FLOAT),
            'bool', 'boolean' => new PrimitiveSchemaType(PrimitiveSchemaType::BOOL),
            'null' => new PrimitiveSchemaType(PrimitiveSchemaType::NULL),
            'mixed', 'void', 'never' => new PrimitiveSchemaType(PrimitiveSchemaType::MIXED),
            'object' => new PrimitiveSchemaType(PrimitiveSchemaType::OBJECT),
            'callable' => new PrimitiveSchemaType(PrimitiveSchemaType::CALLABLE),
            'iterable' => new PrimitiveSchemaType(PrimitiveSchemaType::ITERABLE),
            'resource' => new PrimitiveSchemaType(PrimitiveSchemaType::RESOURCE),
            'scalar' => new PrimitiveSchemaType(PrimitiveSchemaType::SCALAR),
            'numeric' => new PrimitiveSchemaType(PrimitiveSchemaType::NUMERIC),
            'false' => new PrimitiveSchemaType(PrimitiveSchemaType::FALSE),
            'true' => new PrimitiveSchemaType(PrimitiveSchemaType::TRUE),
            'array' => new ArraySchemaType(
                new PrimitiveSchemaType(PrimitiveSchemaType::MIXED),
                new PrimitiveSchemaType(PrimitiveSchemaType::MIXED),
                ArraySchemaType::KIND_ARRAY,
            ),
            default => $isBuiltIn
                ? new PrimitiveSchemaType(PrimitiveSchemaType::MIXED)
                : new ClassReferenceSchemaType($this->resolveClassName($name, $contextClass)),
        };
    }

    private function isBuiltInName(string $name): bool
    {
        return in_array(strtolower($name), [
            'string', 'int', 'integer', 'float', 'double', 'bool', 'boolean', 'null', 'mixed', 'void', 'never', 'array', 'object', 'callable', 'iterable', 'resource', 'scalar', 'numeric', 'false', 'true',
        ], true);
    }

    private function resolveClassName(string $name, string $contextClass): string
    {
        $name = ltrim($name, '\\');
        if ($name === '') {
            return $name;
        }

        if (str_contains($name, '\\')) {
            return $name;
        }

        $namespace = '';
        if ($contextClass !== '' && str_contains($contextClass, '\\')) {
            $namespace = substr($contextClass, 0, strrpos($contextClass, '\\'));
        }

        $candidate = $namespace === '' ? $name : $namespace . '\\' . $name;

        if (class_exists($candidate) || interface_exists($candidate) || enum_exists($candidate)) {
            return $candidate;
        }

        return $name;
    }

    /**
     * @param list<SchemaType> $types
     */
    private function union(array $types): SchemaType
    {
        $flattened = [];
        $seen = [];

        foreach ($types as $type) {
            $innerTypes = $type instanceof UnionSchemaType ? $type->types : [$type];
            foreach ($innerTypes as $innerType) {
                $key = json_encode($innerType->toArray(), JSON_THROW_ON_ERROR);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $flattened[] = $innerType;
            }
        }

        if (count($flattened) === 1) {
            return $flattened[0];
        }

        return new UnionSchemaType($flattened);
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($char === '<') {
                $depth++;
            } elseif ($char === '>') {
                $depth = max(0, $depth - 1);
            }

            if ($char === $separator && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $parts[] = trim($current);

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}

<?php

declare(strict_types=1);

namespace Phore\Schema\Schema\Type;

/**
 * Beschreibt primitive PHP-Typen wie string, int, float, bool, null und mixed.
 *
 * Dieser Typ wird für skalare Reflection-Typen und einfache PHPDoc-Typnamen verwendet,
 * die keine Objekt- oder Array-Struktur besitzen.
 */
final class PrimitiveSchemaType implements SchemaType
{
    public const STRING = 'string';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const BOOL = 'bool';
    public const NULL = 'null';
    public const MIXED = 'mixed';
    public const OBJECT = 'object';
    public const CALLABLE = 'callable';
    public const ITERABLE = 'iterable';
    public const RESOURCE = 'resource';
    public const SCALAR = 'scalar';
    public const NUMERIC = 'numeric';
    public const FALSE = 'false';
    public const TRUE = 'true';

    public function __construct(
        private readonly string $kind,
    ) {
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
        ];
    }
}

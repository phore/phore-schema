<?php

declare(strict_types=1);

namespace Phore\Schema\Schema\Type;

/**
 * Beschreibt Array-, Listen- und Map-Typen inklusive Schlüssel- und Werttyp.
 *
 * Dieser Typ wird für native array-Typen sowie PHPDoc-Definitionen wie T[], list<T>
 * oder array<string, T> verwendet.
 */
final class ArraySchemaType implements SchemaType
{
    public const KIND_ARRAY = 'array';
    public const KIND_LIST = 'list';
    public const KIND_MAP = 'map';

    public function __construct(
        public readonly SchemaType $keyType,
        public readonly SchemaType $valueType,
        public readonly string $arrayKind = self::KIND_ARRAY,
    ) {
    }

    public function getKind(): string
    {
        return 'array';
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->getKind(),
            'arrayKind' => $this->arrayKind,
            'keyType' => $this->keyType->toArray(),
            'valueType' => $this->valueType->toArray(),
        ];
    }
}

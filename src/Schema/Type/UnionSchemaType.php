<?php

declare(strict_types=1);

namespace Phore\Schema\Schema\Type;

/**
 * Beschreibt eine Auswahl aus mehreren möglichen Typen.
 *
 * Dieser Typ wird für native PHP-Union-Types und nullable Typen wie string|null oder
 * ?string sowie entsprechende PHPDoc-Union-Typen verwendet.
 */
final class UnionSchemaType implements SchemaType
{
    /**
     * @param list<SchemaType> $types
     */
    public function __construct(
        public readonly array $types,
    ) {
    }

    public function getKind(): string
    {
        return 'union';
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->getKind(),
            'types' => array_map(static fn (SchemaType $type): array => $type->toArray(), $this->types),
        ];
    }
}

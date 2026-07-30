<?php

declare(strict_types=1);

namespace Phore\Schema\Schema\Type;

/**
 * Beschreibt eine Schnittmenge mehrerer gleichzeitig notwendiger Typen.
 *
 * Dieser Typ wird für native PHP-Intersection-Types wie A&B verwendet, wenn ein Wert
 * mehrere Interfaces oder Klassenverträge gleichzeitig erfüllen muss.
 */
final class IntersectionSchemaType implements SchemaType
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
        return 'intersection';
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->getKind(),
            'types' => array_map(static fn (SchemaType $type): array => $type->toArray(), $this->types),
        ];
    }
}

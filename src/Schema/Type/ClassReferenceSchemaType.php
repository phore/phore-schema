<?php

declare(strict_types=1);

namespace Phore\Schema\Schema\Type;

/**
 * Beschreibt eine Referenz auf eine PHP-Klasse, ein Interface oder ein Enum.
 *
 * Dieser Typ wird verwendet, wenn Reflection oder PHPDoc einen nicht-builtin Typ wie
 * MyDto, \Vendor\Package\Entity oder T_Account liefert.
 */
final class ClassReferenceSchemaType implements SchemaType
{
    public function __construct(
        public readonly string $className,
    ) {
    }

    public function getKind(): string
    {
        return 'class';
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->getKind(),
            'className' => $this->className,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Phore\Schema\Schema\Type;

/**
 * Gemeinsames Interface für alle abstrakten Schema-Typen.
 *
 * Implementierungen beschreiben, welcher PHP-/PHPDoc-Typ später z.B. in JSON Schema,
 * OpenAPI oder andere Schemaformate übersetzt werden kann.
 */
interface SchemaType
{
    public function getKind(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

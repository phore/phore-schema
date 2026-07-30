<?php

declare(strict_types=1);

use Phore\Schema\Parser\SchemaParser;
use Phore\Schema\Schema\ClassSchema;

/**
 * Erstellt ein abstraktes Klassen-Schema für eine PHP-Klasse oder ein Objekt.
 *
 * @param class-string|object $classNameOrObject
 */
function phore_schema_class(string|object $classNameOrObject): ClassSchema
{
    return (new SchemaParser())->parseClass($classNameOrObject);
}

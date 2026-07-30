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


/**
 * Hydriert ein Objekt aus einem Array oder stdClass.
 *
 * @template T of object
 * @param class-string<T> $className
 * @param array|stdClass $input
 * @return T
 * @throws ReflectionException
 */
function phore_schema_hydrate(string $className, array|stdClass $input): object
{
    return (new SchemaParser())->parseClass($className)->hydrate($input);
}
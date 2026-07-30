<?php

declare(strict_types=1);

use Phore\Schema\Parser\SchemaParser;
use PHPUnit\Framework\TestCase;

/**
 * Calculates score values.
 *
 * @param string $name User name.
 * @param array<string, int> $scores Score map.
 * @return array<string, int> Total scores.
 */
function schema_test_points(string $name, array $scores = []): array
{
    return $scores;
}

final class FunctionSchemaExample
{
    /**
     * Repeats a value.
     *
     * @param int $count Number of repetitions.
     * @param string|null $suffix Optional suffix.
     * @return string Repeated value.
     */
    public function repeat(int $count, ?string $suffix = null): string
    {
        return str_repeat($suffix ?? 'x', $count);
    }
}

final class FunctionSchemaTest extends TestCase
{
    public function testParsesFunctionSchemaWithParametersAndReturnValue(): void
    {
        $schema = (new SchemaParser())->parseFunction('schema_test_points');

        self::assertSame('function', $schema->getKind());
        self::assertSame('schema_test_points', $schema->name);
        self::assertSame('Calculates score values.', $schema->description);
        self::assertFalse($schema->isMethod);
        self::assertCount(2, $schema->parameters);

        $name = $schema->getParameter('name');
        self::assertNotNull($name);
        self::assertSame('string', $name->type->toArray()['kind']);
        self::assertSame('User name.', $name->description);
        self::assertSame('string', $name->nativeType);
        self::assertSame('string', $name->docType);
        self::assertFalse($name->allowsNull);

        $scores = $schema->getParameter('scores');
        self::assertNotNull($scores);
        self::assertSame('array', $scores->type->toArray()['kind']);
        self::assertSame('map', $scores->arrayKind);
        self::assertTrue($scores->isArray);
        self::assertTrue($scores->isMap);
        self::assertTrue($scores->hasDefaultValue);
        self::assertSame([], $scores->defaultValue);
        self::assertSame('array<string, int>', $scores->docType);

        self::assertSame('array<string, int>', $schema->return->docType);
        self::assertSame('Total scores.', $schema->return->description);
        self::assertSame('map', $schema->return->arrayKind);
        self::assertTrue($schema->return->isMap);
    }

    public function testParsesMethodSchemaViaCallable(): void
    {
        $schema = (new SchemaParser())->parseCallable([new FunctionSchemaExample(), 'repeat']);

        self::assertTrue($schema->isMethod);
        self::assertSame(FunctionSchemaExample::class, $schema->declaringClass);
        self::assertSame('repeat', $schema->name);
        self::assertSame('Repeats a value.', $schema->description);
        self::assertSame('Number of repetitions.', $schema->getParameter('count')?->description);
        self::assertSame('string|null', $schema->getParameter('suffix')?->docType);
        self::assertTrue($schema->getParameter('suffix')?->allowsNull);
        self::assertTrue($schema->getParameter('suffix')?->hasDefaultValue);
        self::assertNull($schema->getParameter('suffix')?->defaultValue);
        self::assertSame('string', $schema->return->docType);
        self::assertSame('Repeated value.', $schema->return->description);
    }
}

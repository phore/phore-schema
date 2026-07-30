<?php

declare(strict_types=1);

use Phore\Schema\Schema\ClassSchema;
use Phore\Schema\Schema\PropertySchema;
use Phore\Schema\Schema\Type\ArraySchemaType;
use Phore\Schema\Schema\Type\PrimitiveSchemaType;
use Phore\Schema\Schema\Type\UnionSchemaType;
use Phore\Schema\Validator\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidatesArrayStructureAgainstClassSchema(): void
    {
        $schema = $this->createUserSchema();
        $validator = new Validator();

        $valid = $validator->validate($schema, [
            'name' => 'Jane',
            'age' => 42,
            'active' => true,
            'tags' => ['admin', 'user'],
            'settings' => ['theme' => 'dark'],
        ]);

        self::assertTrue($valid);
        self::assertSame([], $validator->getErrors());
    }

    public function testValidatesObjectStructureAgainstClassSchema(): void
    {
        $schema = $this->createUserSchema();
        $validator = new Validator();

        $valid = $validator->validate($schema, (object)[
            'name' => 'Jane',
            'age' => null,
            'active' => true,
            'tags' => ['admin'],
            'settings' => (object)['theme' => 'dark'],
        ]);

        self::assertTrue($valid);
        self::assertSame([], $validator->getErrors());
    }

    public function testReportsValidationErrors(): void
    {
        $schema = $this->createUserSchema();
        $validator = new Validator();

        $valid = $validator->validate($schema, [
            'name' => 123,
            'tags' => ['admin' => 'yes'],
            'settings' => ['theme' => 123],
            'unknown' => true,
        ]);

        self::assertFalse($valid);
        self::assertContains('$ .name: expected string, got integer', $this->normalizeErrors($validator->getErrors()));
        self::assertContains('$ .active: required property is missing', $this->normalizeErrors($validator->getErrors()));
        self::assertContains('$ .tags: expected list array', $this->normalizeErrors($validator->getErrors()));
        self::assertContains('$ .settings[\'theme\']: expected string, got integer', $this->normalizeErrors($validator->getErrors()));
        self::assertContains('$ .unknown: unknown property', $this->normalizeErrors($validator->getErrors()));
    }

    private function createUserSchema(): ClassSchema
    {
        return new ClassSchema(
            className: 'App\\User',
            shortName: 'User',
            properties: [
                new PropertySchema('name', new PrimitiveSchemaType(PrimitiveSchemaType::STRING)),
                new PropertySchema(
                    'age',
                    new UnionSchemaType([
                        new PrimitiveSchemaType(PrimitiveSchemaType::INT),
                        new PrimitiveSchemaType(PrimitiveSchemaType::NULL),
                    ]),
                    allowsNull: true,
                ),
                new PropertySchema('active', new PrimitiveSchemaType(PrimitiveSchemaType::BOOL)),
                new PropertySchema(
                    'tags',
                    new ArraySchemaType(
                        new PrimitiveSchemaType(PrimitiveSchemaType::INT),
                        new PrimitiveSchemaType(PrimitiveSchemaType::STRING),
                        ArraySchemaType::KIND_LIST,
                    ),
                    hasDefaultValue: true,
                    defaultValue: [],
                    arrayKind: ArraySchemaType::KIND_LIST,
                    isArray: true,
                ),
                new PropertySchema(
                    'settings',
                    new ArraySchemaType(
                        new PrimitiveSchemaType(PrimitiveSchemaType::STRING),
                        new PrimitiveSchemaType(PrimitiveSchemaType::STRING),
                        ArraySchemaType::KIND_MAP,
                    ),
                    hasDefaultValue: true,
                    defaultValue: [],
                    arrayKind: ArraySchemaType::KIND_MAP,
                    isArray: true,
                    isMap: true,
                ),
            ],
        );
    }

    /**
     * @param list<string> $errors
     * @return list<string>
     */
    private function normalizeErrors(array $errors): array
    {
        return array_map(static fn (string $error): string => str_replace('$.', '$ .', $error), $errors);
    }
}

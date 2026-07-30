<?php

declare(strict_types=1);

use Phore\Schema\Parser\SchemaParser;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../examples/T_Account.php';
require_once __DIR__ . '/../examples/T_User.php';
require_once __DIR__ . '/../examples/T_User2.php';

final class SchemaParserTest extends TestCase
{
    public function testParsesPublicClassProperties(): void
    {
        $schema = (new SchemaParser())->parseClass(T_User::class);

        self::assertSame(T_User::class, $schema->className);
        self::assertCount(7, $schema->properties);
        self::assertSame('string', $schema->getProperty('username')?->type->toArray()['kind']);
        self::assertArrayHasKey('transient', $schema->getProperty('username')?->tags ?? []);
        self::assertSame('default', $schema->getProperty('email')?->defaultValue);
        self::assertTrue($schema->getProperty('email')?->hasDefaultValue);
        self::assertFalse($schema->getProperty('email')?->allowsNull);
        self::assertTrue($schema->getProperty('last_name')?->allowsNull);
        self::assertSame('T_Account', $schema->getProperty('account2')?->docType);
    }

    public function testParsesPhpDocArrayTypes(): void
    {
        $schema = (new SchemaParser())->parseClass(T_User::class);

        $accounts = $schema->getProperty('accounts');
        self::assertNotNull($accounts);
        self::assertSame('array', $accounts->type->toArray()['kind']);
        self::assertSame('list', $accounts->arrayKind);
        self::assertTrue($accounts->isArray);
        self::assertFalse($accounts->isMap);
        self::assertSame('list', $accounts->type->toArray()['arrayKind']);
        self::assertSame('int', $accounts->type->toArray()['keyType']['kind']);
        self::assertSame('class', $accounts->type->toArray()['valueType']['kind']);
        self::assertSame('T_Account', $accounts->type->toArray()['valueType']['className']);

        $accountMap = $schema->getProperty('accountMap');
        self::assertNotNull($accountMap);
        self::assertSame('map', $accountMap->arrayKind);
        self::assertTrue($accountMap->isArray);
        self::assertTrue($accountMap->isMap);
        self::assertSame('map', $accountMap->type->toArray()['arrayKind']);
        self::assertSame('string', $accountMap->type->toArray()['keyType']['kind']);
        self::assertSame('T_Account', $accountMap->type->toArray()['valueType']['className']);
    }

    public function testParsesPromotedConstructorProperties(): void
    {
        $schema = (new SchemaParser())->parseClass(T_User2::class);

        self::assertCount(5, $schema->properties);
        self::assertSame('default', $schema->getProperty('email')?->defaultValue);
        self::assertTrue($schema->getProperty('last_name')?->hasDefaultValue);
        self::assertNull($schema->getProperty('last_name')?->defaultValue);
        self::assertSame('T_Account[]', $schema->getProperty('accounts')?->docType);
    }

    public function testGlobalFunctionParsesClassSchema(): void
    {
        $schema = phore_schema_class(T_User::class);

        self::assertSame(T_User::class, $schema->className);
        self::assertNotNull($schema->getProperty('username'));
    }
}

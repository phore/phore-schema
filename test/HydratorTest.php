<?php

declare(strict_types=1);

use Phore\Schema\Hydrator\HydrationException;
use Phore\Schema\Hydrator\Hydrator;
use Phore\Schema\Parser\SchemaParser;
use PHPUnit\Framework\TestCase;

final class HydratorTestAddress
{
    public function __construct(
        public readonly string $city,
    ) {
    }
}

final class HydratorTestUser
{
    /** @var list<HydratorTestAddress> */
    public array $addresses = [];

    /** @var array<string, HydratorTestAddress> */
    public array $addressMap = [];

    public function __construct(
        public readonly string $name,
        public readonly ?int $age = null,
    ) {
    }
}

final class HydratorTest extends TestCase
{
    public function testHydratesClassSchemaRecursivelyFromArray(): void
    {
        $schema = (new SchemaParser())->parseClass(HydratorTestUser::class);

        $user = (new Hydrator())->hydrate($schema, [
            'name' => 'Jane',
            'age' => 42,
            'addresses' => [
                ['city' => 'Hamburg'],
                ['city' => 'Berlin'],
            ],
            'addressMap' => [
                'home' => ['city' => 'Munich'],
            ],
        ]);

        self::assertInstanceOf(HydratorTestUser::class, $user);
        self::assertSame('Jane', $user->name);
        self::assertSame(42, $user->age);
        self::assertContainsOnlyInstancesOf(HydratorTestAddress::class, $user->addresses);
        self::assertSame('Hamburg', $user->addresses[0]->city);
        self::assertSame('Munich', $user->addressMap['home']->city);
    }

    public function testHydratesClassSchemaRecursivelyFromStdClass(): void
    {
        $schema = (new SchemaParser())->parseClass(HydratorTestUser::class);

        $user = (new Hydrator())->hydrate($schema, (object)[
            'name' => 'Jane',
            'addresses' => [
                (object)['city' => 'Hamburg'],
            ],
            'addressMap' => (object)[
                'home' => (object)['city' => 'Munich'],
            ],
        ]);

        self::assertInstanceOf(HydratorTestUser::class, $user);
        self::assertNull($user->age);
        self::assertSame('Hamburg', $user->addresses[0]->city);
        self::assertSame('Munich', $user->addressMap['home']->city);
    }

    public function testClassSchemaHydrateConvenienceMethod(): void
    {
        $schema = (new SchemaParser())->parseClass(HydratorTestUser::class);

        $user = $schema->hydrate([
            'name' => 'Jane',
            'addresses' => [],
            'addressMap' => [],
        ]);

        self::assertInstanceOf(HydratorTestUser::class, $user);
        self::assertSame('Jane', $user->name);
    }

    public function testThrowsHydrationExceptionWithExactNestedPath(): void
    {
        $schema = (new SchemaParser())->parseClass(HydratorTestUser::class);

        try {
            (new Hydrator())->hydrate($schema, [
                'name' => 'Jane',
                'addresses' => [
                    ['city' => 123],
                ],
                'addressMap' => [],
            ]);
            self::fail('Expected HydrationException was not thrown.');
        } catch (HydrationException $exception) {
            self::assertSame('$.addresses[0].city', $exception->getPath());
            self::assertStringContainsString('expected string, got integer', $exception->getMessage());
        }
    }

    public function testThrowsHydrationExceptionForUnknownPropertyPath(): void
    {
        $schema = (new SchemaParser())->parseClass(HydratorTestUser::class);

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('$.unknown: unknown property');

        (new Hydrator())->hydrate($schema, [
            'name' => 'Jane',
            'addresses' => [],
            'addressMap' => [],
            'unknown' => true,
        ]);
    }
}

<?php

declare(strict_types=1);

use Phore\Schema\Generator\JsonSchema\JsonClassSchemaGenerator;
use Phore\Schema\Generator\JsonSchema\JsonSchema;
use Phore\Schema\Generator\JsonSchema\JsonSchemaCompatibility;
use Phore\Schema\Generator\JsonSchema\JsonSchemaGeneratorOptions;
use Phore\Schema\Schema\ClassSchema;
use Phore\Schema\Schema\PropertySchema;
use Phore\Schema\Schema\Type\ArraySchemaType;
use Phore\Schema\Schema\Type\ClassReferenceSchemaType;
use Phore\Schema\Schema\Type\PrimitiveSchemaType;
use PHPUnit\Framework\TestCase;

final readonly class JsonClassSchemaGeneratorInlineAddress
{
    public string $city;

    public int $zip;
}

final class JsonClassSchemaGeneratorTest extends TestCase
{
    public function testGeneratesJsonSchemaFromSchemaType(): void
    {
        $schema = new ClassSchema(
            className: 'App\\User',
            shortName: 'User',
            description: 'A user object.',
            properties: [
                new PropertySchema(
                    name: 'name',
                    type: new PrimitiveSchemaType(PrimitiveSchemaType::STRING),
                    description: 'The display name.',
                ),
                new PropertySchema(
                    name: 'tags',
                    type: new ArraySchemaType(
                        new PrimitiveSchemaType(PrimitiveSchemaType::INT),
                        new PrimitiveSchemaType(PrimitiveSchemaType::STRING),
                        ArraySchemaType::KIND_LIST,
                    ),
                    hasDefaultValue: true,
                    defaultValue: [],
                    arrayKind: ArraySchemaType::KIND_LIST,
                    isArray: true,
                ),
            ],
        );

        $jsonSchema = (new JsonClassSchemaGenerator())->generate($schema);
        $jsonSchemaFromClass = $schema->toJsonSchema();
        $jsonSchemaData = $jsonSchema->data();

        self::assertInstanceOf(JsonSchema::class, $jsonSchema);
        self::assertSame($jsonSchema->data(), $jsonSchemaFromClass->data());
        self::assertJson($jsonSchema->toString());
        self::assertSame($jsonSchemaData, $jsonSchema->toArray());
        self::assertSame('https://json-schema.org/draft/2020-12/schema', $jsonSchemaData['$schema']);
        self::assertSame('User', $jsonSchemaData['title']);
        self::assertSame('A user object.', $jsonSchemaData['description']);
        self::assertSame('string', $jsonSchemaData['properties']['name']['type']);
        self::assertSame('The display name.', $jsonSchemaData['properties']['name']['description']);
        self::assertSame(['name'], $jsonSchemaData['required']);
        self::assertSame('array', $jsonSchemaData['properties']['tags']['type']);
        self::assertSame('string', $jsonSchemaData['properties']['tags']['items']['type']);
        self::assertSame([], $jsonSchemaData['properties']['tags']['default']);
    }

    public function testCanReferenceOrMergeNestedClassSchemas(): void
    {
        [$userSchema] = $this->createNestedSchemas();

        $referenced = (new JsonClassSchemaGenerator())->generate($userSchema)->data();
        self::assertSame('#/$defs/App.Address', $referenced['properties']['address']['$ref']);
        self::assertSame('Postal address.', $referenced['$defs']['App.Address']['description']);

        $merged = (new JsonClassSchemaGenerator())->generate($userSchema, new JsonSchemaGeneratorOptions(mergeSubTypes: true))->data();
        self::assertSame('object', $merged['properties']['address']['type']);
        self::assertSame('Postal address.', $merged['properties']['address']['description']);
        self::assertArrayNotHasKey('$defs', $merged);
    }

    public function testOpenAiCompatibilityMergesSubTypesAndOmitsUnsupportedKeywords(): void
    {
        [$userSchema] = $this->createNestedSchemas();

        $jsonSchema = (new JsonClassSchemaGenerator())->generate(
            $userSchema,
            new JsonSchemaGeneratorOptions(JsonSchemaCompatibility::OpenAiStructuredOutput),
        )->data();

        self::assertArrayNotHasKey('$schema', $jsonSchema);
        self::assertArrayNotHasKey('$defs', $jsonSchema);
        self::assertSame(['address'], $jsonSchema['required']);
        self::assertSame('object', $jsonSchema['properties']['address']['type']);
        self::assertSame(['city'], $jsonSchema['properties']['address']['required']);
    }

    public function testCanInlineClassReferences(): void
    {
        $schema = new ClassSchema(
            className: 'App\\AddressBook',
            shortName: 'AddressBook',
            properties: [
                new PropertySchema(
                    name: 'addresses',
                    type: new ArraySchemaType(
                        new PrimitiveSchemaType(PrimitiveSchemaType::INT),
                        new ClassReferenceSchemaType(JsonClassSchemaGeneratorInlineAddress::class),
                        ArraySchemaType::KIND_LIST,
                    ),
                    arrayKind: ArraySchemaType::KIND_LIST,
                    isArray: true,
                ),
            ],
        );

        $referenced = (new JsonClassSchemaGenerator())->generate($schema)->data();
        self::assertSame(JsonClassSchemaGeneratorInlineAddress::class, $referenced['properties']['addresses']['items']['phpClass']);

        $inlined = (new JsonClassSchemaGenerator())->generate(
            $schema,
            new JsonSchemaGeneratorOptions(inlineClassReferences: true),
        )->data();
        $itemsSchema = $inlined['properties']['addresses']['items'];

        self::assertSame('object', $itemsSchema['type']);
        self::assertArrayNotHasKey('phpClass', $itemsSchema);
        self::assertSame(false, $itemsSchema['additionalProperties']);
        self::assertSame('string', $itemsSchema['properties']['city']['type']);
        self::assertSame('integer', $itemsSchema['properties']['zip']['type']);

        $openAiSchema = (new JsonClassSchemaGenerator())->generate(
            $schema,
            new JsonSchemaGeneratorOptions(JsonSchemaCompatibility::OpenAiStructuredOutput),
        )->data();
        self::assertArrayNotHasKey('phpClass', $openAiSchema['properties']['addresses']['items']);
    }

    /**
     * @return array{0: ClassSchema, 1: ClassSchema}
     */
    private function createNestedSchemas(): array
    {
        $addressSchema = new ClassSchema(
            className: 'App\\Address',
            shortName: 'Address',
            description: 'Postal address.',
            properties: [
                new PropertySchema('city', new PrimitiveSchemaType(PrimitiveSchemaType::STRING)),
            ],
        );
        $userSchema = new ClassSchema(
            className: 'App\\User',
            shortName: 'User',
            properties: [
                new PropertySchema('address', $addressSchema),
            ],
        );

        return [$userSchema, $addressSchema];
    }
}

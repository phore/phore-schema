<?php

declare(strict_types=1);

use Phore\Schema\Generator\JsonSchema\JsonSchemaCompatibility;
use Phore\Schema\Generator\JsonSchema\JsonSchemaGeneratorOptions;
use Phore\Schema\Hydrator\HydrationException;
use Phore\Schema\Parser\SchemaParser;
use Phore\Schema\Parser\TypeParser;
use Phore\Schema\Schema\Type\EnumSchemaType;
use Phore\Schema\Validator\Validator;
use PHPUnit\Framework\TestCase;

/** Publication state. */
enum DocumentedStatus: string
{
    /** Not yet public. */
    case Draft = 'draft';
    /**
     * Publicly visible.
     * Second line.
     * @deprecated Use draft instead.
     */
    case Published = 'published';
    case Zero = '0';
    case EmptyValue = '';
}

enum IntegerStatus: int { case One = 1; }
enum UnitStatus { case One; }
enum EmptyStatus: string {}
enum OtherStatus: string { case Draft = 'draft'; }

final class EnumArticle
{
    /** Article status. */
    public DocumentedStatus $status;
    public ?DocumentedStatus $optional = null;
    /** @var list<DocumentedStatus> */
    public array $history = [];
    /** @var array<string, DocumentedStatus> */
    public array $byName = [];
    public function __construct(public DocumentedStatus $initial = DocumentedStatus::Draft) {}
}

final class EnumWithStringDoc
{
    /** @var string */
    public DocumentedStatus $status;
}

final class EnumSchemaTest extends TestCase
{
    public function testParserPreservesValuesAndDocs(): void
    {
        $schema = (new SchemaParser())->parseClass(EnumArticle::class);
        $type = $schema->getProperty('status')->type;
        self::assertInstanceOf(EnumSchemaType::class, $type);
        self::assertSame(['draft', 'published', '0', ''], array_column($type->cases, 'value'));
        self::assertSame("Publicly visible.\nSecond line.", $type->cases[1]['description']);
        self::assertSame(['Use draft instead.'], $type->cases[1]['tags']['deprecated']);
        self::assertSame('', $type->cases[2]['description']);
        self::assertSame($type->toArray(), (new TypeParser())->fromPhpDocType(DocumentedStatus::class)->toArray());
        self::assertSame('enum', $schema->getProperty('history')->type->valueType->getKind());
        self::assertSame('enum', (new SchemaParser())->parseClass(EnumWithStringDoc::class)->getProperty('status')->type->getKind());
    }

    public function testJsonProfilesPreservePerValueDescriptions(): void
    {
        $schema = (new SchemaParser())->parseClass(EnumArticle::class);
        foreach (JsonSchemaCompatibility::cases() as $profile) {
            $json = $schema->toJsonSchema(new JsonSchemaGeneratorOptions($profile))->data();
            $status = $json['properties']['status'];
            self::assertSame('string', $status['type']);
            self::assertSame(['draft', 'published', '0', ''], $status['enum']);
            self::assertSame('Article status.', $status['description']);
            self::assertCount(4, $status['anyOf']);
            self::assertSame(['published'], $status['anyOf'][1]['enum']);
            self::assertStringContainsString('Publicly visible.', $status['anyOf'][1]['description']);
            self::assertStringContainsString('@deprecated Use draft instead.', $status['anyOf'][1]['description']);
            self::assertSame($profile === JsonSchemaCompatibility::JsonSchema202012, isset($status['anyOf'][1]['deprecated']));
            self::assertArrayNotHasKey('description', $status['anyOf'][2]);
            self::assertSame($status['enum'], $json['properties']['history']['items']['enum']);
            self::assertSame($status['enum'], $json['properties']['byName']['additionalProperties']['enum']);
            self::assertSame('null', $json['properties']['optional']['anyOf'][1]['type']);
            if ($profile === JsonSchemaCompatibility::OpenAiStructuredOutput) {
                self::assertArrayNotHasKey('default', $json['properties']['initial']);
            } else {
                self::assertSame('draft', $json['properties']['initial']['default']);
            }
        }
    }

    public function testValidationAndHydrationUseExactStrings(): void
    {
        $schema = (new SchemaParser())->parseClass(EnumArticle::class);
        $input = ['status' => 'published', 'history' => ['0', ''], 'byName' => ['first' => 'draft']];
        $validator = new Validator();
        self::assertTrue($validator->validate($schema, $input));
        $article = $schema->hydrate($input);
        self::assertSame(DocumentedStatus::Published, $article->status);
        self::assertSame([DocumentedStatus::Zero, DocumentedStatus::EmptyValue], $article->history);
        self::assertSame(DocumentedStatus::Draft, $article->byName['first']);
        self::assertSame(DocumentedStatus::Draft, $article->initial);
        self::assertNull($article->optional);
        self::assertTrue($validator->validate($schema, $article));
        self::assertSame(DocumentedStatus::Draft, $schema->hydrate(['status' => DocumentedStatus::Draft])->status);
        foreach (['unknown', 0, false, null, OtherStatus::Draft] as $bad) {
            self::assertFalse($validator->validate($schema, ['status' => $bad]));
        }
        try {
            $schema->hydrate(['status' => 'draft', 'history' => ['invalid']]);
            self::fail('Invalid enum value must fail hydration.');
        } catch (HydrationException $error) {
            self::assertSame('$.history[0]', $error->getPath());
        }
    }

    public function testUnsupportedEnumsFailExplicitly(): void
    {
        foreach ([IntegerStatus::class, UnitStatus::class, EmptyStatus::class] as $class) {
            try {
                (new TypeParser())->fromPhpDocType($class);
                self::fail('Unsupported enum must fail: ' . $class);
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString($class, $error->getMessage());
            }
        }
    }
}

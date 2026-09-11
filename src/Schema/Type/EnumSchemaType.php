<?php

declare(strict_types=1);

namespace Phore\Schema\Schema\Type;

use InvalidArgumentException;
use Phore\Schema\Parser\DocBlockParser;
use ReflectionEnum;

/** A string-backed enum and its documented cases, in declaration order. */
final class EnumSchemaType implements SchemaType
{
    public readonly string $className;
    public readonly string $description;
    public readonly array $tags;
    /** @var list<array{name: string, value: string, description: string, tags: array}> */
    public readonly array $cases;

    public function __construct(string $className)
    {
        $enum = new ReflectionEnum($className);
        if (!$enum->isBacked() || $enum->getBackingType()?->getName() !== 'string') {
            throw new InvalidArgumentException('Only string-backed enums are supported: ' . $className);
        }
        $parser = new DocBlockParser();
        $doc = $parser->parse($enum->getDocComment());
        $this->className = $enum->getName();
        $this->description = $doc->description;
        $this->tags = $doc->tags;
        $cases = [];
        foreach ($enum->getCases() as $case) {
            $doc = $parser->parse($case->getDocComment());
            $cases[] = [
                'name' => $case->getName(),
                'value' => $case->getBackingValue(),
                'description' => $doc->description,
                'tags' => $doc->tags,
            ];
        }
        if ($cases === []) {
            throw new InvalidArgumentException('Enum must contain at least one case: ' . $className);
        }
        $this->cases = $cases;
    }

    public function getKind(): string
    {
        return 'enum';
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->getKind(),
            'className' => $this->className,
            'backingType' => 'string',
            'description' => $this->description,
            'tags' => $this->tags,
            'cases' => $this->cases,
        ];
    }

    public function accepts(mixed $value): bool
    {
        return $value instanceof $this->className
            || (is_string($value) && in_array($value, array_column($this->cases, 'value'), true));
    }
}

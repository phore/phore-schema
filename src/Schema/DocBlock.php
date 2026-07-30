<?php

declare(strict_types=1);

namespace Phore\Schema\Schema;

final class DocBlock
{
    /**
     * @param array<string, list<string>> $tags
     */
    public function __construct(
        public readonly string $description = '',
        public readonly array $tags = [],
    ) {
    }

    public function firstTag(string $name): ?string
    {
        return $this->tags[$name][0] ?? null;
    }

    /**
     * @return array{description: string, tags: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'tags' => $this->tags,
        ];
    }
}

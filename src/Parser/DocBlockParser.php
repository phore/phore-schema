<?php

declare(strict_types=1);

namespace Phore\Schema\Parser;

use Phore\Schema\Schema\DocBlock;

final class DocBlockParser
{
    public function parse(string|false $docComment): DocBlock
    {
        if ($docComment === false || trim($docComment) === '') {
            return new DocBlock();
        }

        $lines = preg_split('/\R/', $docComment);
        if ($lines === false) {
            return new DocBlock();
        }

        $description = [];
        $tags = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\/\*\*|\*\/|^\*/', '', $line) ?? '';
            $line = trim($line);

            if ($line === '') {
                if ($description !== []) {
                    $description[] = '';
                }
                continue;
            }

            if (str_starts_with($line, '@')) {
                if (preg_match('/^@(\S+)(?:\s+(.*))?$/', $line, $matches) === 1) {
                    $tagName = $matches[1];
                    $tagValue = trim($matches[2] ?? '');
                    $tags[$tagName] ??= [];
                    $tags[$tagName][] = $tagValue;
                }
                continue;
            }

            $description[] = $line;
        }

        return new DocBlock($this->normalizeDescription($description), $tags);
    }

    /**
     * @param list<string> $lines
     */
    private function normalizeDescription(array $lines): string
    {
        while (($lines[0] ?? null) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return trim(implode("\n", $lines));
    }
}

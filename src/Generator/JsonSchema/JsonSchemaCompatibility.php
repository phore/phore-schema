<?php

declare(strict_types=1);

namespace Phore\Schema\Generator\JsonSchema;

enum JsonSchemaCompatibility: string
{
    case JsonSchema202012 = 'json-schema-2020-12';
    case OpenAiStructuredOutput = 'openai-structured-output';
    case Minimal = 'minimal';
}

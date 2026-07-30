<?php

declare(strict_types=1);

namespace Phore\Schema\Generator\JsonSchema;

final class JsonSchemaGeneratorOptions
{
    public function __construct(
        public readonly JsonSchemaCompatibility $compatibility = JsonSchemaCompatibility::JsonSchema202012,
        public readonly bool $mergeSubTypes = false,
    ) {
    }

    public function supportsDefinitions(): bool
    {
        return match ($this->compatibility) {
            JsonSchemaCompatibility::JsonSchema202012 => true,
            JsonSchemaCompatibility::OpenAiStructuredOutput, JsonSchemaCompatibility::Minimal => false,
        };
    }

    public function supportsAllOf(): bool
    {
        return match ($this->compatibility) {
            JsonSchemaCompatibility::JsonSchema202012 => true,
            JsonSchemaCompatibility::OpenAiStructuredOutput, JsonSchemaCompatibility::Minimal => false,
        };
    }

    public function supportsPattern(): bool
    {
        return match ($this->compatibility) {
            JsonSchemaCompatibility::JsonSchema202012, JsonSchemaCompatibility::OpenAiStructuredOutput => true,
            JsonSchemaCompatibility::Minimal => false,
        };
    }

    public function shouldEmitSchemaKeyword(): bool
    {
        return $this->compatibility === JsonSchemaCompatibility::JsonSchema202012;
    }

    public function requiresAllPropertiesRequired(): bool
    {
        return $this->compatibility === JsonSchemaCompatibility::OpenAiStructuredOutput;
    }

    public function shouldEmitDefault(): bool
    {
        return $this->compatibility !== JsonSchemaCompatibility::OpenAiStructuredOutput;
    }

    public function effectiveMergeSubTypes(): bool
    {
        return $this->mergeSubTypes || !$this->supportsDefinitions();
    }
}

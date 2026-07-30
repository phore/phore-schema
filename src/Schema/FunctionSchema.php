<?php

declare(strict_types=1);

namespace Phore\Schema\Schema;

use Phore\Schema\Schema\Type\SchemaType;

final class FunctionSchema implements SchemaType
{
    /**
     * @param list<FunctionParameterSchema> $parameters
     * @param array<string, list<string>> $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly FunctionReturnSchema $return,
        public readonly string $description = '',
        public readonly array $parameters = [],
        public readonly array $tags = [],
        public readonly ?string $declaringClass = null,
        public readonly bool $isMethod = false,
    ) {
    }

    public function getKind(): string
    {
        return 'function';
    }

    public function getParameter(string $name): ?FunctionParameterSchema
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->name === $name) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->getKind(),
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => array_map(static fn (FunctionParameterSchema $parameter): array => $parameter->toArray(), $this->parameters),
            'return' => $this->return->toArray(),
            'tags' => $this->tags,
            'declaringClass' => $this->declaringClass,
            'isMethod' => $this->isMethod,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Phore\Schema\Schema;

use Phore\Schema\Schema\Type\SchemaType;

final class FunctionParameterSchema
{
    public function __construct(
        public readonly string $name,
        public readonly SchemaType $type,
        public readonly string $description = '',
        public readonly ?string $nativeType = null,
        public readonly ?string $docType = null,
        public readonly bool $allowsNull = false,
        public readonly bool $hasDefaultValue = false,
        public readonly mixed $defaultValue = null,
        public readonly bool $isVariadic = false,
        public readonly bool $isPassedByReference = false,
        public readonly ?string $arrayKind = null,
        public readonly bool $isArray = false,
        public readonly bool $isMap = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->toArray(),
            'description' => $this->description,
            'nativeType' => $this->nativeType,
            'docType' => $this->docType,
            'allowsNull' => $this->allowsNull,
            'hasDefaultValue' => $this->hasDefaultValue,
            'defaultValue' => $this->hasDefaultValue ? $this->defaultValue : null,
            'isVariadic' => $this->isVariadic,
            'isPassedByReference' => $this->isPassedByReference,
            'arrayKind' => $this->arrayKind,
            'isArray' => $this->isArray,
            'isMap' => $this->isMap,
        ];
    }
}

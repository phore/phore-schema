<?php

declare(strict_types=1);

namespace Phore\Schema\Schema;

use Phore\Schema\Schema\Type\SchemaType;

final class FunctionReturnSchema
{
    public function __construct(
        public readonly SchemaType $type,
        public readonly string $description = '',
        public readonly ?string $nativeType = null,
        public readonly ?string $docType = null,
        public readonly bool $allowsNull = false,
        public readonly bool $isVoid = false,
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
            'type' => $this->type->toArray(),
            'description' => $this->description,
            'nativeType' => $this->nativeType,
            'docType' => $this->docType,
            'allowsNull' => $this->allowsNull,
            'isVoid' => $this->isVoid,
            'arrayKind' => $this->arrayKind,
            'isArray' => $this->isArray,
            'isMap' => $this->isMap,
        ];
    }
}

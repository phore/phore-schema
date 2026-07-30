<?php

declare(strict_types=1);

namespace Phore\Schema\Generator\JsonSchema;

use JsonException;
use JsonSerializable;

final class JsonSchema implements JsonSerializable
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    /**
     * Liefert die JSON-Schema-Daten als PHP-Array.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Alias für data(), passend zu anderen Schema-Objekten der Library.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data();
    }

    /**
     * Serialisiert das JSON Schema als JSON-String.
     *
     * @throws JsonException
     */
    public function toString(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->data, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data();
    }

    public function __toString(): string
    {
        try {
            return $this->toString();
        } catch (JsonException) {
            return '{}';
        }
    }
}

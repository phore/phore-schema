<?php

declare(strict_types=1);

namespace Phore\Schema\Hydrator;

use RuntimeException;
use Throwable;

final class HydrationException extends RuntimeException
{
    public function __construct(
        private readonly string $path,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($path . ': ' . $message, previous: $previous);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}

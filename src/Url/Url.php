<?php

declare(strict_types=1);

namespace Lexbor\Url;

final class Url
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public readonly string $scheme,
        public readonly string $host,
        public readonly string $path,
        private readonly array $errors = [],
    ) {
    }

    public function serialize(): string
    {
        return "{$this->scheme}://{$this->host}{$this->path}";
    }

    /**
     * @return list<ValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

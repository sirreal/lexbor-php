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
        public readonly string $username,
        public readonly string $password,
        public readonly string $host,
        public readonly ?int $port,
        public readonly string $path,
        public readonly ?string $query = null,
        public readonly ?string $fragment = null,
        private readonly array $errors = [],
    ) {
    }

    public function serialize(): string
    {
        $authority = $this->host;

        if ($this->username !== '' || $this->password !== '') {
            $authority = $this->username
                . ($this->password !== '' ? ":{$this->password}" : '')
                . "@{$authority}";
        }

        if ($this->port !== null) {
            $authority .= ":{$this->port}";
        }

        return "{$this->scheme}://{$authority}{$this->path}"
            . ($this->query !== null ? "?{$this->query}" : '')
            . ($this->fragment !== null ? "#{$this->fragment}" : '');
    }

    /**
     * @return list<ValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

<?php

declare(strict_types=1);

namespace Lexbor\Url;

final class Url
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public string $scheme,
        public readonly string $username,
        public readonly string $password,
        public string $host,
        public ?int $port,
        public readonly string $path,
        public readonly ?string $query = null,
        public readonly ?string $fragment = null,
        private readonly array $errors = [],
    ) {
    }

    public function setProtocol(string $protocol): bool
    {
        $protocol = str_replace(["\t", "\n", "\r"], '', $protocol);

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*)(?::|$)/', $protocol, $matches) !== 1) {
            return false;
        }

        $scheme = strtolower($matches[1]);

        if ($scheme === $this->scheme) {
            $this->clearDefaultPort();
            return true;
        }

        if ($scheme === 'file' || $this->scheme === 'file') {
            return true;
        }

        if ($this->isSpecialScheme($scheme) !== $this->isSpecialScheme($this->scheme)) {
            return true;
        }

        $this->scheme = $scheme;
        $this->clearDefaultPort();

        return true;
    }

    public function setHostname(string $hostname): bool
    {
        if ($hostname === '' && in_array($this->scheme, ['ftp', 'http', 'https', 'ws', 'wss'], true)) {
            return false;
        }

        $this->host = strtolower($hostname);

        return true;
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

    private function isSpecialScheme(string $scheme): bool
    {
        return $scheme === 'file' || in_array($scheme, ['ftp', 'http', 'https', 'ws', 'wss'], true);
    }

    private function defaultPort(string $scheme): ?int
    {
        return match ($scheme) {
            'ftp' => 21,
            'http', 'ws' => 80,
            'https', 'wss' => 443,
            default => null,
        };
    }

    private function clearDefaultPort(): void
    {
        if ($this->port === $this->defaultPort($this->scheme)) {
            $this->port = null;
        }
    }
}

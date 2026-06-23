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
        public string $username,
        public string $password,
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

    public function setUsername(string $username): bool
    {
        if (! $this->canHaveCredentials()) {
            return true;
        }

        $this->username = $this->percentEncodeUserInfo($username);

        return true;
    }

    public function setPassword(string $password): bool
    {
        if (! $this->canHaveCredentials()) {
            return true;
        }

        $this->password = $this->percentEncodeUserInfo($password);

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

    private function canHaveCredentials(): bool
    {
        return $this->host !== '' && $this->scheme !== 'file';
    }

    private function percentEncodeUserInfo(string $value): string
    {
        $encoded = '';
        $length = strlen($value);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($value[$offset]);

            if ($this->isUserInfoByteAllowed($byte)) {
                $encoded .= $value[$offset];
                continue;
            }

            $encoded .= sprintf('%%%02X', $byte);
        }

        return $encoded;
    }

    private function isUserInfoByteAllowed(int $byte): bool
    {
        return $byte >= 0x21
            && $byte <= 0x7E
            && ! in_array(
                $byte,
                [
                    0x22,
                    0x23,
                    0x2F,
                    0x3A,
                    0x3B,
                    0x3C,
                    0x3D,
                    0x3E,
                    0x3F,
                    0x40,
                    0x5B,
                    0x5C,
                    0x5D,
                    0x5E,
                    0x60,
                    0x7B,
                    0x7C,
                    0x7D,
                ],
                true,
            );
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

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

    public function setHost(string $host): bool
    {
        if ($host === '' && $this->hasCredentialsOrPort()) {
            return true;
        }

        if ($this->isInvalidReplacementHost($host, true)) {
            return false;
        }

        if ($this->shouldIgnoreFileHostPort($host)) {
            return true;
        }

        $url = $this->parseWithReplacementHost($host, true);

        if ($url === null) {
            return false;
        }

        $this->host = $url->host;

        if ($this->hasExplicitPort($host)) {
            $this->port = $url->port === $this->defaultPort($this->scheme) ? null : $url->port;
        }

        return true;
    }

    public function setHostname(string $hostname): bool
    {
        if ($hostname === '' && $this->hasCredentialsOrPort()) {
            return true;
        }

        if ($this->isInvalidReplacementHost($hostname, false)) {
            return false;
        }

        if ($this->shouldIgnoreFileHostPort($hostname)) {
            return true;
        }

        $url = $this->parseWithReplacementHost($hostname, false);

        if ($url === null) {
            return false;
        }

        $this->host = $url->host;

        return true;
    }

    public function setPort(string $port): bool
    {
        if (! $this->canHaveCredentials()) {
            return true;
        }

        if ($port === '') {
            $this->port = null;
            return true;
        }

        if (! ctype_digit($port) || ! $this->isValidPort($port)) {
            return false;
        }

        $portNumber = (int) $this->normalizePort($port);
        $this->port = $portNumber === $this->defaultPort($this->scheme) ? null : $portNumber;

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

    private function hasCredentialsOrPort(): bool
    {
        return $this->username !== '' || $this->password !== '' || $this->port !== null;
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

    private function parseWithReplacementHost(string $host, bool $allowPort): ?self
    {
        if ($this->isInvalidReplacementHost($host, $allowPort)) {
            return null;
        }

        $hostForParsing = $this->hostForParsing($host);

        if ($this->scheme === 'file' && preg_match('/^[A-Za-z][|:]$/', $hostForParsing) === 1) {
            return null;
        }

        return (new Parser())->parse("{$this->scheme}://{$hostForParsing}");
    }

    private function isInvalidReplacementHost(string $host, bool $allowPort): bool
    {
        if ($this->hasInvalidHostCodePoint($host)
            || $this->hasFileDrivePrefix($host)
            || $this->hasAuthorityDelimiter($host)
            || $this->hasMalformedPortSeparator($host)
            || ! $this->hasValidExplicitPort($host)
        ) {
            return true;
        }

        return ! $allowPort && ! $this->shouldIgnoreFileHostPort($host) && $this->hasPortSeparator($host);
    }

    private function hasInvalidHostCodePoint(string $host): bool
    {
        $length = strlen($host);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($host[$offset]);

            if ($byte <= 0x20 || $byte === 0x7F) {
                return true;
            }
        }

        return false;
    }

    private function hasAuthorityDelimiter(string $host): bool
    {
        return str_contains($host, '/')
            || str_contains($host, '?')
            || str_contains($host, '#')
            || str_contains($host, '@')
            || str_contains($host, '\\');
    }

    private function shouldIgnoreFileHostPort(string $host): bool
    {
        return $this->scheme === 'file'
            && ! $this->hasFileDrivePrefix($host)
            && $this->hasPortSeparator($host);
    }

    private function hasFileDrivePrefix(string $host): bool
    {
        return $this->scheme === 'file' && preg_match('/^[A-Za-z][|:]/', $host) === 1;
    }

    private function hasPortSeparator(string $host): bool
    {
        if (! str_starts_with($host, '[')) {
            return str_contains($host, ':');
        }

        $close = strpos($host, ']');

        return $close === false || substr($host, $close + 1) !== '';
    }

    private function hasMalformedPortSeparator(string $host): bool
    {
        if (str_starts_with($host, '[')) {
            $close = strpos($host, ']');

            if ($close === false) {
                return true;
            }

            $suffix = substr($host, $close + 1);

            if ($suffix === '') {
                return false;
            }

            if (! str_starts_with($suffix, ':')) {
                return true;
            }

            $port = substr($suffix, 1);

            return $port !== '' && ! ctype_digit($port);
        }

        $colon = strpos($host, ':');

        if ($colon === false) {
            return false;
        }

        if (strpos($host, ':', $colon + 1) !== false) {
            return true;
        }

        $port = substr($host, $colon + 1);

        return $port !== '' && ! ctype_digit($port);
    }

    private function hasExplicitPort(string $host): bool
    {
        return $this->explicitPortString($host) !== null;
    }

    private function hasValidExplicitPort(string $host): bool
    {
        $port = $this->explicitPortString($host);

        if ($port === null) {
            return true;
        }

        return $this->isValidPort($port);
    }

    private function isValidPort(string $port): bool
    {
        $normalized = $this->normalizePort($port);

        return strlen($normalized) <= 5 && (int) $normalized <= 65535;
    }

    private function normalizePort(string $port): string
    {
        $normalized = ltrim($port, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    private function hostForParsing(string $host): string
    {
        if (! str_ends_with($host, ':')) {
            return $host;
        }

        if (str_starts_with($host, '[')) {
            $close = strpos($host, ']');

            if ($close !== false && $close === strlen($host) - 2) {
                return substr($host, 0, -1);
            }

            return $host;
        }

        $hostWithoutColon = substr($host, 0, -1);

        return str_contains($hostWithoutColon, ':') ? $host : $hostWithoutColon;
    }

    private function explicitPortString(string $host): ?string
    {
        if (str_starts_with($host, '[')) {
            $close = strpos($host, ']');

            if ($close === false || ! str_starts_with(substr($host, $close + 1), ':')) {
                return null;
            }

            $port = substr($host, $close + 2);

            return ctype_digit($port) ? $port : null;
        }

        $colon = strrpos($host, ':');

        if ($colon === false) {
            return null;
        }

        $port = substr($host, $colon + 1);

        return ctype_digit($port) ? $port : null;
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

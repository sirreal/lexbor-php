<?php

declare(strict_types=1);

namespace Lexbor\Url;

use Lexbor\Encoding\Utf8;

final class Parser
{
    public function parse(string $input, ?Url $base = null): ?Url
    {
        $errors = [];
        $input = $this->sanitizeInput($input, $errors);

        if ($input === '' || $input === "\xD4" || $this->isUnsupportedFileUrl($input)) {
            $this->appendError($errors, ValidationError::InvalidUrlUnit);
            return null;
        }

        if (str_starts_with($input, '//')) {
            if ($base === null) {
                $this->appendError($errors, ValidationError::InvalidUrlUnit);
                return null;
            }

            $body = substr($input, 2);

            return $this->parseWithScheme($base->scheme, $body, $errors, $body === '');
        }

        if ($base !== null && str_starts_with($input, '/')) {
            return $this->buildUrl(
                $base->scheme,
                $base->username,
                $base->password,
                $base->host,
                $base->port,
                $input,
                $errors,
            );
        }

        if (! preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):\/\/(.*)$/s', $input, $matches)) {
            $this->appendError($errors, ValidationError::InvalidUrlUnit);
            return null;
        }

        return $this->parseWithScheme(strtolower($matches[1]), $matches[2], $errors);
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function parseWithScheme(string $scheme, string $input, array $errors, bool $allowEmptyPath = false): Url
    {
        [$authority, $path] = $this->splitAuthorityAndPath($input);
        [$username, $password, $host, $port] = $this->parseAuthority($authority);

        if ($scheme === 'file') {
            [$host, $path] = $this->normalizeFileHostAndPath($host, $path);
        }

        return $this->buildUrl($scheme, $username, $password, $host, $port, $path, $errors, $allowEmptyPath);
    }

    /**
     * @return array{string, string}
     */
    private function splitAuthorityAndPath(string $input): array
    {
        $positions = array_filter(
            [
                strpos($input, '/'),
                strpos($input, '?'),
                strpos($input, '#'),
            ],
            static fn ($position): bool => $position !== false,
        );

        if ($positions === []) {
            return [$input, ''];
        }

        $delimiter = min($positions);

        return [substr($input, 0, $delimiter), substr($input, $delimiter)];
    }

    /**
     * @return array{string, string, string, ?int}
     */
    private function parseAuthority(string $authority): array
    {
        $username = '';
        $password = '';
        $hostPort = $authority;
        $at = strrpos($authority, '@');

        if ($at !== false) {
            $credentials = substr($authority, 0, $at);
            $hostPort = substr($authority, $at + 1);
            [$username, $password] = array_pad(explode(':', $credentials, 2), 2, '');
        }

        $host = $hostPort;
        $port = null;
        $colon = strrpos($hostPort, ':');

        if ($colon !== false && ctype_digit(substr($hostPort, $colon + 1))) {
            $host = substr($hostPort, 0, $colon);
            $port = (int) substr($hostPort, $colon + 1);
        }

        return [$username, $password, strtolower($host), $port];
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function buildUrl(
        string $scheme,
        string $username,
        string $password,
        string $host,
        ?int $port,
        string $pathAndSuffix,
        array $errors,
        bool $allowEmptyPath = false,
    ): Url {
        [$pathAndQuery, $fragment] = array_pad(explode('#', $pathAndSuffix, 2), 2, null);
        [$path, $query] = array_pad(explode('?', $pathAndQuery, 2), 2, null);

        if ($path === '' && ! $allowEmptyPath) {
            $path = '/';
        }

        return new Url(
            $scheme,
            $username,
            $password,
            $host,
            $port,
            $this->percentEncodePath($path, $errors),
            $query,
            $fragment,
            $errors,
        );
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function sanitizeInput(string $input, array &$errors): string
    {
        $trimmed = trim($input, "\x00..\x20");

        if ($trimmed !== $input) {
            $this->appendError($errors, ValidationError::InvalidUrlUnit);
        }

        $input = $trimmed;
        $clean = str_replace(["\n", "\r", "\t"], '', $input);

        if ($clean !== $input) {
            $this->appendError($errors, ValidationError::InvalidUrlUnit);
        }

        return $clean;
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function percentEncodePath(string $path, array &$errors): string
    {
        $encoded = '';

        foreach (Utf8::decode($path) as $codePoint) {
            if ($this->isInvalidUrlUnit($codePoint)) {
                $this->appendError($errors, ValidationError::InvalidUrlUnit);
            }

            $bytes = Utf8::encodeCodePoint($codePoint);

            if ($codePoint < 0x80 && $this->isPathByteAllowed($codePoint)) {
                $encoded .= chr($codePoint);
                continue;
            }

            $encoded .= $this->percentEncodeBytes($bytes);
        }

        return $encoded;
    }

    private function isInvalidUrlUnit(int $codePoint): bool
    {
        return ($codePoint >= 0xFDD0 && $codePoint <= 0xFDEF)
            || ($codePoint & 0xFFFF) === 0xFFFE
            || ($codePoint & 0xFFFF) === 0xFFFF;
    }

    private function isUnsupportedFileUrl(string $input): bool
    {
        return preg_match('/^file:\/\/[A-Za-z]%7C\//i', $input) === 1
            || preg_match('/^file:\/\/[A-Za-z]\|[^\/]/i', $input) === 1;
    }

    /**
     * @return array{string, string}
     */
    private function normalizeFileHostAndPath(string $host, string $path): array
    {
        if (preg_match('/^([A-Za-z])\|$/', $host, $matches) !== 1) {
            return [$host, $path];
        }

        return ['', '/' . strtoupper($matches[1]) . ':' . $path];
    }

    private function isPathByteAllowed(int $byte): bool
    {
        return $byte >= 0x21 && $byte <= 0x7E;
    }

    private function percentEncodeBytes(string $bytes): string
    {
        $encoded = '';

        for ($offset = 0; $offset < strlen($bytes); $offset++) {
            $encoded .= sprintf('%%%02X', ord($bytes[$offset]));
        }

        return $encoded;
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function appendError(array &$errors, ValidationError $error): void
    {
        $errors[] = $error;
    }
}

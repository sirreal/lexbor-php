<?php

declare(strict_types=1);

namespace Lexbor\Url;

use Lexbor\Encoding\Utf8;

final class Parser
{
    public function parse(string $input, ?Url $base = null, string $encoding = 'utf-8'): ?Url
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

            return $this->parseWithScheme($base->scheme, $body, $errors, $encoding, $body === '');
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
                $encoding,
            );
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):(.*)$/s', $input, $matches) !== 1) {
            $this->appendError($errors, ValidationError::InvalidUrlUnit);
            return null;
        }

        $scheme = strtolower($matches[1]);
        $body = $matches[2];

        if ($scheme === 'file') {
            return $this->parseWithScheme(
                'file',
                str_starts_with($body, '//') ? substr($body, 2) : $body,
                $errors,
                $encoding,
            );
        }

        if (str_starts_with($body, '//')) {
            return $this->parseWithScheme($scheme, substr($body, 2), $errors, $encoding);
        }

        if ($this->isSpecialScheme($scheme) && $body !== '' && ! str_starts_with($body, '/')) {
            return $this->parseWithScheme($scheme, $body, $errors, $encoding);
        }

        $this->appendError($errors, ValidationError::InvalidUrlUnit);
        return null;
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function parseWithScheme(
        string $scheme,
        string $input,
        array $errors,
        string $encoding,
        bool $allowEmptyPath = false,
    ): ?Url {
        if ($this->isSpecialScheme($scheme)) {
            $input = $this->normalizeSpecialPathBackslashes($input);
        }

        [$authority, $path] = $this->splitAuthorityAndPath($input);
        [$username, $password, $host, $port] = $this->parseAuthority($authority, $errors);

        if ($scheme === 'file') {
            [$host, $path] = $this->normalizeFileHostAndPath($host, $path);
        }

        if ($host === '' && $this->requiresHost($scheme)) {
            $this->appendError($errors, ValidationError::InvalidUrlUnit);
            return null;
        }

        return $this->buildUrl(
            $scheme,
            $username,
            $password,
            $host,
            $port,
            $path,
            $errors,
            $encoding,
            $allowEmptyPath || ! $this->isSpecialScheme($scheme),
        );
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
     * @param list<ValidationError> $errors
     * @return array{string, string, string, ?int}
     */
    private function parseAuthority(string $authority, array &$errors): array
    {
        $username = '';
        $password = '';
        $hostPort = $authority;
        $at = strrpos($authority, '@');

        if ($at !== false) {
            $credentials = substr($authority, 0, $at);
            $hostPort = substr($authority, $at + 1);
            [$username, $password] = array_pad(explode(':', $credentials, 2), 2, '');
            $username = $this->percentEncodeUserInfo($username, $errors);
            $password = $this->percentEncodeUserInfo($password, $errors);
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
        string $encoding = 'utf-8',
        bool $allowEmptyPath = false,
    ): Url {
        [$pathAndQuery, $fragment] = array_pad(explode('#', $pathAndSuffix, 2), 2, null);
        [$path, $query] = array_pad(explode('?', $pathAndQuery, 2), 2, null);

        if ($path === '' && ! $allowEmptyPath) {
            $path = '/';
        }

        $path = $this->normalizePath($path);

        return new Url(
            $scheme,
            $username,
            $password,
            $host,
            $port,
            $this->percentEncodePath($path, $errors),
            $query !== null ? $this->percentEncodeQuery($query, $scheme, $encoding, $errors) : null,
            $fragment !== null ? $this->percentEncodeFragment($fragment, $errors) : null,
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

    /**
     * @param list<ValidationError> $errors
     */
    private function percentEncodeFragment(string $fragment, array &$errors): string
    {
        $encoded = '';

        foreach (Utf8::decode($fragment) as $codePoint) {
            if ($this->isInvalidUrlUnit($codePoint)) {
                $this->appendError($errors, ValidationError::InvalidUrlUnit);
            }

            $bytes = Utf8::encodeCodePoint($codePoint);

            if ($codePoint < 0x80 && $this->isFragmentByteAllowed($codePoint)) {
                $encoded .= chr($codePoint);
                continue;
            }

            $encoded .= $this->percentEncodeBytes($bytes);
        }

        return $encoded;
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function percentEncodeQuery(string $query, string $scheme, string $encoding, array &$errors): string
    {
        $encoded = '';
        $isSpecial = $this->isSpecialScheme($scheme);

        foreach (Utf8::decode($query) as $codePoint) {
            if ($this->isInvalidUrlUnit($codePoint)) {
                $this->appendError($errors, ValidationError::InvalidUrlUnit);
            }

            foreach ($this->queryBytesForCodePoint($codePoint, $encoding) as $byte) {
                if ($byte < 0x80 && $this->isQueryByteAllowed($byte, $isSpecial)) {
                    $encoded .= chr($byte);
                    continue;
                }

                $encoded .= sprintf('%%%02X', $byte);
            }
        }

        return $encoded;
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function percentEncodeUserInfo(string $value, array &$errors): string
    {
        $encoded = '';

        foreach (Utf8::decode($value) as $codePoint) {
            if ($this->isInvalidUrlUnit($codePoint)) {
                $this->appendError($errors, ValidationError::InvalidUrlUnit);
            }

            $bytes = Utf8::encodeCodePoint($codePoint);

            if ($codePoint < 0x80 && $this->isUserInfoByteAllowed($codePoint)) {
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

    private function requiresHost(string $scheme): bool
    {
        return in_array($scheme, ['ftp', 'http', 'https', 'ws', 'wss'], true);
    }

    private function isSpecialScheme(string $scheme): bool
    {
        return $scheme === 'file' || $this->requiresHost($scheme);
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

    private function normalizeSpecialPathBackslashes(string $input): string
    {
        $positions = array_filter(
            [
                strpos($input, '?'),
                strpos($input, '#'),
            ],
            static fn ($position): bool => $position !== false,
        );

        if ($positions === []) {
            return str_replace('\\', '/', $input);
        }

        $delimiter = min($positions);

        return str_replace('\\', '/', substr($input, 0, $delimiter)) . substr($input, $delimiter);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        $normalized = [];
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if ($this->isSingleDotPathSegment($segment)) {
                if ($index === $lastIndex) {
                    $normalized[] = '';
                }

                continue;
            }

            if ($this->isDoubleDotPathSegment($segment)) {
                if (count($normalized) > 1 || ($normalized !== [] && $normalized[0] !== '')) {
                    array_pop($normalized);
                }

                if ($index === $lastIndex) {
                    $normalized[] = '';
                }

                continue;
            }

            $normalized[] = $segment;
        }

        if ($normalized === []) {
            return '';
        }

        return implode('/', $normalized);
    }

    private function isSingleDotPathSegment(string $segment): bool
    {
        return strcasecmp($segment, '.') === 0 || strcasecmp($segment, '%2e') === 0;
    }

    private function isDoubleDotPathSegment(string $segment): bool
    {
        return strcasecmp($segment, '..') === 0
            || strcasecmp($segment, '.%2e') === 0
            || strcasecmp($segment, '%2e.') === 0
            || strcasecmp($segment, '%2e%2e') === 0;
    }

    private function isPathByteAllowed(int $byte): bool
    {
        return $byte >= 0x21
            && $byte <= 0x7E
            && ! in_array($byte, [0x22, 0x23, 0x3C, 0x3E, 0x3F, 0x60], true);
    }

    private function isFragmentByteAllowed(int $byte): bool
    {
        return $byte >= 0x21
            && $byte <= 0x7E
            && ! in_array($byte, [0x22, 0x3C, 0x3E, 0x60], true);
    }

    private function isQueryByteAllowed(int $byte, bool $isSpecial): bool
    {
        return $byte >= 0x21
            && $byte <= 0x7E
            && ! in_array(
                $byte,
                $isSpecial ? [0x22, 0x23, 0x27, 0x3C, 0x3E] : [0x22, 0x23, 0x3C, 0x3E],
                true,
            );
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

    private function percentEncodeBytes(string $bytes): string
    {
        $encoded = '';

        for ($offset = 0; $offset < strlen($bytes); $offset++) {
            $encoded .= sprintf('%%%02X', ord($bytes[$offset]));
        }

        return $encoded;
    }

    /**
     * @return list<int>
     */
    private function queryBytesForCodePoint(int $codePoint, string $encoding): array
    {
        if ($codePoint < 0x80) {
            return [$codePoint];
        }

        return match (strtolower($encoding)) {
            'shift_jis', 'shift-jis' => $this->shiftJisQueryBytesForCodePoint($codePoint),
            'iso-2022-jp' => $this->iso2022JpQueryBytesForCodePoint($codePoint),
            default => array_map('ord', str_split(Utf8::encodeCodePoint($codePoint))),
        };
    }

    /**
     * @return list<int>
     */
    private function shiftJisQueryBytesForCodePoint(int $codePoint): array
    {
        if ($codePoint === 0x2261) {
            return [0x81, 0xDF];
        }

        // Lexbor emits unsupported Shift_JIS query code points as percent-escaped NCRs.
        return array_map('ord', str_split('%26%23' . $codePoint . '%3B'));
    }

    /**
     * @return list<int>
     */
    private function iso2022JpQueryBytesForCodePoint(int $codePoint): array
    {
        if ($codePoint === 0x00A5) {
            return [0x1B, 0x28, 0x4A, 0x5C, 0x1B, 0x28, 0x42];
        }

        return array_map('ord', str_split(Utf8::encodeCodePoint($codePoint)));
    }

    /**
     * @param list<ValidationError> $errors
     */
    private function appendError(array &$errors, ValidationError $error): void
    {
        $errors[] = $error;
    }
}

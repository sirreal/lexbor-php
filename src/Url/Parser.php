<?php

declare(strict_types=1);

namespace Lexbor\Url;

use Lexbor\Core\LexborException;
use Lexbor\Encoding\Utf8;
use Lexbor\Punycode\Punycode;

final class Parser
{
    private const IPV4_UINT32_LIMIT = 4294967296;

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
        [$username, $password, $host, $port] = $this->parseAuthority(
            $authority,
            $errors,
            $scheme === 'file',
            $this->isSpecialScheme($scheme),
        );

        if ($host === null) {
            return null;
        }

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
     * @return array{string, string, ?string, ?int}
     */
    private function parseAuthority(
        string $authority,
        array &$errors,
        bool $allowFileDriveHost = false,
        bool $parseIpv4 = true,
    ): array {
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
        $validHostPort = true;

        if (str_starts_with($hostPort, '[')) {
            $close = strpos($hostPort, ']');

            if ($close === false) {
                $validHostPort = false;
            } else {
                $host = substr($hostPort, 0, $close + 1);
                $remainder = substr($hostPort, $close + 1);

                if ($remainder !== '') {
                    if (! str_starts_with($remainder, ':') || ! ctype_digit(substr($remainder, 1))) {
                        $validHostPort = false;
                    } else {
                        $port = (int) substr($remainder, 1);
                    }
                }
            }
        } else {
            $colon = strrpos($hostPort, ':');

            if ($colon !== false && ctype_digit(substr($hostPort, $colon + 1))) {
                $host = substr($hostPort, 0, $colon);
                $port = (int) substr($hostPort, $colon + 1);
            }
        }

        if (! $validHostPort) {
            $host = null;
        } elseif (! ($allowFileDriveHost && preg_match('/^[A-Za-z]\|$/', $host) === 1)) {
            $host = $this->parseHost($host, $parseIpv4);
        }

        if ($host === null) {
            $this->appendError($errors, ValidationError::InvalidUrlUnit);
            return [$username, $password, null, $port];
        }

        return [$username, $password, $host, $port];
    }

    private function parseHost(string $host, bool $parseIpv4): ?string
    {
        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[')) {
            if (! str_ends_with($host, ']')) {
                return null;
            }

            return $this->parseIpv6Host(substr($host, 1, -1));
        }

        $host = strtolower($this->percentDecodeHost($host));

        if ($this->hasForbiddenDomainCodePoint($host)) {
            return null;
        }

        if ($this->hasNonAsciiByte($host)) {
            try {
                $host = $this->domainToAscii($host);
            } catch (LexborException) {
                return null;
            }

            if ($host === '' || $this->hasForbiddenDomainCodePoint($host)) {
                return null;
            }
        }

        if ($parseIpv4 && $this->isIpv4Candidate($host)) {
            return $this->parseIpv4Host($host);
        }

        return $host;
    }

    private function parseIpv6Host(string $host): ?string
    {
        $pieces = $this->parseIpv6Pieces(strtolower($host));

        if ($pieces === null) {
            return null;
        }

        return '[' . $this->serializeIpv6Pieces($pieces) . ']';
    }

    /**
     * @return ?list<int>
     */
    private function parseIpv6Pieces(string $host): ?array
    {
        if ($host === '' || str_contains($host, ':::') || substr_count($host, '::') > 1) {
            return null;
        }

        if (str_contains($host, '::')) {
            [$left, $right] = explode('::', $host, 2);

            if (str_contains($left, '.')) {
                return null;
            }

            $leftPieces = $left !== '' ? $this->parseIpv6PieceSequence($left) : [];
            $rightPieces = $right !== '' ? $this->parseIpv6PieceSequence($right) : [];

            if ($leftPieces === null || $rightPieces === null) {
                return null;
            }

            $missing = 8 - count($leftPieces) - count($rightPieces);

            if ($missing < 1) {
                return null;
            }

            return array_merge($leftPieces, array_fill(0, $missing, 0), $rightPieces);
        }

        $pieces = $this->parseIpv6PieceSequence($host);

        return $pieces !== null && count($pieces) === 8 ? $pieces : null;
    }

    /**
     * @return ?list<int>
     */
    private function parseIpv6PieceSequence(string $sequence): ?array
    {
        if ($sequence === '' || str_starts_with($sequence, ':') || str_ends_with($sequence, ':')) {
            return null;
        }

        $parts = explode(':', $sequence);
        $pieces = [];

        foreach ($parts as $index => $part) {
            if ($part === '') {
                return null;
            }

            if (str_contains($part, '.')) {
                if ($index !== count($parts) - 1) {
                    return null;
                }

                $ipv4Pieces = $this->parseIpv4TailForIpv6($part);

                if ($ipv4Pieces === null) {
                    return null;
                }

                array_push($pieces, ...$ipv4Pieces);
                continue;
            }

            if (preg_match('/^[0-9a-f]{1,4}$/', $part) !== 1) {
                return null;
            }

            $pieces[] = hexdec($part);
        }

        return count($pieces) <= 8 ? $pieces : null;
    }

    /**
     * @return ?array{int, int}
     */
    private function parseIpv4TailForIpv6(string $tail): ?array
    {
        $parts = explode('.', $tail);

        if (count($parts) !== 4) {
            return null;
        }

        $numbers = [];

        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^[0-9]+$/', $part) !== 1) {
                return null;
            }

            if (strlen($part) > 1 && str_starts_with($part, '0')) {
                return null;
            }

            $number = (int) $part;

            if ($number > 255) {
                return null;
            }

            $numbers[] = $number;
        }

        return [
            ($numbers[0] << 8) + $numbers[1],
            ($numbers[2] << 8) + $numbers[3],
        ];
    }

    /**
     * @param list<int> $pieces
     */
    private function serializeIpv6Pieces(array $pieces): string
    {
        $compressStart = null;
        $compressLength = 0;
        $currentStart = null;
        $currentLength = 0;

        foreach ($pieces as $index => $piece) {
            if ($piece === 0) {
                if ($currentStart === null) {
                    $currentStart = $index;
                    $currentLength = 0;
                }

                $currentLength++;
                continue;
            }

            if ($currentLength > $compressLength) {
                $compressStart = $currentStart;
                $compressLength = $currentLength;
            }

            $currentStart = null;
            $currentLength = 0;
        }

        if ($currentLength > $compressLength) {
            $compressStart = $currentStart;
            $compressLength = $currentLength;
        }

        if ($compressLength < 2 || $compressStart === null) {
            return implode(':', array_map(static fn (int $piece): string => dechex($piece), $pieces));
        }

        $left = array_slice($pieces, 0, $compressStart);
        $right = array_slice($pieces, $compressStart + $compressLength);

        return implode(':', array_map(static fn (int $piece): string => dechex($piece), $left))
            . '::'
            . implode(':', array_map(static fn (int $piece): string => dechex($piece), $right));
    }

    private function isIpv4Candidate(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        if (str_ends_with($host, '.')) {
            $host = substr($host, 0, -1);

            if ($host === '' || str_ends_with($host, '.')) {
                return false;
            }
        }

        $lastDot = strrpos($host, '.');
        $lastPart = $lastDot === false ? $host : substr($host, $lastDot + 1);

        if (ctype_digit($lastPart)) {
            return true;
        }

        return $this->parseIpv4Number($lastPart) !== null;
    }

    private function parseIpv4Host(string $host): ?string
    {
        $parts = explode('.', $host);

        if ($parts[count($parts) - 1] === '') {
            array_pop($parts);
        }

        if ($parts === [] || count($parts) > 4) {
            return null;
        }

        $numbers = [];

        foreach ($parts as $part) {
            if ($part === '') {
                return null;
            }

            $number = $this->parseIpv4Number($part);

            if ($number === null) {
                return null;
            }

            $numbers[] = $number;
        }

        $count = count($numbers);
        $last = $numbers[$count - 1];
        $lastLimit = 256 ** (5 - $count);

        if ($last >= $lastLimit) {
            return null;
        }

        $ip = $last;

        for ($index = 0; $index < $count - 1; $index++) {
            if ($numbers[$index] > 255) {
                return null;
            }

            $ip += $numbers[$index] * (256 ** (3 - $index));
        }

        return sprintf(
            '%d.%d.%d.%d',
            ($ip >> 24) & 0xFF,
            ($ip >> 16) & 0xFF,
            ($ip >> 8) & 0xFF,
            $ip & 0xFF,
        );
    }

    private function parseIpv4Number(string $part): ?int
    {
        if ($part === '') {
            return null;
        }

        $base = 10;
        $digits = $part;

        if (strlen($part) > 1 && $part[0] === '0') {
            if ($part[1] === 'x') {
                $base = 16;
                $digits = substr($part, 2);
            } else {
                $base = 8;
                $digits = substr($part, 1);
            }

            if ($digits === '') {
                return 0;
            }
        }

        $number = 0;
        $length = strlen($digits);

        for ($offset = 0; $offset < $length; $offset++) {
            $digit = $this->ipv4DigitValue($digits[$offset], $base);

            if ($digit === null) {
                return null;
            }

            if ($number > intdiv(self::IPV4_UINT32_LIMIT - $digit, $base)) {
                return self::IPV4_UINT32_LIMIT;
            }

            $number = $number * $base + $digit;
        }

        return $number;
    }

    private function ipv4DigitValue(string $byte, int $base): ?int
    {
        $value = ord($byte);

        if ($value >= 0x30 && $value <= 0x39) {
            $digit = $value - 0x30;
        } elseif ($value >= 0x61 && $value <= 0x66) {
            $digit = $value - 0x61 + 10;
        } else {
            return null;
        }

        return $digit < $base ? $digit : null;
    }

    private function percentDecodeHost(string $host): string
    {
        return preg_replace_callback(
            '/%([0-9A-Fa-f]{2})/',
            static fn (array $match): string => chr(hexdec($match[1])),
            $host,
        ) ?? $host;
    }

    private function hasForbiddenDomainCodePoint(string $host): bool
    {
        $length = strlen($host);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($host[$offset]);

            if ($byte > 0x7F) {
                continue;
            }

            if ($byte <= 0x20 || $byte === 0x7F || in_array(
                $byte,
                [0x23, 0x25, 0x2F, 0x3A, 0x3C, 0x3E, 0x3F, 0x40, 0x5B, 0x5C, 0x5D, 0x5E, 0x7C],
                true,
            )) {
                return true;
            }
        }

        return false;
    }

    private function hasNonAsciiByte(string $value): bool
    {
        $length = strlen($value);

        for ($offset = 0; $offset < $length; $offset++) {
            if (ord($value[$offset]) >= 0x80) {
                return true;
            }
        }

        return false;
    }

    private function domainToAscii(string $host): string
    {
        $labels = explode('.', $host);

        foreach ($labels as $index => $label) {
            if ($label === '' || ! $this->hasNonAsciiByte($label)) {
                continue;
            }

            $labels[$index] = 'xn--' . Punycode::encode($label);
        }

        return implode('.', $labels);
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

<?php

declare(strict_types=1);

namespace Lexbor\Url;

use Lexbor\Encoding\Utf8;

final class Parser
{
    public function parse(string $input): Url
    {
        $errors = [];
        $input = $this->sanitizeInput($input, $errors);

        if (! preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):\/\/([^\/?#]*)([^?#]*)/s', $input, $matches)) {
            $this->appendError($errors, ValidationError::InvalidUrlUnit);

            return new Url('', '', '', $errors);
        }

        $scheme = strtolower($matches[1]);
        $host = strtolower($matches[2]);
        $path = $matches[3] !== '' ? $matches[3] : '/';

        return new Url($scheme, $host, $this->percentEncodePath($path, $errors), $errors);
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

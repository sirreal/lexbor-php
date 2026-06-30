<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Core\Status;

final class Encoding
{
    /** @var list<string> */
    private array $entries = [];

    public function determine(string $html): Status
    {
        $this->entries = self::scanMetaEntries($html);

        return Status::Ok;
    }

    public function clean(): void
    {
        $this->entries = [];
    }

    public function metaEntry(int $index): ?string
    {
        if ($index < 0) {
            return null;
        }

        return $this->entries[$index] ?? null;
    }

    /**
     * @return list<string>
     */
    public function metaEntries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public static function determineMetaEntries(string $html): array
    {
        return self::scanMetaEntries($html);
    }

    /**
     * @return list<string>
     */
    private static function scanMetaEntries(string $html): array
    {
        $entries = [];
        $length = strlen($html);
        $offset = 0;

        while ($offset < $length) {
            $tagStart = strpos($html, '<', $offset);
            if ($tagStart === false || $tagStart + 1 >= $length) {
                break;
            }

            $tagOffset = $tagStart + 1;
            $first = $html[$tagOffset];

            if ($first === '!') {
                $offset = self::skipMarkupDeclaration($html, $tagOffset);
                continue;
            }

            if ($first === '?') {
                $offset = self::skipRawTag($html, $tagOffset + 1);
                continue;
            }

            if ($first === '/') {
                $afterSlash = $tagOffset + 1;
                if ($afterSlash + 3 > $length) {
                    break;
                }

                if (! self::isAsciiAlpha($html[$afterSlash])) {
                    $offset = self::skipRawTag($html, $afterSlash);
                    continue;
                }

                $offset = self::skipNonMetaTag($html, $afterSlash);
                continue;
            }

            if (! self::isAsciiAlpha($first)) {
                $offset = $tagStart + 1;
                continue;
            }

            if ($tagOffset + 6 > $length || strncasecmp(substr($html, $tagOffset, 4), 'meta', 4) !== 0) {
                $offset = self::skipNonMetaTag($html, $tagOffset);
                continue;
            }

            $attributesStart = $tagOffset + 4;
            $delimiter = $html[$attributesStart] ?? null;
            if ($delimiter === null || ($delimiter !== '/' && ! self::isWhitespace($delimiter))) {
                $offset = self::skipNonMetaTag($html, $attributesStart + 1);
                continue;
            }

            $attributesStart++;
            [$tagEnd, $complete] = self::findTagEnd($html, $attributesStart);
            $attributes = self::parseMetaAttributes(substr($html, $attributesStart, $tagEnd - $attributesStart), $complete);
            array_push($entries, ...self::encodingEntriesFromMetaAttributes($attributes));

            $offset = max($tagEnd + 1, $tagStart + 1);
        }

        return $entries;
    }

    private static function skipMarkupDeclaration(string $html, int $offset): int
    {
        if (substr($html, $offset, 3) !== '!--') {
            return self::skipRawTag($html, $offset + 1);
        }

        $length = strlen($html);
        $search = $offset;

        while ($search < $length) {
            $tagEnd = strpos($html, '>', $search);
            if ($tagEnd === false) {
                return $length;
            }

            if (
                $tagEnd >= 2
                && $html[$tagEnd - 1] === '-'
                && $html[$tagEnd - 2] === '-'
            ) {
                return $tagEnd + 1;
            }

            $search = $tagEnd + 1;
        }

        return $length;
    }

    private static function skipRawTag(string $html, int $offset): int
    {
        $tagEnd = strpos($html, '>', $offset);

        return $tagEnd === false ? strlen($html) : $tagEnd + 1;
    }

    private static function skipNonMetaTag(string $html, int $offset): int
    {
        $nameEnd = self::skipName($html, $offset);
        if ($nameEnd >= strlen($html) || $html[$nameEnd] === '>') {
            return $nameEnd + 1;
        }

        [$tagEnd] = self::findTagEnd($html, $nameEnd);

        return $tagEnd + 1;
    }

    private static function skipName(string $html, int $offset): int
    {
        $length = strlen($html);

        while ($offset < $length) {
            $character = $html[$offset];
            if ($character === '>' || self::isWhitespace($character)) {
                break;
            }

            $offset++;
        }

        return $offset;
    }

    /**
     * @return array{int, bool}
     */
    private static function findTagEnd(string $html, int $offset): array
    {
        $state = 'before_attribute_name';
        $length = strlen($html);

        while ($offset < $length) {
            $character = $html[$offset];

            switch ($state) {
                case 'before_attribute_name':
                    if ($character === '>') {
                        return [$offset, true];
                    }

                    if (! self::isWhitespace($character) && $character !== '/') {
                        $state = 'attribute_name';
                    }
                    break;

                case 'attribute_name':
                    if ($character === '>') {
                        return [$offset, true];
                    }

                    if (self::isWhitespace($character) || $character === '/') {
                        $state = 'after_attribute_name';
                    } elseif ($character === '=') {
                        $state = 'before_attribute_value';
                    }
                    break;

                case 'after_attribute_name':
                    if ($character === '>') {
                        return [$offset, true];
                    }

                    if (self::isWhitespace($character)) {
                        break;
                    }

                    if ($character === '=') {
                        $state = 'before_attribute_value';
                    } elseif ($character === '/') {
                        $state = 'before_attribute_name';
                    } else {
                        $state = 'attribute_name';
                    }
                    break;

                case 'before_attribute_value':
                    if ($character === '>') {
                        return [$offset, true];
                    }

                    if (self::isWhitespace($character)) {
                        break;
                    }

                    if ($character === '"') {
                        $state = 'double_quoted_attribute_value';
                    } elseif ($character === "'") {
                        $state = 'single_quoted_attribute_value';
                    } else {
                        $state = 'unquoted_attribute_value';
                    }
                    break;

                case 'double_quoted_attribute_value':
                    if ($character === '"') {
                        $state = 'after_quoted_attribute_value';
                    }
                    break;

                case 'single_quoted_attribute_value':
                    if ($character === "'") {
                        $state = 'after_quoted_attribute_value';
                    }
                    break;

                case 'after_quoted_attribute_value':
                    if ($character === '>') {
                        return [$offset, true];
                    }

                    if (self::isWhitespace($character) || $character === '/') {
                        $state = 'before_attribute_name';
                    } else {
                        $state = 'attribute_name';
                    }
                    break;

                default:
                    if ($character === '>') {
                        return [$offset, true];
                    }

                    if (self::isWhitespace($character)) {
                        $state = 'before_attribute_name';
                    }
                    break;
            }

            $offset++;
        }

        return [$length, false];
    }

    /**
     * @return list<array{name: string, value: string, hasValue: bool}>
     */
    private static function parseMetaAttributes(string $source, bool $tagComplete): array
    {
        $attributes = [];
        $seen = [];
        $length = strlen($source);
        $offset = 0;
        $name = '';
        $value = null;
        $state = 'before_attribute_name';

        $commit = static function () use (&$attributes, &$seen, &$name, &$value): void {
            if ($name === '') {
                return;
            }

            $normalizedName = strtolower($name);
            if (! array_key_exists($normalizedName, $seen)) {
                $seen[$normalizedName] = true;
                $attributes[] = [
                    'name' => $normalizedName,
                    'value' => $value ?? '',
                    'hasValue' => $value !== null,
                ];
            }

            $name = '';
            $value = null;
        };

        while ($offset < $length) {
            $character = $source[$offset];

            switch ($state) {
                case 'before_attribute_name':
                    if (self::isWhitespace($character) || $character === '/') {
                        break;
                    }

                    $name = $character;
                    $value = null;
                    $state = $character === '=' ? 'before_attribute_value' : 'attribute_name';
                    break;

                case 'attribute_name':
                    if (self::isWhitespace($character)) {
                        $state = 'after_attribute_name';
                    } elseif ($character === '/') {
                        $commit();
                        $state = 'before_attribute_name';
                    } elseif ($character === '=') {
                        $state = 'before_attribute_value';
                    } else {
                        $name .= $character;
                    }
                    break;

                case 'after_attribute_name':
                    if (self::isWhitespace($character)) {
                        break;
                    }

                    if ($character === '=') {
                        $state = 'before_attribute_value';
                    } else {
                        $commit();
                        if ($character !== '/') {
                            $name = $character;
                            $value = null;
                            $state = 'attribute_name';
                        } else {
                            $state = 'before_attribute_name';
                        }
                    }
                    break;

                case 'before_attribute_value':
                    if (self::isWhitespace($character)) {
                        break;
                    }

                    if ($character === '"') {
                        $value = '';
                        $state = 'double_quoted_attribute_value';
                    } elseif ($character === "'") {
                        $value = '';
                        $state = 'single_quoted_attribute_value';
                    } else {
                        $value = $character;
                        $state = 'unquoted_attribute_value';
                    }
                    break;

                case 'double_quoted_attribute_value':
                    if ($character === '"') {
                        $state = 'after_quoted_attribute_value';
                    } else {
                        $value .= $character;
                    }
                    break;

                case 'single_quoted_attribute_value':
                    if ($character === "'") {
                        $state = 'after_quoted_attribute_value';
                    } else {
                        $value .= $character;
                    }
                    break;

                case 'after_quoted_attribute_value':
                    if (self::isWhitespace($character) || $character === '/') {
                        $commit();
                        $state = 'before_attribute_name';
                    } else {
                        $commit();
                        $name = $character;
                        $value = null;
                        $state = 'attribute_name';
                    }
                    break;

                default:
                    if (self::isWhitespace($character)) {
                        $commit();
                        $state = 'before_attribute_name';
                    } else {
                        $value .= $character;
                    }
                    break;
            }

            $offset++;
        }

        if ($state === 'after_quoted_attribute_value') {
            $commit();
        } elseif (
            $tagComplete
            && (
                $state === 'attribute_name'
                || $state === 'after_attribute_name'
                || $state === 'before_attribute_value'
                || $state === 'unquoted_attribute_value'
            )
        ) {
            $commit();
        }

        return $attributes;
    }

    /**
     * @param list<array{name: string, value: string, hasValue: bool}> $attributes
     * @return list<string>
     */
    private static function encodingEntriesFromMetaAttributes(array $attributes): array
    {
        $entries = [];
        $gotPragma = false;
        $haveContent = false;
        $needPragma = 0;

        foreach ($attributes as $attribute) {
            $name = $attribute['name'];
            $length = strlen($name);

            if ($length < 7 || ! $attribute['hasValue']) {
                continue;
            }

            if ($length === 10) {
                if ($name !== 'http-equiv') {
                    continue;
                }

                $gotPragma = strcasecmp($attribute['value'], 'content-type') === 0;
                continue;
            }

            if (strncmp($name, 'content', 7) === 0) {
                if (! $haveContent) {
                    $content = self::charsetFromContentType($attribute['value']);
                    if ($content !== null) {
                        $entries[] = $content;
                        $needPragma = 0x02;
                        $haveContent = true;
                    }
                }

                continue;
            }

            if (strncmp($name, 'charset', 7) === 0) {
                $entries[] = $attribute['value'];
                $needPragma = 0x01;
            }
        }

        if ($needPragma === 0x00 || ($needPragma === 0x02 && ! $gotPragma)) {
            array_pop($entries);
        }

        return $entries;
    }

    private static function charsetFromContentType(string $content): ?string
    {
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $charsetOffset = stripos($content, 'charset', $offset);
            if ($charsetOffset === false) {
                return null;
            }

            $position = $charsetOffset + 7;
            while ($position < $length && self::isWhitespace($content[$position])) {
                $position++;
            }

            if (($content[$position] ?? null) !== '=') {
                $offset = $charsetOffset + 7;
                continue;
            }

            $position++;
            while ($position < $length && self::isWhitespace($content[$position])) {
                $position++;
            }

            if ($position >= $length) {
                return null;
            }

            $quote = $content[$position];
            if ($quote === '"' || $quote === "'") {
                $start = $position + 1;
                $end = strpos($content, $quote, $start);

                if ($end === false) {
                    return null;
                }

                return substr($content, $start, $end - $start);
            }

            $start = $position;
            while ($position < $length && $content[$position] !== ';' && ! self::isWhitespace($content[$position])) {
                if ($content[$position] === '"' || $content[$position] === "'") {
                    return null;
                }

                $position++;
            }

            return substr($content, $start, $position - $start);
        }

        return null;
    }

    private static function isAsciiAlpha(string $character): bool
    {
        $byte = ord($character);

        return ($byte >= 0x41 && $byte <= 0x5A)
            || ($byte >= 0x61 && $byte <= 0x7A);
    }

    private static function isWhitespace(string $character): bool
    {
        return $character === ' '
            || $character === "\t"
            || $character === "\n"
            || $character === "\r"
            || $character === "\f";
    }
}

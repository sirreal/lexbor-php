<?php

declare(strict_types=1);

namespace Lexbor\Html;

final class TokenizerError
{
    public const int LAST_ENTRY = 49;

    /** @var list<string> */
    private const DESCRIPTIONS = [
        'abrupt closing of empty comment',
        'abrupt doctype public identifier',
        'abrupt doctype system identifier',
        'absence of digits in numeric character reference',
        'cdata in html content',
        'character reference outside unicode range',
        'control character in input stream',
        'control character reference',
        'end tag with attributes',
        'duplicate attribute',
        'end tag with trailing solidus',
        'eof before tag name',
        'eof in cdata',
        'eof in comment',
        'eof in doctype',
        'eof in script html comment like text',
        'eof in tag',
        'incorrectly closed comment',
        'incorrectly opened comment',
        'invalid character sequence after doctype name',
        'invalid first character of tag name',
        'missing attribute value',
        'missing doctype name',
        'missing doctype public identifier',
        'missing doctype system identifier',
        'missing end tag name',
        'missing quote before doctype public identifier',
        'missing quote before doctype system identifier',
        'missing semicolon after character reference',
        'missing whitespace after doctype public keyword',
        'missing whitespace after doctype system keyword',
        'missing whitespace before doctype name',
        'missing whitespace between attributes',
        'missing whitespace between doctype public and system identifiers',
        'nested comment',
        'noncharacter character reference',
        'noncharacter in input stream',
        'non void html element start tag with trailing solidus',
        'null character reference',
        'surrogate character reference',
        'surrogate in input stream',
        'unexpected character after doctype system identifier',
        'unexpected character in attribute name',
        'unexpected character in unquoted attribute value',
        'unexpected equals sign before attribute name',
        'unexpected null character',
        'unexpected question mark instead of tag name',
        'unexpected solidus in tag',
        'unknown named character reference',
    ];

    public static function description(int $id): string
    {
        return self::DESCRIPTIONS[$id] ?? 'unknown error';
    }
}

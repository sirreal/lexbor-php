<?php

declare(strict_types=1);

namespace Lexbor\Tests\Url;

use Lexbor\Url\Parser;
use Lexbor\Url\ValidationError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<ValidationError>}>
     */
    public static function upstreamValidationProvider(): iterable
    {
        yield 'validation.c #1 leading spaces' => [
            '  http://lexbor.com/',
            'http://lexbor.com/',
            [ValidationError::InvalidUrlUnit],
        ];
        yield 'validation.c #2 trailing spaces' => [
            'http://lexbor.com/    ',
            'http://lexbor.com/',
            [ValidationError::InvalidUrlUnit],
        ];
        yield 'validation.c #3 newline in host' => [
            "http://lexb\nor.com/",
            'http://lexbor.com/',
            [ValidationError::InvalidUrlUnit],
        ];
        yield 'validation.c #4 tab in host' => [
            "http://lexb\tor.com/",
            'http://lexbor.com/',
            [ValidationError::InvalidUrlUnit],
        ];
        yield 'validation.c #5 carriage return in host' => [
            "http://lexb\ror.com/",
            'http://lexbor.com/',
            [ValidationError::InvalidUrlUnit],
        ];
        yield 'validation.c #6 ascii path' => [
            'http://lexbor.com/path/to/world',
            'http://lexbor.com/path/to/world',
            [],
        ];
        yield 'validation.c #7 non-ascii path code point' => [
            "http://lexbor.com/path/to/wo\u{9FFF}rld",
            'http://lexbor.com/path/to/wo%E9%BF%BFrld',
            [],
        ];
        yield 'validation.c #8 noncharacter path code point' => [
            "http://lexbor.com/path/to/wo\u{7FFFE}rld",
            'http://lexbor.com/path/to/wo%F1%BF%BF%BErld',
            [ValidationError::InvalidUrlUnit],
        ];
    }

    /**
     * @param list<ValidationError> $expectedErrors
     */
    #[DataProvider('upstreamValidationProvider')]
    public function testUpstreamValidationFixtures(string $source, string $expected, array $expectedErrors): void
    {
        $url = (new Parser())->parse($source);

        self::assertSame($expected, $url->serialize());
        self::assertSame($expectedErrors, $url->errors());
    }

    public function testValidationErrorsPreserveOccurrences(): void
    {
        $url = (new Parser())->parse(" http://lexbor.com/path/\u{7FFFE}");

        self::assertSame('http://lexbor.com/path/%F1%BF%BF%BE', $url->serialize());
        self::assertSame(
            [ValidationError::InvalidUrlUnit, ValidationError::InvalidUrlUnit],
            $url->errors(),
        );
    }
}

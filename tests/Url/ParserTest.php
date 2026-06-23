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

        self::assertNotNull($url);
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

    /**
     * @return iterable<string, array{string, ?string, ?array<string, mixed>}>
     */
    public static function upstreamUrlProvider(): iterable
    {
        yield 'url.ton #1 full URL' => [
            'https://user:pass@lexbor.com:450/docs/lexbor/?search=lxb_status_t#version',
            null,
            [
                'done' => 'https://user:pass@lexbor.com:450/docs/lexbor/?search=lxb_status_t#version',
                'scheme' => 'https',
                'username' => 'user',
                'password' => 'pass',
                'host' => 'lexbor.com',
                'port' => 450,
                'path' => '/docs/lexbor/',
                'query' => 'search=lxb_status_t',
                'fragment' => 'version',
            ],
        ];
        yield 'url.ton #2 relative URL with base' => [
            '/docs/lexbor/?search=lxb_status_t#version',
            'https://user:pass@lexbor.com:450',
            [
                'done' => 'https://user:pass@lexbor.com:450/docs/lexbor/?search=lxb_status_t#version',
                'scheme' => 'https',
                'username' => 'user',
                'password' => 'pass',
                'host' => 'lexbor.com',
                'port' => 450,
                'path' => '/docs/lexbor/',
                'query' => 'search=lxb_status_t',
                'fragment' => 'version',
            ],
        ];
        yield 'url.ton #3 host only' => [
            'https://lexbor.com',
            null,
            [
                'done' => 'https://lexbor.com/',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'url.ton #4 host and port' => [
            'https://lexbor.com:450',
            null,
            [
                'done' => 'https://lexbor.com:450/',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'port' => 450,
                'path' => '/',
            ],
        ];
        yield 'url.ton #5 invalid file drive URL' => [
            'file://c%7C/',
            null,
            null,
        ];
        yield 'url.ton #6 invalid byte URL' => [
            " \xD4",
            null,
            null,
        ];
        yield 'url.ton #7 empty scheme-relative with base' => [
            '//',
            'hohoho://lexbor.com/docs/blah',
            [
                'done' => 'hohoho://',
                'scheme' => 'hohoho',
                'host' => '',
                'path' => '',
            ],
        ];
    }

    /**
     * @param ?array<string, mixed> $expected
     */
    #[DataProvider('upstreamUrlProvider')]
    public function testUpstreamUrlFixtures(string $source, ?string $baseSource, ?array $expected): void
    {
        $parser = new Parser();
        $base = $baseSource !== null ? $parser->parse($baseSource) : null;

        if ($baseSource !== null) {
            self::assertNotNull($base);
        }

        $url = $parser->parse($source, $base);

        if ($expected === null) {
            self::assertNull($url);
            return;
        }

        self::assertNotNull($url);
        self::assertSame($expected['done'], $url->serialize());
        self::assertSame($expected['scheme'], $url->scheme);
        self::assertSame($expected['username'] ?? '', $url->username);
        self::assertSame($expected['password'] ?? '', $url->password);
        self::assertSame($expected['host'], $url->host);
        self::assertSame($expected['port'] ?? null, $url->port);
        self::assertSame($expected['path'], $url->path);
        self::assertSame($expected['query'] ?? null, $url->query);
        self::assertSame($expected['fragment'] ?? null, $url->fragment);
    }

    public function testHostOnlyUrlCanHaveQueryAndFragment(): void
    {
        $url = (new Parser())->parse('https://lexbor.com?query=value#fragment');

        self::assertNotNull($url);
        self::assertSame('https://lexbor.com/?query=value#fragment', $url->serialize());
        self::assertSame('lexbor.com', $url->host);
        self::assertSame('/', $url->path);
        self::assertSame('query=value', $url->query);
        self::assertSame('fragment', $url->fragment);
    }

    public function testSchemeRelativeUrlWithHostNormalizesEmptyPath(): void
    {
        $parser = new Parser();
        $base = $parser->parse('http://base.example/path');

        self::assertNotNull($base);

        $url = $parser->parse('//lexbor.com', $base);

        self::assertNotNull($url);
        self::assertSame('http://lexbor.com/', $url->serialize());
        self::assertSame('http', $url->scheme);
        self::assertSame('lexbor.com', $url->host);
        self::assertSame('/', $url->path);
    }

    /**
     * @return iterable<string, array{string, ?array<string, mixed>}>
     */
    public static function upstreamFileProvider(): iterable
    {
        yield 'file.ton #1 literal drive pipe' => [
            'file://C|/my/docs',
            [
                'done' => 'file:///C:/my/docs',
                'scheme' => 'file',
                'host' => '',
                'path' => '/C:/my/docs',
            ],
        ];
        yield 'file.ton #2 ordinary file host' => [
            'file://CdiSk/my/docs',
            [
                'done' => 'file://cdisk/my/docs',
                'scheme' => 'file',
                'host' => 'cdisk',
                'path' => '/my/docs',
            ],
        ];
        yield 'file.ton #3 invalid drive-like host' => [
            'file://C|disk/my/docs',
            null,
        ];
    }

    /**
     * @param ?array<string, mixed> $expected
     */
    #[DataProvider('upstreamFileProvider')]
    public function testUpstreamFileFixtures(string $source, ?array $expected): void
    {
        $url = (new Parser())->parse($source);

        if ($expected === null) {
            self::assertNull($url);
            return;
        }

        self::assertNotNull($url);
        self::assertSame($expected['done'], $url->serialize());
        self::assertSame($expected['scheme'], $url->scheme);
        self::assertSame($expected['host'], $url->host);
        self::assertSame($expected['path'], $url->path);
    }
}

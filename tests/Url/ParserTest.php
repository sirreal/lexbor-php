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

    public function testNonSpecialNumericHostIsNotIpv4Normalized(): void
    {
        $url = (new Parser())->parse('my://16909060');

        self::assertNotNull($url);
        self::assertSame('my://16909060', $url->serialize());
        self::assertSame('16909060', $url->host);
    }

    public function testOversizedIpv4NumberRejectsWithoutOverflow(): void
    {
        $url = (new Parser())->parse('https://' . str_repeat('9', 80));

        self::assertNull($url);
    }

    public function testInvalidOctalIpv4CandidatesAreRejected(): void
    {
        self::assertNull((new Parser())->parse('https://09'));
        self::assertNull((new Parser())->parse('https://1.2.3.09'));
    }

    public function testIpv6HostWithPortSerializes(): void
    {
        $url = (new Parser())->parse('https://[::1]:8443/docs');

        self::assertNotNull($url);
        self::assertSame('https://[::1]:8443/docs', $url->serialize());
        self::assertSame('[::1]', $url->host);
        self::assertSame(8443, $url->port);
    }

    public function testIpv6Ipv4TailRejectsLeadingZeroParts(): void
    {
        self::assertNull((new Parser())->parse('https://[::ffff:192.168.001.001]'));
    }

    public function testIpv6Ipv4TailMustEndAddress(): void
    {
        self::assertNull((new Parser())->parse('https://[192.0.2.1::]'));
        self::assertNull((new Parser())->parse('https://[192.0.2.1::1]'));
        self::assertNull((new Parser())->parse('https://[1:2:192.0.2.1::]'));
    }

    public function testProtocolMutationClearsNewDefaultPort(): void
    {
        $http = (new Parser())->parse('https://lexbor.com:80/');
        $https = (new Parser())->parse('http://lexbor.com:443/');
        $sameScheme = (new Parser())->parse('http://lexbor.com:80/');

        self::assertNotNull($http);
        self::assertNotNull($https);
        self::assertNotNull($sameScheme);

        self::assertTrue($http->setProtocol('http'));
        self::assertTrue($https->setProtocol('https'));
        self::assertTrue($sameScheme->setProtocol('http'));
        self::assertSame('http://lexbor.com/', $http->serialize());
        self::assertSame('https://lexbor.com/', $https->serialize());
        self::assertSame('http://lexbor.com/', $sameScheme->serialize());
        self::assertNull($http->port);
        self::assertNull($https->port);
        self::assertNull($sameScheme->port);
    }

    public function testProtocolMutationDoesNotCrossFileBoundary(): void
    {
        $file = (new Parser())->parse('file:///tmp/a');
        $http = (new Parser())->parse('http://lexbor.com/');

        self::assertNotNull($file);
        self::assertNotNull($http);

        self::assertTrue($file->setProtocol('http'));
        self::assertTrue($http->setProtocol('file'));
        self::assertSame('file:///tmp/a', $file->serialize());
        self::assertSame('http://lexbor.com/', $http->serialize());
    }

    public function testProtocolMutationStripsAsciiTabNewlineAndCarriageReturn(): void
    {
        $leadingTab = (new Parser())->parse('https://lexbor.com/');
        $innerNewline = (new Parser())->parse('https://lexbor.com/');
        $innerTab = (new Parser())->parse('http://lexbor.com/');
        $trailingCarriageReturn = (new Parser())->parse('https://lexbor.com/');

        self::assertNotNull($leadingTab);
        self::assertNotNull($innerNewline);
        self::assertNotNull($innerTab);
        self::assertNotNull($trailingCarriageReturn);

        self::assertTrue($leadingTab->setProtocol("\thttp"));
        self::assertTrue($innerNewline->setProtocol("ht\ntp"));
        self::assertTrue($innerTab->setProtocol("http\ts"));
        self::assertTrue($trailingCarriageReturn->setProtocol("http\r"));
        self::assertSame('http://lexbor.com/', $leadingTab->serialize());
        self::assertSame('http://lexbor.com/', $innerNewline->serialize());
        self::assertSame('https://lexbor.com/', $innerTab->serialize());
        self::assertSame('http://lexbor.com/', $trailingCarriageReturn->serialize());
    }

    public function testCredentialMutationEncodesUserInfoAndSkipsFileUrls(): void
    {
        $url = (new Parser())->parse('https://lexbor.com/');
        $file = (new Parser())->parse('file:///tmp/a');

        self::assertNotNull($url);
        self::assertNotNull($file);

        self::assertTrue($url->setUsername('us=er'));
        self::assertTrue($url->setPassword('pass:word'));
        self::assertTrue($file->setUsername('user'));
        self::assertTrue($file->setPassword('pass'));
        self::assertSame('https://us%3Der:pass%3Aword@lexbor.com/', $url->serialize());
        self::assertSame('file:///tmp/a', $file->serialize());
    }

    public function testHostMutationRejectsAuthorityDelimitersAndPreservesHostnamePort(): void
    {
        $url = (new Parser())->parse('https://lexbor.com:8443/path');
        $bad = (new Parser())->parse('https://lexbor.com/');
        $file = (new Parser())->parse('file://example.com/tmp');
        $nonSpecialWithPort = (new Parser())->parse('foo://a:8080/p');
        $nonSpecialWithCredentials = (new Parser())->parse('foo://user:pass@a:99/p');
        $nonSpecialWithoutState = (new Parser())->parse('foo://a/p');

        self::assertNotNull($url);
        self::assertNotNull($bad);
        self::assertNotNull($file);
        self::assertNotNull($nonSpecialWithPort);
        self::assertNotNull($nonSpecialWithCredentials);
        self::assertNotNull($nonSpecialWithoutState);

        self::assertTrue($url->setHostname('example.com'));
        self::assertSame('https://example.com:8443/path', $url->serialize());
        self::assertTrue($url->setHost('other.example'));
        self::assertSame('https://other.example:8443/path', $url->serialize());
        self::assertTrue($url->setHost('default.example:443'));
        self::assertSame('https://default.example/path', $url->serialize());
        self::assertTrue($url->setHost('other.example:80'));
        self::assertSame('https://other.example:80/path', $url->serialize());
        self::assertTrue($url->setHost('zero-default.example:000443'));
        self::assertSame('https://zero-default.example/path', $url->serialize());
        self::assertTrue($url->setHost('zero-port.example:000080'));
        self::assertSame('https://zero-port.example:80/path', $url->serialize());
        self::assertTrue($url->setHost('empty-port.example:'));
        self::assertSame('https://empty-port.example:80/path', $url->serialize());

        self::assertFalse($bad->setHost('user@example.com'));
        self::assertFalse($bad->setHost('example.com/path'));
        self::assertFalse($bad->setHost("example.com\x08"));
        self::assertFalse($bad->setHostname("example.com\x08"));
        self::assertFalse($bad->setHost('example.com:65536'));
        self::assertFalse($bad->setHost('example.com:65536:'));
        self::assertSame('https://lexbor.com/', $bad->serialize());

        self::assertFalse($file->setHost('C|'));
        self::assertFalse($file->setHostname('C|'));
        self::assertFalse($file->setHost('C|:'));
        self::assertFalse($file->setHost('C:'));
        self::assertFalse($file->setHost('C:123'));
        self::assertTrue($file->setHost('other:123'));
        self::assertTrue($file->setHost('other:'));
        self::assertTrue($file->setHostname('other:456'));
        self::assertTrue($file->setHostname('[::1]:123'));
        self::assertFalse($file->setHost('other: '));
        self::assertFalse($file->setHostname("other:\x7F"));
        self::assertFalse($file->setHost('other:123/path'));
        self::assertFalse($file->setHost('other/path:123'));
        self::assertFalse($file->setHost('other:65536'));
        self::assertFalse($file->setHost('other:123:'));
        self::assertFalse($file->setHostname('[::1]x'));
        self::assertFalse($file->setHostname('user@other:123'));
        self::assertSame('file://example.com/tmp', $file->serialize());

        self::assertTrue($nonSpecialWithPort->setHostname(''));
        self::assertTrue($nonSpecialWithCredentials->setHost(''));
        self::assertTrue($nonSpecialWithoutState->setHost(''));
        self::assertSame('foo://a:8080/p', $nonSpecialWithPort->serialize());
        self::assertSame('foo://user:pass@a:99/p', $nonSpecialWithCredentials->serialize());
        self::assertSame('foo:///p', $nonSpecialWithoutState->serialize());
    }

    public function testSpecialSchemeBackslashNormalizationStopsAtQuery(): void
    {
        $url = (new Parser())->parse('https://lexbor.com\docs?q=\path');

        self::assertNotNull($url);
        self::assertSame('https://lexbor.com/docs?q=\path', $url->serialize());
        self::assertSame('/docs', $url->path);
        self::assertSame('q=\path', $url->query);
    }

    public function testSpecialSchemeBackslashNormalizationStopsAtFragment(): void
    {
        $url = (new Parser())->parse('https://lexbor.com\docs#frag\path');

        self::assertNotNull($url);
        self::assertSame('https://lexbor.com/docs#frag\path', $url->serialize());
        self::assertSame('/docs', $url->path);
        self::assertSame('frag\path', $url->fragment);
    }

    /**
     * @return iterable<string, array{string, ?array<string, string>}>
     */
    public static function upstreamSchemeProvider(): iterable
    {
        yield 'scheme.ton #1 https' => [
            'https://lexbor.com',
            [
                'done' => 'https://lexbor.com/',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #2 http' => [
            'http://lexbor.com',
            [
                'done' => 'http://lexbor.com/',
                'scheme' => 'http',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #3 ws' => [
            'ws://lexbor.com',
            [
                'done' => 'ws://lexbor.com/',
                'scheme' => 'ws',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #4 wss' => [
            'wss://lexbor.com',
            [
                'done' => 'wss://lexbor.com/',
                'scheme' => 'wss',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #5 ftp' => [
            'ftp://lexbor.com',
            [
                'done' => 'ftp://lexbor.com/',
                'scheme' => 'ftp',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #6 non-special' => [
            'my://lexbor.com',
            [
                'done' => 'my://lexbor.com',
                'scheme' => 'my',
                'host' => 'lexbor.com',
                'path' => '',
            ],
        ];
        yield 'scheme.ton #7 missing scheme' => [
            '://lexbor.com',
            null,
        ];
        yield 'scheme.ton #8 non-ascii scheme' => [
            "\u{0401}://lexbor.com",
            null,
        ];
        yield 'scheme.ton #9 scheme only without colon' => [
            'http',
            null,
        ];
        yield 'scheme.ton #10 special single slash' => [
            'http:/',
            null,
        ];
        yield 'scheme.ton #11 special empty host' => [
            'http://',
            null,
        ];
        yield 'scheme.ton #12 file host' => [
            'file://lexbor.com',
            [
                'done' => 'file://lexbor.com/',
                'scheme' => 'file',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #13 file empty authority' => [
            'file://',
            [
                'done' => 'file:///',
                'scheme' => 'file',
                'host' => '',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #14 file single slash' => [
            'file:/',
            [
                'done' => 'file:///',
                'scheme' => 'file',
                'host' => '',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #15 file scheme only' => [
            'file:',
            [
                'done' => 'file:///',
                'scheme' => 'file',
                'host' => '',
                'path' => '/',
            ],
        ];
        yield 'scheme.ton #16 file without colon' => [
            'file',
            null,
        ];
    }

    /**
     * @param ?array<string, string> $expected
     */
    #[DataProvider('upstreamSchemeProvider')]
    public function testUpstreamSchemeFixtures(string $source, ?array $expected): void
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

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function upstreamQueryProvider(): iterable
    {
        yield 'query.ton #1 ascii query' => [
            'https://lexbor.com/?abc=xyz',
            'utf-8',
            [
                'done' => 'https://lexbor.com/?abc=xyz',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=xyz',
            ],
        ];
        yield 'query.ton #2 special query apostrophe' => [
            "https://lexbor.com/?abc='",
            'utf-8',
            [
                'done' => 'https://lexbor.com/?abc=%27',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=%27',
            ],
        ];
        yield 'query.ton #3 non-special query apostrophe' => [
            "my://lexbor.com/?abc='",
            'utf-8',
            [
                'done' => "my://lexbor.com/?abc='",
                'scheme' => 'my',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => "abc='",
            ],
        ];
        yield 'query.ton #4 utf-8 equivalent sign' => [
            "https://lexbor.com/?abc=\u{2261}",
            'utf-8',
            [
                'done' => 'https://lexbor.com/?abc=%E2%89%A1',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=%E2%89%A1',
            ],
        ];
        yield 'query.ton #5 shift_jis equivalent sign' => [
            "https://lexbor.com/?abc=\u{2261}",
            'Shift_JIS',
            [
                'done' => 'https://lexbor.com/?abc=%81%DF',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=%81%DF',
            ],
        ];
        yield 'query.ton #6 shift_jis fallback numeric character reference' => [
            "https://lexbor.com/?abc=\u{203D}",
            'Shift_JIS',
            [
                'done' => 'https://lexbor.com/?abc=%26%238253%3B',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=%26%238253%3B',
            ],
        ];
        yield 'query.ton #7 iso-2022-jp yen sign' => [
            "https://lexbor.com/?abc=\u{00A5}",
            'ISO-2022-JP',
            [
                'done' => 'https://lexbor.com/?abc=%1B(J\\%1B(B',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=%1B(J\\%1B(B',
            ],
        ];
        yield 'query.ton #8 shift_jis mixed query' => [
            "https://lexbor.com/?abc=1+1 \u{2261} 2%20\u{203D}",
            'Shift_JIS',
            [
                'done' => 'https://lexbor.com/?abc=1+1%20%81%DF%202%20%26%238253%3B',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=1+1%20%81%DF%202%20%26%238253%3B',
            ],
        ];
        yield 'query.ton #9 utf-8 multiple code points' => [
            "https://lexbor.com/?abc=\u{2261}\u{203D}",
            'utf-8',
            [
                'done' => 'https://lexbor.com/?abc=%E2%89%A1%E2%80%BD',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=%E2%89%A1%E2%80%BD',
            ],
        ];
        yield 'query.ton #10 query question mark' => [
            'https://lexbor.com/?abc=x?yz',
            'utf-8',
            [
                'done' => 'https://lexbor.com/?abc=x?yz',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => 'abc=x?yz',
            ],
        ];
        yield 'query.ton #11 leading question mark in query' => [
            'https://lexbor.com/??abc=xyz',
            'utf-8',
            [
                'done' => 'https://lexbor.com/??abc=xyz',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => '?abc=xyz',
            ],
        ];
        yield 'query.ton #12 empty query' => [
            'https://lexbor.com/?',
            'utf-8',
            [
                'done' => 'https://lexbor.com/?',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'query' => '',
            ],
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('upstreamQueryProvider')]
    public function testUpstreamQueryFixtures(string $source, string $encoding, array $expected): void
    {
        $url = (new Parser())->parse($source, null, $encoding);

        self::assertNotNull($url);
        self::assertSame($expected['done'], $url->serialize());
        self::assertSame($expected['scheme'], $url->scheme);
        self::assertSame($expected['host'], $url->host);
        self::assertSame($expected['path'], $url->path);
        self::assertSame($expected['query'], $url->query);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function upstreamPathProvider(): iterable
    {
        foreach (self::urlFixtureEntries('path.ton') as $index => $entry) {
            yield 'path.ton #' . ($index + 1) => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('upstreamPathProvider')]
    public function testUpstreamPathFixtures(array $entry): void
    {
        $url = (new Parser())->parse($entry['url'], null, $entry['encoding'] ?? 'utf-8');

        if ($entry['failed'] ?? false) {
            self::assertNull($url);
            return;
        }

        self::assertNotNull($url);
        self::assertSame($entry['done'], $url->serialize());
        self::assertSame($entry['host'] ?? '', $url->host);
        self::assertSame($entry['path'] ?? '', $url->path);
        self::assertSame($entry['query'] ?? null, $url->query);
        self::assertSame($entry['fragment'] ?? null, $url->fragment);

        if (array_key_exists('path_length', $entry)) {
            self::assertSame($entry['path_length'], self::pathSegmentCount($url->path));
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function upstreamSlowPathProvider(): iterable
    {
        foreach (self::urlFixtureEntries('slow_path.ton') as $index => $entry) {
            yield 'slow_path.ton #' . ($index + 1) => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('upstreamSlowPathProvider')]
    public function testUpstreamSlowPathFixtures(array $entry): void
    {
        $url = (new Parser())->parse($entry['url'], null, $entry['encoding'] ?? 'utf-8');

        if ($entry['failed'] ?? false) {
            self::assertNull($url);
            return;
        }

        self::assertNotNull($url);
        self::assertSame($entry['done'], $url->serialize());
        self::assertSame($entry['host'] ?? '', $url->host);
        self::assertSame($entry['path'] ?? '', $url->path);
        self::assertSame($entry['query'] ?? null, $url->query);
        self::assertSame($entry['fragment'] ?? null, $url->fragment);

        if (array_key_exists('path_length', $entry)) {
            self::assertSame($entry['path_length'], self::pathSegmentCount($url->path));
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function upstreamDomainProvider(): iterable
    {
        foreach (self::urlFixtureEntries('domain.ton') as $index => $entry) {
            yield 'domain.ton #' . ($index + 1) => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('upstreamDomainProvider')]
    public function testUpstreamDomainFixtures(array $entry): void
    {
        $url = (new Parser())->parse($entry['url'], null, $entry['encoding'] ?? 'utf-8');

        if ($entry['failed'] ?? false) {
            self::assertNull($url);
            return;
        }

        self::assertNotNull($url);
        self::assertSame($entry['done'], $url->serialize());
        self::assertSame($entry['scheme'], $url->scheme);
        self::assertSame($entry['host'] ?? '', $url->host);
        self::assertSame($entry['path'] ?? '', $url->path);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function upstreamIpv4Provider(): iterable
    {
        foreach (self::urlFixtureEntries('ipv4.ton') as $index => $entry) {
            yield 'ipv4.ton #' . ($index + 1) => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('upstreamIpv4Provider')]
    public function testUpstreamIpv4Fixtures(array $entry): void
    {
        $url = (new Parser())->parse($entry['url'], null, $entry['encoding'] ?? 'utf-8');

        if ($entry['failed'] ?? false) {
            self::assertNull($url);
            return;
        }

        self::assertNotNull($url);
        self::assertSame($entry['done'], $url->serialize());
        self::assertSame($entry['scheme'], $url->scheme);
        self::assertSame($entry['host'] ?? '', $url->host);
        self::assertSame($entry['path'] ?? '', $url->path);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function upstreamIpv6Provider(): iterable
    {
        foreach (self::urlFixtureEntries('ipv6.ton') as $index => $entry) {
            yield 'ipv6.ton #' . ($index + 1) => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('upstreamIpv6Provider')]
    public function testUpstreamIpv6Fixtures(array $entry): void
    {
        $url = (new Parser())->parse($entry['url'], null, $entry['encoding'] ?? 'utf-8');

        if ($entry['failed'] ?? false) {
            self::assertNull($url);
            return;
        }

        self::assertNotNull($url);
        self::assertSame($entry['done'], $url->serialize());
        self::assertSame($entry['scheme'], $url->scheme);
        self::assertSame($entry['host'] ?? '', $url->host);
        self::assertSame($entry['path'] ?? '', $url->path);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function upstreamChangesProtocolProvider(): iterable
    {
        foreach (self::urlFixtureEntries('changes.ton') as $index => $entry) {
            $changes = $entry['change'];
            $activeChanges = array_filter(
                $changes,
                static fn (mixed $value): bool => $value !== null,
            );

            if (array_keys($activeChanges) !== ['protocol']) {
                continue;
            }

            yield 'changes.ton #' . ($index + 1) => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('upstreamChangesProtocolProvider')]
    public function testUpstreamChangesProtocolFixtures(array $entry): void
    {
        $url = (new Parser())->parse($entry['url']);

        self::assertNotNull($url);
        self::assertSame(! ($entry['failed'] ?? false), $url->setProtocol($entry['change']['protocol']));
        self::assertSame($entry['done'], $url->serialize());
        self::assertSame($entry['scheme'], $url->scheme);
        self::assertSame($entry['host'] ?? '', $url->host);
        self::assertSame($entry['path'] ?? '', $url->path);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function upstreamChangesCredentialsProvider(): iterable
    {
        foreach (self::urlFixtureEntries('changes.ton') as $index => $entry) {
            $changes = $entry['change'];
            $activeChanges = array_filter(
                $changes,
                static fn (mixed $value): bool => $value !== null,
            );
            $keys = array_keys($activeChanges);

            if ($keys !== ['username'] && $keys !== ['password']) {
                continue;
            }

            yield 'changes.ton #' . ($index + 1) => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('upstreamChangesCredentialsProvider')]
    public function testUpstreamChangesCredentialsFixtures(array $entry): void
    {
        $url = (new Parser())->parse($entry['url']);
        $changes = $entry['change'];

        self::assertNotNull($url);

        if ($changes['username'] !== null) {
            self::assertSame(! ($entry['failed'] ?? false), $url->setUsername($changes['username']));
        }

        if ($changes['password'] !== null) {
            self::assertSame(! ($entry['failed'] ?? false), $url->setPassword($changes['password']));
        }

        self::assertSame($entry['done'], $url->serialize());
        self::assertSame($entry['scheme'], $url->scheme);
        self::assertSame($entry['username'] ?? '', $url->username);
        self::assertSame($entry['password'] ?? '', $url->password);
        self::assertSame($entry['host'] ?? '', $url->host);
        self::assertSame($entry['path'] ?? '', $url->path);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function upstreamChangesHostProvider(): iterable
    {
        foreach (self::urlFixtureEntries('changes.ton') as $index => $entry) {
            if ($index < 15 || $index > 22) {
                continue;
            }

            $changes = $entry['change'];
            $activeChanges = array_filter(
                $changes,
                static fn (mixed $value): bool => $value !== null,
            );
            $keys = array_keys($activeChanges);

            if ($keys !== ['host'] && $keys !== ['hostname']) {
                continue;
            }

            yield 'changes.ton #' . ($index + 1) => [$entry];
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('upstreamChangesHostProvider')]
    public function testUpstreamChangesHostFixtures(array $entry): void
    {
        $url = (new Parser())->parse($entry['url']);
        $changes = $entry['change'];

        self::assertNotNull($url);

        if ($changes['host'] !== null) {
            self::assertSame(! ($entry['failed'] ?? false), $url->setHost($changes['host']));
        }

        if ($changes['hostname'] !== null) {
            self::assertSame(! ($entry['failed'] ?? false), $url->setHostname($changes['hostname']));
        }

        self::assertSame($entry['done'], $url->serialize());
        self::assertSame($entry['scheme'], $url->scheme);
        self::assertSame($entry['host'] ?? '', $url->host);
        self::assertSame($entry['path'] ?? '', $url->path);
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

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function upstreamFragmentProvider(): iterable
    {
        yield 'fragment.ton #1 ascii fragment' => [
            'https://lexbor.com/#install',
            [
                'done' => 'https://lexbor.com/#install',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'fragment' => 'install',
            ],
        ];
        yield 'fragment.ton #2 fragment space' => [
            'https://lexbor.com/#ins tall',
            [
                'done' => 'https://lexbor.com/#ins%20tall',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'fragment' => 'ins%20tall',
            ],
        ];
        yield 'fragment.ton #3 empty fragment' => [
            'https://lexbor.com/#',
            [
                'done' => 'https://lexbor.com/#',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'fragment' => '',
            ],
        ];
        yield 'fragment.ton #4 utf-8 fragment' => [
            "https://lexbor.com/#\u{0401}",
            [
                'done' => 'https://lexbor.com/#%D0%81',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
                'fragment' => '%D0%81',
            ],
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('upstreamFragmentProvider')]
    public function testUpstreamFragmentFixtures(string $source, array $expected): void
    {
        $url = (new Parser())->parse($source);

        self::assertNotNull($url);
        self::assertSame($expected['done'], $url->serialize());
        self::assertSame($expected['scheme'], $url->scheme);
        self::assertSame($expected['host'], $url->host);
        self::assertSame($expected['path'], $url->path);
        self::assertSame($expected['fragment'], $url->fragment);
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function upstreamUsernamePasswordProvider(): iterable
    {
        yield 'username_password.ton #1 username and password' => [
            'https://user:password@lexbor.com',
            [
                'done' => 'https://user:password@lexbor.com/',
                'scheme' => 'https',
                'username' => 'user',
                'password' => 'password',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #2 username only' => [
            'https://user@lexbor.com',
            [
                'done' => 'https://user@lexbor.com/',
                'scheme' => 'https',
                'username' => 'user',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #3 password only' => [
            'https://:password@lexbor.com',
            [
                'done' => 'https://:password@lexbor.com/',
                'scheme' => 'https',
                'password' => 'password',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #4 at sign in password' => [
            'https://user:password@next@lexbor.com',
            [
                'done' => 'https://user:password%40next@lexbor.com/',
                'scheme' => 'https',
                'username' => 'user',
                'password' => 'password%40next',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #5 multiple at signs in password' => [
            'https://user:password@next@ends@lexbor.com',
            [
                'done' => 'https://user:password%40next%40ends@lexbor.com/',
                'scheme' => 'https',
                'username' => 'user',
                'password' => 'password%40next%40ends',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #6 multiple at signs in username' => [
            'https://user@next@ends@lexbor.com',
            [
                'done' => 'https://user%40next%40ends@lexbor.com/',
                'scheme' => 'https',
                'username' => 'user%40next%40ends',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #7 userinfo delimiters' => [
            'https://us=er:pass:word@lexbor.com',
            [
                'done' => 'https://us%3Der:pass%3Aword@lexbor.com/',
                'scheme' => 'https',
                'username' => 'us%3Der',
                'password' => 'pass%3Aword',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #8 utf-8 password' => [
            "https://user:pass\u{0401}word@lexbor.com",
            [
                'done' => 'https://user:pass%D0%81word@lexbor.com/',
                'scheme' => 'https',
                'username' => 'user',
                'password' => 'pass%D0%81word',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #9 empty credentials' => [
            'https://@lexbor.com',
            [
                'done' => 'https://lexbor.com/',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #10 empty username and password' => [
            'https://:@lexbor.com',
            [
                'done' => 'https://lexbor.com/',
                'scheme' => 'https',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
        yield 'username_password.ton #11 colon-only password' => [
            'https://:::::@lexbor.com',
            [
                'done' => 'https://:%3A%3A%3A%3A@lexbor.com/',
                'scheme' => 'https',
                'password' => '%3A%3A%3A%3A',
                'host' => 'lexbor.com',
                'path' => '/',
            ],
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('upstreamUsernamePasswordProvider')]
    public function testUpstreamUsernamePasswordFixtures(string $source, array $expected): void
    {
        $url = (new Parser())->parse($source);

        self::assertNotNull($url);
        self::assertSame($expected['done'], $url->serialize());
        self::assertSame($expected['scheme'], $url->scheme);
        self::assertSame($expected['username'] ?? '', $url->username);
        self::assertSame($expected['password'] ?? '', $url->password);
        self::assertSame($expected['host'], $url->host);
        self::assertSame($expected['path'], $url->path);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function urlFixtureEntries(string $filename): array
    {
        $path = dirname(__DIR__) . '/Fixtures/Url/' . $filename;
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read URL fixture: {$filename}");
        }

        $json = preg_replace('/\/\*.*?\*\//s', '', $contents);
        $json = preg_replace('/,\s*([\]}])/m', '$1', $json ?? '');
        $entries = json_decode($json ?? '', true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($entries)) {
            throw new \RuntimeException("URL fixture did not decode to a list: {$filename}");
        }

        return $entries;
    }

    private static function pathSegmentCount(string $path): int
    {
        return $path === '' ? 0 : substr_count($path, '/');
    }
}

<?php

declare(strict_types=1);

namespace Lexbor\Tests\Url;

use Lexbor\Url\Parser;
use Lexbor\Url\SearchParams;
use PHPUnit\Framework\TestCase;

final class OtherTest extends TestCase
{
    public function testUpstreamOtherUrlClone(): void
    {
        $url = (new Parser())->parse('https://192.168.0.1/');

        self::assertNotNull($url);

        $clone = clone $url;

        self::assertNotSame($url, $clone);
        self::assertSame('https://192.168.0.1/', $clone->serialize());
    }

    public function testUpstreamOtherSpecialSchemePathMemoryRegression(): void
    {
        $input = 'ftp:a' . chr(92) . str_repeat('a', 22) . str_repeat(chr(92), 3);
        $parser = new Parser();

        for ($i = 0; $i < 100; $i++) {
            $url = $parser->parse($input);

            self::assertNotNull($url);
        }
    }

    public function testUpstreamOtherFileChangeHostname(): void
    {
        $url = (new Parser())->parse('file:');

        self::assertNotNull($url);
        self::assertTrue($url->setHostname(''));
        self::assertSame('file:///', $url->serialize());
    }

    public function testUpstreamOtherSearchParamsAppendAfterTailToken(): void
    {
        $params = new SearchParams('abc');
        $params->append('k', 'v');

        self::assertSame('abc=&k=v', $params->serialize());
    }

    public function testUpstreamOtherPathSlowPathGrow(): void
    {
        $url = (new Parser())->parse('http://a/' . str_repeat('^', 2000));

        self::assertNotNull($url);
        self::assertSame(2001, strlen($url->path));
        self::assertSame('/' . str_repeat('^', 2000), $url->path);
    }

    public function testUpstreamErrorsUseLogAfterFreeParserReuse(): void
    {
        $parser = new Parser();

        self::assertNull($parser->parse("x://:\n/"));

        $url = $parser->parse('https://lexbor.com/');

        self::assertNotNull($url);
        self::assertSame('https://lexbor.com/', $url->serialize());
        self::assertSame([], $url->errors());
    }
}

<?php

declare(strict_types=1);

namespace Lexbor\Tests\Url;

use Lexbor\Url\SearchParams;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchParamsTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string, bool}>
     */
    public static function upstreamInitProvider(): iterable
    {
        yield 'search_params.c init #1 empty query' => ['', '', false];
        yield 'search_params.c init #2 preserves order' => ['xyz=789&abc=123', 'xyz=789&abc=123', false];
        yield 'search_params.c init #3 sorted query' => ['xyz=789&abc=123', 'abc=123&xyz=789', true];
        yield 'search_params.c init #4 strips leading question mark' => ['?xyz=789&abc=123', 'xyz=789&abc=123', false];
        yield 'search_params.c init #5 missing value' => ['xyz789&abc=123', 'xyz789=&abc=123', false];
        yield 'search_params.c init #6 empty value' => ['xyz=&abc=123', 'xyz=&abc=123', false];
        yield 'search_params.c init #7 name with equals value' => ['xyz789abc=123', 'xyz789abc=123', false];
        yield 'search_params.c init #8 single name' => ['xyz', 'xyz=', false];
        yield 'search_params.c init #9 names without values' => ['xyz&abc', 'xyz=&abc=', false];
        yield 'search_params.c init #10 sorted names without values' => ['xyz&abc', 'abc=&xyz=', true];
        yield 'search_params.c init #11 sorted numeric name' => ['xyz=789&123=abc', '123=abc&xyz=789', true];
        yield 'search_params.c init #12 pluses preserved' => ['xyz=7+8+9', 'xyz=7+8+9', false];
        yield 'search_params.c init #13 spaces become pluses' => ['xyz=7 8 9', 'xyz=7+8+9', false];
        yield 'search_params.c init #14 utf-8 value' => ['xyz=спасибо', 'xyz=%D1%81%D0%BF%D0%B0%D1%81%D0%B8%D0%B1%D0%BE', false];
        yield 'search_params.c init #15 null query' => [null, '', true];
    }

    #[DataProvider('upstreamInitProvider')]
    public function testUpstreamInitialization(?string $query, string $expected, bool $sort): void
    {
        $params = new SearchParams($query);

        if ($sort) {
            $params->sort();
        }

        self::assertSame($expected, $params->serialize());
    }

    public function testDecodedInputSerializesWithFormEncoding(): void
    {
        $params = new SearchParams('x=%D1%81&plus=7+8+9');

        self::assertSame('с', $params->get('x'));
        self::assertSame('7 8 9', $params->get('plus'));
        self::assertSame('x=%D1%81&plus=7+8+9', $params->serialize());
    }

    public function testLiteralPlusIsPercentEncoded(): void
    {
        $params = new SearchParams();
        $params->append('x', '+');

        self::assertSame('x=%2B', $params->serialize());
    }

    public function testTrailingEmptyQueryPartIsIgnored(): void
    {
        self::assertSame('', (new SearchParams('?'))->serialize());
        self::assertSame('a=', (new SearchParams('a&'))->serialize());
        self::assertSame('=', (new SearchParams('&'))->serialize());
        self::assertSame('a=', (new SearchParams('a=&'))->serialize());
    }

    public function testInvalidDecodedBytesSerializeByByte(): void
    {
        $params = new SearchParams('x=%FF&broken=%C3%28');

        self::assertSame("\xFF", $params->get('x'));
        self::assertSame("\xC3(", $params->get('broken'));
        self::assertSame('x=%FF&broken=%C3%28', $params->serialize());
    }

    public function testUpstreamNullInitAppend(): void
    {
        $params = new SearchParams(null);
        $params->append('abc', 'xyz');

        self::assertSame('abc=xyz', $params->serialize());
    }

    public function testUpstreamAppend(): void
    {
        $params = new SearchParams('first=last&new=old');
        $params->append('abc', 'xyz');

        self::assertSame('first=last&new=old&abc=xyz', $params->serialize());
    }

    public function testUpstreamDeleteNameValue(): void
    {
        $params = new SearchParams('first=last&abc=xyz&new=old');
        $params->delete('abc', 'xyz');

        self::assertSame('first=last&new=old', $params->serialize());
    }

    public function testUpstreamDeleteName(): void
    {
        $params = new SearchParams('first=last&abc=xyz&new=old');
        $params->delete('abc');

        self::assertSame('first=last&new=old', $params->serialize());
    }

    public function testUpstreamDeleteNameDuplicates(): void
    {
        $params = new SearchParams('abc=123&first=last&abc=xyz&new=old');
        $params->delete('abc');

        self::assertSame('first=last&new=old', $params->serialize());
    }

    public function testUpstreamDeleteNameValueDuplicates(): void
    {
        $params = new SearchParams('abc=xyz&first=last&abc=xyz&new=old');
        $params->delete('abc', 'xyz');

        self::assertSame('first=last&new=old', $params->serialize());
    }

    public function testUpstreamGet(): void
    {
        $params = new SearchParams('first=last&abc=xyz&new=old&abc=123');

        self::assertSame('xyz', $params->get('abc'));
    }

    public function testUpstreamInitNullGet(): void
    {
        $params = new SearchParams(null);

        self::assertNull($params->get('abc'));
    }

    public function testUpstreamHasOnlyName(): void
    {
        $params = new SearchParams('first=last&abc=xyz&new=old');

        self::assertTrue($params->has('abc'));
    }

    public function testUpstreamHasNameValue(): void
    {
        $params = new SearchParams('abc=123&first=last&abc=xyz&new=old');

        self::assertTrue($params->has('abc', 'xyz'));
    }

    public function testUpstreamHasNameValueNotEqual(): void
    {
        $params = new SearchParams('abc=123&first=last&abc=890&new=old');

        self::assertFalse($params->has('abc', 'xyz'));
    }

    public function testUpstreamNullInitHasOnlyName(): void
    {
        $params = new SearchParams(null);

        self::assertFalse($params->has('abc'));
    }

    public function testUpstreamSet(): void
    {
        $params = new SearchParams('abc=123&first=last&abc=890&new=old&abc=all');

        self::assertCount(5, $params);

        $params->set('abc', 'xyz');

        self::assertCount(3, $params);
        self::assertSame('abc=xyz&first=last&new=old', $params->serialize());
    }

    public function testUpstreamSetWithout(): void
    {
        $params = new SearchParams('first=last&new=old');

        self::assertCount(2, $params);

        $params->set('abc', 'xyz');

        self::assertCount(3, $params);
        self::assertSame('first=last&new=old&abc=xyz', $params->serialize());
    }
}

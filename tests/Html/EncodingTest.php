<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Html\Encoding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, int}>
     */
    public static function upstreamMetaProvider(): iterable
    {
        yield 'html/encoding.c #1 charset unquoted' => ['<meta charset=utf-8>', 'utf-8', 0];
        yield 'html/encoding.c #2 charset single quoted' => ["<meta charset='utf-8'>", 'utf-8', 0];
        yield 'html/encoding.c #3 charset preserves quoted whitespace' => ["<meta charset=' utf-8 '>", ' utf-8 ', 0];
        yield 'html/encoding.c #4 http-equiv before charset' => ['<meta http-equiv="content-type" charset=utf-8>', 'utf-8', 0];
        yield 'html/encoding.c #5 charset before http-equiv' => ['<meta charset=utf-8 http-equiv="content-type">', 'utf-8', 0];
        yield 'html/encoding.c #6 content-type charset' => ['<meta http-equiv="content-type" content="text/html; charset=utf-8">', 'utf-8', 0];
        yield 'html/encoding.c #7 content before http-equiv' => ['<meta content="text/html; charset=utf-8" http-equiv="content-type">', 'utf-8', 0];
        yield 'html/encoding.c #8 wrong http-equiv' => ['<meta content="text/html; charset=utf-8" http-equiv="content-typ">', null, 0];
        yield 'html/encoding.c #9 quoted charset inside content' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8\'">', 'utf-8', 0];
        yield 'html/encoding.c #10 quoted content charset preserves whitespace' => ['<meta http-equiv="content-type" content="text/html; charset=\' utf-8 \'">', ' utf-8 ', 0];
        yield 'html/encoding.c #11 rejects unclosed quoted content charset' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8">', null, 0];
        yield 'html/encoding.c #12 rejects unclosed quoted content charset with trailing spaces' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8   ">', null, 0];
        yield 'html/encoding.c #13 second meta entry' => [
            '<meta http-equiv="content-type" content="text/html; charset=windows-1251">'
            . '<meta http-equiv="content-type" content="text/html; charset=utf-8">',
            'utf-8',
            1,
        ];
        yield 'html/encoding.c #14 content without http-equiv' => ['<meta content="text/html; charset=utf-8">', null, 0];
        yield 'html/encoding.c #15 content-type without charset' => ['<meta http-equiv="content-type" content="text/html">', null, 0];
        yield 'html/encoding.c #16 meta after html tag' => ["<html>\n <meta http-equiv=\"content-type\" charset=utf-8>", 'utf-8', 0];
        yield 'html/encoding.c #17 ignores meta text inside quoted end-tag attribute' => [
            "</html lala='><meta charset=cp1251>'>\n <meta http-equiv=\"content-type\" charset=utf-8>",
            'utf-8',
            0,
        ];
        yield 'html/encoding.c #18 charset before viewport metadata' => ['<meta charset="windows-1251" name="viewport" content="width">', 'windows-1251', 0];
        yield 'html/encoding.c #19 charset among bogus attributes' => ['<meta bu charset="windows-1251" be name="viewport" bu content="width" be>', 'windows-1251', 0];
        yield 'html/encoding.c #20 whitespace before equals' => ['<meta charset =utf-8>', 'utf-8', 0];
        yield 'html/encoding.c #21 whitespace around equals' => ['<meta charset = utf-8>', 'utf-8', 0];
        yield 'html/encoding.c #22 repeated whitespace around equals' => ['<meta charset   =   utf-8   >', 'utf-8', 0];
        yield 'html/encoding.c #23 quoted value after whitespace around equals' => ["<meta charset = 'utf-8'>", 'utf-8', 0];
        yield 'regression skips meta-looking text inside comments' => ['<!-- > <meta charset=utf-8> -->', null, 0];
        yield 'regression resumes scanning after comment close' => [
            '<!-- > <meta charset=utf-8> --><meta charset=windows-1251>',
            'windows-1251',
            0,
        ];
        yield 'regression valueless charset does not suppress content charset' => [
            '<meta charset content="text/html; charset=utf-8" http-equiv=content-type>',
            'utf-8',
            0,
        ];
        yield 'regression unquoted content charset rejects apostrophe' => [
            '<meta http-equiv=content-type content="text/html; charset=utf\'8">',
            null,
            0,
        ];
        yield 'regression unquoted content charset rejects quote' => [
            '<meta http-equiv=content-type content=\'text/html; charset=utf"8\'>',
            null,
            0,
        ];
        yield 'regression unquoted charset preserves slash' => ['<meta charset=utf/8>', 'utf/8', 0];
        yield 'regression content charset scan does not require token boundary' => [
            '<meta http-equiv=content-type content="text/html; foocharset=utf-8">',
            'utf-8',
            0,
        ];
        yield 'regression mixed content charset remains first entry' => [
            '<meta http-equiv=content-type content="text/html; charset=koi8-r" charset=utf-8>',
            'koi8-r',
            0,
        ];
        yield 'regression mixed direct charset remains second entry' => [
            '<meta http-equiv=content-type content="text/html; charset=koi8-r" charset=utf-8>',
            'utf-8',
            1,
        ];
        yield 'regression charset prefix attribute name matches upstream' => ['<meta charsetx=utf-8>', 'utf-8', 0];
        yield 'regression content prefix attribute name matches upstream' => [
            '<meta http-equiv=content-type contentx="text/html; charset=utf-8">',
            'utf-8',
            0,
        ];
        yield 'regression processing instruction raw skip exposes following meta' => [
            '<?x="><meta charset=cp1251>"><meta charset=utf-8>',
            'cp1251',
            0,
        ];
        yield 'regression non-alpha end tag raw skip exposes following meta' => [
            '</1 x="><meta charset=cp1251>"><meta charset=utf-8>',
            'cp1251',
            0,
        ];
        yield 'regression short comment close exposes following meta' => ['<!--><meta charset=utf-8>', 'utf-8', 0];
        yield 'regression slash in non-meta name raw-skips to inner meta' => [
            '<div/lala=\'><meta charset=cp1251>\'><meta charset=utf-8>',
            'cp1251',
            0,
        ];
        yield 'regression slash in alpha end-tag name raw-skips to inner meta' => [
            '</html/lala=\'><meta charset=cp1251>\'><meta charset=utf-8>',
            'cp1251',
            0,
        ];
        yield 'regression length-10 charset prefix is ignored like http-equiv branch' => [
            '<meta charsetxxx=utf-8>',
            null,
            0,
        ];
        yield 'regression length-10 content prefix is ignored like http-equiv branch' => [
            '<meta http-equiv=content-type contentxxx="text/html; charset=utf-8">',
            null,
            0,
        ];
        yield 'regression slash after charset name leaves it valueless' => ['<meta charset/=utf-8>', null, 0];
        yield 'regression slash after content name leaves it valueless' => [
            '<meta http-equiv=content-type content/="text/html; charset=utf-8">',
            null,
            0,
        ];
        yield 'regression slash after http-equiv name leaves pragma unset' => [
            '<meta http-equiv/=content-type content=charset=utf-8>',
            null,
            0,
        ];
        yield 'regression bare equals consumes following charset as bogus value' => [
            '<meta = charset=utf-8>',
            null,
            0,
        ];
    }

    #[DataProvider('upstreamMetaProvider')]
    public function testUpstreamEncodingByMeta(string $html, ?string $expected, int $index): void
    {
        self::assertMetaEntry($html, $expected, $index);
        self::assertIncrementalMetaEntry($html, $expected, $index);
    }

    private static function assertMetaEntry(string $html, ?string $expected, int $index): void
    {
        $encoding = new Encoding();

        self::assertSame(Status::Ok, $encoding->determine($html));
        self::assertSame($expected, $encoding->metaEntry($index));
        self::assertSame($expected, $encoding->metaEntries()[$index] ?? null);
    }

    private static function assertIncrementalMetaEntry(string $html, ?string $expected, int $index): void
    {
        $encoding = new Encoding();
        $length = strlen($html);

        for ($offset = 0; $offset <= $length; $offset++) {
            $encoding->clean();
            self::assertSame(Status::Ok, $encoding->determine(substr($html, 0, $offset)));

            $entry = $encoding->metaEntry($index);
            if ($entry !== null) {
                self::assertSame($expected, $entry);
                return;
            }
        }

        self::assertNull($expected);
    }
}

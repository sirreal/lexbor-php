<?php

declare(strict_types=1);

namespace Lexbor\Tests\Encoding;

use Lexbor\Encoding\Encoding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EncodingTest extends TestCase
{
    public function testUpstreamEncodingLabelMapParity(): void
    {
        $labels = self::upstreamEncodingLabels();

        self::assertCount(219, $labels);

        foreach ($labels as [$label, $encoding]) {
            self::assertSame($encoding, Encoding::dataByPreName($label), $label);
            self::assertSame($encoding, Encoding::dataByPreName(strtoupper($label)), $label);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function bomSniffProvider(): iterable
    {
        yield 'encoding.c UTF-8 BOM' => ["\xEF\xBB\xBFabc", Encoding::UTF_8];
        yield 'encoding.c UTF-16BE BOM' => ["\xFE\xFFabc", Encoding::UTF_16BE];
        yield 'encoding.c UTF-16LE BOM' => ["\xFF\xFEabc", Encoding::UTF_16LE];
        yield 'encoding.c empty input' => ['', Encoding::DEFAULT];
        yield 'encoding.c partial UTF-8 BOM' => ["\xEF\xBB", Encoding::DEFAULT];
        yield 'encoding.c no BOM' => ['abc', Encoding::DEFAULT];
    }

    #[DataProvider('bomSniffProvider')]
    public function testUpstreamEncodingBomSniff(string $input, string $expected): void
    {
        self::assertSame($expected, Encoding::bomSniff($input));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function dataByPreNameWhitespaceProvider(): iterable
    {
        yield 'encoding.c trims ASCII whitespace' => [" \t\n\f\rutf-8 \t\n\f\r", Encoding::UTF_8];
        yield 'encoding.c trims label before alias lookup' => [" \tiso-8859-1\n", Encoding::WINDOWS_1252];
        yield 'encoding.c whitespace only' => [" \t\n\f\r", null];
        yield 'encoding.c does not trim vertical tab' => ["\vutf-8", null];
        yield 'encoding.c unknown label' => ['definitely-not-an-encoding', null];
    }

    #[DataProvider('dataByPreNameWhitespaceProvider')]
    public function testUpstreamEncodingDataByPreNameWhitespaceHandling(string $name, ?string $expected): void
    {
        self::assertSame($expected, Encoding::dataByPreName($name));
    }

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function prescanValidateProvider(): iterable
    {
        yield 'encoding.c UTF-16BE is prescan-normalized to UTF-8' => ['utf-16be', Encoding::UTF_8, Encoding::UTF_8];
        yield 'encoding.c UTF-16LE is prescan-normalized to UTF-8' => ['utf-16le', Encoding::UTF_8, Encoding::UTF_8];
        yield 'encoding.c UTF-16 alias is prescan-normalized to UTF-8' => ['utf-16', Encoding::UTF_8, Encoding::UTF_8];
        yield 'encoding.c x-user-defined is prescan-normalized to windows-1252' => [
            'x-user-defined',
            Encoding::WINDOWS_1252,
            Encoding::WINDOWS_1252,
        ];
        yield 'encoding.c normal label is unchanged' => ['windows-31j', 'Shift_JIS', 'Shift_JIS'];
        yield 'encoding.c replacement label is unchanged' => ['replacement', 'replacement', 'replacement'];
        yield 'encoding.c unknown label maps to default enum' => ['definitely-not-an-encoding', null, Encoding::DEFAULT];
    }

    #[DataProvider('prescanValidateProvider')]
    public function testUpstreamEncodingPrescanValidate(string $name, ?string $dataExpected, string $enumExpected): void
    {
        self::assertSame($dataExpected, Encoding::dataPrescanValidate($name));
        self::assertSame($enumExpected, Encoding::prescanValidate($name));
    }

    /**
     * @return list<array{string, string}>
     */
    private static function upstreamEncodingLabels(): array
    {
        $source = dirname(__DIR__, 2) . '/upstream/lexbor/source/lexbor/encoding/res.c';
        $contents = file_get_contents($source);

        self::assertIsString($contents);

        self::assertSame(
            1,
            preg_match('/lxb_encoding_res_map\[LXB_ENCODING_LAST_ENTRY\]\s*=\s*\{(.*?)\n\};/s', $contents, $mapMatch),
            'Unable to parse upstream encoding map.',
        );
        preg_match_all(
            '/\{LXB_ENCODING_[^\{\}]*?\(lxb_char_t \*\) "([^"]+)"\}/s',
            $mapMatch[1],
            $nameMatches,
        );

        self::assertCount(43, $nameMatches[1]);

        self::assertSame(
            1,
            preg_match('/lxb_encoding_res_shs_entities\[220\]\s*=\s*\{(.*?)\n\};/s', $contents, $shsMatch),
            'Unable to parse upstream encoding label table.',
        );
        preg_match_all(
            '/\{"([^"]+)",\s*\(void \*\) &lxb_encoding_res_map\[(\d+)\]/',
            $shsMatch[1],
            $labelMatches,
            PREG_SET_ORDER,
        );

        $labels = [];
        foreach ($labelMatches as $match) {
            $labels[] = [$match[1], $nameMatches[1][(int) $match[2]]];
        }

        return $labels;
    }
}

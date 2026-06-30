<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Html\TokenizerError;
use Lexbor\Html\TreeError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorDescriptionTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function tokenizerErrorProvider(): iterable
    {
        $descriptions = self::upstreamDescriptions(
            '/upstream/lexbor/source/lexbor/html/tokenizer/error.c',
            'LXB_HTML_TOKENIZER_ERROR_LAST_ENTRY',
        );

        self::assertCount(TokenizerError::LAST_ENTRY, $descriptions);

        for ($id = 0; $id < TokenizerError::LAST_ENTRY + 2; $id++) {
            yield 'tokenizer/errors.c #' . $id => [$id, $descriptions[$id] ?? 'unknown error'];
        }
    }

    #[DataProvider('tokenizerErrorProvider')]
    public function testUpstreamTokenizerErrorDescriptions(int $id, string $expected): void
    {
        $description = TokenizerError::description($id);

        self::assertSame($expected, $description);
        self::assertNotSame('', $description);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function treeErrorProvider(): iterable
    {
        $descriptions = self::upstreamDescriptions(
            '/upstream/lexbor/source/lexbor/html/tree/error.c',
            'LXB_HTML_RULES_ERROR_LAST_ENTRY',
        );

        self::assertCount(TreeError::LAST_ENTRY, $descriptions);

        for ($id = 0; $id < TreeError::LAST_ENTRY + 2; $id++) {
            yield 'tree/errors.c #' . $id => [$id, $descriptions[$id] ?? 'unknown error'];
        }
    }

    #[DataProvider('treeErrorProvider')]
    public function testUpstreamTreeErrorDescriptions(int $id, string $expected): void
    {
        $description = TreeError::description($id);

        self::assertSame($expected, $description);
        self::assertNotSame('', $description);
    }

    /**
     * @return list<string>
     */
    private static function upstreamDescriptions(string $path, string $lastEntryConstant): array
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . $path);

        self::assertIsString($contents);
        self::assertSame(
            1,
            preg_match('/errors\[' . preg_quote($lastEntryConstant, '/') . '\]\s*=\s*\{(.*?)\n    \};/s', $contents, $match),
            "Unable to parse upstream error table from {$path}.",
        );

        preg_match_all('/lexbor_str\("([^"]+)"\)/', $match[1], $descriptions);

        return $descriptions[1];
    }
}

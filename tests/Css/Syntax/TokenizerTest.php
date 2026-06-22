<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Syntax;

use Lexbor\Css\Syntax\Tokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamSingleTokenProvider(): iterable
    {
        yield 'single-tokens.ton #1 left parenthesis' => ['(', [['left-parenthesis', '(', 1]]];
        yield 'single-tokens.ton #2 right parenthesis' => [')', [['right-parenthesis', ')', 1]]];
        yield 'single-tokens.ton #3 comma' => [',', [['comma', ',', 1]]];
        yield 'single-tokens.ton #4 colon' => [':', [['colon', ':', 1]]];
        yield 'single-tokens.ton #5 semicolon' => [';', [['semicolon', ';', 1]]];
        yield 'single-tokens.ton #6 left square bracket' => ['[', [['left-square-bracket', '[', 1]]];
        yield 'single-tokens.ton #7 right square bracket' => [']', [['right-square-bracket', ']', 1]]];
        yield 'single-tokens.ton #8 left curly bracket' => ['{', [['left-curly-bracket', '{', 1]]];
        yield 'single-tokens.ton #9 right curly bracket' => ['}', [['right-curly-bracket', '}', 1]]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamWhitespaceProvider(): iterable
    {
        yield 'whitespace.ton #1 space' => [' ', [['whitespace', ' ', 1]]];
        yield 'whitespace.ton #2 tabs' => ["\t \t", [['whitespace', "\t \t", 3]]];
        yield 'whitespace.ton #3 carriage return' => ["\r", [['whitespace', "\n", 1]]];
        yield 'whitespace.ton #4 CRLF' => ["\r\n", [['whitespace', "\n", 2]]];
        yield 'whitespace.ton #5 form feed' => ["\f", [['whitespace', "\n", 1]]];
        yield 'whitespace.ton #6 mixed whitespace' => ["\f\f\r\t\r\n\n \r", [['whitespace', "\n\n\n\t\n\n \n", 9]]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamCommentProvider(): iterable
    {
        yield 'comment.ton #1 simple comment' => ['/* Comment */', [['comment', '/* Comment */', 13]]];
        yield 'comment.ton #2 unclosed comment' => ['/* Comment ', [['comment', '/* Comment */', 11]]];
        yield 'comment.ton #3 spaced close marker' => ['/* Comment * / */', [['comment', '/* Comment * / */', 17]]];
        yield 'comment.ton #4 repeated spaced close marker' => ['/* Comment * / */', [['comment', '/* Comment * / */', 17]]];
        yield 'comment.ton #5 nested opener text' => ['/* Comment * /* */', [['comment', '/* Comment * /* */', 18]]];
        yield 'comment.ton #6 null replacement' => ["/* \0 */", [['comment', "/* \u{FFFD} */", 7]]];
        yield 'comment.ton #7 literal escape text' => ['/* \\72 */', [['comment', '/* \\72 */', 9]]];
        yield 'comment.ton #8 CRLF normalization' => ["/* \r\n */", [['comment', "/* \n */", 8]]];
        yield 'comment.ton #9 form feed normalization' => ["/* \f */", [['comment', "/* \n */", 7]]];
        yield 'comment.ton #10 mixed newline normalization' => ["/* \f\n\r\r\n */", [['comment', "/* \n\n\n\n */", 11]]];
        yield 'comment.ton #11 trailing asterisk' => ['/* Comment *', [['comment', '/* Comment **/', 12]]];
        yield 'comment.ton #12 comment followed by hash' => ['/* Comment */#id', [
            ['comment', '/* Comment */', 13],
            ['hash', '#id', 3],
        ]];
        yield 'comment.ton #13 bare opener' => ['/*', [['comment', '/**/', 2]]];
    }

    #[DataProvider('upstreamSingleTokenProvider')]
    #[DataProvider('upstreamWhitespaceProvider')]
    #[DataProvider('upstreamCommentProvider')]
    public function testUpstreamTokenizerFixtures(string $css, array $expected): void
    {
        $tokens = (new Tokenizer())->tokenize($css);

        self::assertCount(count($expected), $tokens);

        foreach ($expected as $index => [$type, $value, $length]) {
            self::assertSame($type, $tokens[$index]->type);
            self::assertSame($value, $tokens[$index]->value);
            self::assertSame($length, $tokens[$index]->length);
        }
    }
}

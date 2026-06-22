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

    #[DataProvider('upstreamSingleTokenProvider')]
    #[DataProvider('upstreamWhitespaceProvider')]
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

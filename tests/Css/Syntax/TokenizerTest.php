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

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamHashProvider(): iterable
    {
        yield 'hash.ton #1 ascii name' => ['#wygodski', [['hash', '#wygodski', 9]]];
        yield 'hash.ton #2 leading hex escape' => ['#\\77ygodski', [['hash', '#wygodski', 11]]];
        yield 'hash.ton #3 embedded hex escape with terminator' => ['#wyg\\6F dski', [['hash', '#wygodski', 12]]];
        yield 'hash.ton #4 trailing hex escape' => ['#wygodsk\\69', [['hash', '#wygodski', 11]]];
        yield 'hash.ton #5 all escaped' => ['#\\77\\79\\67\\6F\\64\\73\\6B\\69', [['hash', '#wygodski', 25]]];
        yield 'hash.ton #6 leading hyphen' => ['#-wygodski', [['hash', '#-wygodski', 10]]];
        yield 'hash.ton #7 leading digit' => ['#1wygodski', [['hash', '#1wygodski', 10]]];
        yield 'hash.ton #8 lone hyphen name' => ['#-', [['hash', '#-', 2]]];
        yield 'hash.ton #9 lone digit name' => ['#1', [['hash', '#1', 2]]];
        yield 'hash.ton #10 underscore name' => ['#_', [['hash', '#_', 2]]];
        yield 'hash.ton #11 non-ascii name' => ['#Марк_Яковлевич_Выгодский', [['hash', '#Марк_Яковлевич_Выгодский', 47]]];
        yield 'hash.ton #12 trailing reverse solidus' => ['#\\', [['hash', "#\u{FFFD}", 2]]];
        yield 'hash.ton #13 short hex escape' => ['#\\77', [['hash', '#w', 4]]];
        yield 'hash.ton #14 invalid hash plus delim' => ['#+', [
            ['delim', '#', 1],
            ['delim', '+', 1],
        ]];
        yield 'hash.ton #15 non-ascii hash then ident' => ['#Марк+Яковлевич', [
            ['hash', '#Марк', 9],
            ['delim', '+', 1],
            ['ident', 'Яковлевич', 18],
        ]];
        yield 'hash.ton #16 escaped letters with terminators' => ['#\\77 \\79 \\67 \\6F \\64 \\73 \\6B \\69', [['hash', '#wygodski', 32]]];
        yield 'hash.ton #17 escaped null' => ["#\\\0", [['hash', "#\u{FFFD}", 3]]];
        yield 'hash.ton #18 null name byte' => ["#\0", [['hash', "#\u{FFFD}", 2]]];
        yield 'hash.ton #19 embedded null name byte' => ["#wygod\0ski", [['hash', "#wygod\u{FFFD}ski", 10]]];
        yield 'hash.ton #20 lone number sign' => ['#', [['delim', '#', 1]]];
        yield 'hash.ton #21 delim then hash' => ['##id', [
            ['delim', '#', 1],
            ['hash', '#id', 3],
        ]];
        yield 'hash.ton #22 invalid escape before LF' => ["#\\\n", [
            ['delim', '#', 1],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'hash.ton #23 invalid escape before CR' => ["#\\\r", [
            ['delim', '#', 1],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'hash.ton #24 invalid escape before FF' => ["#\\\f", [
            ['delim', '#', 1],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamReverseSolidusProvider(): iterable
    {
        yield 'reverse-solidus.ton #1 trailing reverse solidus' => ['\\', [['ident', "\u{FFFD}", 1]]];
        yield 'reverse-solidus.ton #2 invalid escape before LF' => ["\\\n", [
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'reverse-solidus.ton #3 invalid escape before CR' => ["\\\r", [
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'reverse-solidus.ton #4 invalid escape before FF' => ["\\\f", [
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'reverse-solidus.ton #5 hex escape' => ['\\47', [['ident', 'G', 3]]];
        yield 'reverse-solidus.ton #6 lowercase escaped phrase' => ['\\47\\6f\\64\\20\\6f\\66\\20\\57\\61\\72', [['ident', 'God of War', 30]]];
        yield 'reverse-solidus.ton #7 uppercase escaped phrase' => ['\\47\\6F\\64\\20\\6F\\66\\20\\57\\61\\72', [['ident', 'God of War', 30]]];
        yield 'reverse-solidus.ton #8 escaped ident then hash' => ['\\47#id', [
            ['ident', 'G', 3],
            ['hash', '#id', 3],
        ]];
        yield 'reverse-solidus.ton #9 escaped space then hash' => ['\\  #id', [
            ['ident', ' ', 2],
            ['whitespace', ' ', 1],
            ['hash', '#id', 3],
        ]];
        yield 'reverse-solidus.ton #10 escaped hex with separated hash' => ['\\47  #id', [
            ['ident', 'G', 4],
            ['whitespace', ' ', 1],
            ['hash', '#id', 3],
        ]];
        yield 'reverse-solidus.ton #11 escaped hex adjacent hash' => ['\\47 #id', [
            ['ident', 'G', 4],
            ['hash', '#id', 3],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamIdentProvider(): iterable
    {
        yield 'ident.ton #1 ascii ident' => ['godofwar', [['ident', 'godofwar', 8]]];
        yield 'ident.ton #2 leading hex escape' => ['\\67odofwar', [['ident', 'godofwar', 10]]];
        yield 'ident.ton #3 leading hex escape with terminator' => ['\\67 odofwar', [['ident', 'godofwar', 11]]];
        yield 'ident.ton #4 escaped ident then whitespace' => ['\\67  odofwar', [
            ['ident', 'g', 4],
            ['whitespace', ' ', 1],
            ['ident', 'odofwar', 7],
        ]];
        yield 'ident.ton #5 all escaped' => ['\\67\\6F\\64\\6F\\66\\77\\61\\72', [['ident', 'godofwar', 24]]];
        yield 'ident.ton #6 escaped CRLF terminator' => ["\\67\r\nodofwar", [['ident', 'godofwar', 12]]];
        yield 'ident.ton #7 escaped CR terminator' => ["\\67\rodofwar", [['ident', 'godofwar', 11]]];
        yield 'ident.ton #8 escaped LF terminator' => ["\\67\nodofwar", [['ident', 'godofwar', 11]]];
        yield 'ident.ton #9 escaped CRLF at EOF' => ["\\67\r\n", [['ident', 'g', 5]]];
        yield 'ident.ton #10 escaped CR at EOF' => ["\\67\r", [['ident', 'g', 4]]];
        yield 'ident.ton #11 escaped LF at EOF' => ["\\67\n", [['ident', 'g', 4]]];
        yield 'ident.ton #12 trailing reverse solidus' => ['resident-evil\\', [['ident', "resident-evil\u{FFFD}", 14]]];
        yield 'ident.ton #13 invalid escape before LF' => ["resident-evil\\\n", [
            ['ident', 'resident-evil', 13],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'ident.ton #14 delim between identifiers' => ['resident*evil', [
            ['ident', 'resident', 8],
            ['delim', '*', 1],
            ['ident', 'evil', 4],
        ]];
        yield 'ident.ton #15 mixed-case hyphenated ident' => ['Silent-Hill', [['ident', 'Silent-Hill', 11]]];
        yield 'ident.ton #16 case preserving ident' => ['sIlEnt-hIll', [['ident', 'sIlEnt-hIll', 11]]];
        yield 'ident.ton #17 digits after start' => ['Silent1-Hill5', [['ident', 'Silent1-Hill5', 13]]];
        yield 'ident.ton #18 double hyphen start' => ['--silent-Hill', [['ident', '--silent-Hill', 13]]];
        yield 'ident.ton #19 single hyphen start' => ['-Silent-Hill', [['ident', '-Silent-Hill', 12]]];
        yield 'ident.ton #20 hyphen underscore start' => ['-_Silent-Hill', [['ident', '-_Silent-Hill', 13]]];
        yield 'ident.ton #21 underscore start' => ['_Silent-Hill', [['ident', '_Silent-Hill', 12]]];
        yield 'ident.ton #22 repeated hyphen underscore start' => ['-----_Silent-Hill', [['ident', '-----_Silent-Hill', 17]]];
        yield 'ident.ton #23 repeated hyphen suffix' => ['-----_Silent-Hill-----', [['ident', '-----_Silent-Hill-----', 22]]];
        yield 'ident.ton #24 repeated hyphen letter start' => ['-----Silent-Hill-----', [['ident', '-----Silent-Hill-----', 21]]];
        yield 'ident.ton #25 escaped form feed terminator' => ["\\67\fodofwar", [['ident', 'godofwar', 11]]];
        yield 'ident.ton #26 cyrillic ident' => ['Город', [['ident', 'Город', 10]]];
        yield 'ident.ton #27 hyphen cyrillic ident' => ['-Город', [['ident', '-Город', 11]]];
        yield 'ident.ton #28 cyrillic with underscores' => ['--Г_о_р_о_д', [['ident', '--Г_о_р_о_д', 16]]];
        yield 'ident.ton #29 middle dot ident' => ['·', [['ident', '·', 2]]];
        yield 'ident.ton #30 latin capital A grave ident' => ['À', [['ident', 'À', 2]]];
        yield 'ident.ton #31 latin capital O diaeresis ident' => ['Ö', [['ident', 'Ö', 2]]];
        yield 'ident.ton #32 latin capital O stroke ident' => ['Ø', [['ident', 'Ø', 2]]];
        yield 'ident.ton #33 latin small O diaeresis ident' => ['ö', [['ident', 'ö', 2]]];
        yield 'ident.ton #34 latin small O stroke ident' => ['ø', [['ident', 'ø', 2]]];
        yield 'ident.ton #35 greek lower numeral sign ident' => ['ͽ', [['ident', 'ͽ', 2]]];
        yield 'ident.ton #36 multiplication sign delim' => ['×x', [
            ['delim', '×', 2],
            ['ident', 'x', 1],
        ]];
        yield 'ident.ton #37 division sign delim' => ['÷x', [
            ['delim', '÷', 2],
            ['ident', 'x', 1],
        ]];
        yield 'ident.ton #38 greek question mark delim' => [';x', [
            ['delim', ';', 2],
            ['ident', 'x', 1],
        ]];
        yield 'ident.ton #39 null ident' => ["\0", [['ident', "\u{FFFD}", 1]]];
        yield 'ident.ton #40 embedded null' => ["Mass\0Effect", [['ident', "Mass\u{FFFD}Effect", 11]]];
        yield 'ident.ton #41 trailing null' => ["Mass_Effect\0", [['ident', "Mass_Effect\u{FFFD}", 12]]];
        yield 'ident.ton #42 invalid escape before FF' => ["Mass\\\fEffect", [
            ['ident', 'Mass', 4],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
            ['ident', 'Effect', 6],
        ]];
        yield 'ident.ton #43 form feed between identifiers' => ["Mass\fEffect", [
            ['ident', 'Mass', 4],
            ['whitespace', "\n", 1],
            ['ident', 'Effect', 6],
        ]];
    }

    #[DataProvider('upstreamSingleTokenProvider')]
    #[DataProvider('upstreamWhitespaceProvider')]
    #[DataProvider('upstreamCommentProvider')]
    #[DataProvider('upstreamHashProvider')]
    #[DataProvider('upstreamReverseSolidusProvider')]
    #[DataProvider('upstreamIdentProvider')]
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

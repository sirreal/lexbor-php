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

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamAtKeywordProvider(): iterable
    {
        yield 'at.ton #1 ascii at-keyword' => ['@war', [['at-keyword', '@war', 4]]];
        yield 'at.ton #2 short at-keyword' => ['@w', [['at-keyword', '@w', 2]]];
        yield 'at.ton #3 leading hex escape' => ['@\\77 ar', [['at-keyword', '@war', 7]]];
        yield 'at.ton #4 embedded hex escape' => ['@w\\61r', [['at-keyword', '@war', 6]]];
        yield 'at.ton #5 trailing hex escape' => ['@wa\\72', [['at-keyword', '@war', 6]]];
        yield 'at.ton #6 all escaped' => ['@\\77\\61\\72', [['at-keyword', '@war', 10]]];
        yield 'at.ton #7 two escaped letters' => ['@\\77\\61', [['at-keyword', '@wa', 7]]];
        yield 'at.ton #8 one escaped letter' => ['@\\77', [['at-keyword', '@w', 4]]];
        yield 'at.ton #9 double hyphen' => ['@--', [['at-keyword', '@--', 3]]];
        yield 'at.ton #10 hyphen underscore' => ['@-_', [['at-keyword', '@-_', 3]]];
        yield 'at.ton #11 hyphen escaped letter' => ['@-\\77', [['at-keyword', '@-w', 5]]];
        yield 'at.ton #12 invalid hash start' => ['@#', [
            ['delim', '@', 1],
            ['delim', '#', 1],
        ]];
        yield 'at.ton #13 replacement character' => ['@�', [['at-keyword', '@�', 4]]];
        yield 'at.ton #14 null name' => ["@\0", [['at-keyword', "@\u{FFFD}", 2]]];
        yield 'at.ton #15 escaped null' => ["@\\\0", [['at-keyword', "@\u{FFFD}", 3]]];
        yield 'at.ton #16 embedded escaped null' => ["@w\\\0ar", [['at-keyword', "@w\u{FFFD}ar", 6]]];
        yield 'at.ton #17 embedded null' => ["@w\0ar", [['at-keyword', "@w\u{FFFD}ar", 5]]];
        yield 'at.ton #18 trailing null' => ["@war\0", [['at-keyword', "@war\u{FFFD}", 5]]];
        yield 'at.ton #19 invalid escape after hyphen' => ["@-\\\n", [
            ['delim', '@', 1],
            ['delim', '-', 1],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'at.ton #20 hyphen null' => ["@-\0", [['at-keyword', "@-\u{FFFD}", 3]]];
        yield 'at.ton #21 repeated null name' => ["@\0", [['at-keyword', "@\u{FFFD}", 2]]];
        yield 'at.ton #22 dimension after invalid at-keyword' => ['@-1F', [
            ['delim', '@', 1],
            ['dimension', '-1F', 3],
        ]];
        yield 'at.ton #23 positive dimension after invalid at-keyword' => ['@+1F', [
            ['delim', '@', 1],
            ['dimension', '1F', 3],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamNumberProvider(): iterable
    {
        yield 'number.ton #1 integer' => ['1', [['number', '1', 1]]];
        yield 'number.ton #2 two digits' => ['10', [['number', '10', 2]]];
        yield 'number.ton #3 large integer' => ['1000000', [['number', '1000000', 7]]];
        yield 'number.ton #4 negative integer' => ['-1', [['number', '-1', 2]]];
        yield 'number.ton #5 negative three digits' => ['-123', [['number', '-123', 4]]];
        yield 'number.ton #6 decimal below one' => ['0.1234', [['number', '0.1234', 6]]];
        yield 'number.ton #7 decimal' => ['1.5678', [['number', '1.5678', 6]]];
        yield 'number.ton #8 large decimal' => ['1234.5678', [['number', '1234.5678', 9]]];
        yield 'number.ton #9 negative decimal below one' => ['-0.1234', [['number', '-0.1234', 7]]];
        yield 'number.ton #10 negative decimal' => ['-1.5678', [['number', '-1.5678', 7]]];
        yield 'number.ton #11 negative large decimal' => ['-1234.5678', [['number', '-1234.5678', 10]]];
        yield 'number.ton #12 positive signed decimal below one' => ['+0.1234', [['number', '0.1234', 7]]];
        yield 'number.ton #13 positive signed decimal' => ['+1.5678', [['number', '1.5678', 7]]];
        yield 'number.ton #14 positive signed large decimal' => ['+1234.5678', [['number', '1234.5678', 10]]];
        yield 'number.ton #15 leading dot decimal' => ['.5678', [['number', '0.5678', 5]]];
        yield 'number.ton #16 positive leading dot decimal' => ['+.5678', [['number', '0.5678', 6]]];
        yield 'number.ton #17 negative leading dot decimal' => ['-.5678', [['number', '-0.5678', 6]]];
        yield 'number.ton #18 positive exponent' => ['1.5678e+12', [['number', '1567800000000', 10]]];
        yield 'number.ton #19 leading dot positive exponent' => ['.5678e+12', [['number', '567800000000', 9]]];
        yield 'number.ton #20 signed leading dot positive exponent' => ['+.5678e+12', [['number', '567800000000', 10]]];
        yield 'number.ton #21 negative leading dot positive exponent' => ['-.5678e+12', [['number', '-567800000000', 10]]];
        yield 'number.ton #22 negative exponent' => ['1.5678e-12', [['number', '1.5678e-12', 10]]];
        yield 'number.ton #23 exponent without sign' => ['1.5678e12', [['number', '1567800000000', 9]]];
        yield 'number.ton #24 zero' => ['0', [['number', '0', 1]]];
        yield 'number.ton #25 negative zero' => ['-0', [['number', '0', 2]]];
        yield 'number.ton #26 dot zero' => ['.0', [['number', '0', 2]]];
        yield 'number.ton #27 decimal tenth' => ['0.1', [['number', '0.1', 3]]];
        yield 'number.ton #28 leading dot nine' => ['.9', [['number', '0.9', 2]]];
        yield 'number.ton #29 negative leading dot hundredth' => ['-.01', [['number', '-0.01', 4]]];
        yield 'number.ton #30 one millionth' => ['0.000001', [['number', '0.000001', 8]]];
        yield 'number.ton #31 fractional precision' => ['0.00000123456', [['number', '0.00000123456', 13]]];
        yield 'number.ton #32 scientific threshold' => ['0.0000001', [['number', '1e-7', 9]]];
        yield 'number.ton #33 trailing zeros' => ['1.1000000', [['number', '1.1', 9]]];
        yield 'number.ton #34 rounded 20 digit integer' => ['99999999999999999999', [['number', '100000000000000000000', 20]]];
        yield 'number.ton #35 rounded 20 digit decimal' => ['99999999999999999999.111', [['number', '100000000000000000000', 24]]];
        yield 'number.ton #36 exponent notation threshold' => ['999999999999999999999', [['number', '1e+21', 21]]];
        yield 'number.ton #37 signed 64-bit boundary' => ['9223372036854775808', [['number', '9223372036854776000', 19]]];
        yield 'number.ton #38 unsigned 64-bit boundary' => ['18446744073709551616', [['number', '18446744073709552000', 20]]];
        yield 'number.ton #39 max double' => ['1.7976931348623157E+308', [['number', '1.7976931348623157e+308', 23]]];
        yield 'number.ton #40 negative exponent decimal' => ['-5.7e-1', [['number', '-0.57', 7]]];
        yield 'number.ton #41 padded negative exponent' => ['1.1e-01', [['number', '0.11', 7]]];
        yield 'number.ton #42 invalid exponent before hash' => ['1.1e#hash', [
            ['dimension', '1.1e', 4],
            ['hash', '#hash', 5],
        ]];
        yield 'number.ton #43 number before hash' => ['1.1#hash', [
            ['number', '1.1', 3],
            ['hash', '#hash', 5],
        ]];
        yield 'number.ton #44 integer dot before hash' => ['1.#hash', [
            ['number', '1', 1],
            ['delim', '.', 1],
            ['hash', '#hash', 5],
        ]];
        yield 'number.ton #45 repeated integer dot before hash' => ['1.#hash', [
            ['number', '1', 1],
            ['delim', '.', 1],
            ['hash', '#hash', 5],
        ]];
        yield 'number.ton #46 positive dot fallback' => ['+.', [
            ['delim', '+', 1],
            ['delim', '.', 1],
        ]];
        yield 'number.ton #47 positive dot before hash' => ['+.#hash', [
            ['delim', '+', 1],
            ['delim', '.', 1],
            ['hash', '#hash', 5],
        ]];
        yield 'number.ton #48 plus fallback' => ['+', [['delim', '+', 1]]];
        yield 'number.ton #49 negative dot fallback' => ['-.', [
            ['delim', '-', 1],
            ['delim', '.', 1],
        ]];
        yield 'number.ton #50 negative dot before hash' => ['-.#hash', [
            ['delim', '-', 1],
            ['delim', '.', 1],
            ['hash', '#hash', 5],
        ]];
        yield 'number.ton #51 minus fallback' => ['-', [['delim', '-', 1]]];
        yield 'number.ton #52 double hyphen ident' => ['--', [['ident', '--', 2]]];
        yield 'number.ton #53 minus trailing reverse solidus' => ['-\\', [['ident', "-\u{FFFD}", 2]]];
        yield 'number.ton #54 double hyphen trailing reverse solidus' => ['--\\', [['ident', "--\u{FFFD}", 3]]];
        yield 'number.ton #55 minus escaped hash' => ['-\\#id', [['ident', '-#id', 5]]];
        yield 'number.ton #56 double hyphen escaped hash' => ['--\\#id', [['ident', '--#id', 6]]];
        yield 'number.ton #57 minus null' => ["-\0", [['ident', "-\u{FFFD}", 2]]];
        yield 'number.ton #58 double hyphen null' => ["--\0", [['ident', "--\u{FFFD}", 3]]];
        yield 'number.ton #59 triple hyphen ident' => ['---', [['ident', '---', 3]]];
        yield 'number.ton #60 double hyphen escaped letter' => ['--\\77', [['ident', '--w', 5]]];
        yield 'number.ton #61 ident then hash' => ['--\\77#id', [
            ['ident', '--w', 5],
            ['hash', '#id', 3],
        ]];
        yield 'number.ton #62 capped 256 digit integer' => [str_repeat('1', 256), [['number', '1.1111111111111113e+127', 256]]];
        yield 'number.ton #63 capped long decimal' => ['1.' . str_repeat('1', 255), [['number', '1.1111111111111112', 257]]];
        yield 'number.ton #64 invalid exponent at EOF' => ['1.1e', [['dimension', '1.1e', 4]]];
        yield 'number.ton #65 integer dot at EOF' => ['1.', [
            ['number', '1', 1],
            ['delim', '.', 1],
        ]];
        yield 'number.ton #66 invalid positive exponent' => ['1.1e+', [
            ['dimension', '1.1e', 4],
            ['delim', '+', 1],
        ]];
        yield 'number.ton #67 invalid negative exponent' => ['1.1e-', [['dimension', '1.1e-', 5]]];
        yield 'number.ton #68 escaped dimension unit' => ['1.1\\77', [['dimension', '1.1w', 6]]];
        yield 'number.ton #69 invalid escape before CR' => ["1.1\\\r", [
            ['number', '1.1', 3],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'number.ton #70 invalid escape before CRLF' => ["1.1\\\r\n", [
            ['number', '1.1', 3],
            ['delim', '\\', 1],
            ['whitespace', "\n", 2],
        ]];
        yield 'number.ton #71 invalid escape before LF' => ["1.1\\\n", [
            ['number', '1.1', 3],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'number.ton #72 invalid escape before FF' => ["1.1\\\f", [
            ['number', '1.1', 3],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'number.ton #73 dimension then invalid escape before CR' => ["1.1w\\\r", [
            ['dimension', '1.1w', 4],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'number.ton #74 dimension then invalid escape before CRLF' => ["1.1w\\\r\n", [
            ['dimension', '1.1w', 4],
            ['delim', '\\', 1],
            ['whitespace', "\n", 2],
        ]];
        yield 'number.ton #75 dimension then invalid escape before LF' => ["1.1w\\\n", [
            ['dimension', '1.1w', 4],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'number.ton #76 dimension then invalid escape before FF' => ["1.1w\\\f", [
            ['dimension', '1.1w', 4],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'number.ton #77 number then negative zero dimension' => ['0-00F', [
            ['number', '0', 1],
            ['dimension', '0F', 4],
        ]];
        yield 'number.ton #78 number then positive zero dimension' => ['0+00F', [
            ['number', '0', 1],
            ['dimension', '0F', 4],
        ]];
        yield 'number.ton #79 invalid exponent dimension' => ['0.1e-F', [['dimension', '0.1e-F', 6]]];
        yield 'number.ton #80 number then positive dimension' => ['0.1+0F', [
            ['number', '0.1', 3],
            ['dimension', '0F', 3],
        ]];
        yield 'number.ton #81 number then negative dimension' => ['0.1-0F', [
            ['number', '0.1', 3],
            ['dimension', '0F', 3],
        ]];
        yield 'number.ton #82 dimension with double hyphen unit' => ['0.1--0F', [['dimension', '0.1--0F', 7]]];
        yield 'number.ton #83 invalid exponent then dot dimension' => ['0.1e+.0F', [
            ['dimension', '0.1e', 4],
            ['dimension', '0F', 4],
        ]];
        yield 'number.ton #84 escaped dimension unit after hyphen' => ['123-\\47', [['dimension', '123-G', 7]]];
        yield 'number.ton #85 double hyphen digit ident' => ['--1F', [['ident', '--1F', 4]]];
        yield 'number.ton #86 repeated invalid positive exponent' => ['1.1e+', [
            ['dimension', '1.1e', 4],
            ['delim', '+', 1],
        ]];
        yield 'number.ton #87 leading dot invalid exponent' => ['.1e', [['dimension', '0.1e', 3]]];
        yield 'number.ton #88 chained negative dimensions' => ['-3-2-d\\', [
            ['number', '-3', 2],
            ['dimension', '-2-d' . "\u{FFFD}", 5],
        ]];
        yield 'number.ton #89 exponent one' => ['1e+1', [['number', '10', 4]]];
        yield 'number.ton #90 exponent two' => ['1e+2', [['number', '100', 4]]];
        yield 'number.ton #91 negative exponent one' => ['1e-1', [['number', '0.1', 4]]];
        yield 'number.ton #92 negative exponent two' => ['1e-2', [['number', '0.01', 4]]];
        yield 'number.ton #93 exponent overflow clamp' => ['1e999999999999999999999', [['number', '1.797693134862316e+308', 23]]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamStringProvider(): iterable
    {
        $replacement = "\u{FFFD}";
        $noisy = "ode >>stream x\x01ЭY\x0B|Ф≈µ?3ун#!ПMА<\tя\x17ЦЁ@6!\x0F\t\t\x10…ЈyЙF @іYРЇ\x01“В/R\x13@Q\x01Q.ЄшИѕ[KХА%†®|ўELxHФkѓµZ∞VЛёґжґhЂЕ+Јjн•Рљ€Щ/";

        yield 'string.ton #1 double quoted string' => ['"Onimusha"', [['string', '"Onimusha"', 10]]];
        yield 'string.ton #2 single quoted string' => ["'Onimusha'", [['string', '"Onimusha"', 10]]];
        yield 'string.ton #3 newline in string' => ["\"Onimu\nsha\"", [
            ['bad-string', '"Onimu"', 6],
            ['whitespace', "\n", 1],
            ['ident', 'sha', 3],
            ['string', '""', 1],
        ]];
        yield 'string.ton #4 EOF in string' => ['"Onimusha', [['string', '"Onimusha"', 9]]];
        yield 'string.ton #5 EOF after reverse solidus' => ["\"Onimusha\\", [['string', "\"Onimusha{$replacement}\"", 10]]];
        yield 'string.ton #6 escaped LF continuation' => ['"' . "Onim\\\nusha" . '"', [['string', '"Onimusha"', 12]]];
        yield 'string.ton #7 escaped CRLF continuation' => ['"' . "Onim\\\r\nusha" . '"', [['string', '"Onimusha"', 13]]];
        yield 'string.ton #8 leading hex escape' => ['"\\67odofwar"', [['string', '"godofwar"', 12]]];
        yield 'string.ton #9 leading hex escape with terminator' => ['"\\67 odofwar"', [['string', '"godofwar"', 13]]];
        yield 'string.ton #10 escaped letter then space' => ['"\\67  odofwar"', [['string', '"g odofwar"', 14]]];
        yield 'string.ton #11 all escaped' => ['"\\67\\6F\\64\\6F\\66\\77\\61\\72"', [['string', '"godofwar"', 26]]];
        yield 'string.ton #12 all escaped with terminators' => ['"\\67 \\6F \\64 \\6F \\66 \\77 \\61 \\72"', [['string', '"godofwar"', 33]]];
        yield 'string.ton #13 hex escape CRLF terminator' => ['"\\67' . "\r\n" . 'odofwar"', [['string', '"godofwar"', 14]]];
        yield 'string.ton #14 hex escape CR terminator' => ['"\\67' . "\r" . 'odofwar"', [['string', '"godofwar"', 13]]];
        yield 'string.ton #15 hex escape LF terminator' => ['"\\67' . "\n" . 'odofwar"', [['string', '"godofwar"', 13]]];
        yield 'string.ton #16 hex escape CRLF at EOF' => ['"\\67' . "\r\n" . '"', [['string', '"g"', 7]]];
        yield 'string.ton #17 hex escape CR at EOF' => ['"\\67' . "\r" . '"', [['string', '"g"', 6]]];
        yield 'string.ton #18 hex escape LF at EOF' => ['"\\67' . "\n" . '"', [['string', '"g"', 6]]];
        yield 'string.ton #19 escaped quote at EOF' => ['"resident-evil\"', [['string', '"resident-evil\""', 16]]];
        yield 'string.ton #20 escaped LF before close' => ['"resident-evil\\' . "\n" . '"', [['string', '"resident-evil"', 17]]];
        yield 'string.ton #21 hex escape then escaped FF' => ['"\\67\\' . "\f" . 'odofwar"', [['string', '"godofwar"', 14]]];
        yield 'string.ton #22 hex escape FF terminator' => ['"\\67' . "\f" . 'odofwar"', [['string', '"godofwar"', 13]]];
        yield 'string.ton #23 mixed binary and UTF-8 string' => ['"' . $noisy . '"', [['string', '"' . $noisy . '"', 163]]];
        yield 'string.ton #24 single quotes inside double quoted string' => ['"\'Final \' Fantasy\'"', [['string', '"\'Final \' Fantasy\'"', 19]]];
        yield 'string.ton #25 double quotes inside single quoted string' => ['\'"Final " Fantasy"\'', [['string', '"\"Final \" Fantasy\""', 19]]];
        yield 'string.ton #26 escaped FF continuation' => ['"g\\' . "\f" . 'odofwar"', [['string', '"godofwar"', 12]]];
        yield 'string.ton #27 escaped FF before hex escape' => ['"\\' . "\f" . '\\67' . "\r\n" . 'odofwar"', [['string', '"godofwar"', 16]]];
        yield 'string.ton #28 escaped FF after hex escape' => ['"\\67' . "\r\n" . 'odofwar\\' . "\f" . '"', [['string', '"godofwar"', 16]]];
        yield 'string.ton #29 null replacement' => ['"' . "\0Sidewalks\0and\0Skeletons\0" . '"', [['string', "\"{$replacement}Sidewalks{$replacement}and{$replacement}Skeletons{$replacement}\"", 27]]];
        yield 'string.ton #30 escaped null replacement' => ['"\\' . "\0" . 'Sidewalks\\' . "\0" . 'and\\' . "\0" . 'Skeletons\\' . "\0" . '"', [['string', "\"{$replacement}Sidewalks{$replacement}and{$replacement}Skeletons{$replacement}\"", 31]]];
        yield 'string.ton #31 escaped null' => ['"\\' . "\0" . '"', [['string', "\"{$replacement}\"", 4]]];
        yield 'string.ton #32 null' => ['"' . "\0" . '"', [['string', "\"{$replacement}\"", 3]]];
        yield 'string.ton #33 escaped reverse solidus' => ['"Onim\\\\usha"', [['string', '"Onim\\\\usha"', 12]]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamCdoCdcProvider(): iterable
    {
        yield 'CDO-CDC.ton #1 CDO' => ['<!--', [['CDO', '<!--', 4]]];
        yield 'CDO-CDC.ton #2 split CDO' => ['<!- -', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['delim', '-', 1],
            ['whitespace', ' ', 1],
            ['delim', '-', 1],
        ]];
        yield 'CDO-CDC.ton #3 space before CDC tail' => ['<! --', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['whitespace', ' ', 1],
            ['ident', '--', 2],
        ]];
        yield 'CDO-CDC.ton #4 space after less-than' => ['< !--', [
            ['delim', '<', 1],
            ['whitespace', ' ', 1],
            ['delim', '!', 1],
            ['ident', '--', 2],
        ]];
        yield 'CDO-CDC.ton #5 null after bang hyphen' => ["<!-\0", [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['ident', "-\u{FFFD}", 2],
        ]];
        yield 'CDO-CDC.ton #6 trailing reverse solidus after bang hyphen' => ['<!-\\', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['ident', "-\u{FFFD}", 2],
        ]];
        yield 'CDO-CDC.ton #7 escaped hash after bang hyphen' => ['<!-\\#id', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['ident', '-#id', 5],
        ]];
        yield 'CDO-CDC.ton #8 escaped phrase after bang hyphen' => ['<!-\\47\\6f\\64\\20\\6f\\66\\20\\57\\61\\72', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['ident', '-God of War', 31],
        ]];
        yield 'CDO-CDC.ton #9 invalid escape before LF' => ["<!-\\\n", [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['delim', '-', 1],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'CDO-CDC.ton #10 invalid escape before CR' => ["<!-\\\r", [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['delim', '-', 1],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'CDO-CDC.ton #11 invalid escape before CRLF' => ["<!-\\\r\n", [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['delim', '-', 1],
            ['delim', '\\', 1],
            ['whitespace', "\n", 2],
        ]];
        yield 'CDO-CDC.ton #12 invalid escape before FF' => ["<!-\\\f", [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['delim', '-', 1],
            ['delim', '\\', 1],
            ['whitespace', "\n", 1],
        ]];
        yield 'CDO-CDC.ton #13 hash after bang hyphen' => ['<!-#id', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['delim', '-', 1],
            ['hash', '#id', 3],
        ]];
        yield 'CDO-CDC.ton #14 CDC' => ['-->', [['CDC', '-->', 3]]];
        yield 'CDO-CDC.ton #15 split CDC' => ['- ->', [
            ['delim', '-', 1],
            ['whitespace', ' ', 1],
            ['delim', '-', 1],
            ['delim', '>', 1],
        ]];
        yield 'CDO-CDC.ton #16 space before greater-than' => ['-- >', [
            ['ident', '--', 2],
            ['whitespace', ' ', 1],
            ['delim', '>', 1],
        ]];
        yield 'CDO-CDC.ton #17 three hyphens before greater-than' => ['--- >', [
            ['ident', '---', 3],
            ['whitespace', ' ', 1],
            ['delim', '>', 1],
        ]];
        yield 'CDO-CDC.ton #18 less-than before negative dimension' => ['<-1F', [
            ['delim', '<', 1],
            ['dimension', '-1F', 3],
        ]];
        yield 'CDO-CDC.ton #19 less-than before double-hyphen ident' => ['<--1F', [
            ['delim', '<', 1],
            ['ident', '--1F', 4],
        ]];
        yield 'CDO-CDC.ton #20 bang before negative dimension' => ['<!-1F', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['dimension', '-1F', 3],
        ]];
        yield 'CDO-CDC.ton #21 bang before positive dimension' => ['<!+1F', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['dimension', '1F', 3],
        ]];
        yield 'CDO-CDC.ton #22 hyphen before positive dimension' => ['<-+1F', [
            ['delim', '<', 1],
            ['delim', '-', 1],
            ['dimension', '1F', 3],
        ]];
        yield 'CDO-CDC.ton #23 less-than before positive dimension' => ['<+1F', [
            ['delim', '<', 1],
            ['dimension', '1F', 3],
        ]];
        yield 'CDO-CDC.ton #24 bang before negative decimal dimension' => ['<!-.1F', [
            ['delim', '<', 1],
            ['delim', '!', 1],
            ['dimension', '-0.1F', 4],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>, bool}>
     */
    public static function upstreamUnicodeRangeProvider(): iterable
    {
        yield 'unicode_range.ton #1 max scalar-like range' => ['U+FFFFFF', [['unicode-range', 'U+FFFFFF-FFFFFF', 8]], true];
        yield 'unicode_range.ton #2 all wildcard digits' => ['U+??????', [['unicode-range', 'U+0-FFFFFF', 8]], true];
        yield 'unicode_range.ton #3 one hex five wildcards' => ['U+1?????', [['unicode-range', 'U+100000-1FFFFF', 8]], true];
        yield 'unicode_range.ton #4 two hex four wildcards' => ['U+12????', [['unicode-range', 'U+120000-12FFFF', 8]], true];
        yield 'unicode_range.ton #5 three hex three wildcards' => ['U+123???', [['unicode-range', 'U+123000-123FFF', 8]], true];
        yield 'unicode_range.ton #6 four hex two wildcards' => ['U+1234??', [['unicode-range', 'U+123400-1234FF', 8]], true];
        yield 'unicode_range.ton #7 five hex one wildcard' => ['U+12345?', [['unicode-range', 'U+123450-12345F', 8]], true];
        yield 'unicode_range.ton #8 exact six hex digits' => ['U+123456', [['unicode-range', 'U+123456-123456', 8]], true];
        yield 'unicode_range.ton #9 explicit range' => ['U+20-ff', [['unicode-range', 'U+20-FF', 7]], true];
        yield 'unicode_range.ton #10 seventh digit remains dimension' => ['U+1234567-ff', [
            ['unicode-range', 'U+123456-123456', 8],
            ['dimension', '7-ff', 4],
        ], true];
        yield 'unicode_range.ton #11 end range stops after six digits' => ['U+123456-abcdefabc', [
            ['unicode-range', 'U+123456-ABCDEF', 15],
            ['ident', 'abc', 3],
        ], true];
        yield 'unicode_range.ton #12 trailing hyphen delimiter' => ['U+123456-', [
            ['unicode-range', 'U+123456-123456', 8],
            ['delim', '-', 1],
        ], true];
        yield 'unicode_range.ton #13 trailing hyphen ident' => ['U+123456-x', [
            ['unicode-range', 'U+123456-123456', 8],
            ['ident', '-x', 2],
        ], true];
        yield 'unicode_range.ton #15 ident after partial hex range' => ['U+123xyz', [
            ['unicode-range', 'U+123-123', 5],
            ['ident', 'xyz', 3],
        ], true];
        yield 'unicode_range.ton #16 missing range start' => ['U+', [
            ['ident', 'U', 1],
            ['delim', '+', 1],
        ], true];
        yield 'unicode_range.ton #17 invalid range start' => ['U+x', [
            ['ident', 'U', 1],
            ['delim', '+', 1],
            ['ident', 'x', 1],
        ], true];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamOtherProvider(): iterable
    {
        yield 'other.ton #1 mixed tokenizer smoke' => ["/* Comment */123.456ident'string'#hash@media \\67mase", [
            ['comment', '/* Comment */', 13],
            ['dimension', '123.456ident', 12],
            ['string', '"string"', 8],
            ['hash', '#hash', 5],
            ['at-keyword', '@media', 6],
            ['whitespace', ' ', 1],
            ['ident', 'gmase', 7],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{string, string, int}>}>
     */
    public static function upstreamBrokenUtf8Provider(): iterable
    {
        $replacement = "\u{FFFD}";
        $surrogateStart = "\xED\xA0\x80";
        $surrogateHighEnd = "\xED\xAF\xBF";
        $surrogateLowStart = "\xED\xB0\x80";
        $surrogateEnd = "\xED\xBF\xBF";
        $truncatedSurrogate = "\xED\xA0";

        yield 'broken-utf-8.ton #1 invalid continuation in ident' => ["wor\x9Fld", [['ident', "wor{$replacement}ld", 6]]];
        yield 'broken-utf-8.ton #2 surrogate start in ident' => ['wor' . $surrogateStart . 'ld', [['ident', "wor{$replacement}ld", 8]]];
        yield 'broken-utf-8.ton #3 surrogate high end in ident' => ['wor' . $surrogateHighEnd . 'ld', [['ident', "wor{$replacement}ld", 8]]];
        yield 'broken-utf-8.ton #4 surrogate low start in ident' => ['wor' . $surrogateLowStart . 'ld', [['ident', "wor{$replacement}ld", 8]]];
        yield 'broken-utf-8.ton #5 surrogate end in ident' => ['wor' . $surrogateEnd . 'ld', [['ident', "wor{$replacement}ld", 8]]];
        yield 'broken-utf-8.ton #6 non-surrogate before surrogate range' => ['wor' . "\u{D7FF}" . 'ld', [['ident', 'wor' . "\u{D7FF}" . 'ld', 8]]];
        yield 'broken-utf-8.ton #7 valid non-surrogate code point' => ['wor' . "\u{C000}" . 'ld', [['ident', 'wor' . "\u{C000}" . 'ld', 8]]];
        yield 'broken-utf-8.ton #8 surrogate in string' => ['"wor' . $surrogateStart . 'ld"', [['string', "\"wor{$replacement}ld\"", 10]]];
        yield 'broken-utf-8.ton #9 invalid bytes in string' => ["\"wor\xA0\x80ld\"", [['string', "\"wor{$replacement}{$replacement}ld\"", 9]]];
        yield 'broken-utf-8.ton #10 truncated surrogate then text in string' => ['"wor' . $truncatedSurrogate . 'ld"', [['string', "\"wor{$replacement}{$replacement}ld\"", 9]]];
        yield 'broken-utf-8.ton #11 truncated surrogate at EOF in string' => ['"wor' . $truncatedSurrogate, [['string', "\"wor{$replacement}{$replacement}\"", 6]]];
        yield 'broken-utf-8.ton #12 truncated surrogate at EOF in ident' => ['wor' . $truncatedSurrogate, [['ident', "wor{$replacement}{$replacement}", 5]]];
        yield 'broken-utf-8.ton #13 truncated surrogate then text in ident' => ['wor' . $truncatedSurrogate . 'ld', [['ident', "wor{$replacement}{$replacement}ld", 7]]];
        yield 'broken-utf-8.ton #14 micro sign delimiter' => ['µ', [['delim', 'µ', 2]]];
    }

    #[DataProvider('upstreamSingleTokenProvider')]
    #[DataProvider('upstreamWhitespaceProvider')]
    #[DataProvider('upstreamCommentProvider')]
    #[DataProvider('upstreamHashProvider')]
    #[DataProvider('upstreamReverseSolidusProvider')]
    #[DataProvider('upstreamIdentProvider')]
    #[DataProvider('upstreamAtKeywordProvider')]
    #[DataProvider('upstreamNumberProvider')]
    #[DataProvider('upstreamStringProvider')]
    #[DataProvider('upstreamCdoCdcProvider')]
    #[DataProvider('upstreamUnicodeRangeProvider')]
    #[DataProvider('upstreamOtherProvider')]
    #[DataProvider('upstreamBrokenUtf8Provider')]
    public function testUpstreamTokenizerFixtures(string $css, array $expected, bool $withUnicodeRange = false): void
    {
        $tokens = (new Tokenizer())->tokenize($css, $withUnicodeRange);

        self::assertCount(count($expected), $tokens);

        foreach ($expected as $index => [$type, $value, $length]) {
            self::assertSame($type, $tokens[$index]->type);
            self::assertSame($value, $tokens[$index]->value);
            self::assertSame($length, $tokens[$index]->length);
        }
    }

    public function testNumberSerializationIgnoresSerializePrecision(): void
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '17');

        try {
            $tokens = (new Tokenizer())->tokenize('0.1 1.5678 1.1e#hash 9223372036854775808');
        } finally {
            if ($previous !== false) {
                ini_set('serialize_precision', $previous);
            }
        }

        self::assertCount(8, $tokens);
        self::assertSame('0.1', $tokens[0]->value);
        self::assertSame('1.5678', $tokens[2]->value);
        self::assertSame('1.1e', $tokens[4]->value);
        self::assertSame('9223372036854776000', $tokens[7]->value);
    }

    public function testEscapedNonAsciiPreservesFullUtf8CodePoint(): void
    {
        $tokens = (new Tokenizer())->tokenize("\"\\Э\" \\Э");

        self::assertCount(3, $tokens);
        self::assertSame('string', $tokens[0]->type);
        self::assertSame('"Э"', $tokens[0]->value);
        self::assertSame(5, $tokens[0]->length);
        self::assertSame('whitespace', $tokens[1]->type);
        self::assertSame('ident', $tokens[2]->type);
        self::assertSame('Э', $tokens[2]->value);
        self::assertSame(3, $tokens[2]->length);
    }
}

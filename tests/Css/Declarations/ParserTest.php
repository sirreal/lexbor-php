<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Declarations;

use Lexbor\Css\Declarations\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function upstreamSyntaxProvider(): iterable
    {
        yield 'syntax.ton #1 unknown declaration becomes custom' => ['name: value', [
            ['type' => 'custom', 'name' => 'name', 'value' => 'value', 'important' => false],
        ]];
        yield 'syntax.ton #2 known width with invalid value is undef' => ['width: value', [
            ['type' => 'undef', 'name' => 'width', 'value' => 'value', 'important' => false],
        ]];
        yield 'syntax.ton #3 valid width length' => ['width: 1px', [
            ['type' => 'property', 'name' => 'width', 'value' => '1px', 'important' => false],
        ]];
        yield 'syntax.ton #4 invalid width with important' => ['width: value !important', [
            ['type' => 'undef', 'name' => 'width', 'value' => 'value', 'important' => true],
        ]];
        yield 'syntax.ton #5 valid width with important' => ['width: 1px !important', [
            ['type' => 'property', 'name' => 'width', 'value' => '1px', 'important' => true],
        ]];
        yield 'syntax.ton #6 valid width with spaced important' => ['width: 1px    !important   ', [
            ['type' => 'property', 'name' => 'width', 'value' => '1px', 'important' => true],
        ]];
        yield 'syntax.ton #7 valid width with spaced important and semicolon' => ['width: 1px    !important   ;', [
            ['type' => 'property', 'name' => 'width', 'value' => '1px', 'important' => true],
        ]];
        yield 'syntax.ton #8 valid width with trailing semicolon' => ['width: 1px  ;', [
            ['type' => 'property', 'name' => 'width', 'value' => '1px', 'important' => false],
        ]];
        yield 'syntax.ton #9 empty width value with important is undef' => ['width: !important;', [
            ['type' => 'undef', 'name' => 'width', 'value' => '', 'important' => true],
        ]];
        yield 'syntax.ton #10 custom property with important' => ['myprop: 1px !important', [
            ['type' => 'custom', 'name' => 'myprop', 'value' => '1px', 'important' => true],
        ]];
        yield 'syntax.ton #11 custom property with spaced important' => ['myprop: 1px    !important   ', [
            ['type' => 'custom', 'name' => 'myprop', 'value' => '1px', 'important' => true],
        ]];
        yield 'syntax.ton #12 custom property with spaced important and semicolon' => ['myprop: 1px    !important   ;', [
            ['type' => 'custom', 'name' => 'myprop', 'value' => '1px', 'important' => true],
        ]];
        yield 'syntax.ton #13 custom property with trailing semicolon' => ['myprop: 1px  ;', [
            ['type' => 'custom', 'name' => 'myprop', 'value' => '1px', 'important' => false],
        ]];
        yield 'syntax.ton #14 custom property with whitespace around colon' => ['myprop         :    1px  ;', [
            ['type' => 'custom', 'name' => 'myprop', 'value' => '1px', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function widthRegressionProvider(): iterable
    {
        yield 'unitless zero width is valid' => ['width: 0', [
            ['type' => 'property', 'name' => 'width', 'value' => '0', 'important' => false],
        ]];
        yield 'negative width length is invalid' => ['width: -1px', [
            ['type' => 'undef', 'name' => 'width', 'value' => '-1px', 'important' => false],
        ]];
        yield 'negative width percentage is invalid' => ['width: -1%', [
            ['type' => 'undef', 'name' => 'width', 'value' => '-1%', 'important' => false],
        ]];
        yield 'intrinsic min-content width is valid' => ['width: min-content', [
            ['type' => 'property', 'name' => 'width', 'value' => 'min-content', 'important' => false],
        ]];
        yield 'intrinsic max-content width is valid' => ['width: max-content', [
            ['type' => 'property', 'name' => 'width', 'value' => 'max-content', 'important' => false],
        ]];
        yield 'large exponent width length is valid' => ['width: 1e21px', [
            ['type' => 'property', 'name' => 'width', 'value' => '1e+21px', 'important' => false],
        ]];
        yield 'small exponent width length is valid' => ['width: 1e-7px', [
            ['type' => 'property', 'name' => 'width', 'value' => '1e-7px', 'important' => false],
        ]];
        yield 'large exponent width percentage is valid' => ['width: 1e21%', [
            ['type' => 'property', 'name' => 'width', 'value' => '1e+21%', 'important' => false],
        ]];
        yield 'small exponent width percentage is valid' => ['width: 1e-7%', [
            ['type' => 'property', 'name' => 'width', 'value' => '1e-7%', 'important' => false],
        ]];
        yield 'comment cannot join width number and unit' => ['width: 1/**/px', [
            ['type' => 'undef', 'name' => 'width', 'value' => '1px', 'important' => false],
        ]];
        yield 'comment cannot join width number and percent sign' => ['width: 1/**/%', [
            ['type' => 'undef', 'name' => 'width', 'value' => '1%', 'important' => false],
        ]];
        yield 'comment cannot join intrinsic width keyword' => ['width: min-/**/content', [
            ['type' => 'undef', 'name' => 'width', 'value' => 'min-content', 'important' => false],
        ]];
        yield 'escaped ident cannot become width dimension' => ['width: \31 px', [
            ['type' => 'undef', 'name' => 'width', 'value' => '1px', 'important' => false],
        ]];
        yield 'escaped ident cannot become width percentage' => ['width: \31 %', [
            ['type' => 'undef', 'name' => 'width', 'value' => '1%', 'important' => false],
        ]];
        yield 'escaped ident cannot become unitless zero width' => ['width: \30', [
            ['type' => 'undef', 'name' => 'width', 'value' => '0', 'important' => false],
        ]];
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('upstreamSyntaxProvider')]
    public function testUpstreamSyntaxFixtures(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('widthRegressionProvider')]
    public function testWidthRegressions(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }
}

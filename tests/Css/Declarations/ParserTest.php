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
        yield 'syntax.ton #15 invalid numeric declaration name' => ['1px: drop', [
            ['type' => 'undef', 'name' => '', 'value' => '1px: drop', 'important' => false],
        ]];
        yield 'syntax.ton #16 invalid declaration without colon' => ['name value', [
            ['type' => 'undef', 'name' => '', 'value' => 'name value', 'important' => false],
        ]];
        yield 'syntax.ton #17 invalid, custom, then invalid declarations' => ['name value; myprop: 1px; 2px: broken', [
            ['type' => 'undef', 'name' => '', 'value' => 'name value', 'important' => false],
            ['type' => 'custom', 'name' => 'myprop', 'value' => '1px', 'important' => false],
            ['type' => 'undef', 'name' => '', 'value' => '2px: broken', 'important' => false],
        ]];
        yield 'syntax.ton #18 at-rule with block is invalid declaration' => ['@at-some prelude {block}', [
            ['type' => 'undef', 'name' => '', 'value' => '@at-some prelude {block}', 'important' => false],
        ]];
        yield 'syntax.ton #19 at-rule without semicolon is invalid declaration' => ['@at-some prelude', [
            ['type' => 'undef', 'name' => '', 'value' => '@at-some prelude', 'important' => false],
        ]];
        yield 'syntax.ton #20 semicolon-terminated at-rule is invalid declaration' => ['@at-some prelude;', [
            ['type' => 'undef', 'name' => '', 'value' => '@at-some prelude', 'important' => false],
        ]];
        yield 'syntax.ton #21 at-rule with empty block is invalid declaration' => ['@at-some prelude {}', [
            ['type' => 'undef', 'name' => '', 'value' => '@at-some prelude {}', 'important' => false],
        ]];
        yield 'syntax.ton #22 bare at-rule is invalid declaration' => ['@at-some', [
            ['type' => 'undef', 'name' => '', 'value' => '@at-some', 'important' => false],
        ]];
        yield 'syntax.ton #23 bare semicolon-terminated at-rule is invalid declaration' => ['@at-some;', [
            ['type' => 'undef', 'name' => '', 'value' => '@at-some', 'important' => false],
        ]];
        yield 'syntax.ton #24 adjacent at-rule block is invalid declaration' => ['@at-some{xxx}', [
            ['type' => 'undef', 'name' => '', 'value' => '@at-some{xxx}', 'important' => false],
        ]];
        yield 'syntax.ton #25 at-rule invalid declaration then custom declaration' => ['@at-some xxx {yyy} @at-some xxx; myprop: 1px', [
            ['type' => 'undef', 'name' => '', 'value' => '@at-some xxx {yyy} @at-some xxx', 'important' => false],
            ['type' => 'custom', 'name' => 'myprop', 'value' => '1px', 'important' => false],
        ]];
        yield 'syntax.ton #26 known text-decoration with invalid value' => ['text-decoration: hsl(20 blah err', [
            ['type' => 'undef', 'name' => 'text-decoration', 'value' => 'hsl(20 blah err', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function upstreamWidthProvider(): iterable
    {
        yield from self::lengthSizeProvider('width', 'width.ton');
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function upstreamHeightProvider(): iterable
    {
        yield from self::lengthSizeProvider('height', 'height.ton');
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function upstreamDisplayProvider(): iterable
    {
        $values = [
            'initial',
            'inherit',
            'unset',
            'revert',
            'block flow',
            'block flow-root',
            'block table',
            'block flex',
            'block grid',
            'block ruby',
            'block',
            'inline flow',
            'inline flow-root',
            'inline table',
            'inline flex',
            'inline grid',
            'inline ruby',
            'inline',
            'run-in flow',
            'run-in flow-root',
            'run-in table',
            'run-in flex',
            'run-in grid',
            'run-in ruby',
            'run-in',
            'flow',
            'flow-root',
            'table',
            'flex',
            'grid',
            'ruby',
            'flow block',
            'flow inline',
            'flow run-in',
            'flow-root block',
            'flow-root inline',
            'flow-root run-in',
            'table block',
            'table inline',
            'table run-in',
            'flex block',
            'flex inline',
            'flex run-in',
            'grid block',
            'grid inline',
            'grid run-in',
            'ruby block',
            'ruby inline',
            'ruby run-in',
            'block flow list-item',
            'block flow-root list-item',
            'block list-item',
            'inline flow list-item',
            'inline flow-root list-item',
            'inline list-item',
            'run-in flow list-item',
            'run-in flow-root list-item',
            'run-in list-item',
            'flow list-item',
            'flow-root list-item',
            'list-item',
            'block list-item flow',
            'block list-item flow-root',
            'inline list-item flow',
            'inline list-item flow-root',
            'run-in list-item flow',
            'run-in list-item flow-root',
            'list-item flow',
            'list-item flow-root',
            'flow block list-item',
            'flow inline list-item',
            'flow run-in list-item',
            'flow-root block list-item',
            'flow-root inline list-item',
            'flow-root run-in list-item',
            'flow list-item block',
            'flow list-item inline',
            'flow list-item run-in',
            'flow-root list-item block',
            'flow-root list-item inline',
            'flow-root list-item run-in',
            'list-item block',
            'list-item inline',
            'list-item run-in',
            'list-item block flow',
            'list-item block flow-root',
            'list-item inline flow',
            'list-item inline flow-root',
            'list-item run-in flow',
            'list-item run-in flow-root',
            'list-item flow block',
            'list-item flow inline',
            'list-item flow run-in',
            'list-item flow-root block',
            'list-item flow-root inline',
            'list-item flow-root run-in',
            'table-row-group',
            'table-header-group',
            'table-footer-group',
            'table-row',
            'table-cell',
            'table-column-group',
            'table-column',
            'table-caption',
            'ruby-base',
            'ruby-text',
            'ruby-base-container',
            'ruby-text-container',
            'contents',
            'none',
            'inline-block',
            'inline-table',
            'inline-flex',
            'inline-grid',
        ];

        foreach ($values as $index => $value) {
            yield sprintf('display.ton #%d %s', $index + 1, $value) => ["display: {$value}", [
                ['type' => 'property', 'name' => 'display', 'value' => $value, 'important' => false],
            ]];
        }
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
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function boxSpacingProvider(): iterable
    {
        yield 'margin accepts one length' => ['margin: 1px', [
            ['type' => 'property', 'name' => 'margin', 'value' => '1px', 'important' => false],
        ]];
        yield 'margin accepts four mixed length percentage auto values' => ['margin: 1px 2% auto -4em', [
            ['type' => 'property', 'name' => 'margin', 'value' => '1px 2% auto -4em', 'important' => false],
        ]];
        yield 'margin accepts css-wide keyword' => ['margin: revert', [
            ['type' => 'property', 'name' => 'margin', 'value' => 'revert', 'important' => false],
        ]];
        yield 'margin rejects more than four values' => ['margin: 1px 2px 3px 4px 5px', [
            ['type' => 'undef', 'name' => 'margin', 'value' => '1px 2px 3px 4px 5px', 'important' => false],
        ]];
        yield 'margin side accepts auto' => ['margin-left: auto', [
            ['type' => 'property', 'name' => 'margin-left', 'value' => 'auto', 'important' => false],
        ]];
        yield 'margin side accepts negative percentage' => ['margin-top: -10%', [
            ['type' => 'property', 'name' => 'margin-top', 'value' => '-10%', 'important' => false],
        ]];
        yield 'margin side rejects multiple values' => ['margin-right: 1px 2px', [
            ['type' => 'undef', 'name' => 'margin-right', 'value' => '1px 2px', 'important' => false],
        ]];
        yield 'padding accepts shorthand lengths' => ['padding: 1px 2px 3px 4px', [
            ['type' => 'property', 'name' => 'padding', 'value' => '1px 2px 3px 4px', 'important' => false],
        ]];
        yield 'padding accepts signed values like Lexbor state table' => ['padding: -1px -2%', [
            ['type' => 'property', 'name' => 'padding', 'value' => '-1px -2%', 'important' => false],
        ]];
        yield 'padding rejects auto' => ['padding: auto', [
            ['type' => 'undef', 'name' => 'padding', 'value' => 'auto', 'important' => false],
        ]];
        yield 'padding side accepts percentage' => ['padding-bottom: 12.5%', [
            ['type' => 'property', 'name' => 'padding-bottom', 'value' => '12.5%', 'important' => false],
        ]];
        yield 'padding side rejects auto' => ['padding-left: auto', [
            ['type' => 'undef', 'name' => 'padding-left', 'value' => 'auto', 'important' => false],
        ]];
        yield 'padding side rejects multiple values' => ['padding-top: 1px 2px', [
            ['type' => 'undef', 'name' => 'padding-top', 'value' => '1px 2px', 'important' => false],
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
    #[DataProvider('upstreamWidthProvider')]
    public function testUpstreamWidthFixtures(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('upstreamHeightProvider')]
    public function testUpstreamHeightFixtures(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('upstreamDisplayProvider')]
    public function testUpstreamDisplayFixtures(string $css, array $expected): void
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

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('boxSpacingProvider')]
    public function testBoxSpacingDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    private static function lengthSizeProvider(string $property, string $fixture): iterable
    {
        $values = [
            'initial',
            'inherit',
            'unset',
            'revert',
            'auto',
            '0em',
            '0ex',
            '0cap',
            '0ch',
            '0ic',
            '0rem',
            '0lh',
            '0rlh',
            '0vw',
            '0vh',
            '0vi',
            '0vb',
            '0vmin',
            '0vmax',
            '0cm',
            '0mm',
            '0Q',
            '0in',
            '0pt',
            '0pc',
            '0px',
            '128em',
            '128ex',
            '128cap',
            '128ch',
            '128ic',
            '128rem',
            '128lh',
            '128rlh',
            '128vw',
            '128vh',
            '128vi',
            '128vb',
            '128vmin',
            '128vmax',
            '128cm',
            '128mm',
            '128Q',
            '128in',
            '128pt',
            '128pc',
            '128px',
            '0',
            '0%',
            '128%',
            'min-content',
            'max-content',
        ];

        foreach ($values as $index => $value) {
            yield sprintf('%s #%d %s', $fixture, $index + 1, $value) => ["{$property}: {$value}", [
                ['type' => 'property', 'name' => $property, 'value' => $value, 'important' => false],
            ]];
        }
    }
}

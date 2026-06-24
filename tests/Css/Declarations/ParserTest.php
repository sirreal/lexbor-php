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
        yield 'margin accepts comment-separated values' => ['margin: 1px/**/2px', [
            ['type' => 'property', 'name' => 'margin', 'value' => '1px 2px', 'important' => false],
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
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function keywordDeclarationProvider(): iterable
    {
        yield 'box-sizing accepts content-box' => ['box-sizing: content-box', [
            ['type' => 'property', 'name' => 'box-sizing', 'value' => 'content-box', 'important' => false],
        ]];
        yield 'box-sizing accepts border-box with important' => ['box-sizing: border-box !important', [
            ['type' => 'property', 'name' => 'box-sizing', 'value' => 'border-box', 'important' => true],
        ]];
        yield 'box-sizing rejects unknown keyword' => ['box-sizing: padding-box', [
            ['type' => 'undef', 'name' => 'box-sizing', 'value' => 'padding-box', 'important' => false],
        ]];
        yield 'position accepts fixed' => ['position: fixed', [
            ['type' => 'property', 'name' => 'position', 'value' => 'fixed', 'important' => false],
        ]];
        yield 'position accepts comments around complete keyword' => ['position: /**/fixed/**/', [
            ['type' => 'property', 'name' => 'position', 'value' => 'fixed', 'important' => false],
        ]];
        yield 'position accepts css-wide keyword' => ['position: revert', [
            ['type' => 'property', 'name' => 'position', 'value' => 'revert', 'important' => false],
        ]];
        yield 'position rejects comment-split keyword' => ['position: fi/**/xed', [
            ['type' => 'undef', 'name' => 'position', 'value' => 'fixed', 'important' => false],
        ]];
        yield 'position rejects multiple keywords' => ['position: sticky fixed', [
            ['type' => 'undef', 'name' => 'position', 'value' => 'sticky fixed', 'important' => false],
        ]];
        yield 'clear accepts logical keyword' => ['clear: inline-start', [
            ['type' => 'property', 'name' => 'clear', 'value' => 'inline-start', 'important' => false],
        ]];
        yield 'clear accepts physical keyword' => ['clear: bottom', [
            ['type' => 'property', 'name' => 'clear', 'value' => 'bottom', 'important' => false],
        ]];
        yield 'clear rejects multiple keywords' => ['clear: left right', [
            ['type' => 'undef', 'name' => 'clear', 'value' => 'left right', 'important' => false],
        ]];
        yield 'direction accepts rtl' => ['direction: rtl', [
            ['type' => 'property', 'name' => 'direction', 'value' => 'rtl', 'important' => false],
        ]];
        yield 'direction rejects auto' => ['direction: auto', [
            ['type' => 'undef', 'name' => 'direction', 'value' => 'auto', 'important' => false],
        ]];
        yield 'visibility accepts collapse' => ['visibility: collapse', [
            ['type' => 'property', 'name' => 'visibility', 'value' => 'collapse', 'important' => false],
        ]];
        yield 'visibility rejects none' => ['visibility: none', [
            ['type' => 'undef', 'name' => 'visibility', 'value' => 'none', 'important' => false],
        ]];
        yield 'overflow-x accepts scroll' => ['overflow-x: scroll', [
            ['type' => 'property', 'name' => 'overflow-x', 'value' => 'scroll', 'important' => false],
        ]];
        yield 'overflow-x accepts trailing comment' => ['overflow-x: scroll/**/', [
            ['type' => 'property', 'name' => 'overflow-x', 'value' => 'scroll', 'important' => false],
        ]];
        yield 'overflow-y accepts clip' => ['overflow-y: clip', [
            ['type' => 'property', 'name' => 'overflow-y', 'value' => 'clip', 'important' => false],
        ]];
        yield 'overflow-block accepts auto' => ['overflow-block: auto', [
            ['type' => 'property', 'name' => 'overflow-block', 'value' => 'auto', 'important' => false],
        ]];
        yield 'overflow-inline accepts visible' => ['overflow-inline: visible', [
            ['type' => 'property', 'name' => 'overflow-inline', 'value' => 'visible', 'important' => false],
        ]];
        yield 'overflow-x rejects overlay' => ['overflow-x: overlay', [
            ['type' => 'undef', 'name' => 'overflow-x', 'value' => 'overlay', 'important' => false],
        ]];
        yield 'overflow-wrap accepts break-word' => ['overflow-wrap: break-word', [
            ['type' => 'property', 'name' => 'overflow-wrap', 'value' => 'break-word', 'important' => false],
        ]];
        yield 'overflow-wrap accepts anywhere' => ['overflow-wrap: anywhere', [
            ['type' => 'property', 'name' => 'overflow-wrap', 'value' => 'anywhere', 'important' => false],
        ]];
        yield 'overflow-wrap rejects visible' => ['overflow-wrap: visible', [
            ['type' => 'undef', 'name' => 'overflow-wrap', 'value' => 'visible', 'important' => false],
        ]];
        yield 'word-wrap follows overflow-wrap keywords' => ['word-wrap: break-word', [
            ['type' => 'property', 'name' => 'word-wrap', 'value' => 'break-word', 'important' => false],
        ]];
        yield 'word-wrap rejects overflow keywords' => ['word-wrap: scroll', [
            ['type' => 'undef', 'name' => 'word-wrap', 'value' => 'scroll', 'important' => false],
        ]];
        yield 'keyword declaration rejects comment-separated important marker' => ['box-sizing: border-box/**/ ! /**/ important', [
            ['type' => 'undef', 'name' => 'box-sizing', 'value' => 'border-box !  important', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function displayCommentProvider(): iterable
    {
        yield 'display accepts comment-separated complete keywords' => ['display: block/**/flow', [
            ['type' => 'property', 'name' => 'display', 'value' => 'block flow', 'important' => false],
        ]];
        yield 'display rejects comment-split keyword' => ['display: in/**/line', [
            ['type' => 'undef', 'name' => 'display', 'value' => 'inline', 'important' => false],
        ]];
        yield 'display rejects comment-split legacy keyword' => ['display: inline-/**/block', [
            ['type' => 'undef', 'name' => 'display', 'value' => 'inline-block', 'important' => false],
        ]];
        yield 'display rejects comment-split css-wide keyword' => ['display: in/**/herit', [
            ['type' => 'undef', 'name' => 'display', 'value' => 'inherit', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function numericDeclarationProvider(): iterable
    {
        yield 'opacity accepts number' => ['opacity: .5', [
            ['type' => 'property', 'name' => 'opacity', 'value' => '0.5', 'important' => false],
        ]];
        yield 'opacity accepts percentage' => ['opacity: 50%', [
            ['type' => 'property', 'name' => 'opacity', 'value' => '50%', 'important' => false],
        ]];
        yield 'opacity accepts css-wide keyword' => ['opacity: inherit', [
            ['type' => 'property', 'name' => 'opacity', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'opacity rejects length' => ['opacity: 1px', [
            ['type' => 'undef', 'name' => 'opacity', 'value' => '1px', 'important' => false],
        ]];
        yield 'opacity rejects comment-split percentage' => ['opacity: 1/**/%', [
            ['type' => 'undef', 'name' => 'opacity', 'value' => '1%', 'important' => false],
        ]];
        yield 'order accepts integer' => ['order: -2', [
            ['type' => 'property', 'name' => 'order', 'value' => '-2', 'important' => false],
        ]];
        yield 'order accepts normalized exponent integer' => ['order: 1e2', [
            ['type' => 'property', 'name' => 'order', 'value' => '100', 'important' => false],
        ]];
        yield 'order rejects decimal number' => ['order: 1.5', [
            ['type' => 'undef', 'name' => 'order', 'value' => '1.5', 'important' => false],
        ]];
        yield 'order rejects integer above long range' => ['order: 1e19', [
            ['type' => 'undef', 'name' => 'order', 'value' => '10000000000000000000', 'important' => false],
        ]];
        yield 'order rejects comment-split integer' => ['order: 1/**/2', [
            ['type' => 'undef', 'name' => 'order', 'value' => '12', 'important' => false],
        ]];
        yield 'z-index accepts auto' => ['z-index: auto', [
            ['type' => 'property', 'name' => 'z-index', 'value' => 'auto', 'important' => false],
        ]];
        yield 'z-index accepts integer' => ['z-index: 10', [
            ['type' => 'property', 'name' => 'z-index', 'value' => '10', 'important' => false],
        ]];
        yield 'z-index rejects percentage' => ['z-index: 10%', [
            ['type' => 'undef', 'name' => 'z-index', 'value' => '10%', 'important' => false],
        ]];
        yield 'z-index rejects integer below long range' => ['z-index: -1e19', [
            ['type' => 'undef', 'name' => 'z-index', 'value' => '-10000000000000000000', 'important' => false],
        ]];
        yield 'font-weight accepts normal' => ['font-weight: normal', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => 'normal', 'important' => false],
        ]];
        yield 'font-weight accepts bold' => ['font-weight: bold', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => 'bold', 'important' => false],
        ]];
        yield 'font-weight accepts bolder' => ['font-weight: bolder', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => 'bolder', 'important' => false],
        ]];
        yield 'font-weight accepts lighter' => ['font-weight: lighter', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => 'lighter', 'important' => false],
        ]];
        yield 'font-weight accepts css-wide keyword' => ['font-weight: inherit', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'font-weight lowercases mixed-case keyword' => ['font-weight: BoLd', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => 'bold', 'important' => false],
        ]];
        yield 'font-weight accepts lower range number' => ['font-weight: 1', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => '1', 'important' => false],
        ]];
        yield 'font-weight accepts upper range exponent number' => ['font-weight: 1e3', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => '1000', 'important' => false],
        ]];
        yield 'font-weight accepts decimal number like Lexbor' => ['font-weight: 100.5', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => '100.5', 'important' => false],
        ]];
        yield 'font-weight rejects number below range' => ['font-weight: .5', [
            ['type' => 'undef', 'name' => 'font-weight', 'value' => '0.5', 'important' => false],
        ]];
        yield 'font-weight rejects number above range' => ['font-weight: 1000.1', [
            ['type' => 'undef', 'name' => 'font-weight', 'value' => '1000.1', 'important' => false],
        ]];
        yield 'font-weight rejects percentage' => ['font-weight: 100%', [
            ['type' => 'undef', 'name' => 'font-weight', 'value' => '100%', 'important' => false],
        ]];
        yield 'font-weight rejects length' => ['font-weight: 100px', [
            ['type' => 'undef', 'name' => 'font-weight', 'value' => '100px', 'important' => false],
        ]];
        yield 'font-weight rejects multiple values' => ['font-weight: 400 bold', [
            ['type' => 'undef', 'name' => 'font-weight', 'value' => '400 bold', 'important' => false],
        ]];
        yield 'font-weight rejects comment-split number' => ['font-weight: 10/**/0', [
            ['type' => 'undef', 'name' => 'font-weight', 'value' => '100', 'important' => false],
        ]];
        yield 'font-weight rejects comment-split keyword' => ['font-weight: bo/**/ld', [
            ['type' => 'undef', 'name' => 'font-weight', 'value' => 'bold', 'important' => false],
        ]];
        yield 'font-weight keeps important flag' => ['font-weight: 700 !important', [
            ['type' => 'property', 'name' => 'font-weight', 'value' => '700', 'important' => true],
        ]];
        yield 'font-stretch accepts normal' => ['font-stretch: normal', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'normal', 'important' => false],
        ]];
        yield 'font-stretch accepts ultra-condensed' => ['font-stretch: ultra-condensed', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'ultra-condensed', 'important' => false],
        ]];
        yield 'font-stretch accepts extra-condensed' => ['font-stretch: extra-condensed', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'extra-condensed', 'important' => false],
        ]];
        yield 'font-stretch accepts condensed' => ['font-stretch: condensed', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'condensed', 'important' => false],
        ]];
        yield 'font-stretch accepts semi-condensed' => ['font-stretch: semi-condensed', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'semi-condensed', 'important' => false],
        ]];
        yield 'font-stretch accepts semi-expanded' => ['font-stretch: semi-expanded', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'semi-expanded', 'important' => false],
        ]];
        yield 'font-stretch accepts expanded' => ['font-stretch: expanded', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'expanded', 'important' => false],
        ]];
        yield 'font-stretch accepts extra-expanded' => ['font-stretch: extra-expanded', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'extra-expanded', 'important' => false],
        ]];
        yield 'font-stretch accepts ultra-expanded' => ['font-stretch: ultra-expanded', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'ultra-expanded', 'important' => false],
        ]];
        yield 'font-stretch accepts css-wide keyword' => ['font-stretch: inherit', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'font-stretch lowercases mixed-case keyword' => ['font-stretch: SeMi-ExPaNdEd', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => 'semi-expanded', 'important' => false],
        ]];
        yield 'font-stretch accepts percentage' => ['font-stretch: 87.5%', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => '87.5%', 'important' => false],
        ]];
        yield 'font-stretch accepts normalized exponent percentage' => ['font-stretch: 1e2%', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => '100%', 'important' => false],
        ]];
        yield 'font-stretch rejects negative percentage' => ['font-stretch: -1%', [
            ['type' => 'undef', 'name' => 'font-stretch', 'value' => '-1%', 'important' => false],
        ]];
        yield 'font-stretch rejects number' => ['font-stretch: 100', [
            ['type' => 'undef', 'name' => 'font-stretch', 'value' => '100', 'important' => false],
        ]];
        yield 'font-stretch rejects length' => ['font-stretch: 100px', [
            ['type' => 'undef', 'name' => 'font-stretch', 'value' => '100px', 'important' => false],
        ]];
        yield 'font-stretch rejects multiple values' => ['font-stretch: normal 100%', [
            ['type' => 'undef', 'name' => 'font-stretch', 'value' => 'normal 100%', 'important' => false],
        ]];
        yield 'font-stretch rejects unknown keyword' => ['font-stretch: narrower', [
            ['type' => 'undef', 'name' => 'font-stretch', 'value' => 'narrower', 'important' => false],
        ]];
        yield 'font-stretch rejects comment-split keyword' => ['font-stretch: semi-/**/expanded', [
            ['type' => 'undef', 'name' => 'font-stretch', 'value' => 'semi-expanded', 'important' => false],
        ]];
        yield 'font-stretch rejects comment-split percentage' => ['font-stretch: 10/**/%', [
            ['type' => 'undef', 'name' => 'font-stretch', 'value' => '10%', 'important' => false],
        ]];
        yield 'font-stretch keeps important flag' => ['font-stretch: 120% !important', [
            ['type' => 'property', 'name' => 'font-stretch', 'value' => '120%', 'important' => true],
        ]];
        yield 'line-height accepts normal' => ['line-height: normal', [
            ['type' => 'property', 'name' => 'line-height', 'value' => 'normal', 'important' => false],
        ]];
        yield 'line-height accepts number' => ['line-height: 1.2', [
            ['type' => 'property', 'name' => 'line-height', 'value' => '1.2', 'important' => false],
        ]];
        yield 'line-height accepts length' => ['line-height: -1px', [
            ['type' => 'property', 'name' => 'line-height', 'value' => '-1px', 'important' => false],
        ]];
        yield 'line-height accepts percentage' => ['line-height: 120%', [
            ['type' => 'property', 'name' => 'line-height', 'value' => '120%', 'important' => false],
        ]];
        yield 'line-height rejects multiple tokens' => ['line-height: 1px 2px', [
            ['type' => 'undef', 'name' => 'line-height', 'value' => '1px 2px', 'important' => false],
        ]];
        yield 'tab-size accepts number' => ['tab-size: 4', [
            ['type' => 'property', 'name' => 'tab-size', 'value' => '4', 'important' => false],
        ]];
        yield 'tab-size accepts normalized exponent number' => ['tab-size: 1e1', [
            ['type' => 'property', 'name' => 'tab-size', 'value' => '10', 'important' => false],
        ]];
        yield 'tab-size accepts length' => ['tab-size: 2em', [
            ['type' => 'property', 'name' => 'tab-size', 'value' => '2em', 'important' => false],
        ]];
        yield 'tab-size accepts negative number like Lexbor' => ['tab-size: -2', [
            ['type' => 'property', 'name' => 'tab-size', 'value' => '-2', 'important' => false],
        ]];
        yield 'tab-size serializes q length with Lexbor uppercase spelling' => ['tab-size: 2q', [
            ['type' => 'property', 'name' => 'tab-size', 'value' => '2Q', 'important' => false],
        ]];
        yield 'tab-size accepts css-wide keyword' => ['tab-size: inherit', [
            ['type' => 'property', 'name' => 'tab-size', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'tab-size rejects normal' => ['tab-size: normal', [
            ['type' => 'undef', 'name' => 'tab-size', 'value' => 'normal', 'important' => false],
        ]];
        yield 'tab-size rejects percentage' => ['tab-size: 4%', [
            ['type' => 'undef', 'name' => 'tab-size', 'value' => '4%', 'important' => false],
        ]];
        yield 'tab-size rejects multiple values' => ['tab-size: 4 2px', [
            ['type' => 'undef', 'name' => 'tab-size', 'value' => '4 2px', 'important' => false],
        ]];
        yield 'tab-size rejects comment-split length' => ['tab-size: 2/**/em', [
            ['type' => 'undef', 'name' => 'tab-size', 'value' => '2em', 'important' => false],
        ]];
        yield 'tab-size keeps important flag' => ['tab-size: 8px !important', [
            ['type' => 'property', 'name' => 'tab-size', 'value' => '8px', 'important' => true],
        ]];
        yield 'letter-spacing accepts normal' => ['letter-spacing: normal', [
            ['type' => 'property', 'name' => 'letter-spacing', 'value' => 'normal', 'important' => false],
        ]];
        yield 'letter-spacing accepts zero number' => ['letter-spacing: 0', [
            ['type' => 'property', 'name' => 'letter-spacing', 'value' => '0', 'important' => false],
        ]];
        yield 'letter-spacing accepts length' => ['letter-spacing: -0.25em', [
            ['type' => 'property', 'name' => 'letter-spacing', 'value' => '-0.25em', 'important' => false],
        ]];
        yield 'letter-spacing rejects nonzero number' => ['letter-spacing: 1', [
            ['type' => 'undef', 'name' => 'letter-spacing', 'value' => '1', 'important' => false],
        ]];
        yield 'word-spacing accepts normal' => ['word-spacing: normal', [
            ['type' => 'property', 'name' => 'word-spacing', 'value' => 'normal', 'important' => false],
        ]];
        yield 'word-spacing accepts length' => ['word-spacing: 2px', [
            ['type' => 'property', 'name' => 'word-spacing', 'value' => '2px', 'important' => false],
        ]];
        yield 'word-spacing rejects percentage' => ['word-spacing: 2%', [
            ['type' => 'undef', 'name' => 'word-spacing', 'value' => '2%', 'important' => false],
        ]];
        yield 'word-spacing rejects comment-split length' => ['word-spacing: 2/**/px', [
            ['type' => 'undef', 'name' => 'word-spacing', 'value' => '2px', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function minMaxSizeDeclarationProvider(): iterable
    {
        yield 'min-width accepts auto' => ['min-width: auto', [
            ['type' => 'property', 'name' => 'min-width', 'value' => 'auto', 'important' => false],
        ]];
        yield 'min-width accepts min-content' => ['min-width: min-content', [
            ['type' => 'property', 'name' => 'min-width', 'value' => 'min-content', 'important' => false],
        ]];
        yield 'min-width accepts percentage' => ['min-width: 25%', [
            ['type' => 'property', 'name' => 'min-width', 'value' => '25%', 'important' => false],
        ]];
        yield 'min-width rejects none' => ['min-width: none', [
            ['type' => 'undef', 'name' => 'min-width', 'value' => 'none', 'important' => false],
        ]];
        yield 'min-height accepts max-content' => ['min-height: max-content', [
            ['type' => 'property', 'name' => 'min-height', 'value' => 'max-content', 'important' => false],
        ]];
        yield 'min-height accepts length' => ['min-height: 12px', [
            ['type' => 'property', 'name' => 'min-height', 'value' => '12px', 'important' => false],
        ]];
        yield 'min-height accepts css-wide keyword' => ['min-height: inherit', [
            ['type' => 'property', 'name' => 'min-height', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'min-height rejects multiple values' => ['min-height: 1px 2px', [
            ['type' => 'undef', 'name' => 'min-height', 'value' => '1px 2px', 'important' => false],
        ]];
        yield 'max-width accepts none' => ['max-width: none', [
            ['type' => 'property', 'name' => 'max-width', 'value' => 'none', 'important' => false],
        ]];
        yield 'max-width accepts min-content' => ['max-width: min-content', [
            ['type' => 'property', 'name' => 'max-width', 'value' => 'min-content', 'important' => false],
        ]];
        yield 'max-width accepts percentage' => ['max-width: 75%', [
            ['type' => 'property', 'name' => 'max-width', 'value' => '75%', 'important' => false],
        ]];
        yield 'max-width rejects auto' => ['max-width: auto', [
            ['type' => 'undef', 'name' => 'max-width', 'value' => 'auto', 'important' => false],
        ]];
        yield 'max-height accepts max-content' => ['max-height: max-content', [
            ['type' => 'property', 'name' => 'max-height', 'value' => 'max-content', 'important' => false],
        ]];
        yield 'max-height accepts length' => ['max-height: 20em', [
            ['type' => 'property', 'name' => 'max-height', 'value' => '20em', 'important' => false],
        ]];
        yield 'max-height accepts css-wide keyword' => ['max-height: revert', [
            ['type' => 'property', 'name' => 'max-height', 'value' => 'revert', 'important' => false],
        ]];
        yield 'max-height rejects comment-split keyword' => ['max-height: max-/**/content', [
            ['type' => 'undef', 'name' => 'max-height', 'value' => 'max-content', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function positionOffsetDeclarationProvider(): iterable
    {
        yield 'top accepts auto' => ['top: auto', [
            ['type' => 'property', 'name' => 'top', 'value' => 'auto', 'important' => false],
        ]];
        yield 'top accepts negative length' => ['top: -2px', [
            ['type' => 'property', 'name' => 'top', 'value' => '-2px', 'important' => false],
        ]];
        yield 'top accepts percentage' => ['top: 25%', [
            ['type' => 'property', 'name' => 'top', 'value' => '25%', 'important' => false],
        ]];
        yield 'top accepts css-wide keyword' => ['top: initial', [
            ['type' => 'property', 'name' => 'top', 'value' => 'initial', 'important' => false],
        ]];
        yield 'top trims whitespace before trailing comment' => ['top: auto /**/;', [
            ['type' => 'property', 'name' => 'top', 'value' => 'auto', 'important' => false],
        ]];
        yield 'top rejects none' => ['top: none', [
            ['type' => 'undef', 'name' => 'top', 'value' => 'none', 'important' => false],
        ]];
        yield 'right accepts length' => ['right: 1.5em', [
            ['type' => 'property', 'name' => 'right', 'value' => '1.5em', 'important' => false],
        ]];
        yield 'right rejects multiple values' => ['right: 1px 2px', [
            ['type' => 'undef', 'name' => 'right', 'value' => '1px 2px', 'important' => false],
        ]];
        yield 'bottom accepts negative percentage' => ['bottom: -10%', [
            ['type' => 'property', 'name' => 'bottom', 'value' => '-10%', 'important' => false],
        ]];
        yield 'bottom rejects non-length dimension' => ['bottom: 1deg', [
            ['type' => 'undef', 'name' => 'bottom', 'value' => '1deg', 'important' => false],
        ]];
        yield 'left accepts unitless zero' => ['left: 0', [
            ['type' => 'property', 'name' => 'left', 'value' => '0', 'important' => false],
        ]];
        yield 'left rejects nonzero number' => ['left: 1', [
            ['type' => 'undef', 'name' => 'left', 'value' => '1', 'important' => false],
        ]];
        yield 'left rejects comment-split length' => ['left: 1/**/px', [
            ['type' => 'undef', 'name' => 'left', 'value' => '1px', 'important' => false],
        ]];
        yield 'inset-block-start accepts auto' => ['inset-block-start: auto', [
            ['type' => 'property', 'name' => 'inset-block-start', 'value' => 'auto', 'important' => false],
        ]];
        yield 'inset-block-end accepts length' => ['inset-block-end: -3rem', [
            ['type' => 'property', 'name' => 'inset-block-end', 'value' => '-3rem', 'important' => false],
        ]];
        yield 'inset-inline-start accepts percentage' => ['inset-inline-start: 12.5%', [
            ['type' => 'property', 'name' => 'inset-inline-start', 'value' => '12.5%', 'important' => false],
        ]];
        yield 'inset-inline-end accepts css-wide keyword' => ['inset-inline-end: inherit', [
            ['type' => 'property', 'name' => 'inset-inline-end', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'inset-inline-start rejects none' => ['inset-inline-start: none', [
            ['type' => 'undef', 'name' => 'inset-inline-start', 'value' => 'none', 'important' => false],
        ]];
        yield 'inset-inline-end rejects multiple values' => ['inset-inline-end: auto 1px', [
            ['type' => 'undef', 'name' => 'inset-inline-end', 'value' => 'auto 1px', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function textKeywordDeclarationProvider(): iterable
    {
        $validKeywords = [
            'hyphens' => ['none', 'manual', 'auto'],
            'justify-content' => ['flex-start', 'flex-end', 'center', 'space-between', 'space-around'],
            'line-break' => ['auto', 'loose', 'normal', 'strict', 'anywhere'],
            'text-align' => ['start', 'end', 'left', 'right', 'center', 'justify', 'match-parent', 'justify-all'],
            'text-align-all' => ['start', 'end', 'left', 'right', 'center', 'justify', 'match-parent'],
            'text-align-last' => ['auto', 'start', 'end', 'left', 'right', 'center', 'justify', 'match-parent'],
            'text-justify' => ['auto', 'none', 'inter-word', 'inter-character'],
            'text-orientation' => ['mixed', 'upright', 'sideways'],
            'text-overflow' => ['clip', 'ellipsis'],
            'unicode-bidi' => ['normal', 'embed', 'isolate', 'bidi-override', 'isolate-override', 'plaintext'],
            'white-space' => ['normal', 'pre', 'nowrap', 'pre-wrap', 'break-spaces', 'pre-line'],
            'word-break' => ['normal', 'keep-all', 'break-all', 'break-word'],
            'writing-mode' => ['horizontal-tb', 'vertical-rl', 'vertical-lr', 'sideways-rl', 'sideways-lr'],
        ];

        foreach ($validKeywords as $property => $keywords) {
            foreach ($keywords as $keyword) {
                yield "{$property} accepts {$keyword}" => ["{$property}: {$keyword}", [
                    ['type' => 'property', 'name' => $property, 'value' => $keyword, 'important' => false],
                ]];
            }

            yield "{$property} accepts css-wide keyword" => ["{$property}: inherit", [
                ['type' => 'property', 'name' => $property, 'value' => 'inherit', 'important' => false],
            ]];
        }

        yield 'hyphens rejects normal' => ['hyphens: normal', [
            ['type' => 'undef', 'name' => 'hyphens', 'value' => 'normal', 'important' => false],
        ]];
        yield 'justify-content rejects space-evenly' => ['justify-content: space-evenly', [
            ['type' => 'undef', 'name' => 'justify-content', 'value' => 'space-evenly', 'important' => false],
        ]];
        yield 'line-break rejects break-word' => ['line-break: break-word', [
            ['type' => 'undef', 'name' => 'line-break', 'value' => 'break-word', 'important' => false],
        ]];
        yield 'line-break trims whitespace before trailing comment' => ['line-break: strict /**/;', [
            ['type' => 'property', 'name' => 'line-break', 'value' => 'strict', 'important' => false],
        ]];
        yield 'text-align rejects auto' => ['text-align: auto', [
            ['type' => 'undef', 'name' => 'text-align', 'value' => 'auto', 'important' => false],
        ]];
        yield 'text-align rejects multiple keywords' => ['text-align: left right', [
            ['type' => 'undef', 'name' => 'text-align', 'value' => 'left right', 'important' => false],
        ]];
        yield 'text-align-all rejects justify-all' => ['text-align-all: justify-all', [
            ['type' => 'undef', 'name' => 'text-align-all', 'value' => 'justify-all', 'important' => false],
        ]];
        yield 'text-align-last rejects justify-all' => ['text-align-last: justify-all', [
            ['type' => 'undef', 'name' => 'text-align-last', 'value' => 'justify-all', 'important' => false],
        ]];
        yield 'text-justify rejects multiple keywords' => ['text-justify: inter-word inter-character', [
            ['type' => 'undef', 'name' => 'text-justify', 'value' => 'inter-word inter-character', 'important' => false],
        ]];
        yield 'text-justify rejects comment-split keyword' => ['text-justify: inter-/**/word', [
            ['type' => 'undef', 'name' => 'text-justify', 'value' => 'inter-word', 'important' => false],
        ]];
        yield 'text-orientation rejects vertical' => ['text-orientation: vertical', [
            ['type' => 'undef', 'name' => 'text-orientation', 'value' => 'vertical', 'important' => false],
        ]];
        yield 'text-overflow lowercases mixed-case keyword' => ['text-overflow: ElLiPsIs', [
            ['type' => 'property', 'name' => 'text-overflow', 'value' => 'ellipsis', 'important' => false],
        ]];
        yield 'text-overflow rejects overflow keyword' => ['text-overflow: hidden', [
            ['type' => 'undef', 'name' => 'text-overflow', 'value' => 'hidden', 'important' => false],
        ]];
        yield 'text-overflow rejects multiple keywords' => ['text-overflow: clip ellipsis', [
            ['type' => 'undef', 'name' => 'text-overflow', 'value' => 'clip ellipsis', 'important' => false],
        ]];
        yield 'text-overflow rejects comment-split keyword' => ['text-overflow: ellip/**/sis', [
            ['type' => 'undef', 'name' => 'text-overflow', 'value' => 'ellipsis', 'important' => false],
        ]];
        yield 'text-overflow keeps important flag' => ['text-overflow: clip !important', [
            ['type' => 'property', 'name' => 'text-overflow', 'value' => 'clip', 'important' => true],
        ]];
        yield 'unicode-bidi rejects ltr' => ['unicode-bidi: ltr', [
            ['type' => 'undef', 'name' => 'unicode-bidi', 'value' => 'ltr', 'important' => false],
        ]];
        yield 'unicode-bidi rejects comment-split keyword' => ['unicode-bidi: isolate-/**/override', [
            ['type' => 'undef', 'name' => 'unicode-bidi', 'value' => 'isolate-override', 'important' => false],
        ]];
        yield 'white-space lowercases mixed-case keyword' => ['white-space: PrE-WrAp', [
            ['type' => 'property', 'name' => 'white-space', 'value' => 'pre-wrap', 'important' => false],
        ]];
        yield 'white-space rejects overflow-wrap keyword' => ['white-space: break-word', [
            ['type' => 'undef', 'name' => 'white-space', 'value' => 'break-word', 'important' => false],
        ]];
        yield 'white-space rejects multiple keywords' => ['white-space: pre wrap', [
            ['type' => 'undef', 'name' => 'white-space', 'value' => 'pre wrap', 'important' => false],
        ]];
        yield 'white-space rejects comment-split keyword' => ['white-space: pre-/**/wrap', [
            ['type' => 'undef', 'name' => 'white-space', 'value' => 'pre-wrap', 'important' => false],
        ]];
        yield 'white-space keeps important flag' => ['white-space: nowrap !important', [
            ['type' => 'property', 'name' => 'white-space', 'value' => 'nowrap', 'important' => true],
        ]];
        yield 'word-break lowercases mixed-case keyword' => ['word-break: BrEaK-AlL', [
            ['type' => 'property', 'name' => 'word-break', 'value' => 'break-all', 'important' => false],
        ]];
        yield 'word-break rejects line-break keyword' => ['word-break: anywhere', [
            ['type' => 'undef', 'name' => 'word-break', 'value' => 'anywhere', 'important' => false],
        ]];
        yield 'word-break rejects multiple keywords' => ['word-break: keep-all break-word', [
            ['type' => 'undef', 'name' => 'word-break', 'value' => 'keep-all break-word', 'important' => false],
        ]];
        yield 'word-break rejects comment-split keyword' => ['word-break: break-/**/word', [
            ['type' => 'undef', 'name' => 'word-break', 'value' => 'break-word', 'important' => false],
        ]];
        yield 'word-break keeps important flag' => ['word-break: keep-all !important', [
            ['type' => 'property', 'name' => 'word-break', 'value' => 'keep-all', 'important' => true],
        ]];
        yield 'writing-mode rejects horizontal' => ['writing-mode: horizontal', [
            ['type' => 'undef', 'name' => 'writing-mode', 'value' => 'horizontal', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function hangingPunctuationDeclarationProvider(): iterable
    {
        yield 'hanging-punctuation accepts none' => ['hanging-punctuation: none', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'none', 'important' => false],
        ]];
        yield 'hanging-punctuation accepts first' => ['hanging-punctuation: first', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'first', 'important' => false],
        ]];
        yield 'hanging-punctuation accepts force-end' => ['hanging-punctuation: force-end', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'force-end', 'important' => false],
        ]];
        yield 'hanging-punctuation accepts allow-end' => ['hanging-punctuation: allow-end', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'allow-end', 'important' => false],
        ]];
        yield 'hanging-punctuation accepts last' => ['hanging-punctuation: last', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'last', 'important' => false],
        ]];
        yield 'hanging-punctuation accepts css-wide keyword' => ['hanging-punctuation: inherit', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'hanging-punctuation accepts all keyword groups' => ['hanging-punctuation: first force-end last', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'first force-end last', 'important' => false],
        ]];
        yield 'hanging-punctuation serializes in Lexbor group order' => ['hanging-punctuation: last allow-end first', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'first allow-end last', 'important' => false],
        ]];
        yield 'hanging-punctuation treats comments between keywords as separators' => ['hanging-punctuation: first/**/last', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'first last', 'important' => false],
        ]];
        yield 'hanging-punctuation lowercases mixed-case keywords' => ['hanging-punctuation: FiRsT ALLOW-END', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'first allow-end', 'important' => false],
        ]];
        yield 'hanging-punctuation rejects duplicate first group' => ['hanging-punctuation: first first', [
            ['type' => 'undef', 'name' => 'hanging-punctuation', 'value' => 'first first', 'important' => false],
        ]];
        yield 'hanging-punctuation rejects duplicate force allow group' => ['hanging-punctuation: force-end allow-end', [
            ['type' => 'undef', 'name' => 'hanging-punctuation', 'value' => 'force-end allow-end', 'important' => false],
        ]];
        yield 'hanging-punctuation rejects duplicate last group' => ['hanging-punctuation: last last', [
            ['type' => 'undef', 'name' => 'hanging-punctuation', 'value' => 'last last', 'important' => false],
        ]];
        yield 'hanging-punctuation rejects none with another keyword' => ['hanging-punctuation: none first', [
            ['type' => 'undef', 'name' => 'hanging-punctuation', 'value' => 'none first', 'important' => false],
        ]];
        yield 'hanging-punctuation rejects css-wide keyword with local keyword' => ['hanging-punctuation: inherit last', [
            ['type' => 'undef', 'name' => 'hanging-punctuation', 'value' => 'inherit last', 'important' => false],
        ]];
        yield 'hanging-punctuation rejects unknown keyword' => ['hanging-punctuation: auto', [
            ['type' => 'undef', 'name' => 'hanging-punctuation', 'value' => 'auto', 'important' => false],
        ]];
        yield 'hanging-punctuation rejects non-ident token' => ['hanging-punctuation: 1', [
            ['type' => 'undef', 'name' => 'hanging-punctuation', 'value' => '1', 'important' => false],
        ]];
        yield 'hanging-punctuation rejects comment-split keyword' => ['hanging-punctuation: force-/**/end', [
            ['type' => 'undef', 'name' => 'hanging-punctuation', 'value' => 'force-end', 'important' => false],
        ]];
        yield 'hanging-punctuation keeps important flag' => ['hanging-punctuation: allow-end !important', [
            ['type' => 'property', 'name' => 'hanging-punctuation', 'value' => 'allow-end', 'important' => true],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function textDecorationDeclarationProvider(): iterable
    {
        yield 'text-decoration-line accepts none' => ['text-decoration-line: none', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'none', 'important' => false],
        ]];
        yield 'text-decoration-line accepts underline' => ['text-decoration-line: underline', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'underline', 'important' => false],
        ]];
        yield 'text-decoration-line accepts overline' => ['text-decoration-line: overline', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'overline', 'important' => false],
        ]];
        yield 'text-decoration-line accepts line-through' => ['text-decoration-line: line-through', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'line-through', 'important' => false],
        ]];
        yield 'text-decoration-line accepts blink' => ['text-decoration-line: blink', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'blink', 'important' => false],
        ]];
        yield 'text-decoration-line accepts css-wide keyword' => ['text-decoration-line: inherit', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'text-decoration-line lowercases mixed-case keyword' => ['text-decoration-line: UnderLine', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'underline', 'important' => false],
        ]];
        yield 'text-decoration-line accepts all line keywords' => ['text-decoration-line: underline overline line-through blink', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'underline overline line-through blink', 'important' => false],
        ]];
        yield 'text-decoration-line serializes in Lexbor order' => ['text-decoration-line: blink line-through overline underline', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'underline overline line-through blink', 'important' => false],
        ]];
        yield 'text-decoration-line treats comments between keywords as separators' => ['text-decoration-line: underline/**/blink', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'underline blink', 'important' => false],
        ]];
        yield 'text-decoration-line rejects whitespace-comment-whitespace separator like Lexbor' => ['text-decoration-line: underline /**/ blink', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => 'underline  blink', 'important' => false],
        ]];
        yield 'text-decoration-line keeps important flag' => ['text-decoration-line: overline !important', [
            ['type' => 'property', 'name' => 'text-decoration-line', 'value' => 'overline', 'important' => true],
        ]];
        yield 'text-decoration-line rejects duplicate keyword' => ['text-decoration-line: underline underline', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => 'underline underline', 'important' => false],
        ]];
        yield 'text-decoration-line rejects none with line keyword' => ['text-decoration-line: none underline', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => 'none underline', 'important' => false],
        ]];
        yield 'text-decoration-line rejects line keyword with none' => ['text-decoration-line: underline none', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => 'underline none', 'important' => false],
        ]];
        yield 'text-decoration-line rejects css-wide keyword with line keyword' => ['text-decoration-line: initial underline', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => 'initial underline', 'important' => false],
        ]];
        yield 'text-decoration-line rejects line keyword with css-wide keyword' => ['text-decoration-line: underline initial', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => 'underline initial', 'important' => false],
        ]];
        yield 'text-decoration-line rejects unknown keyword' => ['text-decoration-line: strikethrough', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => 'strikethrough', 'important' => false],
        ]];
        yield 'text-decoration-line rejects non-ident token' => ['text-decoration-line: 1', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => '1', 'important' => false],
        ]];
        yield 'text-decoration-line rejects comment-split keyword' => ['text-decoration-line: under/**/line', [
            ['type' => 'undef', 'name' => 'text-decoration-line', 'value' => 'underline', 'important' => false],
        ]];
        yield 'text-decoration-style accepts solid' => ['text-decoration-style: solid', [
            ['type' => 'property', 'name' => 'text-decoration-style', 'value' => 'solid', 'important' => false],
        ]];
        yield 'text-decoration-style accepts double' => ['text-decoration-style: double', [
            ['type' => 'property', 'name' => 'text-decoration-style', 'value' => 'double', 'important' => false],
        ]];
        yield 'text-decoration-style accepts dotted' => ['text-decoration-style: dotted', [
            ['type' => 'property', 'name' => 'text-decoration-style', 'value' => 'dotted', 'important' => false],
        ]];
        yield 'text-decoration-style accepts dashed' => ['text-decoration-style: dashed', [
            ['type' => 'property', 'name' => 'text-decoration-style', 'value' => 'dashed', 'important' => false],
        ]];
        yield 'text-decoration-style accepts wavy' => ['text-decoration-style: wavy', [
            ['type' => 'property', 'name' => 'text-decoration-style', 'value' => 'wavy', 'important' => false],
        ]];
        yield 'text-decoration-style accepts css-wide keyword' => ['text-decoration-style: revert', [
            ['type' => 'property', 'name' => 'text-decoration-style', 'value' => 'revert', 'important' => false],
        ]];
        yield 'text-decoration-style lowercases mixed-case keyword' => ['text-decoration-style: WaVy', [
            ['type' => 'property', 'name' => 'text-decoration-style', 'value' => 'wavy', 'important' => false],
        ]];
        yield 'text-decoration-style rejects comment-separated leading whitespace like Lexbor' => ['text-decoration-style: /**/  wavy', [
            ['type' => 'undef', 'name' => 'text-decoration-style', 'value' => 'wavy', 'important' => false],
        ]];
        yield 'text-decoration-style keeps important flag' => ['text-decoration-style: dotted !important', [
            ['type' => 'property', 'name' => 'text-decoration-style', 'value' => 'dotted', 'important' => true],
        ]];
        yield 'text-decoration-style rejects line keyword' => ['text-decoration-style: underline', [
            ['type' => 'undef', 'name' => 'text-decoration-style', 'value' => 'underline', 'important' => false],
        ]];
        yield 'text-decoration-style rejects multiple values' => ['text-decoration-style: solid dotted', [
            ['type' => 'undef', 'name' => 'text-decoration-style', 'value' => 'solid dotted', 'important' => false],
        ]];
        yield 'text-decoration-style rejects unknown keyword' => ['text-decoration-style: groove', [
            ['type' => 'undef', 'name' => 'text-decoration-style', 'value' => 'groove', 'important' => false],
        ]];
        yield 'text-decoration-style rejects non-ident token' => ['text-decoration-style: 1', [
            ['type' => 'undef', 'name' => 'text-decoration-style', 'value' => '1', 'important' => false],
        ]];
        yield 'text-decoration-style rejects comment-split keyword' => ['text-decoration-style: wa/**/vy', [
            ['type' => 'undef', 'name' => 'text-decoration-style', 'value' => 'wavy', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function verticalAlignDeclarationProvider(): iterable
    {
        yield 'vertical-align accepts baseline' => ['vertical-align: baseline', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'baseline', 'important' => false],
        ]];
        yield 'vertical-align accepts text-bottom' => ['vertical-align: text-bottom', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'text-bottom', 'important' => false],
        ]];
        yield 'vertical-align accepts alphabetic' => ['vertical-align: alphabetic', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'alphabetic', 'important' => false],
        ]];
        yield 'vertical-align accepts ideographic' => ['vertical-align: ideographic', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'ideographic', 'important' => false],
        ]];
        yield 'vertical-align accepts middle' => ['vertical-align: middle', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'middle', 'important' => false],
        ]];
        yield 'vertical-align accepts central' => ['vertical-align: central', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'central', 'important' => false],
        ]];
        yield 'vertical-align accepts mathematical' => ['vertical-align: mathematical', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'mathematical', 'important' => false],
        ]];
        yield 'vertical-align accepts text-top' => ['vertical-align: text-top', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'text-top', 'important' => false],
        ]];
        yield 'vertical-align accepts first' => ['vertical-align: first', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'first', 'important' => false],
        ]];
        yield 'vertical-align accepts last' => ['vertical-align: last', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'last', 'important' => false],
        ]];
        yield 'vertical-align accepts css-wide keyword' => ['vertical-align: inherit', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'vertical-align accepts sub' => ['vertical-align: sub', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'sub', 'important' => false],
        ]];
        yield 'vertical-align accepts super' => ['vertical-align: super', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'super', 'important' => false],
        ]];
        yield 'vertical-align accepts top' => ['vertical-align: top', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'top', 'important' => false],
        ]];
        yield 'vertical-align accepts center' => ['vertical-align: center', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'center', 'important' => false],
        ]];
        yield 'vertical-align accepts bottom' => ['vertical-align: bottom', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'bottom', 'important' => false],
        ]];
        yield 'vertical-align accepts length' => ['vertical-align: 2px', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => '2px', 'important' => false],
        ]];
        yield 'vertical-align lowercases mixed-case length unit' => ['vertical-align: 2PX', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => '2px', 'important' => false],
        ]];
        yield 'vertical-align serializes q length with Lexbor uppercase spelling' => ['vertical-align: 2q', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => '2Q', 'important' => false],
        ]];
        yield 'vertical-align accepts percentage' => ['vertical-align: 12.5%', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => '12.5%', 'important' => false],
        ]];
        yield 'vertical-align accepts signed percentage' => ['vertical-align: -10%', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => '-10%', 'important' => false],
        ]];
        yield 'vertical-align accepts unitless zero' => ['vertical-align: 0', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => '0', 'important' => false],
        ]];
        yield 'vertical-align lowercases mixed-case keyword' => ['vertical-align: MaThEmAtIcAl', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'mathematical', 'important' => false],
        ]];
        yield 'vertical-align serializes shift after alignment' => ['vertical-align: sub baseline', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'baseline sub', 'important' => false],
        ]];
        yield 'vertical-align serializes type before alignment' => ['vertical-align: baseline last', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'last baseline', 'important' => false],
        ]];
        yield 'vertical-align serializes all components in Lexbor order' => ['vertical-align: sub first baseline', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'first baseline sub', 'important' => false],
        ]];
        yield 'vertical-align accepts css-wide keyword with alignment like Lexbor' => ['vertical-align: inherit baseline', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'inherit baseline', 'important' => false],
        ]];
        yield 'vertical-align lets type reset prior alignment like Lexbor' => ['vertical-align: baseline first middle', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'first middle', 'important' => false],
        ]];
        yield 'vertical-align lets css-wide type reset prior shift like Lexbor' => ['vertical-align: sub inherit super', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'inherit super', 'important' => false],
        ]];
        yield 'vertical-align treats adjacent comment as separator' => ['vertical-align: baseline/**/sub', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'baseline sub', 'important' => false],
        ]];
        yield 'vertical-align keeps important flag' => ['vertical-align: baseline sub !important', [
            ['type' => 'property', 'name' => 'vertical-align', 'value' => 'baseline sub', 'important' => true],
        ]];
        yield 'vertical-align rejects whitespace-comment-whitespace separator like Lexbor' => ['vertical-align: baseline /**/ sub', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => 'baseline  sub', 'important' => false],
        ]];
        yield 'vertical-align rejects duplicate alignment group' => ['vertical-align: baseline middle', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => 'baseline middle', 'important' => false],
        ]];
        yield 'vertical-align rejects duplicate shift group' => ['vertical-align: sub super', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => 'sub super', 'important' => false],
        ]];
        yield 'vertical-align rejects duplicate type group' => ['vertical-align: first last', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => 'first last', 'important' => false],
        ]];
        yield 'vertical-align rejects duplicate shift after type reset' => ['vertical-align: sub inherit super bottom', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => 'sub inherit super bottom', 'important' => false],
        ]];
        yield 'vertical-align rejects unknown keyword' => ['vertical-align: normal', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => 'normal', 'important' => false],
        ]];
        yield 'vertical-align rejects nonzero number' => ['vertical-align: 1', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => '1', 'important' => false],
        ]];
        yield 'vertical-align rejects non-length dimension' => ['vertical-align: 1deg', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => '1deg', 'important' => false],
        ]];
        yield 'vertical-align rejects comment-split keyword' => ['vertical-align: base/**/line', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => 'baseline', 'important' => false],
        ]];
        yield 'vertical-align rejects comment-split length' => ['vertical-align: 1/**/px', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => '1px', 'important' => false],
        ]];
        yield 'vertical-align rejects comment-separated leading whitespace like Lexbor' => ['vertical-align: /**/  baseline', [
            ['type' => 'undef', 'name' => 'vertical-align', 'value' => 'baseline', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function fontStyleDeclarationProvider(): iterable
    {
        yield 'font-style accepts normal' => ['font-style: normal', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'normal', 'important' => false],
        ]];
        yield 'font-style accepts italic' => ['font-style: italic', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'italic', 'important' => false],
        ]];
        yield 'font-style accepts oblique without angle' => ['font-style: oblique', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'oblique', 'important' => false],
        ]];
        yield 'font-style accepts css-wide keyword' => ['font-style: inherit', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'font-style lowercases mixed-case keyword' => ['font-style: ItAlIc', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'italic', 'important' => false],
        ]];
        yield 'font-style accepts oblique positive angle' => ['font-style: oblique 10deg', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'oblique 10deg', 'important' => false],
        ]];
        yield 'font-style accepts oblique negative angle boundary' => ['font-style: oblique -90deg', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'oblique -90deg', 'important' => false],
        ]];
        yield 'font-style accepts oblique upper angle boundary' => ['font-style: oblique 90deg', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'oblique 90deg', 'important' => false],
        ]];
        yield 'font-style lowercases angle unit' => ['font-style: oblique 1RAD', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'oblique 1rad', 'important' => false],
        ]];
        yield 'font-style accepts raw turn value like Lexbor' => ['font-style: oblique .5turn', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'oblique 0.5turn', 'important' => false],
        ]];
        yield 'font-style treats comment between oblique and angle as separator' => ['font-style: oblique/**/10deg', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'oblique 10deg', 'important' => false],
        ]];
        yield 'font-style rejects angle below range' => ['font-style: oblique -91deg', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'oblique -91deg', 'important' => false],
        ]];
        yield 'font-style rejects angle above range' => ['font-style: oblique 91deg', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'oblique 91deg', 'important' => false],
        ]];
        yield 'font-style rejects bare angle' => ['font-style: 10deg', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => '10deg', 'important' => false],
        ]];
        yield 'font-style rejects oblique number' => ['font-style: oblique 10', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'oblique 10', 'important' => false],
        ]];
        yield 'font-style rejects oblique length' => ['font-style: oblique 10px', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'oblique 10px', 'important' => false],
        ]];
        yield 'font-style rejects normal with angle' => ['font-style: normal 10deg', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'normal 10deg', 'important' => false],
        ]];
        yield 'font-style rejects multiple angles' => ['font-style: oblique 10deg 20deg', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'oblique 10deg 20deg', 'important' => false],
        ]];
        yield 'font-style rejects unknown keyword' => ['font-style: slanted', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'slanted', 'important' => false],
        ]];
        yield 'font-style rejects comment-split keyword' => ['font-style: ob/**/lique', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'oblique', 'important' => false],
        ]];
        yield 'font-style rejects comment-split angle' => ['font-style: oblique 10/**/deg', [
            ['type' => 'undef', 'name' => 'font-style', 'value' => 'oblique 10deg', 'important' => false],
        ]];
        yield 'font-style keeps important flag' => ['font-style: oblique -15deg !important', [
            ['type' => 'property', 'name' => 'font-style', 'value' => 'oblique -15deg', 'important' => true],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function fontSizeDeclarationProvider(): iterable
    {
        yield 'font-size accepts xx-small' => ['font-size: xx-small', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'xx-small', 'important' => false],
        ]];
        yield 'font-size accepts x-small' => ['font-size: x-small', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'x-small', 'important' => false],
        ]];
        yield 'font-size accepts small' => ['font-size: small', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'small', 'important' => false],
        ]];
        yield 'font-size accepts medium' => ['font-size: medium', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'medium', 'important' => false],
        ]];
        yield 'font-size accepts large' => ['font-size: large', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'large', 'important' => false],
        ]];
        yield 'font-size accepts x-large' => ['font-size: x-large', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'x-large', 'important' => false],
        ]];
        yield 'font-size accepts xx-large' => ['font-size: xx-large', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'xx-large', 'important' => false],
        ]];
        yield 'font-size accepts xxx-large' => ['font-size: xxx-large', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'xxx-large', 'important' => false],
        ]];
        yield 'font-size accepts math' => ['font-size: math', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'math', 'important' => false],
        ]];
        yield 'font-size accepts larger' => ['font-size: larger', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'larger', 'important' => false],
        ]];
        yield 'font-size accepts smaller' => ['font-size: smaller', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'smaller', 'important' => false],
        ]];
        yield 'font-size accepts css-wide keyword' => ['font-size: revert', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'revert', 'important' => false],
        ]];
        yield 'font-size lowercases mixed-case keyword' => ['font-size: Xx-LaRgE', [
            ['type' => 'property', 'name' => 'font-size', 'value' => 'xx-large', 'important' => false],
        ]];
        yield 'font-size accepts length' => ['font-size: 12px', [
            ['type' => 'property', 'name' => 'font-size', 'value' => '12px', 'important' => false],
        ]];
        yield 'font-size lowercases length unit' => ['font-size: 12PX', [
            ['type' => 'property', 'name' => 'font-size', 'value' => '12px', 'important' => false],
        ]];
        yield 'font-size serializes q unit with Lexbor uppercase spelling' => ['font-size: 2q', [
            ['type' => 'property', 'name' => 'font-size', 'value' => '2Q', 'important' => false],
        ]];
        yield 'font-size accepts percentage' => ['font-size: 125%', [
            ['type' => 'property', 'name' => 'font-size', 'value' => '125%', 'important' => false],
        ]];
        yield 'font-size accepts normalized exponent percentage' => ['font-size: 1e2%', [
            ['type' => 'property', 'name' => 'font-size', 'value' => '100%', 'important' => false],
        ]];
        yield 'font-size accepts unitless zero' => ['font-size: 0', [
            ['type' => 'property', 'name' => 'font-size', 'value' => '0', 'important' => false],
        ]];
        yield 'font-size keeps important flag' => ['font-size: 14px !important', [
            ['type' => 'property', 'name' => 'font-size', 'value' => '14px', 'important' => true],
        ]];
        yield 'font-size rejects negative length' => ['font-size: -1px', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => '-1px', 'important' => false],
        ]];
        yield 'font-size rejects negative percentage' => ['font-size: -1%', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => '-1%', 'important' => false],
        ]];
        yield 'font-size rejects nonzero number' => ['font-size: 1', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => '1', 'important' => false],
        ]];
        yield 'font-size rejects auto' => ['font-size: auto', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => 'auto', 'important' => false],
        ]];
        yield 'font-size rejects unknown keyword' => ['font-size: huge', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => 'huge', 'important' => false],
        ]];
        yield 'font-size rejects multiple values' => ['font-size: medium 12px', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => 'medium 12px', 'important' => false],
        ]];
        yield 'font-size rejects non-length dimension' => ['font-size: 1deg', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => '1deg', 'important' => false],
        ]];
        yield 'font-size rejects comment-split keyword' => ['font-size: me/**/dium', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => 'medium', 'important' => false],
        ]];
        yield 'font-size rejects comment-split percentage' => ['font-size: 10/**/%', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => '10%', 'important' => false],
        ]];
        yield 'font-size rejects function' => ['font-size: calc(12px)', [
            ['type' => 'undef', 'name' => 'font-size', 'value' => 'calc(12px)', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function fontFamilyDeclarationProvider(): iterable
    {
        yield 'font-family accepts serif' => ['font-family: serif', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'serif', 'important' => false],
        ]];
        yield 'font-family accepts sans-serif' => ['font-family: sans-serif', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'sans-serif', 'important' => false],
        ]];
        yield 'font-family accepts system-ui' => ['font-family: system-ui', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'system-ui', 'important' => false],
        ]];
        yield 'font-family accepts ui-sans-serif' => ['font-family: ui-sans-serif', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'ui-sans-serif', 'important' => false],
        ]];
        yield 'font-family accepts css-wide keyword as Lexbor value' => ['font-family: inherit', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'font-family accepts css-wide keyword in list like Lexbor' => ['font-family: inherit, serif', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'inherit, serif', 'important' => false],
        ]];
        yield 'font-family canonicalizes mixed-case known keyword' => ['font-family: SeRiF', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'serif', 'important' => false],
        ]];
        yield 'font-family canonicalizes system color keyword' => ['font-family: canvas', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'Canvas', 'important' => false],
        ]];
        yield 'font-family accepts known non-font value like Lexbor' => ['font-family: Red', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'red', 'important' => false],
        ]];
        yield 'font-family preserves unknown ident case' => ['font-family: MyFont', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'MyFont', 'important' => false],
        ]];
        yield 'font-family serializes quoted one-word family as ident' => ['font-family: "Arial"', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'Arial', 'important' => false],
        ]];
        yield 'font-family serializes quoted hyphen family as ident' => ['font-family: "My-Font"', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'My-Font', 'important' => false],
        ]];
        yield 'font-family keeps quoted family with space' => ['font-family: "Open Sans"', [
            ['type' => 'property', 'name' => 'font-family', 'value' => '"Open Sans"', 'important' => false],
        ]];
        yield 'font-family keeps quoted non-ascii family as string like Lexbor' => ['font-family: "é"', [
            ['type' => 'property', 'name' => 'font-family', 'value' => '"é"', 'important' => false],
        ]];
        yield 'font-family reserializes non-ascii ident as string like Lexbor' => ['font-family: é', [
            ['type' => 'property', 'name' => 'font-family', 'value' => '"é"', 'important' => false],
        ]];
        yield 'font-family normalizes single quoted family to double quotes' => ['font-family: \'Open Sans\'', [
            ['type' => 'property', 'name' => 'font-family', 'value' => '"Open Sans"', 'important' => false],
        ]];
        yield 'font-family serializes quoted numeric family as bare name like Lexbor' => ['font-family: "123"', [
            ['type' => 'property', 'name' => 'font-family', 'value' => '123', 'important' => false],
        ]];
        yield 'font-family accepts comma separated families' => ['font-family: Arial, serif', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'Arial, serif', 'important' => false],
        ]];
        yield 'font-family treats comments around comma as whitespace' => ['font-family: Arial/**/,/**/serif', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'Arial, serif', 'important' => false],
        ]];
        yield 'font-family keeps important flag' => ['font-family: Arial, serif !important', [
            ['type' => 'property', 'name' => 'font-family', 'value' => 'Arial, serif', 'important' => true],
        ]];
        yield 'font-family rejects comment-separated leading whitespace like Lexbor' => ['font-family: /**/  Arial', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Arial', 'important' => false],
        ]];
        yield 'font-family rejects comment-separated whitespace before comma like Lexbor' => ['font-family: Arial /**/ , serif', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Arial  , serif', 'important' => false],
        ]];
        yield 'font-family rejects comment-separated important marker like Lexbor' => ['font-family: Arial ! /**/ important', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Arial !  important', 'important' => false],
        ]];
        yield 'font-family rejects trailing comment after spaced important like Lexbor' => ['font-family: Arial !important/**/', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Arial !important', 'important' => false],
        ]];
        yield 'font-family rejects trailing comment after compact important like Lexbor' => ['font-family: Arial!important/**/', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Arial!important', 'important' => false],
        ]];
        yield 'font-family rejects empty value' => ['font-family:', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => '', 'important' => false],
        ]];
        yield 'font-family rejects number' => ['font-family: 1', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => '1', 'important' => false],
        ]];
        yield 'font-family rejects function' => ['font-family: calc(Arial)', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'calc(Arial)', 'important' => false],
        ]];
        yield 'font-family rejects leading comma' => ['font-family: , serif', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => ', serif', 'important' => false],
        ]];
        yield 'font-family rejects trailing comma' => ['font-family: Arial,', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Arial,', 'important' => false],
        ]];
        yield 'font-family rejects empty comma item' => ['font-family: Arial,, serif', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Arial,, serif', 'important' => false],
        ]];
        yield 'font-family rejects missing comma' => ['font-family: Arial serif', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Arial serif', 'important' => false],
        ]];
        yield 'font-family rejects unquoted multi-word family' => ['font-family: Times New Roman', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'Times New Roman', 'important' => false],
        ]];
        yield 'font-family rejects comment-split known keyword' => ['font-family: se/**/rif', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'serif', 'important' => false],
        ]];
        yield 'font-family rejects comment-split unknown ident' => ['font-family: My/**/Font', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => 'MyFont', 'important' => false],
        ]];
        yield 'font-family rejects block token' => ['font-family: [Arial]', [
            ['type' => 'undef', 'name' => 'font-family', 'value' => '[Arial]', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function textIndentDeclarationProvider(): iterable
    {
        yield 'text-indent accepts length' => ['text-indent: 2px', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px', 'important' => false],
        ]];
        yield 'text-indent accepts negative length' => ['text-indent: -2px', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '-2px', 'important' => false],
        ]];
        yield 'text-indent serializes q unit with Lexbor uppercase spelling' => ['text-indent: 2q', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2Q', 'important' => false],
        ]];
        yield 'text-indent lowercases mixed-case length unit' => ['text-indent: 2PX', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px', 'important' => false],
        ]];
        yield 'text-indent accepts percentage' => ['text-indent: 10%', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '10%', 'important' => false],
        ]];
        yield 'text-indent accepts negative percentage' => ['text-indent: -10%', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '-10%', 'important' => false],
        ]];
        yield 'text-indent accepts unitless zero' => ['text-indent: 0', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '0', 'important' => false],
        ]];
        yield 'text-indent accepts css-wide keyword' => ['text-indent: unset', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => 'unset', 'important' => false],
        ]];
        yield 'text-indent accepts length then hanging' => ['text-indent: 2px hanging', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px hanging', 'important' => false],
        ]];
        yield 'text-indent serializes hanging after length' => ['text-indent: hanging 2px', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px hanging', 'important' => false],
        ]];
        yield 'text-indent accepts adjacent positive length after hanging' => ['text-indent: hanging+2px', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px hanging', 'important' => false],
        ]];
        yield 'text-indent accepts length then each-line' => ['text-indent: 2px each-line', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px each-line', 'important' => false],
        ]];
        yield 'text-indent serializes each-line after length' => ['text-indent: each-line 2px', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px each-line', 'important' => false],
        ]];
        yield 'text-indent accepts length with both keywords' => ['text-indent: 2px hanging each-line', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px hanging each-line', 'important' => false],
        ]];
        yield 'text-indent serializes both keywords in Lexbor order' => ['text-indent: each-line hanging 2px', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px hanging each-line', 'important' => false],
        ]];
        yield 'text-indent treats comments between keyword and length as whitespace' => ['text-indent: hanging/**/2px', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2px hanging', 'important' => false],
        ]];
        yield 'text-indent keeps important flag' => ['text-indent: 2em each-line !important', [
            ['type' => 'property', 'name' => 'text-indent', 'value' => '2em each-line', 'important' => true],
        ]];
        yield 'text-indent rejects hanging without length' => ['text-indent: hanging', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'hanging', 'important' => false],
        ]];
        yield 'text-indent rejects each-line without length' => ['text-indent: each-line', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'each-line', 'important' => false],
        ]];
        yield 'text-indent rejects keywords without length' => ['text-indent: hanging each-line', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'hanging each-line', 'important' => false],
        ]];
        yield 'text-indent rejects css-wide keyword with length' => ['text-indent: inherit 2px', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'inherit 2px', 'important' => false],
        ]];
        yield 'text-indent rejects duplicate hanging' => ['text-indent: 2px hanging hanging', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => '2px hanging hanging', 'important' => false],
        ]];
        yield 'text-indent rejects duplicate each-line' => ['text-indent: 2px each-line each-line', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => '2px each-line each-line', 'important' => false],
        ]];
        yield 'text-indent rejects multiple lengths' => ['text-indent: 1px 2px', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => '1px 2px', 'important' => false],
        ]];
        yield 'text-indent rejects auto' => ['text-indent: auto', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'auto', 'important' => false],
        ]];
        yield 'text-indent rejects none' => ['text-indent: none', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'none', 'important' => false],
        ]];
        yield 'text-indent rejects non-length dimension' => ['text-indent: 1deg', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => '1deg', 'important' => false],
        ]];
        yield 'text-indent rejects nonzero number' => ['text-indent: 1', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => '1', 'important' => false],
        ]];
        yield 'text-indent rejects comment-split keyword' => ['text-indent: hang/**/ing 2px', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'hanging 2px', 'important' => false],
        ]];
        yield 'text-indent rejects function' => ['text-indent: calc(1px)', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'calc(1px)', 'important' => false],
        ]];
        yield 'text-indent rejects adjacent negative length after hanging' => ['text-indent: hanging-2px', [
            ['type' => 'undef', 'name' => 'text-indent', 'value' => 'hanging-2px', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function textCombineUprightDeclarationProvider(): iterable
    {
        yield 'text-combine-upright accepts none' => ['text-combine-upright: none', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'none', 'important' => false],
        ]];
        yield 'text-combine-upright accepts all' => ['text-combine-upright: all', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'all', 'important' => false],
        ]];
        yield 'text-combine-upright accepts css-wide keyword' => ['text-combine-upright: revert', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'revert', 'important' => false],
        ]];
        yield 'text-combine-upright lowercases mixed-case keyword' => ['text-combine-upright: AlL', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'all', 'important' => false],
        ]];
        yield 'text-combine-upright accepts digits without integer' => ['text-combine-upright: digits', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits', 'important' => false],
        ]];
        yield 'text-combine-upright accepts digits 2' => ['text-combine-upright: digits 2', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 2', 'important' => false],
        ]];
        yield 'text-combine-upright accepts digits 4' => ['text-combine-upright: digits 4', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 4', 'important' => false],
        ]];
        yield 'text-combine-upright normalizes signed integer' => ['text-combine-upright: digits +4', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 4', 'important' => false],
        ]];
        yield 'text-combine-upright accepts adjacent plus two' => ['text-combine-upright: digits+2', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 2', 'important' => false],
        ]];
        yield 'text-combine-upright accepts adjacent plus four' => ['text-combine-upright: digits+4', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 4', 'important' => false],
        ]];
        yield 'text-combine-upright normalizes exponent integer' => ['text-combine-upright: digits 2e0', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 2', 'important' => false],
        ]];
        yield 'text-combine-upright normalizes integral decimal' => ['text-combine-upright: digits 2.0', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 2', 'important' => false],
        ]];
        yield 'text-combine-upright treats comments before integer as whitespace' => ['text-combine-upright: digits/**/4', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 4', 'important' => false],
        ]];
        yield 'text-combine-upright keeps important flag' => ['text-combine-upright: digits 2 !important', [
            ['type' => 'property', 'name' => 'text-combine-upright', 'value' => 'digits 2', 'important' => true],
        ]];
        yield 'text-combine-upright rejects auto' => ['text-combine-upright: auto', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'auto', 'important' => false],
        ]];
        yield 'text-combine-upright rejects none with integer' => ['text-combine-upright: none 2', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'none 2', 'important' => false],
        ]];
        yield 'text-combine-upright rejects all with integer' => ['text-combine-upright: all 2', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'all 2', 'important' => false],
        ]];
        yield 'text-combine-upright rejects css-wide keyword with integer' => ['text-combine-upright: inherit 2', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'inherit 2', 'important' => false],
        ]];
        yield 'text-combine-upright rejects digits 1' => ['text-combine-upright: digits 1', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'digits 1', 'important' => false],
        ]];
        yield 'text-combine-upright rejects digits 3' => ['text-combine-upright: digits 3', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'digits 3', 'important' => false],
        ]];
        yield 'text-combine-upright rejects negative integer' => ['text-combine-upright: digits -2', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'digits -2', 'important' => false],
        ]];
        yield 'text-combine-upright rejects fractional integer' => ['text-combine-upright: digits 2.5', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'digits 2.5', 'important' => false],
        ]];
        yield 'text-combine-upright rejects percentage integer' => ['text-combine-upright: digits 2%', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'digits 2%', 'important' => false],
        ]];
        yield 'text-combine-upright rejects length integer' => ['text-combine-upright: digits 2px', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'digits 2px', 'important' => false],
        ]];
        yield 'text-combine-upright rejects too many values' => ['text-combine-upright: digits 2 4', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'digits 2 4', 'important' => false],
        ]];
        yield 'text-combine-upright rejects comment-split keyword' => ['text-combine-upright: di/**/gits', [
            ['type' => 'undef', 'name' => 'text-combine-upright', 'value' => 'digits', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function textTransformDeclarationProvider(): iterable
    {
        yield 'text-transform accepts none' => ['text-transform: none', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'none', 'important' => false],
        ]];
        yield 'text-transform accepts capitalize' => ['text-transform: capitalize', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'capitalize', 'important' => false],
        ]];
        yield 'text-transform accepts uppercase' => ['text-transform: uppercase', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'uppercase', 'important' => false],
        ]];
        yield 'text-transform accepts lowercase' => ['text-transform: lowercase', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'lowercase', 'important' => false],
        ]];
        yield 'text-transform accepts full-width' => ['text-transform: full-width', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'full-width', 'important' => false],
        ]];
        yield 'text-transform accepts full-size-kana' => ['text-transform: full-size-kana', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'full-size-kana', 'important' => false],
        ]];
        yield 'text-transform accepts css-wide keyword' => ['text-transform: inherit', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'text-transform lowercases mixed-case keyword' => ['text-transform: UpPeRcAsE', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'uppercase', 'important' => false],
        ]];
        yield 'text-transform accepts case then full-width' => ['text-transform: uppercase full-width', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'uppercase full-width', 'important' => false],
        ]];
        yield 'text-transform serializes case before full-width' => ['text-transform: full-width uppercase', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'uppercase full-width', 'important' => false],
        ]];
        yield 'text-transform serializes all groups in Lexbor order' => ['text-transform: full-size-kana full-width lowercase', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'lowercase full-width full-size-kana', 'important' => false],
        ]];
        yield 'text-transform accepts width and kana without case' => ['text-transform: full-width full-size-kana', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'full-width full-size-kana', 'important' => false],
        ]];
        yield 'text-transform treats comments between keywords as whitespace' => ['text-transform: uppercase/**/full-size-kana', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'uppercase full-size-kana', 'important' => false],
        ]];
        yield 'text-transform keeps important flag' => ['text-transform: lowercase full-width !important', [
            ['type' => 'property', 'name' => 'text-transform', 'value' => 'lowercase full-width', 'important' => true],
        ]];
        yield 'text-transform rejects none with extra keyword' => ['text-transform: none uppercase', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'none uppercase', 'important' => false],
        ]];
        yield 'text-transform rejects css-wide keyword with extra keyword' => ['text-transform: inherit uppercase', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'inherit uppercase', 'important' => false],
        ]];
        yield 'text-transform rejects duplicate case group' => ['text-transform: uppercase lowercase', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'uppercase lowercase', 'important' => false],
        ]];
        yield 'text-transform rejects duplicate full-width' => ['text-transform: full-width full-width', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'full-width full-width', 'important' => false],
        ]];
        yield 'text-transform rejects duplicate full-size-kana' => ['text-transform: full-size-kana full-size-kana', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'full-size-kana full-size-kana', 'important' => false],
        ]];
        yield 'text-transform rejects unknown keyword' => ['text-transform: math-auto', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'math-auto', 'important' => false],
        ]];
        yield 'text-transform rejects comment-split keyword' => ['text-transform: upper-/**/case', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'upper-case', 'important' => false],
        ]];
        yield 'text-transform rejects number' => ['text-transform: 1', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => '1', 'important' => false],
        ]];
        yield 'text-transform rejects function' => ['text-transform: calc(1)', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'calc(1)', 'important' => false],
        ]];
        yield 'text-transform rejects non-ident extra token' => ['text-transform: uppercase 1', [
            ['type' => 'undef', 'name' => 'text-transform', 'value' => 'uppercase 1', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function flexKeywordDeclarationProvider(): iterable
    {
        $validKeywords = [
            'align-content' => ['flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'stretch'],
            'align-items' => ['flex-start', 'flex-end', 'center', 'baseline', 'stretch'],
            'align-self' => ['auto', 'flex-start', 'flex-end', 'center', 'baseline', 'stretch'],
            'flex-direction' => ['row', 'row-reverse', 'column', 'column-reverse'],
            'flex-wrap' => ['nowrap', 'wrap', 'wrap-reverse'],
        ];

        foreach ($validKeywords as $property => $keywords) {
            foreach ($keywords as $keyword) {
                yield "{$property} accepts {$keyword}" => ["{$property}: {$keyword}", [
                    ['type' => 'property', 'name' => $property, 'value' => $keyword, 'important' => false],
                ]];
            }

            yield "{$property} accepts css-wide keyword" => ["{$property}: revert", [
                ['type' => 'property', 'name' => $property, 'value' => 'revert', 'important' => false],
            ]];
        }

        yield 'align-content rejects baseline' => ['align-content: baseline', [
            ['type' => 'undef', 'name' => 'align-content', 'value' => 'baseline', 'important' => false],
        ]];
        yield 'align-content rejects multiple keywords' => ['align-content: center stretch', [
            ['type' => 'undef', 'name' => 'align-content', 'value' => 'center stretch', 'important' => false],
        ]];
        yield 'align-items rejects space-between' => ['align-items: space-between', [
            ['type' => 'undef', 'name' => 'align-items', 'value' => 'space-between', 'important' => false],
        ]];
        yield 'align-self rejects space-around' => ['align-self: space-around', [
            ['type' => 'undef', 'name' => 'align-self', 'value' => 'space-around', 'important' => false],
        ]];
        yield 'flex-direction rejects wrap' => ['flex-direction: wrap', [
            ['type' => 'undef', 'name' => 'flex-direction', 'value' => 'wrap', 'important' => false],
        ]];
        yield 'flex-direction rejects comment-split keyword' => ['flex-direction: row-/**/reverse', [
            ['type' => 'undef', 'name' => 'flex-direction', 'value' => 'row-reverse', 'important' => false],
        ]];
        yield 'flex-wrap rejects row' => ['flex-wrap: row', [
            ['type' => 'undef', 'name' => 'flex-wrap', 'value' => 'row', 'important' => false],
        ]];
        yield 'flex-wrap rejects multiple keywords' => ['flex-wrap: wrap nowrap', [
            ['type' => 'undef', 'name' => 'flex-wrap', 'value' => 'wrap nowrap', 'important' => false],
        ]];
        yield 'flex-wrap trims whitespace before trailing comment' => ['flex-wrap: wrap /**/;', [
            ['type' => 'property', 'name' => 'flex-wrap', 'value' => 'wrap', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function flexValueDeclarationProvider(): iterable
    {
        yield 'flex-grow accepts zero' => ['flex-grow: 0', [
            ['type' => 'property', 'name' => 'flex-grow', 'value' => '0', 'important' => false],
        ]];
        yield 'flex-grow accepts decimal number' => ['flex-grow: 1.5', [
            ['type' => 'property', 'name' => 'flex-grow', 'value' => '1.5', 'important' => false],
        ]];
        yield 'flex-grow accepts exponent number' => ['flex-grow: 1e2', [
            ['type' => 'property', 'name' => 'flex-grow', 'value' => '100', 'important' => false],
        ]];
        yield 'flex-grow accepts css-wide keyword' => ['flex-grow: inherit', [
            ['type' => 'property', 'name' => 'flex-grow', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'flex-grow trims whitespace before trailing comment' => ['flex-grow: 2 /**/;', [
            ['type' => 'property', 'name' => 'flex-grow', 'value' => '2', 'important' => false],
        ]];
        yield 'flex-grow rejects negative number' => ['flex-grow: -1', [
            ['type' => 'undef', 'name' => 'flex-grow', 'value' => '-1', 'important' => false],
        ]];
        yield 'flex-grow rejects percentage' => ['flex-grow: 1%', [
            ['type' => 'undef', 'name' => 'flex-grow', 'value' => '1%', 'important' => false],
        ]];
        yield 'flex-grow rejects comment-split number' => ['flex-grow: 1/**/2', [
            ['type' => 'undef', 'name' => 'flex-grow', 'value' => '12', 'important' => false],
        ]];
        yield 'flex-shrink accepts number' => ['flex-shrink: 2', [
            ['type' => 'property', 'name' => 'flex-shrink', 'value' => '2', 'important' => false],
        ]];
        yield 'flex-shrink accepts css-wide keyword' => ['flex-shrink: revert', [
            ['type' => 'property', 'name' => 'flex-shrink', 'value' => 'revert', 'important' => false],
        ]];
        yield 'flex-shrink rejects negative number' => ['flex-shrink: -0.5', [
            ['type' => 'undef', 'name' => 'flex-shrink', 'value' => '-0.5', 'important' => false],
        ]];
        yield 'flex-shrink rejects length' => ['flex-shrink: 1px', [
            ['type' => 'undef', 'name' => 'flex-shrink', 'value' => '1px', 'important' => false],
        ]];
        yield 'flex-basis accepts auto' => ['flex-basis: auto', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => 'auto', 'important' => false],
        ]];
        yield 'flex-basis accepts content' => ['flex-basis: content', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => 'content', 'important' => false],
        ]];
        yield 'flex-basis accepts min-content' => ['flex-basis: min-content', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => 'min-content', 'important' => false],
        ]];
        yield 'flex-basis accepts max-content' => ['flex-basis: max-content', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => 'max-content', 'important' => false],
        ]];
        yield 'flex-basis accepts length' => ['flex-basis: 12px', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => '12px', 'important' => false],
        ]];
        yield 'flex-basis serializes q unit with Lexbor uppercase spelling' => ['flex-basis: 2q', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => '2Q', 'important' => false],
        ]];
        yield 'flex-basis lowercases mixed-case px unit' => ['flex-basis: 2PX', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => '2px', 'important' => false],
        ]];
        yield 'flex-basis accepts negative length like Lexbor width handler' => ['flex-basis: -1px', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => '-1px', 'important' => false],
        ]];
        yield 'flex-basis accepts negative percentage like Lexbor width handler' => ['flex-basis: -10%', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => '-10%', 'important' => false],
        ]];
        yield 'flex-basis accepts unitless zero' => ['flex-basis: 0', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => '0', 'important' => false],
        ]];
        yield 'flex-basis accepts css-wide keyword' => ['flex-basis: unset', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => 'unset', 'important' => false],
        ]];
        yield 'flex-basis trims whitespace before trailing comment' => ['flex-basis: content /**/;', [
            ['type' => 'property', 'name' => 'flex-basis', 'value' => 'content', 'important' => false],
        ]];
        yield 'flex-basis rejects none' => ['flex-basis: none', [
            ['type' => 'undef', 'name' => 'flex-basis', 'value' => 'none', 'important' => false],
        ]];
        yield 'flex-basis rejects nonzero number' => ['flex-basis: 1', [
            ['type' => 'undef', 'name' => 'flex-basis', 'value' => '1', 'important' => false],
        ]];
        yield 'flex-basis rejects non-length dimension' => ['flex-basis: 1deg', [
            ['type' => 'undef', 'name' => 'flex-basis', 'value' => '1deg', 'important' => false],
        ]];
        yield 'flex-basis rejects multiple values' => ['flex-basis: auto 1px', [
            ['type' => 'undef', 'name' => 'flex-basis', 'value' => 'auto 1px', 'important' => false],
        ]];
        yield 'flex-basis rejects comment-split length' => ['flex-basis: 1/**/px', [
            ['type' => 'undef', 'name' => 'flex-basis', 'value' => '1px', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function flexFlowDeclarationProvider(): iterable
    {
        yield 'flex-flow accepts direction keyword' => ['flex-flow: row', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'row', 'important' => false],
        ]];
        yield 'flex-flow accepts wrap keyword' => ['flex-flow: wrap', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'wrap', 'important' => false],
        ]];
        yield 'flex-flow accepts direction then wrap' => ['flex-flow: row wrap', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'row wrap', 'important' => false],
        ]];
        yield 'flex-flow accepts wrap then direction and serializes direction first' => ['flex-flow: wrap-reverse column-reverse', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'column-reverse wrap-reverse', 'important' => false],
        ]];
        yield 'flex-flow accepts css-wide keyword' => ['flex-flow: inherit', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'flex-flow lowercases mixed-case direction keyword' => ['flex-flow: RoW', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'row', 'important' => false],
        ]];
        yield 'flex-flow accepts css-wide keyword then wrap like Lexbor state machine' => ['flex-flow: initial nowrap', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'initial nowrap', 'important' => false],
        ]];
        yield 'flex-flow lowercases mixed-case css-wide keyword then wrap' => ['flex-flow: InItIaL NOWRAP', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'initial nowrap', 'important' => false],
        ]];
        yield 'flex-flow accepts comment-separated complete keywords' => ['flex-flow: row/**/wrap', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'row wrap', 'important' => false],
        ]];
        yield 'flex-flow lowercases mixed-case wrap then direction' => ['flex-flow: WRAP ROW', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'row wrap', 'important' => false],
        ]];
        yield 'flex-flow trims whitespace before trailing comment' => ['flex-flow: column /**/;', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'column', 'important' => false],
        ]];
        yield 'flex-flow rejects duplicate directions' => ['flex-flow: row column', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => 'row column', 'important' => false],
        ]];
        yield 'flex-flow rejects duplicate wraps' => ['flex-flow: wrap nowrap', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => 'wrap nowrap', 'important' => false],
        ]];
        yield 'flex-flow rejects too many keywords' => ['flex-flow: row wrap nowrap', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => 'row wrap nowrap', 'important' => false],
        ]];
        yield 'flex-flow rejects unknown keyword' => ['flex-flow: baseline', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => 'baseline', 'important' => false],
        ]];
        yield 'flex-flow rejects non-ident value' => ['flex-flow: 1px', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => '1px', 'important' => false],
        ]];
        yield 'flex-flow rejects wrap before css-wide keyword' => ['flex-flow: wrap inherit', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => 'wrap inherit', 'important' => false],
        ]];
        yield 'flex-flow rejects css-wide keyword before direction' => ['flex-flow: unset row', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => 'unset row', 'important' => false],
        ]];
        yield 'flex-flow rejects comment-split direction keyword' => ['flex-flow: row-/**/reverse', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => 'row-reverse', 'important' => false],
        ]];
        yield 'flex-flow rejects comment-split wrap keyword' => ['flex-flow: wrap-/**/reverse', [
            ['type' => 'undef', 'name' => 'flex-flow', 'value' => 'wrap-reverse', 'important' => false],
        ]];
        yield 'flex-flow keeps important flag' => ['flex-flow: wrap row !important', [
            ['type' => 'property', 'name' => 'flex-flow', 'value' => 'row wrap', 'important' => true],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function flexShorthandDeclarationProvider(): iterable
    {
        yield 'flex accepts none keyword' => ['flex: none', [
            ['type' => 'property', 'name' => 'flex', 'value' => 'none', 'important' => false],
        ]];
        yield 'flex accepts css-wide keyword' => ['flex: revert', [
            ['type' => 'property', 'name' => 'flex', 'value' => 'revert', 'important' => false],
        ]];
        yield 'flex lowercases mixed-case none keyword' => ['flex: NoNe', [
            ['type' => 'property', 'name' => 'flex', 'value' => 'none', 'important' => false],
        ]];
        yield 'flex accepts grow number' => ['flex: 1', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1', 'important' => false],
        ]];
        yield 'flex accepts negative grow number like Lexbor shorthand' => ['flex: -1', [
            ['type' => 'property', 'name' => 'flex', 'value' => '-1', 'important' => false],
        ]];
        yield 'flex accepts grow and shrink numbers' => ['flex: 1 0', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 0', 'important' => false],
        ]];
        yield 'flex accepts grow shrink and zero basis' => ['flex: 1 0 0', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 0 0', 'important' => false],
        ]];
        yield 'flex reinterprets three nonzero numbers like Lexbor' => ['flex: 1 2 3', [
            ['type' => 'property', 'name' => 'flex', 'value' => '2 3 1', 'important' => false],
        ]];
        yield 'flex accepts grow and length basis' => ['flex: 1 12px', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 12px', 'important' => false],
        ]];
        yield 'flex serializes q basis unit with Lexbor uppercase spelling' => ['flex: 1 2q', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 2Q', 'important' => false],
        ]];
        yield 'flex accepts grow and auto basis' => ['flex: 1 auto', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 auto', 'important' => false],
        ]];
        yield 'flex accepts grow and content basis' => ['flex: 1 content', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 content', 'important' => false],
        ]];
        yield 'flex accepts grow shrink and length basis' => ['flex: 1 2 12px', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 2 12px', 'important' => false],
        ]];
        yield 'flex accepts length basis' => ['flex: 12px', [
            ['type' => 'property', 'name' => 'flex', 'value' => '12px', 'important' => false],
        ]];
        yield 'flex accepts percentage basis' => ['flex: 25%', [
            ['type' => 'property', 'name' => 'flex', 'value' => '25%', 'important' => false],
        ]];
        yield 'flex serializes basis first input after grow' => ['flex: auto 2', [
            ['type' => 'property', 'name' => 'flex', 'value' => '2 auto', 'important' => false],
        ]];
        yield 'flex serializes basis first input after grow and shrink' => ['flex: content 1 2', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 2 content', 'important' => false],
        ]];
        yield 'flex lowercases mixed-case basis first input' => ['flex: MIN-CONTENT 2', [
            ['type' => 'property', 'name' => 'flex', 'value' => '2 min-content', 'important' => false],
        ]];
        yield 'flex accepts signed percentage basis before numbers' => ['flex: -10% 2 3', [
            ['type' => 'property', 'name' => 'flex', 'value' => '2 3 -10%', 'important' => false],
        ]];
        yield 'flex treats comments between numbers as whitespace' => ['flex: 1/**/2', [
            ['type' => 'property', 'name' => 'flex', 'value' => '1 2', 'important' => false],
        ]];
        yield 'flex trims whitespace before trailing comment' => ['flex: auto /**/;', [
            ['type' => 'property', 'name' => 'flex', 'value' => 'auto', 'important' => false],
        ]];
        yield 'flex rejects direction keyword' => ['flex: row', [
            ['type' => 'undef', 'name' => 'flex', 'value' => 'row', 'important' => false],
        ]];
        yield 'flex rejects wrap keyword' => ['flex: wrap', [
            ['type' => 'undef', 'name' => 'flex', 'value' => 'wrap', 'important' => false],
        ]];
        yield 'flex rejects css-wide keyword with extra value' => ['flex: inherit 1', [
            ['type' => 'undef', 'name' => 'flex', 'value' => 'inherit 1', 'important' => false],
        ]];
        yield 'flex rejects none with extra value' => ['flex: none 1', [
            ['type' => 'undef', 'name' => 'flex', 'value' => 'none 1', 'important' => false],
        ]];
        yield 'flex rejects grow followed by unknown ident' => ['flex: 1 foo', [
            ['type' => 'undef', 'name' => 'flex', 'value' => '1 foo', 'important' => false],
        ]];
        yield 'flex rejects basis followed by non-number' => ['flex: auto foo', [
            ['type' => 'undef', 'name' => 'flex', 'value' => 'auto foo', 'important' => false],
        ]];
        yield 'flex rejects too many values' => ['flex: 1 0 0 0', [
            ['type' => 'undef', 'name' => 'flex', 'value' => '1 0 0 0', 'important' => false],
        ]];
        yield 'flex rejects non-length dimension' => ['flex: 1 2deg', [
            ['type' => 'undef', 'name' => 'flex', 'value' => '1 2deg', 'important' => false],
        ]];
        yield 'flex rejects comment-split length' => ['flex: 1/**/px', [
            ['type' => 'undef', 'name' => 'flex', 'value' => '1px', 'important' => false],
        ]];
        yield 'flex rejects basis followed by length' => ['flex: 1% 2px', [
            ['type' => 'undef', 'name' => 'flex', 'value' => '1% 2px', 'important' => false],
        ]];
        yield 'flex keeps important flag' => ['flex: 2 3 auto !important', [
            ['type' => 'property', 'name' => 'flex', 'value' => '2 3 auto', 'important' => true],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function floatDeclarationProvider(): iterable
    {
        yield 'float accepts none keyword' => ['float: none', [
            ['type' => 'property', 'name' => 'float', 'value' => 'none', 'important' => false],
        ]];
        yield 'float accepts left keyword' => ['float: left', [
            ['type' => 'property', 'name' => 'float', 'value' => 'left', 'important' => false],
        ]];
        yield 'float accepts right keyword' => ['float: right', [
            ['type' => 'property', 'name' => 'float', 'value' => 'right', 'important' => false],
        ]];
        yield 'float accepts top keyword' => ['float: top', [
            ['type' => 'property', 'name' => 'float', 'value' => 'top', 'important' => false],
        ]];
        yield 'float accepts bottom keyword' => ['float: bottom', [
            ['type' => 'property', 'name' => 'float', 'value' => 'bottom', 'important' => false],
        ]];
        yield 'float accepts block-start keyword' => ['float: block-start', [
            ['type' => 'property', 'name' => 'float', 'value' => 'block-start', 'important' => false],
        ]];
        yield 'float accepts block-end keyword' => ['float: block-end', [
            ['type' => 'property', 'name' => 'float', 'value' => 'block-end', 'important' => false],
        ]];
        yield 'float accepts inline-start keyword' => ['float: inline-start', [
            ['type' => 'property', 'name' => 'float', 'value' => 'inline-start', 'important' => false],
        ]];
        yield 'float accepts inline-end keyword' => ['float: inline-end', [
            ['type' => 'property', 'name' => 'float', 'value' => 'inline-end', 'important' => false],
        ]];
        yield 'float accepts snap-block keyword' => ['float: snap-block', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-block', 'important' => false],
        ]];
        yield 'float accepts snap-inline keyword' => ['float: snap-inline', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-inline', 'important' => false],
        ]];
        yield 'float accepts css-wide keyword' => ['float: inherit', [
            ['type' => 'property', 'name' => 'float', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'float lowercases mixed-case keyword' => ['float: RiGhT', [
            ['type' => 'property', 'name' => 'float', 'value' => 'right', 'important' => false],
        ]];
        yield 'float accepts snap-block function with length' => ['float: snap-block(2px)', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-block(2px)', 'important' => false],
        ]];
        yield 'float snap-block serializes q length with Lexbor uppercase spelling' => ['float: snap-block(2q, start)', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-block(2Q, start)', 'important' => false],
        ]];
        yield 'float snap-block accepts zero length and end snap' => ['float: snap-block(0, end)', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-block(0, end)', 'important' => false],
        ]];
        yield 'float snap-block accepts near snap' => ['float: snap-block(2em, near)', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-block(2em, near)', 'important' => false],
        ]];
        yield 'float snap-inline lowercases mixed-case length unit' => ['float: snap-inline(3PX, left)', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-inline(3px, left)', 'important' => false],
        ]];
        yield 'float snap-inline accepts right snap' => ['float: snap-inline(4px, right)', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-inline(4px, right)', 'important' => false],
        ]];
        yield 'float snap-inline accepts near snap' => ['float: snap-inline(5px, near)', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-inline(5px, near)', 'important' => false],
        ]];
        yield 'float lowercases mixed-case snap function' => ['float: SNAP-BLOCK(2PX, StArT)', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-block(2px, start)', 'important' => false],
        ]];
        yield 'float normalizes snap function spacing' => ['float: snap-block( 2px , start )', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-block(2px, start)', 'important' => false],
        ]];
        yield 'float trims whitespace before trailing comment' => ['float: snap-inline(2px) /**/;', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-inline(2px)', 'important' => false],
        ]];
        yield 'float keeps important flag' => ['float: snap-inline(2px, near) !important', [
            ['type' => 'property', 'name' => 'float', 'value' => 'snap-inline(2px, near)', 'important' => true],
        ]];
        yield 'float rejects auto keyword' => ['float: auto', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'auto', 'important' => false],
        ]];
        yield 'float rejects multiple keywords' => ['float: left right', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'left right', 'important' => false],
        ]];
        yield 'float snap-block rejects inline snap keyword' => ['float: snap-block(2px, left)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(2px, left)', 'important' => false],
        ]];
        yield 'float snap-inline rejects block snap keyword' => ['float: snap-inline(2px, start)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-inline(2px, start)', 'important' => false],
        ]];
        yield 'float rejects unknown snap keyword' => ['float: snap-block(2px, middle)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(2px, middle)', 'important' => false],
        ]];
        yield 'float rejects too many snap arguments' => ['float: snap-block(2px, start, end)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(2px, start, end)', 'important' => false],
        ]];
        yield 'float rejects tokens after closed snap-block function' => ['float: snap-block(2px), start', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(2px), start', 'important' => false],
        ]];
        yield 'float rejects tokens after closed snap-inline function' => ['float: snap-inline(2px), right', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-inline(2px), right', 'important' => false],
        ]];
        yield 'float rejects snap percentage' => ['float: snap-block(2%)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(2%)', 'important' => false],
        ]];
        yield 'float rejects nonzero unitless snap length' => ['float: snap-block(1)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(1)', 'important' => false],
        ]];
        yield 'float rejects non-length snap dimension' => ['float: snap-block(1deg)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(1deg)', 'important' => false],
        ]];
        yield 'float rejects empty snap function' => ['float: snap-block()', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block()', 'important' => false],
        ]];
        yield 'float rejects unknown function' => ['float: calc(2px)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'calc(2px)', 'important' => false],
        ]];
        yield 'float rejects comment-split snap length' => ['float: snap-block(2/**/px)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(2px)', 'important' => false],
        ]];
        yield 'float rejects escaped parenthesis in snap-block function name' => ['float: snap-block\((2px)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block((2px)', 'important' => false],
        ]];
        yield 'float rejects escaped parenthesis in snap-inline function name' => ['float: snap-inline\28 (2px, left)', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-inline((2px, left)', 'important' => false],
        ]];
        yield 'float rejects missing snap close parenthesis' => ['float: snap-block(2px', [
            ['type' => 'undef', 'name' => 'float', 'value' => 'snap-block(2px', 'important' => false],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public static function floatSupportDeclarationProvider(): iterable
    {
        yield 'float-reference accepts inline' => ['float-reference: inline', [
            ['type' => 'property', 'name' => 'float-reference', 'value' => 'inline', 'important' => false],
        ]];
        yield 'float-reference accepts column' => ['float-reference: column', [
            ['type' => 'property', 'name' => 'float-reference', 'value' => 'column', 'important' => false],
        ]];
        yield 'float-reference accepts region' => ['float-reference: region', [
            ['type' => 'property', 'name' => 'float-reference', 'value' => 'region', 'important' => false],
        ]];
        yield 'float-reference accepts page' => ['float-reference: page', [
            ['type' => 'property', 'name' => 'float-reference', 'value' => 'page', 'important' => false],
        ]];
        yield 'float-reference accepts css-wide keyword' => ['float-reference: inherit', [
            ['type' => 'property', 'name' => 'float-reference', 'value' => 'inherit', 'important' => false],
        ]];
        yield 'float-reference lowercases mixed-case keyword' => ['float-reference: CoLuMn', [
            ['type' => 'property', 'name' => 'float-reference', 'value' => 'column', 'important' => false],
        ]];
        yield 'float-reference rejects float keyword' => ['float-reference: left', [
            ['type' => 'undef', 'name' => 'float-reference', 'value' => 'left', 'important' => false],
        ]];
        yield 'float-reference rejects multiple keywords' => ['float-reference: inline page', [
            ['type' => 'undef', 'name' => 'float-reference', 'value' => 'inline page', 'important' => false],
        ]];
        yield 'float-reference rejects comment-split keyword' => ['float-reference: in/**/line', [
            ['type' => 'undef', 'name' => 'float-reference', 'value' => 'inline', 'important' => false],
        ]];
        yield 'float-defer accepts integer' => ['float-defer: -2', [
            ['type' => 'property', 'name' => 'float-defer', 'value' => '-2', 'important' => false],
        ]];
        yield 'float-defer accepts exponent integer' => ['float-defer: 1e2', [
            ['type' => 'property', 'name' => 'float-defer', 'value' => '100', 'important' => false],
        ]];
        yield 'float-defer accepts last' => ['float-defer: last', [
            ['type' => 'property', 'name' => 'float-defer', 'value' => 'last', 'important' => false],
        ]];
        yield 'float-defer accepts none' => ['float-defer: none', [
            ['type' => 'property', 'name' => 'float-defer', 'value' => 'none', 'important' => false],
        ]];
        yield 'float-defer accepts css-wide keyword' => ['float-defer: revert', [
            ['type' => 'property', 'name' => 'float-defer', 'value' => 'revert', 'important' => false],
        ]];
        yield 'float-defer lowercases mixed-case keyword' => ['float-defer: LaSt', [
            ['type' => 'property', 'name' => 'float-defer', 'value' => 'last', 'important' => false],
        ]];
        yield 'float-defer rejects decimal number' => ['float-defer: 1.5', [
            ['type' => 'undef', 'name' => 'float-defer', 'value' => '1.5', 'important' => false],
        ]];
        yield 'float-defer rejects percentage' => ['float-defer: 1%', [
            ['type' => 'undef', 'name' => 'float-defer', 'value' => '1%', 'important' => false],
        ]];
        yield 'float-defer rejects unknown keyword' => ['float-defer: auto', [
            ['type' => 'undef', 'name' => 'float-defer', 'value' => 'auto', 'important' => false],
        ]];
        yield 'float-defer rejects comment-split integer' => ['float-defer: 1/**/2', [
            ['type' => 'undef', 'name' => 'float-defer', 'value' => '12', 'important' => false],
        ]];
        yield 'float-offset accepts unitless zero' => ['float-offset: 0', [
            ['type' => 'property', 'name' => 'float-offset', 'value' => '0', 'important' => false],
        ]];
        yield 'float-offset accepts length' => ['float-offset: -1.5em', [
            ['type' => 'property', 'name' => 'float-offset', 'value' => '-1.5em', 'important' => false],
        ]];
        yield 'float-offset lowercases mixed-case length unit' => ['float-offset: 2PX', [
            ['type' => 'property', 'name' => 'float-offset', 'value' => '2px', 'important' => false],
        ]];
        yield 'float-offset serializes q unit with Lexbor uppercase spelling' => ['float-offset: 2q', [
            ['type' => 'property', 'name' => 'float-offset', 'value' => '2Q', 'important' => false],
        ]];
        yield 'float-offset accepts percentage' => ['float-offset: 25%', [
            ['type' => 'property', 'name' => 'float-offset', 'value' => '25%', 'important' => false],
        ]];
        yield 'float-offset accepts signed percentage' => ['float-offset: -10%', [
            ['type' => 'property', 'name' => 'float-offset', 'value' => '-10%', 'important' => false],
        ]];
        yield 'float-offset accepts css-wide keyword' => ['float-offset: unset', [
            ['type' => 'property', 'name' => 'float-offset', 'value' => 'unset', 'important' => false],
        ]];
        yield 'float-offset rejects auto' => ['float-offset: auto', [
            ['type' => 'undef', 'name' => 'float-offset', 'value' => 'auto', 'important' => false],
        ]];
        yield 'float-offset rejects nonzero number' => ['float-offset: 1', [
            ['type' => 'undef', 'name' => 'float-offset', 'value' => '1', 'important' => false],
        ]];
        yield 'float-offset rejects non-length dimension' => ['float-offset: 1deg', [
            ['type' => 'undef', 'name' => 'float-offset', 'value' => '1deg', 'important' => false],
        ]];
        yield 'float-offset rejects multiple values' => ['float-offset: 1px 2px', [
            ['type' => 'undef', 'name' => 'float-offset', 'value' => '1px 2px', 'important' => false],
        ]];
        yield 'float-offset rejects comment-split length' => ['float-offset: 1/**/px', [
            ['type' => 'undef', 'name' => 'float-offset', 'value' => '1px', 'important' => false],
        ]];
        yield 'float-offset keeps important flag' => ['float-offset: 5px !important', [
            ['type' => 'property', 'name' => 'float-offset', 'value' => '5px', 'important' => true],
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
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('keywordDeclarationProvider')]
    public function testKeywordDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('displayCommentProvider')]
    public function testDisplayCommentBoundaries(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('numericDeclarationProvider')]
    public function testNumericDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('minMaxSizeDeclarationProvider')]
    public function testMinMaxSizeDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('positionOffsetDeclarationProvider')]
    public function testPositionOffsetDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('textKeywordDeclarationProvider')]
    public function testTextKeywordDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('hangingPunctuationDeclarationProvider')]
    public function testHangingPunctuationDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('textDecorationDeclarationProvider')]
    public function testTextDecorationDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('verticalAlignDeclarationProvider')]
    public function testVerticalAlignDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('fontStyleDeclarationProvider')]
    public function testFontStyleDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('fontSizeDeclarationProvider')]
    public function testFontSizeDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('fontFamilyDeclarationProvider')]
    public function testFontFamilyDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('textIndentDeclarationProvider')]
    public function testTextIndentDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('textCombineUprightDeclarationProvider')]
    public function testTextCombineUprightDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('textTransformDeclarationProvider')]
    public function testTextTransformDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('flexKeywordDeclarationProvider')]
    public function testFlexKeywordDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('flexValueDeclarationProvider')]
    public function testFlexValueDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('flexFlowDeclarationProvider')]
    public function testFlexFlowDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('flexShorthandDeclarationProvider')]
    public function testFlexShorthandDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('floatDeclarationProvider')]
    public function testFloatDeclarations(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseList($css));
    }

    /**
     * @param list<array{type: string, name: string, value: string, important: bool}> $expected
     */
    #[DataProvider('floatSupportDeclarationProvider')]
    public function testFloatSupportDeclarations(string $css, array $expected): void
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

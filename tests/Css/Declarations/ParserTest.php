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
        yield 'keyword declaration accepts comment before important' => ['box-sizing: border-box/**/ ! /**/ important', [
            ['type' => 'property', 'name' => 'box-sizing', 'value' => 'border-box', 'important' => true],
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
            'unicode-bidi' => ['normal', 'embed', 'isolate', 'bidi-override', 'isolate-override', 'plaintext'],
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
        yield 'unicode-bidi rejects ltr' => ['unicode-bidi: ltr', [
            ['type' => 'undef', 'name' => 'unicode-bidi', 'value' => 'ltr', 'important' => false],
        ]];
        yield 'unicode-bidi rejects comment-split keyword' => ['unicode-bidi: isolate-/**/override', [
            ['type' => 'undef', 'name' => 'unicode-bidi', 'value' => 'isolate-override', 'important' => false],
        ]];
        yield 'writing-mode rejects horizontal' => ['writing-mode: horizontal', [
            ['type' => 'undef', 'name' => 'writing-mode', 'value' => 'horizontal', 'important' => false],
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

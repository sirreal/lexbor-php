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

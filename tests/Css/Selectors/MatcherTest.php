<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Selectors;

use Lexbor\Core\Status;
use Lexbor\Css\Selectors\Matcher;
use Lexbor\Dom\Element;
use Lexbor\Html\Document;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MatcherTest extends TestCase
{
    private const string HTML = '<!doctype html><body>'
        . "<div div='First' class='Strong Massive'>"
        . "    <p p=1><a a=1>a1</a></p>"
        . "    <p p=2><a a=2>a2</a></p>"
        . "    <p p=3><a a=3>a3</a></p>"
        . "    <p p=4><a a=4>a4</a></p>"
        . "    <p p=5><a a=5>a5</a></p>"
        . '</div>'
        . "<div div='Second' class='Massive Stupid'>"
        . "<p p=6 lang='en-GB'>"
        . '    <span id=s1 span=1>abc</span>'
        . '    <span id=s2 span=2>AbC</span>'
        . '    <span id=s3 span=3>ABC</span>'
        . '    <span id=s4 span=4>aBc</span>'
        . '    <span id=s5 span=5>ABc</span>'
        . '</p>'
        . "<p p=7 lang='ru'>"
        . '    <span span=6></span>'
        . '    <a a=6><span span=7></span></a>'
        . '    <a a=7><span span=8></span></a>'
        . '    <span span=9></span>'
        . '    <a a=8></a>'
        . '    <span span=10></span>'
        . '    <span test span=11></span>'
        . "    <span test='' span=12></span>"
        . '</p>'
        . '</div>'
        . '<main>'
        . '    <h2 h2=1 class=mark></h2>'
        . '    <h2 h2=2></h2>'
        . '    <h2 h2=3 class=mark></h2>'
        . '    <h2 h2=4 class=mark></h2>'
        . '    <h2 h2=5></h2>'
        . '    <h2 h2=6 class=mark></h2>'
        . '</main>'
        . '</body>';

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function upstreamSimpleSelectorProvider(): iterable
    {
        yield 'selectors match #1 type selector' => ['a', 'a', ['1', '2', '3', '4', '5', '6', '7', '8']];
        yield 'selectors match #2 id selector' => ['#s3', 'span', ['3']];
        yield 'selectors match #3 attribute presence selector' => ['[p]', 'p', ['1', '2', '3', '4', '5', '6', '7']];
        yield 'selectors match #4 exact attribute selector' => ["[p = '2']", 'p', ['2']];
        yield 'selectors match #5 exact attribute selector is case-sensitive by default' => ["[div = 'first']", 'div', []];
        yield 'selectors match #6 exact attribute selector with i modifier' => ["[div = 'first' i]", 'div', ['First']];
        yield 'selectors match #7 empty attribute equality selector' => ["[test = '']", 'span', ['11', '12']];
        yield 'selectors match #8 include selector is case-sensitive by default' => ["[class ~= 'massive']", 'div', []];
        yield 'selectors match #9 include selector' => ["[class ~= 'Massive']", 'div', ['First', 'Second']];
        yield 'selectors match #10 include selector with i modifier' => ["[class ~= 'massive' i]", 'div', ['First', 'Second']];
        yield 'selectors match #11 empty include selector does not match' => ["[test ~= '']", 'span', []];
        yield 'selectors match #12 dash selector uses HTML default case-insensitive lang' => ["[lang |= 'en']", 'p', ['6']];
        yield 'selectors match #13 dash selector folds default HTML lang case' => ["[lang |= 'eN']", 'p', ['6']];
        yield 'selectors match #14 dash selector with i modifier' => ["[lang |= 'eN' i]", 'p', ['6']];
        yield 'selectors match #15 dash selector exact value' => ["[lang |= 'ru']", 'p', ['7']];
        yield 'selectors match #16 tight dash selector exact value' => ["[lang|='ru']", 'p', ['7']];
        yield 'selectors match #17 spaced dash selector matcher exact value' => ["[lang |='ru']", 'p', ['7']];
        yield 'selectors match #18 spaced dash selector value exact value' => ["[lang|= 'ru']", 'p', ['7']];
        yield 'selectors match #19 dash selector rejects partial prefix' => ["[lang |= 'r']", 'p', []];
        yield 'selectors match #20 empty dash selector matches empty attributes' => ["[test |= '']", 'span', ['11', '12']];
        yield 'selectors match #21 prefix selector' => ["[div ^= 'Fir']", 'div', ['First']];
        yield 'selectors match #22 prefix selector is case-sensitive by default' => ["[div ^= 'fir']", 'div', []];
        yield 'selectors match #23 prefix selector with i modifier' => ["[div ^= 'fir' i]", 'div', ['First']];
        yield 'selectors match #24 prefix selector rejects non-prefix substring' => ["[div ^= 'irst']", 'div', []];
        yield 'selectors match #25 prefix selector exact full value' => ["[div ^= 'First']", 'div', ['First']];
        yield 'selectors match #26 empty prefix selector does not match' => ["[test ^= '']", 'span', []];
        yield 'selectors match #27 suffix selector' => ["[div $= 'irst']", 'div', ['First']];
        yield 'selectors match #28 one-byte suffix selector' => ["[div $= 't']", 'div', ['First']];
        yield 'selectors match #29 suffix selector exact full value' => ["[div $= 'First']", 'div', ['First']];
        yield 'selectors match #30 suffix selector is case-sensitive by default' => ["[div $= 'rSt']", 'div', []];
        yield 'selectors match #31 suffix selector with i modifier' => ["[div $= 'rSt' i]", 'div', ['First']];
        yield 'selectors match #32 suffix selector rejects non-suffix prefix' => ["[div $= 'Firs']", 'div', []];
        yield 'selectors match #33 empty suffix selector does not match' => ["[test $= '']", 'span', []];
        yield 'selectors match #34 substring selector' => ["[div *= 'irs']", 'div', ['First']];
        yield 'selectors match #35 substring selector is case-sensitive by default' => ["[div *= 'iRs']", 'div', []];
        yield 'selectors match #36 substring selector with i modifier' => ["[div *= 'iRs' i]", 'div', ['First']];
        yield 'selectors match #37 substring selector exact full value' => ["[div *= 'First']", 'div', ['First']];
        yield 'selectors match #38 empty substring selector does not match' => ["[div *= '']", 'div', []];
        yield 'selectors match #39 empty substring selector does not match empty attributes' => ["[test *= '']", 'span', []];
        yield 'compound selector matches all simple selectors' => ["span[id = 's4']#s4[span = '4']", 'span', ['4']];
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function upstreamCombinatorSelectorProvider(): iterable
    {
        yield 'selectors match descendant combinator' => ['div span', 'span', ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']];
        yield 'selectors match child combinator empty result' => ['div > span', 'span', []];
        yield 'selectors match child combinator' => ['p > span', 'span', ['1', '2', '3', '4', '5', '6', '9', '10', '11', '12']];
        yield 'selectors match chained child combinators' => ['div > p > a', 'a', ['1', '2', '3', '4', '5', '6', '7', '8']];
        yield 'selectors match subsequent-sibling combinator' => ['span ~ span', 'span', ['2', '3', '4', '5', '9', '10', '11', '12']];
        yield 'selectors match adjacent-sibling combinator' => ["p[p='2'] + p", 'p', ['3']];
        yield 'selectors match adjacent-sibling combinator empty result' => ["p[p='2'] + p[p='4']", 'p', []];
        yield 'selectors match descendant combinator with compounds' => ["p[p='6'][lang |= 'en'] span[id='s4']#s4[span='4']", 'span', ['4']];
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function upstreamFunctionalPseudoSelectorProvider(): iterable
    {
        yield 'selectors match has pseudo function' => ['p:has(a)', 'p', ['1', '2', '3', '4', '5', '7']];
        yield 'selectors match has relative next-sibling pseudo function' => ['span:has(+ a)', 'span', ['6', '9']];
        yield 'selectors match has relative sibling chain pseudo function' => ['div:has(+ div ~ main > h2[h2]) ~ div', 'div', ['Second']];
        yield 'selectors match has relative sibling chain empty result' => ['div:has(+ div ~ main > span[s2][s3]) ~ div', 'div', []];
        yield 'selectors match has descendant selector followed by child selector' => ['div:has(a) a', 'a', ['1', '2', '3', '4', '5', '6', '7', '8']];
        yield 'selectors match has followed by subsequent universal selector' => ['div:has(p) ~ *', 'tagName', ['div', 'main']];
        yield 'selectors match leading has with unmatched relative branch' => [':has(~ a.A, S)', 'tagName', []];
        yield 'selectors match is pseudo function' => ["p:is([p='2'], [p='5'])", 'p', ['2', '5']];
        yield 'selectors match current pseudo function' => ["p:current([p='2'], [p='5'])", 'p', ['2', '5']];
        yield 'selectors match leading current pseudo function with compound continuation' => [':current([p="2"])[p="2"]', 'p', ['2']];
        yield 'selectors match current pseudo function with outer EOF recovery' => ['p:current([p="2"])[p="2"', 'p', ['2']];
        yield 'selectors match current pseudo function with function EOF recovery' => ['p:current([p="2"]', 'p', ['2']];
        yield 'selectors match nested has and not pseudo functions' => ['div:has(p :not(span))', 'div', ['First', 'Second']];
        yield 'selectors match child nth not has compound' => ['div > :nth-child(2n+1):not(:has(a))', 'p', ['6']];
        yield 'selectors match descendant nth not has compound' => ['div > :nth-child(2n+1) :not(:has(a))', 'tagName', ['a', 'a', 'a', 'span', 'span', 'span', 'span', 'span']];
        yield 'selectors match forgiving has under child combinator' => ['div > :has(, a)', 'p', ['1', '2', '3', '4', '5', '7']];
        yield 'selectors match not pseudo function selector list' => [':not(span, div)', 'tagName', ['p', 'a', 'p', 'a', 'p', 'a', 'p', 'a', 'p', 'a', 'p', 'p', 'a', 'a', 'a', 'main', 'h2', 'h2', 'h2', 'h2', 'h2', 'h2']];
        yield 'selectors match where pseudo function' => ['p :where(span#s4)', 'span', ['4']];
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function upstreamNthPseudoSelectorProvider(): iterable
    {
        yield 'selectors match nth-child descendant pseudo function' => ['p[p="7"] span:nth-child(2n+1)', 'span', ['6', '7', '8', '11']];
        yield 'selectors match nth-child child pseudo function' => ['p[p="7"] > span:nth-child(2n+1)', 'span', ['6', '11']];
        yield 'selectors match nth-child of selector pseudo function' => ['p[p="7"] > span:nth-child(2n+1 of [test])', 'span', ['11']];
        yield 'selectors match nth-last-child descendant pseudo function' => ['p[p="7"] span:nth-last-child(2n+1)', 'span', ['7', '8', '9', '10', '12']];
        yield 'selectors match nth-last-child of selector pseudo function' => ['p[p="7"] > span:nth-last-child(2n+1 of [test])', 'span', ['12']];
        yield 'selectors match nth-of-type pseudo function' => ['p[p="7"] > span:nth-of-type(2n+1)', 'span', ['6', '10', '12']];
        yield 'selectors match nth-last-of-type pseudo function' => ['p[p="7"] > span:nth-last-of-type(2n+1)', 'span', ['6', '10', '12']];
        yield 'selectors match nth-child even of selector pseudo function' => ['main > h2:nth-child(even of .mark)', 'h2', ['3', '6']];
        yield 'selectors match nth-last-child even of selector pseudo function' => ['main > h2:nth-last-child(even of .mark)', 'h2', ['1', '4']];
        yield 'selectors match nth-child odd of selector pseudo function' => ['main > h2:nth-child(odd of .mark)', 'h2', ['1', '4']];
        yield 'selectors match nth-last-child odd of selector pseudo function' => ['main > h2:nth-last-child(odd of .mark)', 'h2', ['3', '6']];
        yield 'selectors match nth-child of complex selector pseudo function' => ['div p:nth-child(n+2 of div > p)', 'p', ['2', '3', '4', '5', '7']];
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function structuralPseudoSelectorProvider(): iterable
    {
        yield 'selectors match first-child pseudo class from match_first branch' => ["p[lang|='ru'] > span:first-child", 'span', ['6']];
        yield 'selectors match last-child pseudo class' => ["p[p='7'] > span:last-child", 'span', ['12']];
        yield 'selectors match only-child pseudo class' => ['div > p > a:only-child', 'a', ['1', '2', '3', '4', '5']];
        yield 'selectors match first-of-type pseudo class' => ["p[p='7'] > span:first-of-type", 'span', ['6']];
        yield 'selectors match last-of-type pseudo class' => ["p[p='7'] > a:last-of-type", 'a', ['8']];
        yield 'selectors match only-of-type pseudo class' => ["p[p='7'] > a > span:only-of-type", 'span', ['7', '8']];
        yield 'selectors match empty pseudo class' => ['a:empty', 'a', ['8']];
        yield 'selectors match root pseudo class' => [':root', 'tagName', ['body']];
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function upstreamLexborContainsPseudoSelectorProvider(): iterable
    {
        yield 'selectors match lexbor-contains string' => ['div p span:lexbor-contains("abc")', ['1']];
        yield 'selectors match lexbor-contains string i modifier' => ['div p span:lexbor-contains("abc" i)', ['1', '2', '3', '4', '5']];
        yield 'selectors match lexbor-contains tight string i modifier' => ['div p span:lexbor-contains("abc"i)', ['1', '2', '3', '4', '5']];
        yield 'selectors match lexbor-contains mixed-case string' => ['div p span:lexbor-contains("AbC")', ['2']];
        yield 'selectors match lexbor-contains missing string i modifier' => ['div p span:lexbor-contains("cba"i)', []];
        yield 'selectors match lexbor-contains spaced string i modifier' => ['div p span:lexbor-contains(  "abc"   i)', ['1', '2', '3', '4', '5']];
        yield 'selectors match lexbor-contains trailing-spaced string i modifier' => ['div p span:lexbor-contains(   "abc"   i   )', ['1', '2', '3', '4', '5']];
        yield 'selectors match lexbor-contains spaced mixed-case string' => ['div p span:lexbor-contains(   "AbC" )', ['2']];
        yield 'selectors match lexbor-contains ident i modifier' => ['div p span:lexbor-contains(abc i)', ['1', '2', '3', '4', '5']];
        yield 'selectors match lexbor-contains mixed-case ident' => ['div p span:lexbor-contains(AbC)', ['2']];
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function sourceBackedSimplePseudoSelectorProvider(): iterable
    {
        yield 'selectors match active pseudo class by attribute' => [
            '<!doctype html><body><div id=active active></div><div id=plain></div></body>',
            ':active',
            ['active'],
        ];
        yield 'selectors match focus pseudo class by attribute' => [
            '<!doctype html><body><input id=focus focus><input id=plain></body>',
            ':focus',
            ['focus'],
        ];
        yield 'selectors match hover pseudo class by attribute' => [
            '<!doctype html><body><div id=hover hover></div><div id=plain></div></body>',
            ':hover',
            ['hover'],
        ];
        yield 'selectors match any-link pseudo class on a area and map href' => [
            '<!doctype html><body><a id=a href=x></a><area id=area href=x><link id=link href=x><map id=map href=x></map><a id=plain></a></body>',
            ':any-link',
            ['a', 'area', 'map'],
        ];
        yield 'selectors match link pseudo class on a area and link href' => [
            '<!doctype html><body><a id=a href=x></a><area id=area href=x><link id=link href=x><map id=map href=x></map><a id=plain></a></body>',
            ':link',
            ['a', 'area', 'link'],
        ];
        yield 'selectors match checked pseudo class for checkbox radio option and unknown tags' => [
            '<!doctype html><body><input id=checkbox type=CHECKBOX checked><input id=radio type=radio checked><input id=text type=text checked><input id=no-type checked><option id=option selected></option><x-check id=custom checked></x-check></body>',
            ':checked',
            ['checkbox', 'radio', 'option', 'custom'],
        ];
        yield 'selectors match disabled pseudo class for Lexbor-supported disabled elements' => [
            '<!doctype html><body><button id=button disabled></button><input id=input disabled><select id=select disabled></select><textarea id=textarea disabled></textarea><div id=div disabled></div><x-control id=custom disabled></x-control></body>',
            ':disabled',
            ['button', 'input', 'select', 'textarea', 'custom'],
        ];
        yield 'selectors match disabled pseudo class with fieldset ancestor branch' => [
            '<!doctype html><body><fieldset><div id=fieldset-child disabled></div></fieldset><fieldset><legend></legend><div id=legend-child disabled></div></fieldset></body>',
            ':disabled',
            ['fieldset-child'],
        ];
        yield 'selectors match disabled pseudo class keeps walking fieldset ancestors' => [
            '<!doctype html><body><fieldset><div><fieldset><legend></legend><div id=nested disabled></div></fieldset></div></fieldset><fieldset><legend></legend><div><fieldset><legend></legend><div id=unmatched disabled></div></fieldset></div></fieldset></body>',
            ':disabled',
            ['nested'],
        ];
        yield 'selectors match enabled pseudo class as inverse of Lexbor disabled check' => [
            '<!doctype html><body><button id=enabled></button><button id=disabled disabled></button><div id=div-disabled disabled></div><x-control id=custom-disabled disabled></x-control></body>',
            ':enabled',
            ['enabled', 'div-disabled'],
        ];
        yield 'selectors match enabled pseudo class after fieldset ancestor walk' => [
            '<!doctype html><body><fieldset><div><fieldset><legend></legend><div id=nested disabled></div></fieldset></div></fieldset><fieldset><legend></legend><div><fieldset><legend></legend><div id=unmatched disabled></div></fieldset></div></fieldset></body>',
            'div[id]:enabled',
            ['unmatched'],
        ];
        yield 'selectors match optional pseudo class on unrequired controls' => [
            '<!doctype html><body><input id=input><select id=select></select><textarea id=textarea></textarea><input id=required required><button id=button></button></body>',
            ':optional',
            ['input', 'select', 'textarea'],
        ];
        yield 'selectors match required pseudo class on required controls' => [
            '<!doctype html><body><input id=input required><select id=select required></select><textarea id=textarea required></textarea><button id=button required></button></body>',
            ':required',
            ['input', 'select', 'textarea'],
        ];
        yield 'selectors match placeholder-shown pseudo class on input and textarea' => [
            '<!doctype html><body><input id=input placeholder=hint><textarea id=textarea placeholder=hint></textarea><div id=div placeholder=hint></div></body>',
            ':placeholder-shown',
            ['input', 'textarea'],
        ];
        yield 'selectors match read-write pseudo class on writable input and textarea' => [
            '<!doctype html><body><input id=input><input id=readonly readonly><input id=disabled disabled><textarea id=textarea></textarea><textarea id=textarea-readonly readonly></textarea><div id=div></div></body>',
            ':read-write',
            ['input', 'textarea'],
        ];
        yield 'selectors match read-only pseudo class as inverse of read-write' => [
            '<!doctype html><body><input id=input><input id=readonly readonly><input id=disabled disabled><textarea id=textarea-readonly readonly></textarea><div id=div></div></body>',
            ':read-only',
            ['readonly', 'disabled', 'textarea-readonly', 'div'],
        ];
        yield 'selectors match blank pseudo class for empty whitespace and comment-only descendants' => [
            "<!doctype html><body><div id=empty></div><div id=comment><!--x--></div><div id=space> \n\t</div><div id=nested><span></span></div><div id=text>x</div></body>",
            'div:blank',
            ['empty', 'comment', 'space'],
        ];
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function upstreamMatchingEdgeSelectorProvider(): iterable
    {
        $quirksCaseHtml = "<body><div class='Test'><span id='Foo'></span></div></body>";
        $noQuirksCaseHtml = "<!DOCTYPE html><body><div class='Test'><span id='Foo'></span></div></body>";

        yield 'selectors match id class case #1 no-quirks class is case-sensitive' => [$noQuirksCaseHtml, '.test', 0];
        yield 'selectors match id class case #2 no-quirks id is case-sensitive' => [$noQuirksCaseHtml, '#foo', 0];
        yield 'selectors match id class case #3 no-quirks class exact case' => [$noQuirksCaseHtml, '.Test', 1];
        yield 'selectors match id class case #4 no-quirks id exact case' => [$noQuirksCaseHtml, '#Foo', 1];
        yield 'selectors match id class case #5 quirks class is case-insensitive' => [$quirksCaseHtml, '.test', 1];
        yield 'selectors match id class case #6 quirks id is case-insensitive' => [$quirksCaseHtml, '#foo', 1];

        $nonAsciiClassHtml = '<!DOCTYPE html>'
            . "<i class='\xC3\x9C" . "ber'></i>"
            . "<i class='a\xC2\xB7" . "b'></i>"
            . "<i class='\xCD\xBD" . "x'></i>";

        yield 'selectors match non-ascii class #1 U+00DC' => [$nonAsciiClassHtml, ".\xC3\x9C" . 'ber', 1];
        yield 'selectors match non-ascii class #2 middle dot' => [$nonAsciiClassHtml, ".a\xC2\xB7" . 'b', 1];
        yield 'selectors match non-ascii class #3 Greek' => [$nonAsciiClassHtml, ".\xCD\xBD" . 'x', 1];

        yield 'selectors match include empty #1 space attribute' => ["<!DOCTYPE html><i x=' '></i>", "[x~='']", 0];
        yield 'selectors match include empty #2 tab entity attribute' => ["<!DOCTYPE html><i x='&#9;'></i>", "[x~='']", 0];
        yield 'selectors match include empty #3 leading space token' => ["<!DOCTYPE html><i x=' a'></i>", "[x~='']", 0];
        yield 'selectors match include empty #4 double-space tokens' => ["<!DOCTYPE html><i x='a  b'></i>", "[x~='']", 0];
        yield 'selectors match include empty #5 trailing double-space token' => ["<!DOCTYPE html><i x='a  '></i>", "[x~='']", 0];
        yield 'selectors match include empty #6 trailing space token' => ["<!DOCTYPE html><i x='a '></i>", "[x~='']", 0];
        yield 'selectors match include empty #7 empty attribute' => ["<!DOCTYPE html><i x=''></i>", "[x~='']", 0];
        yield 'selectors match include empty #8 two tokens with empty needle' => ["<!DOCTYPE html><i x='a b'></i>", "[x~='']", 0];
        yield 'selectors match include empty #9 spaced needle does not match token list' => ["<!DOCTYPE html><i x='a b'></i>", "[x~='a b']", 0];
        yield 'selectors match include empty #10 tabbed needle does not match token list' => ["<!DOCTYPE html><i x='a\tb'></i>", "[x~='a\tb']", 0];
        yield 'selectors match include empty #11 first token' => ["<!DOCTYPE html><i x='a b'></i>", '[x~=a]', 1];
        yield 'selectors match include empty #12 second token' => ["<!DOCTYPE html><i x='a b'></i>", '[x~=b]', 1];
        yield 'selectors match include empty #13 tab separator' => ["<!DOCTYPE html><i x='a\tb'></i>", '[x~=b]', 1];
        yield 'selectors match include empty #14 newline separator' => ["<!DOCTYPE html><i x='a\nb'></i>", '[x~=b]', 1];
        yield 'selectors match include empty #15 form-feed separator' => ["<!DOCTYPE html><i x='a\fb'></i>", '[x~=b]', 1];
        yield 'selectors match include empty #16 carriage-return separator' => ["<!DOCTYPE html><i x='a\rb'></i>", '[x~=b]', 1];

        yield 'selectors match EOF attribute #1 presence' => ["<!DOCTYPE html><div att='val'></div>", '[att', 1];
        yield 'selectors match EOF attribute #2 exact unquoted' => ["<!DOCTYPE html><div att='val'></div>", '[att=val', 1];
        yield 'selectors match EOF attribute #3 exact quoted with space' => ["<!DOCTYPE html><div att='a b'></div>", '[att="a b', 1];
        yield 'selectors match EOF attribute #4 exact i modifier' => ["<!DOCTYPE html><div att='VAL'></div>", '[att=val i', 1];
        yield 'selectors match EOF attribute #5 compound presence' => ["<!DOCTYPE html><div att='val'></div><span att='val'></span>", 'div[att', 1];
        yield 'selectors match EOF attribute #6 nested not' => ["<!DOCTYPE html><span class='cls'></span><span></span>", 'span:not([class', 1];

        $htmlCaseAttributeHtml = '<!DOCTYPE html>'
            . "<a rel='NOFOLLOW' data-html='yes' data-x='ABC'></a>"
            . "<a rel='nofollow' data-html='yes'></a>"
            . "<input type='text'>"
            . "<form accept-charset='utf-8'></form>"
            . "<svg><a rel='NOFOLLOW' data-svg='yes'></a></svg>";

        yield 'selectors match HTML case-insensitive attribute #1 rel lowercase' => [$htmlCaseAttributeHtml, '[rel=nofollow][data-html=yes]', 2];
        yield 'selectors match HTML case-insensitive attribute #2 rel uppercase' => [$htmlCaseAttributeHtml, '[rel=NOFOLLOW][data-html=yes]', 2];
        yield 'selectors match HTML case-insensitive attribute #3 explicit i modifier includes SVG' => [$htmlCaseAttributeHtml, '[rel=nofollow i]', 3];
        yield 'selectors match HTML case-insensitive attribute #4 explicit s modifier' => [$htmlCaseAttributeHtml, '[rel=nofollow s]', 1];
        yield 'selectors match HTML case-insensitive attribute #5 data attribute remains sensitive' => [$htmlCaseAttributeHtml, '[data-x=abc]', 0];
        yield 'selectors match HTML case-insensitive attribute #6 data attribute exact' => [$htmlCaseAttributeHtml, '[data-x=ABC]', 1];
        yield 'selectors match HTML case-insensitive attribute #7 type default insensitive' => [$htmlCaseAttributeHtml, '[type=TEXT]', 1];
        yield 'selectors match HTML case-insensitive attribute #8 type explicit sensitive' => [$htmlCaseAttributeHtml, '[type=TEXT s]', 0];
        yield 'selectors match HTML case-insensitive attribute #9 accept-charset default insensitive' => [$htmlCaseAttributeHtml, '[accept-charset=UTF-8]', 1];
        yield 'selectors match HTML case-insensitive attribute #10 accept-charset explicit sensitive' => [$htmlCaseAttributeHtml, '[accept-charset=UTF-8 s]', 0];
        yield 'selectors match HTML case-insensitive attribute #11 SVG rel remains sensitive lowercase' => [$htmlCaseAttributeHtml, '[rel=nofollow][data-svg=yes]', 0];
        yield 'selectors match HTML case-insensitive attribute #12 SVG rel remains sensitive uppercase' => [$htmlCaseAttributeHtml, '[rel=NOFOLLOW][data-svg=yes]', 1];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('upstreamSimpleSelectorProvider')]
    public function testFindMatchesUpstreamSimpleSelectorFoundation(string $selector, string $labelAttribute, array $expected): void
    {
        $document = $this->fixtureDocument();

        self::assertSame(
            $expected,
            self::attributeValues((new Matcher())->find($document->bodyElement(), $selector), $labelAttribute),
        );
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('upstreamCombinatorSelectorProvider')]
    public function testFindMatchesUpstreamCombinatorSelectorFoundation(string $selector, string $labelAttribute, array $expected): void
    {
        $document = $this->fixtureDocument();

        self::assertSame(
            $expected,
            self::attributeValues((new Matcher())->find($document->bodyElement(), $selector), $labelAttribute),
        );
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('upstreamFunctionalPseudoSelectorProvider')]
    public function testFindMatchesUpstreamFunctionalPseudoSelectorFoundation(string $selector, string $labelAttribute, array $expected): void
    {
        $document = $this->fixtureDocument();

        self::assertSame(
            $expected,
            self::attributeValues((new Matcher())->find($document->bodyElement(), $selector), $labelAttribute),
        );
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('upstreamNthPseudoSelectorProvider')]
    public function testFindMatchesUpstreamNthPseudoSelectorFoundation(string $selector, string $labelAttribute, array $expected): void
    {
        $document = $this->fixtureDocument();

        self::assertSame(
            $expected,
            self::attributeValues((new Matcher())->find($document->bodyElement(), $selector), $labelAttribute),
        );
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('structuralPseudoSelectorProvider')]
    public function testFindMatchesStructuralPseudoSelectorFoundation(string $selector, string $labelAttribute, array $expected): void
    {
        $document = $this->fixtureDocument();

        self::assertSame(
            $expected,
            self::attributeValues((new Matcher())->find($document, $selector), $labelAttribute),
        );
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('upstreamLexborContainsPseudoSelectorProvider')]
    public function testFindMatchesUpstreamLexborContainsPseudoSelector(string $selector, array $expected): void
    {
        $document = $this->fixtureDocument();

        self::assertSame(
            $expected,
            self::attributeValues((new Matcher())->find($document->bodyElement(), $selector), 'span'),
        );
    }

    public function testLexborContainsMatchesOnlyImmediateTextChildren(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<!doctype html><body><div id=nested><span>abc</span></div><div id=split>a<span></span>b</div><div id=direct>xyz</div></body>'),
        );

        $matcher = new Matcher();

        self::assertSame(
            [],
            self::attributeValues($matcher->find($document->bodyElement(), 'div:lexbor-contains(abc)'), 'id'),
        );
        self::assertSame(
            [],
            self::attributeValues($matcher->find($document->bodyElement(), 'div:lexbor-contains(ab)'), 'id'),
        );

        $emptyNeedleDocument = new Document();
        self::assertSame(
            Status::Ok,
            $emptyNeedleDocument->parse('<!doctype html><body><div id=empty></div><div id=comment><!--x--></div><div id=nested><span></span></div><div id=direct>x</div></body>'),
        );

        self::assertSame(
            ['direct'],
            self::attributeValues($matcher->find($emptyNeedleDocument->bodyElement(), 'div:lexbor-contains("")'), 'id'),
        );
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('sourceBackedSimplePseudoSelectorProvider')]
    public function testFindMatchesSourceBackedSimplePseudoSelector(string $html, string $selector, array $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame(
            $expected,
            self::attributeValues((new Matcher())->find($document->bodyElement(), $selector), 'id'),
        );
    }

    #[DataProvider('upstreamMatchingEdgeSelectorProvider')]
    public function testFindMatchesUpstreamSelectorEdgeCases(string $html, string $selector, int $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertCount($expected, (new Matcher())->find($document->bodyElement(), $selector));
    }

    public function testRootPseudoClassUsesFirstDocumentElement(): void
    {
        $document = $this->fixtureDocument();

        self::assertNotNull($document->documentType());
        self::assertSame($document->bodyElement(), $document->documentType()->next);
        self::assertSame(
            ['body'],
            self::attributeValues((new Matcher())->find($document, ':root'), 'tagName'),
        );
    }

    public function testLegacyPublicDoctypeStillEnablesQuirksClassMatching(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"><body><div class="Test"></div></body>'),
        );

        self::assertTrue($document->isQuirksMode());
        self::assertCount(1, (new Matcher())->find($document->bodyElement(), '.test'));
    }

    public function testFindMatchesUniversalSelectorInDocumentOrder(): void
    {
        $document = $this->fixtureDocument();

        $matches = (new Matcher())->find($document->bodyElement(), '*');

        self::assertCount(36, $matches);
        self::assertSame('div', $matches[0]->tagName);
        self::assertSame('h2', $matches[35]->tagName);
    }

    public function testFindSupportsLexborMatchRootOption(): void
    {
        $document = $this->fixtureDocument();
        $matcher = new Matcher();
        $firstDiv = $matcher->find($document->bodyElement(), "div[div='First']")[0] ?? null;

        self::assertInstanceOf(Element::class, $firstDiv);
        self::assertSame([], $matcher->find($firstDiv, "div[div='First']"));
        self::assertSame(
            ['First'],
            self::attributeValues($matcher->find($firstDiv, "div[div='First']", Matcher::OPT_MATCH_ROOT), 'div'),
        );
    }

    public function testFindMatchRootOptionStillVisitsDescendants(): void
    {
        $document = $this->fixtureDocument();

        self::assertSame(
            ['First', 'Second'],
            self::attributeValues((new Matcher())->find($document->bodyElement(), 'div', Matcher::OPT_MATCH_ROOT), 'div'),
        );
    }

    public function testFindSupportsLexborMatchFirstOption(): void
    {
        $document = $this->fixtureDocument();
        $selector = "p[lang|='ru'] > span:first-child, [p='7'] [span='6']";

        self::assertSame(
            ['6'],
            self::attributeValues((new Matcher())->find($document->bodyElement(), $selector, Matcher::OPT_MATCH_FIRST), 'span'),
        );
    }

    public function testFindDefaultReportsEachMatchingSelectorListBranch(): void
    {
        $document = $this->fixtureDocument();
        $selector = "p[lang|='ru'] > span:first-child, [p='7'] [span='6']";

        self::assertSame(
            ['6', '6'],
            self::attributeValues((new Matcher())->find($document->bodyElement(), $selector), 'span'),
        );
    }

    public function testFindSupportsLexborDeepNestedNotSelector(): void
    {
        $document = $this->fixtureDocument();
        $depth = 4096;
        $selector = str_repeat(':not(', $depth) . 'div' . str_repeat(')', $depth);

        self::assertSame(
            ['First', 'Second'],
            self::attributeValues((new Matcher())->find($document, $selector), 'div'),
        );
    }

    public function testFindPreservesNonPureNestedNotSelectorChain(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<!doctype html><body><div id=div></div><span id=span></span><a id=a></a></body>'));

        self::assertSame(
            ['div', 'span'],
            self::attributeValues((new Matcher())->find($document->bodyElement(), ':not(:not(div):not(span))'), 'id'),
        );
    }

    public function testMatchesSupportsSelectorLists(): void
    {
        $document = $this->fixtureDocument();
        $span = $document->byId('s4');

        self::assertInstanceOf(Element::class, $span);
        self::assertTrue((new Matcher())->matches($span, 'a, span[id=s4]'));
        self::assertFalse((new Matcher())->matches($span, 'a, [span="5"]'));
    }

    public function testRepeatedIdSelectorsMustAllMatch(): void
    {
        $document = $this->fixtureDocument();
        $span = $document->byId('s4');
        $matcher = new Matcher();

        self::assertInstanceOf(Element::class, $span);
        self::assertTrue($matcher->matches($span, '#s4#s4'));
        self::assertFalse($matcher->matches($span, '#s4#s5'));
        self::assertSame([], $matcher->find($document->bodyElement(), '#s4#s5'));
    }

    public function testMatchesSupportsCombinators(): void
    {
        $document = $this->fixtureDocument();
        $span = $document->byId('s4');
        $matcher = new Matcher();

        self::assertInstanceOf(Element::class, $span);
        self::assertTrue($matcher->matches($span, "p[p='6'] > span"));
        self::assertFalse($matcher->matches($span, "p[p='7'] > span"));
    }

    public function testMatchesSupportsFunctionalPseudos(): void
    {
        $document = $this->fixtureDocument();
        $span = $document->byId('s4');
        $matcher = new Matcher();

        self::assertInstanceOf(Element::class, $span);
        self::assertTrue($matcher->matches($span, 'span:is(a, span):not([span="5"])'));
        self::assertFalse($matcher->matches($span, ':not(span, div)'));
        self::assertFalse($matcher->matches($span, ':not(div,,span)'));
        self::assertSame([], $matcher->find($document->bodyElement(), 'div:has(div p)'));
        self::assertSame([], $matcher->find($document->bodyElement(), 'p:has(p > a)'));
        self::assertSame([], $matcher->find($document->bodyElement(), 'div:has(:where(div p))'));
        self::assertSame([], $matcher->find($document->bodyElement(), 'div:has(:not(:not(div p)))'));
        self::assertSame([], $matcher->find($document->bodyElement(), 'p:has(:is(p > a))'));
        self::assertSame(['First'], self::attributeValues($matcher->find($document->bodyElement(), 'div:has(> p[p="1"])'), 'div'));
        self::assertSame(['2', '5'], self::attributeValues($matcher->find($document->bodyElement(), 'p:is([p="2"], 1%, [p="5"])'), 'p'));
        self::assertSame(['2', '5'], self::attributeValues($matcher->find($document->bodyElement(), 'p:where([p="2"], 1%, [p="5"])'), 'p'));
        self::assertSame(['p', 'p', 'a'], self::attributeValues($matcher->find($document->bodyElement(), 'p:is([p="2"], 1%, [p="5"]), a[a="6"]'), 'tagName'));
        self::assertSame(['3'], self::attributeValues($matcher->find($document->bodyElement(), 'main > h2:nth-child(0n+3)'), 'h2'));
        self::assertSame(['5'], self::attributeValues($matcher->find($document->bodyElement(), 'main > h2:nth-last-child(0n+2)'), 'h2'));
        self::assertSame(['3'], self::attributeValues($matcher->find($document->bodyElement(), 'main > h2:nth-child(3)'), 'h2'));
        self::assertSame(['5'], self::attributeValues($matcher->find($document->bodyElement(), 'main > h2:nth-last-child(2)'), 'h2'));

        $scopeDocument = new Document();
        self::assertSame(Status::Ok, $scopeDocument->parse('<!doctype html><body><div id=root><p id=a class=x></p><p id=b></p><p id=p3></p></div></body>'));

        self::assertSame(
            ['a', 'p3'],
            self::attributeValues($matcher->find($scopeDocument->bodyElement(), 'p:nth-child(odd of div > p)'), 'id'),
        );
        self::assertSame(
            ['a', 'p3'],
            self::attributeValues($matcher->find($scopeDocument->bodyElement(), 'p:nth-child(n of .x, #p3)'), 'id'),
        );
        self::assertSame(
            ['root'],
            self::attributeValues($matcher->find($scopeDocument->bodyElement(), 'div:has(:nth-child(odd of div > p))'), 'id'),
        );
    }

    public function testInvalidFunctionalPseudoDoesNotFallThroughToLaterSimplePseudo(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<!doctype html><body><div div=first></div><div div=second></div></body>'));

        self::assertSame(
            [],
            (new Matcher())->find($document->bodyElement(), 'div:nth-child(2n+2 of div || #id):empty'),
        );
    }

    public function testUnsupportedSimplePseudoDoesNotMatchThroughNegation(): void
    {
        $document = $this->fixtureDocument();
        $matcher = new Matcher();

        self::assertSame([], $matcher->find($document->bodyElement(), 'div:target'));
        self::assertSame([], $matcher->find($document->bodyElement(), 'div:not(:target)'));
    }

    public function testFindUsesRecoveredSelectorForInvalidForgivingHasBranch(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<!doctype html><body><div div=match><span id=hash></span></div><div div=miss></div></body>'));

        self::assertSame(
            ['match'],
            self::attributeValues(
                (new Matcher())->find($document->bodyElement(), 'div:has(:not(div, {([([{}])])}, .class), #hash)'),
                'div',
            ),
        );
    }

    public function testFindPreservesComplexSelectorPrefixAfterForgivingRecovery(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<!doctype html><body><div div=parent><p p=child><a></a></p></div><p p=outer><a></a></p></body>'),
        );

        self::assertSame(
            ['child'],
            self::attributeValues(
                (new Matcher())->find($document->bodyElement(), 'div > p:has(, a)'),
                'p',
            ),
        );
    }

    public function testFindPreservesComplexSelectorSuffixAfterForgivingRecovery(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<!doctype html><body><div><p p=parent><a a=child></a></p></div><p p=outer><a a=outer></a></p></body>'),
        );

        self::assertSame(
            ['child'],
            self::attributeValues(
                (new Matcher())->find($document->bodyElement(), 'div > p:has(, a) > a'),
                'a',
            ),
        );
    }

    private function fixtureDocument(): Document
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse(self::HTML));
        self::assertFalse($document->isQuirksMode());

        return $document;
    }

    /**
     * @param list<Element> $elements
     * @return list<string>
     */
    private static function attributeValues(array $elements, string $name): array
    {
        return array_map(
            static fn (Element $element): string => $name === 'tagName' ? $element->tagName : ($element->getAttribute($name) ?? ''),
            $elements,
        );
    }
}

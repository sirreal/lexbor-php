<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Selectors;

use Lexbor\Core\Status;
use Lexbor\Css\Selectors\Matcher;
use Lexbor\Dom\Element;
use Lexbor\Dom\ExceptionCode;
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
        yield 'selectors match #16 dash selector rejects partial prefix' => ["[lang |= 'r']", 'p', []];
        yield 'selectors match #17 empty dash selector matches empty attributes' => ["[test |= '']", 'span', ['11', '12']];
        yield 'selectors match #18 prefix selector' => ["[div ^= 'Fir']", 'div', ['First']];
        yield 'selectors match #19 prefix selector is case-sensitive by default' => ["[div ^= 'fir']", 'div', []];
        yield 'selectors match #20 prefix selector with i modifier' => ["[div ^= 'fir' i]", 'div', ['First']];
        yield 'selectors match #21 empty prefix selector does not match' => ["[test ^= '']", 'span', []];
        yield 'selectors match #22 suffix selector' => ["[div $= 'irst']", 'div', ['First']];
        yield 'selectors match #23 suffix selector with i modifier' => ["[div $= 'rSt' i]", 'div', ['First']];
        yield 'selectors match #24 empty suffix selector does not match' => ["[test $= '']", 'span', []];
        yield 'selectors match #25 substring selector' => ["[div *= 'irs']", 'div', ['First']];
        yield 'selectors match #26 substring selector is case-sensitive by default' => ["[div *= 'iRs']", 'div', []];
        yield 'selectors match #27 substring selector with i modifier' => ["[div *= 'iRs' i]", 'div', ['First']];
        yield 'selectors match #28 empty substring selector does not match' => ["[div *= '']", 'div', []];
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
        yield 'selectors match is pseudo function' => ["p:is([p='2'], [p='5'])", 'p', ['2', '5']];
        yield 'selectors match nested has and not pseudo functions' => ['div:has(p :not(span))', 'div', ['First', 'Second']];
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
        $doctype = $document->createDocumentType('html');

        self::assertNotNull($doctype);
        self::assertSame(ExceptionCode::Ok, $document->insertBeforeSpec($doctype, $document->bodyElement()));
        self::assertSame(
            ['body'],
            self::attributeValues((new Matcher())->find($document, ':root'), 'tagName'),
        );
    }

    public function testFindMatchesUniversalSelectorInDocumentOrder(): void
    {
        $document = $this->fixtureDocument();

        $matches = (new Matcher())->find($document->bodyElement(), '*');

        self::assertCount(36, $matches);
        self::assertSame('div', $matches[0]->tagName);
        self::assertSame('h2', $matches[35]->tagName);
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

        self::assertSame([], $matcher->find($document->bodyElement(), 'div:hover'));
        self::assertSame([], $matcher->find($document->bodyElement(), 'div:not(:hover)'));
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

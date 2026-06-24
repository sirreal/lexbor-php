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
            static fn (Element $element): string => $element->getAttribute($name) ?? '',
            $elements,
        );
    }
}

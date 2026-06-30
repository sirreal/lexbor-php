<?php

declare(strict_types=1);

namespace Lexbor\Tests\Style;

use Lexbor\Core\Status;
use Lexbor\Dom\Element;
use Lexbor\Html\Document;
use Lexbor\Style\StyleEngine;
use PHPUnit\Framework\TestCase;

final class ElementStyleStepsTest extends TestCase
{
    private StyleEngine $style;

    protected function setUp(): void
    {
        $this->style = new StyleEngine();
    }

    public function testInlineStyleNotConnected(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        $div->setAttribute('style', 'color: red');

        $this->assertStyle('color: red', $div);
    }

    public function testConnectElementStylesRecalc(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $div = $this->targetDiv($document, 'color: red');

        $this->assertStyle('color: red', $div);

        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; width: 100px', $div);
    }

    public function testDisconnectElementKeepsInlineAndReconnects(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'color: red');

        $document->bodyElement()->appendChild($div);
        $this->assertStyle('color: red; width: 100px', $div);

        $div->remove();
        $this->assertStyle('color: red', $div);

        $document->bodyElement()->appendChild($div);
        $this->assertStyle('color: red; width: 100px', $div);
    }

    public function testRemoveAndReaddStyleAttribute(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'color: red');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; width: 100px', $div);

        $div->removeAttribute('style');
        $this->assertStyle('width: 100px', $div);

        $div->setAttribute('style', 'color: red');
        $this->assertStyle('color: red; width: 100px', $div);
    }

    public function testChangeStyleAttributeValue(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'color: red');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; width: 100px', $div);

        $div->setAttribute('style', 'height: 50px');

        $this->assertStyle('height: 50px; width: 100px', $div);
    }

    public function testInlineOverridesStylesheet(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'width: 200px');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 200px', $div);

        $div->remove();
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 200px', $div);
    }

    public function testStylesheetSpecificityBeatsLaterSourceOrder(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, '#target {width: 100px} div {width: 50px}');
        $div = $document->createElement('div');
        $div->setAttribute('id', 'target');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 100px', $div);
    }

    public function testStylesheetSpecificityComparesLexicographically(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet(
            $document,
            '#target {width: 100px} ' . str_repeat('[data-x]', 1001) . ' {width: 200px}',
        );
        $div = $document->createElement('div');
        $div->setAttribute('id', 'target');
        $div->setAttribute('data-x', '1');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 100px', $div);
    }

    public function testSelectorListUsesMatchingBranchSpecificity(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div, #target {width: 100px} .target {width: 50px}');
        $div = $document->createElement('div');
        $div->setAttribute('id', 'target');
        $div->setAttribute('class', 'target');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 100px', $div);
    }

    public function testForgivingPseudoSpecificityUsesNormalizedSelector(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet(
            $document,
            '.target {width: 200px} :is(div, :unknown-pseudo) {width: 100px}',
        );
        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 200px', $div);
    }

    public function testInvalidSelectorListBranchInvalidatesStylesheetRule(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div, :unknown-pseudo {width: 100px}');
        $div = $document->createElement('div');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('', $div);
    }

    public function testStylesheetDeclarationsUseRawLexborValidation(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: /**/ 100px}');
        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('', $div);
    }

    public function testInlineCustomDeclarationsSerialize(): void
    {
        $document = $this->document();
        $div = $document->createElement('div');
        $div->setAttribute('style', 'myprop: 1px; color: red');

        $this->assertStyle('color: red; myprop: 1px', $div);
    }

    public function testInlineCustomDeclarationsKeepCaseSensitiveNames(): void
    {
        $document = $this->document();
        $div = $document->createElement('div');
        $div->setAttribute('style', '--Foo: 1px; --foo: 2px');

        $this->assertStyle('--Foo: 1px; --foo: 2px', $div);
    }

    public function testStylesheetCustomDeclarationsSerialize(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {myprop: 1px; color: red}');
        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; myprop: 1px', $div);
    }

    public function testStylesheetCustomDeclarationsKeepCaseSensitiveNames(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {--Foo: 1px; --foo: 2px}');
        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('--Foo: 1px; --foo: 2px', $div);
    }

    public function testRemoveInlineRevealsStylesheet(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'width: 200px');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 200px', $div);

        $div->removeAttribute('style');

        $this->assertStyle('width: 100px', $div);
    }

    public function testNoStylesAtAll(): void
    {
        $document = $this->document();
        $div = $document->createElement('div');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('', $div);
    }

    public function testMultipleInlineProperties(): void
    {
        $document = $this->document();
        $div = $document->createElement('div');
        $div->setAttribute('style', 'width: 100px; height: 50px; color: blue');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: blue; height: 50px; width: 100px', $div);
    }

    public function testParentChildDisconnect(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.parent {width: 100px} span.child {height: 50px}');

        $parent = $document->createElement('div');
        $parent->setAttribute('class', 'parent');
        $parent->setAttribute('style', 'color: red');
        $child = $document->createElement('span');
        $child->setAttribute('class', 'child');
        $child->setAttribute('style', 'font-size: 14px');
        $parent->appendChild($child);
        $document->bodyElement()->appendChild($parent);

        $this->assertStyle('color: red; width: 100px', $parent);
        $this->assertStyle('font-size: 14px; height: 50px', $child);

        $parent->remove();

        $this->assertStyle('color: red', $parent);
        $this->assertStyle('font-size: 14px', $child);

        $document->bodyElement()->appendChild($parent);

        $this->assertStyle('color: red; width: 100px', $parent);
        $this->assertStyle('font-size: 14px; height: 50px', $child);
    }

    public function testChangeStyleBeforeConnect(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'color: red');
        $div->setAttribute('style', 'color: blue; margin: 5px');

        $this->assertStyle('color: blue; margin: 5px', $div);

        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: blue; margin: 5px; width: 100px', $div);
    }

    public function testRemoveStyleAttrDisconnected(): void
    {
        $document = $this->document();
        $div = $document->createElement('div');
        $div->setAttribute('style', 'color: red');

        $this->assertStyle('color: red', $div);

        $div->removeAttribute('style');

        $this->assertStyle('', $div);
    }

    public function testParsedElementWithInlineAndStylesheet(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse("<div class=target style='color: red'>text</div>"));
        $this->style->attachStylesheet($document, 'div.target {width: 100px; height: 50px}');

        $div = $document->elementsByTagName('div')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        $this->assertStyle('color: red; height: 50px; width: 100px', $div);
    }

    public function testMoveElementBetweenParents(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.a span.item {width: 100px} div.b span.item {height: 200px}');

        $divA = $document->createElement('div');
        $divA->setAttribute('class', 'a');
        $divB = $document->createElement('div');
        $divB->setAttribute('class', 'b');
        $document->bodyElement()->appendChild($divA);
        $document->bodyElement()->appendChild($divB);

        $span = $document->createElement('span');
        $span->setAttribute('class', 'item');
        $span->setAttribute('style', 'color: red');
        $divA->appendChild($span);

        $this->assertStyle('color: red; width: 100px', $span);

        $span->remove();
        $divB->appendChild($span);

        $this->assertStyle('color: red; height: 200px', $span);
    }

    public function testFullLifecycle(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'color: red');

        $this->assertStyle('color: red', $div);

        $document->bodyElement()->appendChild($div);
        $this->assertStyle('color: red; width: 100px', $div);

        $div->setAttribute('style', 'color: blue; margin: 10px');
        $this->assertStyle('color: blue; margin: 10px; width: 100px', $div);

        $div->removeAttribute('style');
        $this->assertStyle('width: 100px', $div);

        $div->setAttribute('style', 'padding: 5px');
        $this->assertStyle('padding: 5px; width: 100px', $div);

        $div->remove();
        $this->assertStyle('padding: 5px', $div);

        $document->bodyElement()->appendChild($div);
        $this->assertStyle('padding: 5px; width: 100px', $div);
    }

    public function testDirtyChildrenLazyCleanup(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet(
            $document,
            'div.a span.c1 {width: 10px} div.a span.c2 {width: 20px} div.b span.c1 {height: 30px} div.b span.c2 {height: 40px}',
        );
        [$ctxA, $ctxB] = $this->twoContextDivs($document);
        $parent = $document->createElement('div');
        $parent->setAttribute('class', 'wrap');
        $child1 = $this->spanWithClassAndStyle($document, 'c1', 'color: red');
        $child2 = $this->spanWithClassAndStyle($document, 'c2', 'color: blue');
        $parent->appendChild($child1);
        $parent->appendChild($child2);

        $ctxA->appendChild($parent);
        $this->assertStyle('color: red; width: 10px', $child1);
        $this->assertStyle('color: blue; width: 20px', $child2);

        $parent->remove();
        $this->assertStyle('color: red', $child1);
        $this->assertStyle('color: blue', $child2);

        $ctxB->appendChild($parent);
        $this->assertStyle('color: red; height: 30px', $child1);
        $this->assertStyle('color: blue; height: 40px', $child2);
    }

    public function testDirtyNoInlineOnlyStylesheet(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');

        $document->bodyElement()->appendChild($div);
        $this->assertStyle('width: 100px', $div);

        $div->remove();
        $this->assertStyle('', $div);

        $document->bodyElement()->appendChild($div);
        $this->assertStyle('width: 100px', $div);
    }

    public function testDirtyChildContextDependentMove(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.a span.item {width: 100px} div.b span.item {height: 200px}');
        [$divA, $divB] = $this->twoContextDivs($document);
        $wrapper = $document->createElement('div');
        $wrapper->setAttribute('class', 'wrap');
        $child = $this->spanWithClassAndStyle($document, 'item', 'color: red');
        $wrapper->appendChild($child);
        $divA->appendChild($wrapper);

        $this->assertStyle('color: red; width: 100px', $child);

        $wrapper->remove();
        $divB->appendChild($wrapper);

        $this->assertStyle('color: red; height: 200px', $child);
    }

    public function testDirtyNewStylesheetWhileDisconnected(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'color: red');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; width: 100px', $div);

        $div->remove();
        $this->assertStyle('color: red', $div);

        $this->style->attachStylesheet($document, 'div.target {height: 50px}');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; height: 50px; width: 100px', $div);
    }

    public function testDirtyChildDetachedBeforeParentReinsert(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.parent {width: 100px} div.parent span.child {height: 50px}');
        $parent = $document->createElement('div');
        $parent->setAttribute('class', 'parent');
        $child = $this->spanWithClassAndStyle($document, 'child', 'color: blue');
        $parent->appendChild($child);
        $document->bodyElement()->appendChild($parent);

        $this->assertStyle('color: blue; height: 50px', $child);

        $parent->remove();
        $child->remove();
        $document->bodyElement()->appendChild($parent);

        $this->assertStyle('width: 100px', $parent);

        $document->bodyElement()->appendChild($child);

        $this->assertStyle('color: blue', $child);
    }

    public function testDirtyRemoveInlineWhileDisconnected(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'color: red');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; width: 100px', $div);

        $div->remove();
        $div->removeAttribute('style');
        $this->assertStyle('', $div);

        $document->bodyElement()->appendChild($div);
        $this->assertStyle('width: 100px', $div);
    }

    public function testDirtyClassRemovedWhileDisconnected(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');
        $div = $this->targetDiv($document, 'color: red');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; width: 100px', $div);

        $div->remove();
        $div->removeAttribute('class');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red', $div);
    }

    public function testImportantStylesheetOverridesInline(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.target {width: 100px !important}');
        $div = $this->targetDiv($document, 'width: 200px');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 100px !important', $div);

        $div->remove();
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('width: 100px !important', $div);
    }

    public function testStylesheetRemovalCleansElements(): void
    {
        $document = $this->document();
        $stylesheet = $this->style->attachStylesheet($document, 'div.target {width: 100px; height: 50px}');
        $div = $this->targetDiv($document, 'color: red');
        $document->bodyElement()->appendChild($div);

        $this->assertStyle('color: red; height: 50px; width: 100px', $div);

        $this->style->removeStylesheet($document, $stylesheet);

        $this->assertStyle('color: red', $div);
    }

    public function testDirtyNoInlineContextMove(): void
    {
        $document = $this->document();
        $this->style->attachStylesheet($document, 'div.a span.item {width: 100px} div.b span.item {height: 200px}');
        [$divA, $divB] = $this->twoContextDivs($document);
        $child = $document->createElement('span');
        $child->setAttribute('class', 'item');

        $divA->appendChild($child);
        $this->assertStyle('width: 100px', $child);

        $child->remove();
        $divB->appendChild($child);

        $this->assertStyle('height: 200px', $child);
    }

    private function document(): Document
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));

        return $document;
    }

    private function targetDiv(Document $document, string $style): Element
    {
        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        $div->setAttribute('style', $style);

        return $div;
    }

    private function spanWithClassAndStyle(Document $document, string $class, string $style): Element
    {
        $span = $document->createElement('span');
        $span->setAttribute('class', $class);
        $span->setAttribute('style', $style);

        return $span;
    }

    /**
     * @return array{Element, Element}
     */
    private function twoContextDivs(Document $document): array
    {
        $divA = $document->createElement('div');
        $divA->setAttribute('class', 'a');
        $divB = $document->createElement('div');
        $divB->setAttribute('class', 'b');
        $document->bodyElement()->appendChild($divA);
        $document->bodyElement()->appendChild($divB);

        return [$divA, $divB];
    }

    private function assertStyle(string $expected, Element $element): void
    {
        self::assertSame($expected, $this->style->serializeElementStyle($element));
    }
}

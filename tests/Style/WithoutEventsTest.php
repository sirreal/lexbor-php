<?php

declare(strict_types=1);

namespace Lexbor\Tests\Style;

use Lexbor\Core\Status;
use Lexbor\Dom\Element;
use Lexbor\Dom\ExceptionCode;
use Lexbor\Dom\Text;
use Lexbor\Html\Document;
use Lexbor\Html\Parser;
use Lexbor\Style\StyleEngine;
use PHPUnit\Framework\TestCase;

final class WithoutEventsTest extends TestCase
{
    private StyleEngine $style;

    protected function setUp(): void
    {
        $this->style = new StyleEngine();
    }

    public function testUpstreamWithoutEventsDocumentDirectSkipsStyleCallbacks(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');

        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));
        $this->assertStyle('', $div);

        $div->remove();
        self::assertNull($div->parent);
    }

    public function testUpstreamWithoutEventsParserPropagatesDomOptions(): void
    {
        $parser = new Parser();
        $parser->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $document = $parser->parse('<html><head></head><body><div>hello</div></body></html>');

        self::assertSame(Document::OPT_WITHOUT_EVENTS, $document->domOptions() & Document::OPT_WITHOUT_EVENTS);
        self::assertNotNull($document->bodyElement());
        self::assertSame('hello', $this->textContent($document->elementsByTagName('div')[0] ?? null));
    }

    public function testDocumentConstructedWithoutEventsCanProcessLaterBodyInsertions(): void
    {
        $document = new Document(Document::OPT_WITHOUT_EVENTS);
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $document->setDomOptions(Document::OPT_UNDEF);

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));

        $this->assertStyle('width: 100px', $div);
    }

    public function testParserCreatedWithoutEventsDocumentCanProcessLaterBodyInsertions(): void
    {
        $parser = new Parser();
        $parser->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $document = $parser->parse('<html><body></body></html>');
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $document->setDomOptions(Document::OPT_UNDEF);

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));

        $this->assertStyle('width: 100px', $div);
    }

    public function testWithoutEventsFramesetShellReplacementCanProcessLaterInsertions(): void
    {
        $document = new Document(Document::OPT_WITHOUT_EVENTS);
        self::assertSame(Status::Ok, $document->parse('<html><frameset></frameset></html>'));
        self::assertSame('frameset', $document->bodyElement()->tagName);
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $document->setDomOptions(Document::OPT_UNDEF);

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));

        $this->assertStyle('width: 100px', $div);
    }

    public function testWithoutEventsFramesetShellReplacementDropsOldShellFromProcessedTree(): void
    {
        $document = new Document();
        $oldBody = $document->bodyElement();
        $this->style->attachStylesheet(
            $document,
            'body:first-child {width: 10px} frameset:first-child {height: 20px}',
        );

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        self::assertSame(Status::Ok, $document->parse('<html><frameset></frameset></html>'));

        self::assertNull($oldBody->parent);
        $this->assertStyle('', $oldBody);
        $this->assertStyle('height: 20px', $document->bodyElement());
    }

    public function testUpstreamWithoutEventsAttributeMutationDoesNotProcessInlineStyle(): void
    {
        $document = new Document();
        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $div = $document->createElement('div');
        $div->setAttribute('style', 'color: red');

        self::assertSame('color: red', $div->getAttribute('style'));
        $this->assertStyle('', $div);

        $div->removeAttribute('style');
        self::assertNull($div->getAttribute('style'));
    }

    public function testWithoutEventsStyleAttributeMutationsKeepPreviousProcessedInlineStyle(): void
    {
        $document = new Document();
        $div = $document->createElement('div');
        $div->setAttribute('style', 'color: red');

        $this->assertStyle('color: red', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $div->setAttribute('style', 'color: blue');

        self::assertSame('color: blue', $div->getAttribute('style'));
        $this->assertStyle('color: red', $div);

        $div->removeAttribute('style');

        self::assertNull($div->getAttribute('style'));
        $this->assertStyle('color: red', $div);
    }

    public function testWithoutEventsParsedInlineStyleMutationsKeepPreviousProcessedInlineStyle(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<div style="color: red"></div>'));

        $div = $document->elementsByTagName('div')[0] ?? null;
        self::assertInstanceOf(Element::class, $div);
        $this->assertStyle('color: red', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $div->setAttribute('style', 'color: blue');

        self::assertSame('color: blue', $div->getAttribute('style'));
        $this->assertStyle('color: red', $div);
    }

    public function testWithoutEventsReinsertDoesNotReusePreviousStyleConnection(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));
        $this->assertStyle('width: 100px', $div);

        $div->remove();
        $this->assertStyle('', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));

        $this->assertStyle('', $div);
    }

    public function testWithoutEventsRemovalAndReinsertKeepPreviousProcessedConnectionState(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));
        $this->assertStyle('width: 100px', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $div->remove();
        self::assertNull($div->parent);
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));

        $this->assertStyle('width: 100px', $div);
    }

    public function testWithoutEventsReinsertDoesNotReuseParsedStyleSourceOrder(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<div class=target></div><style>div.target {width: 20px}</style>'),
        );

        $div = $document->elementsByTagName('div')[0] ?? null;
        $styleElement = $document->elementsByTagName('style')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertInstanceOf(Element::class, $styleElement);
        $this->assertStyle('width: 20px', $div);

        $styleElement->remove();
        $this->assertStyle('', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        self::assertSame(ExceptionCode::Ok, $div->insertAfter($styleElement));

        $this->assertStyle('', $div);
    }

    public function testWithoutEventsRemovalKeepsPreviousParsedStyleSourceOrder(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<div class=target></div><style>div.target {width: 20px}</style>'),
        );

        $div = $document->elementsByTagName('div')[0] ?? null;
        $styleElement = $document->elementsByTagName('style')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertInstanceOf(Element::class, $styleElement);
        $this->assertStyle('width: 20px', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $styleElement->remove();

        self::assertNull($styleElement->parent);
        $this->assertStyle('width: 20px', $div);

        $document->setDomOptions(Document::OPT_UNDEF);
        $this->assertStyle('width: 20px', $div);
    }

    public function testEventfulTextMutationUpdatesParsedStyleSource(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<div class=target></div><style>div.target {width: 20px}</style>'),
        );

        $div = $document->elementsByTagName('div')[0] ?? null;
        $styleElement = $document->elementsByTagName('style')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertInstanceOf(Element::class, $styleElement);
        self::assertInstanceOf(Text::class, $styleElement->firstChild);
        $this->assertStyle('width: 20px', $div);

        $styleElement->firstChild->data = 'div.target {width: 30px}';

        self::assertSame('div.target {width: 30px}', $styleElement->firstChild->data);
        $this->assertStyle('width: 30px', $div);
    }

    public function testTextDataRemainsPublicPropertyCompatible(): void
    {
        $document = new Document();
        $text = $document->createTextNode('x');

        self::assertSame('x', $text->data);
        self::assertTrue(isset($text->data));
        self::assertFalse(empty($text->data));
        self::assertTrue((new \ReflectionProperty($text, 'data'))->isPublic());
    }

    public function testWithoutEventsTextMutationDoesNotChangeProcessedParsedStyleSource(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<div class=target></div><style>div.target {width: 20px}</style>'),
        );

        $div = $document->elementsByTagName('div')[0] ?? null;
        $styleElement = $document->elementsByTagName('style')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertInstanceOf(Element::class, $styleElement);
        self::assertInstanceOf(Text::class, $styleElement->firstChild);
        $this->assertStyle('width: 20px', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $styleElement->firstChild->data = 'div.target {width: 30px}';

        $this->assertStyle('width: 20px', $div);

        $document->setDomOptions(Document::OPT_UNDEF);
        $this->assertStyle('width: 20px', $div);
    }

    public function testEventfulTextMutationBeforeWithoutEventsIsCapturedBeforeSuppression(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<div class=target></div><style>div.target {width: 20px}</style>'),
        );

        $div = $document->elementsByTagName('div')[0] ?? null;
        $styleElement = $document->elementsByTagName('style')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertInstanceOf(Element::class, $styleElement);
        self::assertInstanceOf(Text::class, $styleElement->firstChild);

        $styleElement->firstChild->data = 'div.target {width: 30px}';
        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $styleElement->firstChild->data = 'div.target {width: 40px}';

        $this->assertStyle('width: 30px', $div);

        $document->setDomOptions(Document::OPT_UNDEF);
        $this->assertStyle('width: 30px', $div);
    }

    public function testWithoutEventsTextMutationDoesNotAffectProcessedTextSelectorMatches(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<div>old</div><p> </p>'));
        $this->style->attachStylesheet(
            $document,
            'div:lexbor-contains(neo) {width: 100px} p:blank {height: 20px}',
        );

        $div = $document->elementsByTagName('div')[0] ?? null;
        $paragraph = $document->elementsByTagName('p')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertInstanceOf(Element::class, $paragraph);
        self::assertInstanceOf(Text::class, $div->firstChild);
        self::assertInstanceOf(Text::class, $paragraph->firstChild);
        $this->assertStyle('', $div);
        $this->assertStyle('height: 20px', $paragraph);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $div->firstChild->data = 'neo';
        $paragraph->firstChild->data = 'not blank';

        self::assertSame('neo', $div->firstChild->data);
        self::assertSame('not blank', $paragraph->firstChild->data);
        $this->assertStyle('', $div);
        $this->assertStyle('height: 20px', $paragraph);

        $document->setDomOptions(Document::OPT_UNDEF);
        $this->assertStyle('', $div);
        $this->assertStyle('height: 20px', $paragraph);
    }

    public function testWithoutEventsAttributeMutationDoesNotGainStylesheetMatch(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $div = $document->createElement('div');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));
        $this->assertStyle('', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $div->setAttribute('class', 'target');

        self::assertSame('target', $div->getAttribute('class'));
        $this->assertStyle('', $div);
    }

    public function testWithoutEventsAttributeMutationDoesNotLoseStylesheetMatch(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));
        $this->assertStyle('width: 100px', $div);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $div->removeAttribute('class');

        self::assertNull($div->getAttribute('class'));
        $this->assertStyle('width: 100px', $div);
    }

    public function testWithoutEventsSkippedInsertionDoesNotReplayAfterOptionsReenabled(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div.target {width: 100px}');

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $div = $document->createElement('div');
        $div->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));

        $document->setDomOptions(Document::OPT_UNDEF);

        self::assertSame('target', $div->getAttribute('class'));
        $this->assertStyle('', $div);
    }

    public function testWithoutEventsSkippedInlineStyleMutationDoesNotReplayAfterOptionsReenabled(): void
    {
        $document = new Document();

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $div = $document->createElement('div');
        $div->setAttribute('style', 'color: red');

        $document->setDomOptions(Document::OPT_UNDEF);

        self::assertSame('color: red', $div->getAttribute('style'));
        $this->assertStyle('', $div);
    }

    public function testWithoutEventsStructuralInsertionDoesNotAffectProcessedHasMatch(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div#host:has(span.target) {width: 100px}');

        $host = $document->createElement('div');
        $host->setAttribute('id', 'host');
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($host));
        $this->assertStyle('', $host);

        $child = $document->createElement('span');
        $child->setAttribute('class', 'target');

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        self::assertSame(ExceptionCode::Ok, $host->appendChild($child));

        $this->assertStyle('', $host);

        $document->setDomOptions(Document::OPT_UNDEF);
        $this->assertStyle('', $host);
    }

    public function testEventfulInsertionAfterWithoutEventsProcessesRawSubtreeStructure(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div#host span.target {width: 100px}');

        $child = $document->createElement('span');
        $child->setAttribute('class', 'target');

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $host = $document->createElement('div');
        $host->setAttribute('id', 'host');
        self::assertSame(ExceptionCode::Ok, $host->appendChild($child));

        $document->setDomOptions(Document::OPT_UNDEF);
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($host));

        $this->assertStyle('width: 100px', $child);
    }

    public function testEventfulInsertionAfterWithoutEventsProcessesRawSubtreeForHasMatches(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div#host:has(span.target) {width: 100px}');

        $child = $document->createElement('span');
        $child->setAttribute('class', 'target');

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $host = $document->createElement('div');
        $host->setAttribute('id', 'host');
        self::assertSame(ExceptionCode::Ok, $host->appendChild($child));

        $document->setDomOptions(Document::OPT_UNDEF);
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($host));

        $this->assertStyle('width: 100px', $host);
    }

    public function testEventfulInsertionAfterWithoutEventsProcessesRawSubtreeAttributes(): void
    {
        $document = new Document();

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $div = $document->createElement('div');
        $div->setAttribute('style', 'color: red');

        $document->setDomOptions(Document::OPT_UNDEF);
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($div));

        $this->assertStyle('color: red', $div);
    }

    public function testRawMovedNodeDoesNotUseDocumentStylesAfterProcessedTreeDropsIt(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'span.target {width: 100px}');

        $host = $document->createElement('div');
        $child = $document->createElement('span');
        $child->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $host->appendChild($child));
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($host));
        $this->assertStyle('width: 100px', $child);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $host->remove();
        $child->remove();
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($child));

        $document->setDomOptions(Document::OPT_UNDEF);
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($host));

        self::assertSame($document->bodyElement(), $child->parent);
        $this->assertStyle('', $child);
    }

    public function testWithoutEventsStructuralRemovalDoesNotAffectProcessedHasMatch(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'div#host:has(span.target) {width: 100px}');

        $host = $document->createElement('div');
        $host->setAttribute('id', 'host');
        $child = $document->createElement('span');
        $child->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $host->appendChild($child));
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($host));
        $this->assertStyle('width: 100px', $host);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $child->remove();

        self::assertNull($child->parent);
        $this->assertStyle('width: 100px', $host);

        $document->setDomOptions(Document::OPT_UNDEF);
        $this->assertStyle('width: 100px', $host);
    }

    public function testEventfulRemovalClearsProcessedDescendantsAfterSkippedChildRemoval(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body></body></html>'));
        $this->style->attachStylesheet($document, 'span.target {width: 100px}');

        $host = $document->createElement('div');
        $child = $document->createElement('span');
        $child->setAttribute('class', 'target');
        self::assertSame(ExceptionCode::Ok, $host->appendChild($child));
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($host));
        $this->assertStyle('width: 100px', $child);

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        $child->remove();

        $document->setDomOptions(Document::OPT_UNDEF);
        $host->remove();

        $document->setDomOptions(Document::OPT_WITHOUT_EVENTS);
        self::assertSame(ExceptionCode::Ok, $document->bodyElement()->appendChild($child));

        $this->assertStyle('', $child);
    }

    public function testUpstreamWithoutEventsChunkParserPropagatesDomOptions(): void
    {
        $parser = new Parser();
        $parser->setDomOptions(Document::OPT_WITHOUT_EVENTS);

        $document = $parser->parseChunkBegin();
        self::assertSame(
            Status::Ok,
            $parser->parseChunkProcess('<html><head></head><body><p>chunk</p></body></html>'),
        );
        self::assertSame(Status::Ok, $parser->parseChunkEnd());

        self::assertSame(Document::OPT_WITHOUT_EVENTS, $document->domOptions() & Document::OPT_WITHOUT_EVENTS);
        self::assertNotNull($document->bodyElement());
        self::assertSame('chunk', $this->textContent($document->elementsByTagName('p')[0] ?? null));
    }

    private function assertStyle(string $expected, Element $element): void
    {
        self::assertSame($expected, $this->style->serializeElementStyle($element));
    }

    private function textContent(?Element $element): string
    {
        self::assertInstanceOf(Element::class, $element);

        $content = '';
        for ($child = $element->firstChild; $child !== null; $child = $child->next) {
            $content .= $child instanceof Text ? $child->data : '';
        }

        return $content;
    }
}

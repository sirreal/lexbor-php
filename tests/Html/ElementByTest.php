<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Collection;
use Lexbor\Html\Document;
use PHPUnit\Framework\TestCase;

final class ElementByTest extends TestCase
{
    private const DATA = "<html><head></head><body>"
        . "<div class='with-accordion'>1</div>"
        . "<div class='with-accordion hidden'>2</div>"
        . "<div class='hidden with-accordion'>3</div>"
        . "<div class='hidden with-accordion hidden'>4</div>"
        . "<div class='    with-accordion'>5</div>"
        . "<div class='with-accordion      '>6</div>"
        . "<div class='hidden    hidden  with-accordion'>7</div>"
        . "<div class='with-accordion    hidden'>8</div>"
        . "<div class='wewith-accordion'></div>"
        . "<div class='with-accordionwe'></div>"
        . "<div class='wewith-accordionwe'></div>"
        . "<div class='with- accordion'></div>"
        . "<div class></div>"
        . "<div class=''></div>"
        . "<div id></div>"
        . "<div id=''></div>"
        . "<div id='abc'></div>"
        . "<div foo></div>"
        . "<div foo=''></div>"
        . "<div foo='abc'></div>"
        . "</body></html>";

    public function testByClass(): void
    {
        $document = $this->documentWithFixture();
        $collection = new Collection();

        self::assertSame(Status::Ok, $document->collectElementsByClassName($collection, 'with-accordion'));
        self::assertSame(8, $collection->length());

        self::assertSame(Status::Ok, $document->collectElementsByClassName($collection, ''));
        self::assertSame(8, $collection->length());

        self::assertSame(Status::Ok, $document->collectElementsByClassName($collection, null));
        self::assertSame(8, $collection->length());
    }

    public function testByClassUsesCaseInsensitiveMatchingInQuirksMode(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body><div class="With-Accordion"></div></body></html>'));
        $collection = new Collection();

        self::assertTrue($document->isQuirksMode());
        self::assertSame(Status::Ok, $document->collectElementsByClassName($collection, 'with-accordion'));
        self::assertSame(1, $collection->length());
    }

    public function testByClassUsesCaseSensitiveMatchingInNoQuirksMode(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<!doctype html><html><body><div class="With-Accordion"></div></body></html>'));
        $collection = new Collection();

        self::assertFalse($document->isQuirksMode());
        self::assertSame(Status::Ok, $document->collectElementsByClassName($collection, 'with-accordion'));
        self::assertSame(0, $collection->length());
    }

    public function testByAttr(): void
    {
        $document = $this->documentWithFixture();
        $collection = new Collection();

        self::assertSame(Status::Ok, $document->collectElementsByAttr($collection, 'id', 'abc'));
        self::assertSame(1, $collection->length());

        self::assertSame(Status::Ok, $document->collectElementsByAttr($collection, 'id', ''));
        self::assertSame(3, $collection->length());

        self::assertSame(Status::Ok, $document->collectElementsByAttr($collection, 'foo', 'abc'));
        self::assertSame(4, $collection->length());

        self::assertSame(Status::Ok, $document->collectElementsByAttr($collection, 'foo', ''));
        self::assertSame(6, $collection->length());

        self::assertSame(Status::Ok, $document->collectElementsByAttr($collection, 'foo', null));
        self::assertSame(8, $collection->length());
    }

    public function testDocumentCollectionsIncludeBodyAttributes(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<html><body class="target" foo="abc"><div class="target" foo="abc"></div></body></html>'));

        $collection = new Collection();
        self::assertSame(Status::Ok, $document->collectElementsByClassName($collection, 'target'));
        self::assertSame(2, $collection->length());
        self::assertSame($document->bodyElement(), $collection->item(0));
        self::assertSame($document->bodyElement()->firstChild, $collection->item(1));

        $collection = new Collection();
        self::assertSame(Status::Ok, $document->collectElementsByAttr($collection, 'foo', 'abc'));
        self::assertSame(2, $collection->length());
        self::assertSame($document->bodyElement(), $collection->item(0));
        self::assertSame($document->bodyElement()->firstChild, $collection->item(1));
    }

    public function testParserFixtureBuildsNestedElementTrees(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<section><div class="target"></div></section><div class="target"></div>'));

        $body = $document->bodyElement();
        $section = $body->firstChild;

        self::assertSame('section', $section?->tagName);
        self::assertSame('div', $section?->firstChild?->tagName);
        self::assertSame('div', $section?->next?->tagName);
        self::assertSame(2, count($body->elementsByTagName('div')));
    }

    private function documentWithFixture(): Document
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse(self::DATA));

        return $document;
    }
}

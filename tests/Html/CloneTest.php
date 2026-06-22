<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Element;
use Lexbor\Dom\ExceptionCode;
use Lexbor\Dom\Text;
use Lexbor\Html\Document;
use Lexbor\Html\Serializer;
use PHPUnit\Framework\TestCase;

final class CloneTest extends TestCase
{
    private const HTML = '<div x=abc><span>darkness</span><xx>xXx</xx></div>';

    public function testSingleClone(): void
    {
        $document = $this->documentWithFixture();
        $collection = $document->elementsByTagName('div');

        self::assertCount(1, $collection);

        $node = $collection[0];
        $clone = $node->cloneNode(false);

        self::assertSame($node->ownerDocument, $clone->ownerDocument);
        self::assertSame($node->tagName, $clone->tagName);
        self::assertNotSame($node, $clone);
        self::assertNull($clone->firstChild);

        self::assertSame($node->getAttribute('x'), $clone->getAttribute('x'));
        self::assertNotSame($node->attrByName('x'), $clone->attrByName('x'));

        $clone->setAttribute('x', 'changed');

        self::assertSame('abc', $node->getAttribute('x'));
        self::assertSame('changed', $clone->getAttribute('x'));
    }

    public function testSingleCloneCanBeInsertedAfterOriginal(): void
    {
        $document = $this->documentWithFixture();
        $node = $document->elementsByTagName('div')[0];
        $clone = $node->cloneNode(false);

        self::assertSame(ExceptionCode::Ok, $node->insertAfter($clone));
        self::assertSame($clone, $node->next);
        self::assertNotSame($node, $node->next);
        self::assertCount(2, $document->elementsByTagName('div'));
    }

    public function testDeepClone(): void
    {
        $document = $this->documentWithFixture();
        $node = $document->elementsByTagName('div')[0];
        $clone = $node->cloneNode(true);

        self::assertSame(ExceptionCode::Ok, $node->insertAfter($clone));
        self::assertSame($clone, $node->next);
        self::assertCount(2, $document->elementsByTagName('span'));

        $span = $document->elementsByTagName('span')[0];
        $clonedSpan = $document->elementsByTagName('span')[1];

        self::assertNotSame($span, $clonedSpan);
        self::assertSame($node, $span->parent);
        self::assertSame($clone, $clonedSpan->parent);
    }

    public function testTextClone(): void
    {
        $document = $this->documentWithFixture();
        $span = $document->elementsByTagName('span')[0];
        $text = $span->firstChild;

        self::assertInstanceOf(Text::class, $text);

        $clone = $text->cloneNode(false);

        self::assertInstanceOf(Text::class, $clone);
        self::assertSame('darkness', $clone->data);
        self::assertSame(ExceptionCode::Ok, $span->insertAfter($clone));
        self::assertSame($clone, $span->next);
        self::assertNotSame($span->firstChild, $span->next);

        $clone->data = 'changed';

        self::assertSame('darkness', $text->data);
        self::assertSame('changed', $clone->data);
    }

    public function testImportFrom(): void
    {
        $documentOne = $this->documentWithFixture();
        $documentTwo = $this->documentWithFixture();

        $nodeOne = $documentOne->elementsByTagName('div')[0];
        $nodeTwo = $documentTwo->elementsByTagName('div')[0];

        self::assertNotSame($nodeOne, $nodeTwo);

        $clone = $documentOne->importNode($nodeTwo, true);

        self::assertInstanceOf(Element::class, $clone);
        self::assertSame($documentOne, $clone->ownerDocument);
        self::assertSame($documentOne, $clone->firstChild?->ownerDocument);
        self::assertSame('<div x="abc"><span>darkness</span><xx>xXx</xx></div>', Serializer::serialize($clone));
    }

    public function testDeepCloneCopiesTextDescendants(): void
    {
        $document = $this->documentWithFixture();
        $clone = $document->elementsByTagName('div')[0]->cloneNode(true);

        self::assertSame('<div x="abc"><span>darkness</span><xx>xXx</xx></div>', Serializer::serialize($clone));
    }

    private function documentWithFixture(): Document
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse(self::HTML));

        return $document;
    }
}

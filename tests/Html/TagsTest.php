<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Element;
use Lexbor\Dom\NodeType;
use Lexbor\Html\Document;
use Lexbor\Html\Tag;
use Lexbor\Html\TagRegistry;
use PHPUnit\Framework\TestCase;

final class TagsTest extends TestCase
{
    public function testTags(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<div>a</div>'));
        self::assertSame(Tag::DIV, $document->bodyElement()->firstChild?->localName);

        $element = $document->createElement('div');
        self::assertSame(Tag::DIV, $element->tagId);

        $element = $document->createElement('DiV');
        self::assertSame(Tag::DIV, $element->tagId);
        self::assertSame('div', TagRegistry::nameById($element->tagId));

        $element = $document->createElement('p');
        self::assertSame(Tag::P, $element->tagId);
        self::assertSame('p', TagRegistry::nameById($element->tagId));

        $element = $document->createElement('hoho');
        self::assertGreaterThan(Tag::LAST_ENTRY, $element->tagId);
        self::assertSame('hoho', TagRegistry::nameById($element->tagId));

        $tagId = $element->tagId;

        $element = $document->createElement('hoho');
        self::assertSame($tagId, $element->tagId);

        $element = $document->createElement('hOHo');
        self::assertSame($tagId, $element->tagId);
    }

    public function testTagsCreateEm(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<!doctype html><html><body></body></html>'));

        $cases = [
            Tag::EM_COMMENT => NodeType::Comment,
            Tag::DOCUMENT => NodeType::Document,
            Tag::EM_DOCTYPE => NodeType::DocumentType,
            Tag::TEXT => NodeType::Text,
        ];

        foreach ($cases as $tagId => $nodeType) {
            $node = $document->createInterfaceByTagId($tagId);
            self::assertSame($nodeType, $node->type);
            self::assertSame($tagId, $node->localName);
        }
    }

    public function testParserFixtureMatchesTagNamesCaseInsensitively(): void
    {
        $document = new Document();

        self::assertSame(Status::Ok, $document->parse('<DIV></div>'));
        self::assertSame(Tag::DIV, $document->bodyElement()->firstChild?->localName);
        self::assertSame('div', $document->bodyElement()->firstChild?->tagName);
    }

    public function testUnknownTagInterfaceCreatesElement(): void
    {
        $document = new Document();
        $tagId = $document->tags()->idForName('custom-element');
        $node = $document->createInterfaceByTagId($tagId);

        self::assertInstanceOf(Element::class, $node);
        self::assertSame(NodeType::Element, $node->type);
        self::assertSame($tagId, $node->localName);
        self::assertSame('custom-element', $node->tagName);
    }
}

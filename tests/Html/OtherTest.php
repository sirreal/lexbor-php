<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Html\Document;
use Lexbor\Html\Serializer;
use PHPUnit\Framework\TestCase;

final class OtherTest extends TestCase
{
    public function testUpstreamOtherFixedSvgTags(): void
    {
        $document = new Document();

        self::assertSame(Status::Ok, $document->parse('<svg><a xlink:title>'));
        self::assertSame('<svg><a xlink:title=""></a></svg>', Serializer::serializeDeep($document->bodyElement()));
    }

    public function testUpstreamOtherBadHtmlRemoveAttributes(): void
    {
        $document = new Document();

        self::assertSame(Status::Ok, $document->parse('<a target="x"><div></a>'));
        self::removeAttributes($document->htmlElement());
        self::removeAttributes($document->headElement());
        self::removeAttributes($document->bodyElement());

        self::assertSame(
            '<html><head></head><body><a></a><div><a></a></div></body></html>',
            Serializer::serializeDeep($document),
        );
    }

    public function testFormattingAdoptionDoesNotReparentForeignAnchor(): void
    {
        $document = new Document();

        self::assertSame(Status::Ok, $document->parse('<svg><a><foreignObject><div></a>'));
        self::assertSame(
            '<html><head></head><body><svg><a><foreignobject><div></div></foreignobject></a></svg></body></html>',
            Serializer::serializeDeep($document),
        );

        $document = new Document();

        self::assertSame(Status::Ok, $document->parse('<a><svg><foreignObject><div></a>'));
        self::assertSame(
            '<html><head></head><body><a><svg><foreignobject><div></div></foreignobject></svg></a></body></html>',
            Serializer::serializeDeep($document),
        );
    }

    public function testUpstreamOtherDuplicateAttributesSvgNamespace(): void
    {
        $document = new Document();

        self::assertSame(
            Status::Ok,
            $document->parse(
                '<svg><use xmlns:xlink="http://www.w3.org/1999/xlink" '
                . 'xmlns:xlink="http://www.w3.org/1999/xlink">'
            ),
        );
        self::assertSame(
            '<svg><use xmlns:xlink="http://www.w3.org/1999/xlink"></use></svg>',
            Serializer::serializeDeep($document->bodyElement()),
        );
    }

    public function testUpstreamOtherSelectSizeValueAttributeIsNull(): void
    {
        $document = new Document();

        self::assertSame(Status::Ok, $document->parse('<!doctype html><select size><option>a</option></select>'));
    }

    private static function removeAttributes(Node $node): void
    {
        if ($node instanceof Element) {
            $node->clearAttributes();
        }

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            self::removeAttributes($child);
        }
    }
}

<?php

declare(strict_types=1);

namespace Lexbor\Tests\Dom;

use Lexbor\Dom\DocumentType;
use Lexbor\Dom\NodeType;
use Lexbor\Html\Document;
use Lexbor\Html\Tag;
use PHPUnit\Framework\TestCase;

final class DocumentTypeTest extends TestCase
{
    public function testCreateOnlyName(): void
    {
        $document = new Document();
        $doctype = $document->createDocumentType('lexbor');

        self::assertInstanceOf(DocumentType::class, $doctype);
        self::assertSame('lexbor', $doctype->name());
    }

    public function testCreateOnlyNameHtml(): void
    {
        $document = new Document();
        $doctype = $document->createDocumentType('html');

        self::assertInstanceOf(DocumentType::class, $doctype);
        self::assertSame('html', $doctype->name());
        self::assertNull($doctype->publicId());
        self::assertNull($doctype->systemId());
    }

    public function testCreateFull(): void
    {
        $document = new Document();
        $doctype = $document->createDocumentType(
            'lexbor',
            '-//W3C//DTD HTML 4.01 Transitional//EN',
            'http://www.w3.org/TR/html4/loose.dtd',
        );

        self::assertInstanceOf(DocumentType::class, $doctype);
        self::assertSame('lexbor', $doctype->name());
        self::assertSame('-//W3C//DTD HTML 4.01 Transitional//EN', $doctype->publicId());
        self::assertSame('http://www.w3.org/TR/html4/loose.dtd', $doctype->systemId());
    }

    public function testInvalidNameReturnsNull(): void
    {
        $document = new Document();

        self::assertNull($document->createDocumentType(''));
        self::assertNull($document->createDocumentType('bad name'));
        self::assertNull($document->createDocumentType('bad>name'));
        self::assertNull($document->createDocumentType("bad\0name"));
    }

    public function testEmptyPublicAndSystemIdsArePreserved(): void
    {
        $document = new Document();
        $doctype = $document->createDocumentType('html', '', '');

        self::assertInstanceOf(DocumentType::class, $doctype);
        self::assertSame('', $doctype->publicId());
        self::assertSame('', $doctype->systemId());
    }

    public function testNameIsLowercased(): void
    {
        $document = new Document();
        $doctype = $document->createDocumentType('LeXbOr');

        self::assertInstanceOf(DocumentType::class, $doctype);
        self::assertSame('lexbor', $doctype->name());
    }

    public function testDoctypeInterfaceCreatesDocumentTypeNode(): void
    {
        $document = new Document();
        $node = $document->createInterfaceByTagId(Tag::EM_DOCTYPE);

        self::assertInstanceOf(DocumentType::class, $node);
        self::assertSame(NodeType::DocumentType, $node->type);
        self::assertSame(Tag::EM_DOCTYPE, $node->localName);
        self::assertSame('html', $node->name());
    }
}

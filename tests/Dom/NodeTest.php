<?php

declare(strict_types=1);

namespace Lexbor\Tests\Dom;

use Lexbor\Core\Status;
use Lexbor\Dom\ExceptionCode;
use Lexbor\Dom\Node;
use Lexbor\Dom\NodeType;
use Lexbor\Html\Document;
use Lexbor\Html\Serializer;
use PHPUnit\Framework\TestCase;

final class NodeTest extends TestCase
{
    private const HTML = "<div id='div-1'></div><div id='div-2'></div>";

    public function testInsertBefore(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $p = $document->createElement('p');

        self::assertSame(ExceptionCode::Ok, $body->insertBeforeSpec($p, $body->firstChild->next));
        self::assertSame('<div id="div-1"></div><p></p><div id="div-2"></div>', Serializer::serializeDeep($body));
    }

    public function testAppendChild(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $p = $document->createElement('p');

        self::assertSame(ExceptionCode::Ok, $body->appendChild($p));
        self::assertSame('<div id="div-1"></div><div id="div-2"></div><p></p>', Serializer::serializeDeep($body));
    }

    public function testAppendChildDocumentFragment(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $fragment = $document->createDocumentFragment();

        self::assertSame(ExceptionCode::Ok, $fragment->appendChild($document->createElement('p')));
        self::assertSame(ExceptionCode::Ok, $fragment->appendChild($document->createElement('a')));
        self::assertSame(ExceptionCode::Ok, $body->appendChild($fragment));

        self::assertSame('<div id="div-1"></div><div id="div-2"></div><p></p><a></a>', Serializer::serializeDeep($body));
        self::assertNull($fragment->firstChild);
    }

    public function testReplaceChild(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $p = $document->createElement('p');

        self::assertSame(ExceptionCode::Ok, $body->replaceChild($p, $body->firstChild));
        self::assertSame('<p></p><div id="div-2"></div>', Serializer::serializeDeep($body));
    }

    public function testReplaceChildDocumentFragment(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $fragment = $document->createDocumentFragment();

        self::assertSame(ExceptionCode::Ok, $fragment->appendChild($document->createElement('p')));
        self::assertSame(ExceptionCode::Ok, $fragment->appendChild($document->createElement('a')));
        self::assertSame(ExceptionCode::Ok, $body->replaceChild($fragment, $body->firstChild));

        self::assertSame('<p></p><a></a><div id="div-2"></div>', Serializer::serializeDeep($body));
        self::assertNull($fragment->firstChild);
    }

    public function testRemoveChild(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();

        self::assertSame(ExceptionCode::Ok, $body->removeChild($body->firstChild));
        self::assertSame('<div id="div-2"></div>', Serializer::serializeDeep($body));
    }

    public function testGetById(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse("<div id='idDIV'></div>"));
        $body = $document->bodyElement();

        self::assertSame($body->firstChild, $body->byId('idDIV'));
        self::assertNull($body->byId(' idDIV'));
        self::assertNull($body->byId('idDIV '));
        self::assertNull($body->byId(' dDIV'));
        self::assertNull($body->byId('idDI '));
        self::assertNull($body->byId('iddiv'));
    }

    public function testAppendExistingChildMovesItToEnd(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $first = $body->firstChild;

        self::assertSame(ExceptionCode::Ok, $body->appendChild($first));
        self::assertSame('<div id="div-2"></div><div id="div-1"></div>', Serializer::serializeDeep($body));
        self::assertSame($first, $body->lastChild);
        self::assertNull($first->next);
        self::assertSame($body, $first->parent);
    }

    public function testInsertExistingChildBeforeSiblingReordersIt(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $first = $body->firstChild;
        $last = $body->lastChild;

        self::assertSame(ExceptionCode::Ok, $body->insertBeforeSpec($last, $first));
        self::assertSame('<div id="div-2"></div><div id="div-1"></div>', Serializer::serializeDeep($body));
        self::assertSame($last, $body->firstChild);
        self::assertSame($first, $body->lastChild);
    }

    public function testReplaceChildWithItselfIsStable(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $first = $body->firstChild;

        self::assertSame(ExceptionCode::Ok, $body->replaceChild($first, $first));
        self::assertSame('<div id="div-1"></div><div id="div-2"></div>', Serializer::serializeDeep($body));
        self::assertSame($body, $first->parent);
    }

    public function testReplaceChildWithImmediateNextSiblingKeepsSibling(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $first = $body->firstChild;
        $second = $first->next;

        self::assertSame(ExceptionCode::Ok, $body->replaceChild($second, $first));
        self::assertSame('<div id="div-2"></div>', Serializer::serializeDeep($body));
        self::assertSame($second, $body->firstChild);
        self::assertSame($second, $body->lastChild);
        self::assertSame($body, $second->parent);
        self::assertNull($second->prev);
        self::assertNull($second->next);
    }

    public function testReplaceMiddleChildWithImmediateNextSiblingKeepsTail(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $a = $document->createElement('a');
        self::assertSame(ExceptionCode::Ok, $body->appendChild($a));

        $second = $body->firstChild->next;

        self::assertSame(ExceptionCode::Ok, $body->replaceChild($a, $second));
        self::assertSame('<div id="div-1"></div><a></a>', Serializer::serializeDeep($body));
        self::assertSame($body->firstChild->next, $a);
        self::assertSame($a, $body->lastChild);
        self::assertSame($body, $a->parent);
        self::assertNull($a->next);
    }

    public function testCannotInsertAncestorIntoDescendant(): void
    {
        $document = $this->documentWithFixture();
        $body = $document->bodyElement();
        $first = $body->firstChild;

        self::assertSame(ExceptionCode::HierarchyRequestError, $first->appendChild($body));
        self::assertSame($document, $body->parent);
        self::assertSame($body, $first->parent);
        self::assertSame('<div id="div-1"></div><div id="div-2"></div>', Serializer::serializeDeep($body));
    }

    public function testElementAcceptsConcreteCharacterDataNodeTypes(): void
    {
        foreach ([NodeType::Comment, NodeType::CDataSection, NodeType::ProcessingInstruction] as $type) {
            $document = $this->documentWithFixture();
            $body = $document->bodyElement();
            $node = new Node($type, $document);

            self::assertSame(ExceptionCode::Ok, $body->appendChild($node));
            self::assertSame($body, $node->parent);
            self::assertSame($node, $body->lastChild);
            self::assertNull($node->next);
        }
    }

    private function documentWithFixture(): Document
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse(self::HTML));

        return $document;
    }
}

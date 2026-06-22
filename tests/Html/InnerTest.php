<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Element;
use Lexbor\Html\Document;
use Lexbor\Html\Serializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InnerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function specialTagProvider(): iterable
    {
        yield 'textarea' => [
            '<body><textarea>contents</textarea>',
            '</textarea>foo',
            '<textarea>&lt;/textarea&gt;foo</textarea>',
        ];

        yield 'style' => [
            '<body><style>contents</style>',
            '</style>foo',
            '<style></style>foo</style>',
        ];

        yield 'script' => [
            '<body><script>contents</script>',
            '</script>foo',
            '<script></script>foo</script>',
        ];

        yield 'plaintext' => [
            '<body><plaintext>contents</plaintext>',
            '</plaintext>foo',
            '<plaintext></plaintext>foo</plaintext>',
        ];
    }

    #[DataProvider('specialTagProvider')]
    public function testInnerSpecialTags(string $html, string $inner, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        $node = $document->bodyElement()->firstChild;
        self::assertInstanceOf(Element::class, $node);

        $element = $node->setInnerHtml($inner);

        self::assertSame($node, $element);
        self::assertSame($expected, Serializer::serialize($document->bodyElement()->firstChild));
    }

    public function testInnerHtmlReplacesExistingChildren(): void
    {
        $document = new Document();
        $element = $document->createElement('div');
        $element->appendChild($document->createElement('span'));

        $element->setInnerHtml('<b></b>tail');

        self::assertSame('<div><b></b>tail</div>', Serializer::serialize($element));
    }

    public function testParserFixtureDoesNotPreserveLeadingDoctypeAsBodyText(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<!doctype html><div>x</div>'));

        self::assertSame('<div>x</div>', Serializer::serializeDeep($document->bodyElement()));
    }

    public function testParserFixtureKeepsRawTextContentsAsText(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<script>x < y > z</script>'));

        self::assertSame('<script>x < y > z</script>', Serializer::serializeDeep($document->bodyElement()));
    }

    public function testParserFixtureKeepsPlaintextContentToEndOfFile(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<plaintext>a</plaintext>b'));

        self::assertSame('<plaintext>a</plaintext>b</plaintext>', Serializer::serializeDeep($document->bodyElement()));
    }
}

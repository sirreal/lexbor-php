<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Collection;
use Lexbor\Html\Document;
use Lexbor\Html\Serializer;
use PHPUnit\Framework\TestCase;

final class ParseTest extends TestCase
{
    private const DOCUMENT_HTML = '<html><head></head><body><sometag><p><button>'
        . '</button></p></sometag></body></html>';

    public function testUpstreamParseDocumentSerializesFullTree(): void
    {
        $document = new Document();

        self::assertSame(Status::Ok, $document->parse(self::DOCUMENT_HTML));
        self::assertSame(self::DOCUMENT_HTML, Serializer::serializeDeep($document));
    }

    public function testUpstreamParseDocumentThreeReusesDocument(): void
    {
        $document = new Document();

        self::assertSame(Status::Ok, $document->parse(self::DOCUMENT_HTML));
        self::assertSame(Status::Ok, $document->parse(self::DOCUMENT_HTML));
        self::assertSame(Status::Ok, $document->parse(self::DOCUMENT_HTML));

        self::assertSame(self::DOCUMENT_HTML, Serializer::serializeDeep($document));
    }

    public function testUpstreamParseDocumentCleanCollection(): void
    {
        $document = new Document();
        $collection = new Collection();

        for ($i = 0; $i < 6; $i++) {
            self::assertSame(Status::Ok, $document->parse('<a href=/wiki>123</a>'));

            self::assertSame(
                Status::Ok,
                $document->bodyElement()->collectElementsByAttr($collection, 'href', '/wiki', true),
            );
            self::assertSame(1, $collection->length());
            self::assertSame('<a href="/wiki">123</a>', Serializer::serialize($collection->item(0)));

            $collection->clear();
        }
    }
}

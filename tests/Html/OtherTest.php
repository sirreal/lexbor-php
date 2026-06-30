<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
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
}

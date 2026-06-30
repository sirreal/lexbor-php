<?php

declare(strict_types=1);

namespace Lexbor\Tests\Style;

use Lexbor\Core\Status;
use Lexbor\Dom\Element;
use Lexbor\Dom\ExceptionCode;
use Lexbor\Html\Document;
use Lexbor\Style\StyleEngine;
use PHPUnit\Framework\TestCase;

final class StyleTagTest extends TestCase
{
    private StyleEngine $style;

    protected function setUp(): void
    {
        $this->style = new StyleEngine();
    }

    public function testUpstreamStyleTagStylesheetLifecycle(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse(
                '<div class=father></div>'
                . '<style>div.father {width: 20px; height: 10pt; display: block}</style>',
            ),
        );
        $this->style->attachStylesheet($document, 'div.father {width: 30%}');

        $div = $document->elementsByTagName('div')[0] ?? null;
        $styleElement = $document->elementsByTagName('style')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertInstanceOf(Element::class, $styleElement);
        self::assertStyle('display: block; height: 10pt; width: 30%', $div);

        $styleElement->remove();
        self::assertStyle('width: 30%', $div);

        self::assertSame(ExceptionCode::Ok, $div->insertAfter($styleElement));
        self::assertStyle('display: block; height: 10pt; width: 20px', $div);

        $styleElement->remove();
        self::assertStyle('width: 30%', $div);
    }

    public function testUpstreamTwoStylesheetDestroyAllClearsParsedStyleElements(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse(
                '<div class=alpha></div><span class=beta></span>'
                . '<style>div.alpha {width: 20px}</style>'
                . '<style>span.beta {height: 30px}</style>',
            ),
        );

        $div = $document->elementsByTagName('div')[0] ?? null;
        $span = $document->elementsByTagName('span')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertInstanceOf(Element::class, $span);
        self::assertStyle('width: 20px', $div);
        self::assertStyle('height: 30px', $span);

        $this->style->clearStylesheets($document);

        self::assertStyle('', $div);
        self::assertStyle('', $span);
    }

    public function testStyleElementReinsertAfterDestroyAllReattachesStylesheet(): void
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
        self::assertStyle('width: 20px', $div);

        $this->style->clearStylesheets($document);
        self::assertStyle('', $div);

        $styleElement->remove();
        self::assertSame(ExceptionCode::Ok, $div->insertAfter($styleElement));

        self::assertStyle('width: 20px', $div);
    }

    public function testUpstreamBadStyleTagDoesNotApplyRules(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse('<div class=father></div><style>div.father</style>'),
        );

        $div = $document->elementsByTagName('div')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertStyle('', $div);
    }

    public function testUpstreamStyleTagIgnoresNonHtmlNamespaceStyleElements(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse(
                "<style>.father {width: 120px}</style>"
                . "<div class=father><svg style='width: 10em'>"
                . "<style>.father {height: 380px}</style>"
                . "<path style='width: 50%' class=father></svg></div>",
            ),
        );

        $div = $document->elementsByTagName('div')[0] ?? null;

        self::assertInstanceOf(Element::class, $div);
        self::assertStyle('width: 120px', $div);
    }

    private function assertStyle(string $expected, Element $element): void
    {
        self::assertSame($expected, $this->style->serializeElementStyle($element));
    }
}

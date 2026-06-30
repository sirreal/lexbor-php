<?php

declare(strict_types=1);

namespace Lexbor\Tests\Style;

use Lexbor\Core\Status;
use Lexbor\Dom\Element;
use Lexbor\Dom\ExceptionCode;
use Lexbor\Html\Document;
use Lexbor\Style\StyleEngine;
use PHPUnit\Framework\TestCase;

final class ElementEventsTest extends TestCase
{
    private StyleEngine $style;

    protected function setUp(): void
    {
        $this->style = new StyleEngine();
    }

    public function testUpstreamElementStyleLifecycle(): void
    {
        $document = new Document();
        self::assertSame(
            Status::Ok,
            $document->parse(
                '<div class=father>'
                . "<p class=best'>a</p>"
                . '<p>b</p>'
                . '<s>c</s>'
                . '</div>',
            ),
        );
        $this->style->attachStylesheet(
            $document,
            'div.father {width: 30%} div.father p.best {width: 20px; height: 10pt}',
        );

        $div = $document->elementsByTagName('div')[0] ?? null;
        self::assertInstanceOf(Element::class, $div);

        $paragraph = $document->createElement('p');
        $paragraph->setAttribute('class', 'best');
        $paragraph->setAttribute('style', 'height: 100px');

        self::assertSame(ExceptionCode::Ok, $div->appendChild($paragraph));
        self::assertStyle('height: 100px; width: 20px', $paragraph);

        $paragraph->removeAttribute('style');
        self::assertStyle('height: 10pt; width: 20px', $paragraph);

        $paragraph->setAttribute('style', 'height: 100px');
        self::assertStyle('height: 100px; width: 20px', $paragraph);

        $paragraph->remove();
        self::assertStyle('height: 100px', $paragraph);

        self::assertSame(ExceptionCode::Ok, $div->appendChild($paragraph));
        self::assertStyle('height: 100px; width: 20px', $paragraph);
    }

    private function assertStyle(string $expected, Element $element): void
    {
        self::assertSame($expected, $this->style->serializeElementStyle($element));
    }
}

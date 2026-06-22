<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Attr;
use Lexbor\Html\Document;
use Lexbor\Html\Serializer;
use PHPUnit\Framework\TestCase;

final class AttributesTest extends TestCase
{
    public function testAttributes(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<div id=my-best-id></div>'));

        $body = $document->bodyElement();
        $collection = $body->elementsByTagName('div');

        self::assertNotSame([], $collection);

        $element = $collection[0];
        self::assertSame('my-best-id', $element->getAttribute('id'));

        foreach (['id', 'class', 'some'] as $name) {
            $attr = $element->setAttribute($name, 'oh God');
            self::assertInstanceOf(Attr::class, $attr);

            self::assertTrue($element->hasAttribute($name));
            self::assertSame('oh God', $element->getAttribute($name));

            $attr = $element->attrByName($name);
            self::assertInstanceOf(Attr::class, $attr);
            self::assertSame(Status::Ok, $attr->setValue('new value'));
            self::assertSame('new value', $element->getAttribute($name));

            $element->removeAttribute($name);
            self::assertFalse($element->hasAttribute($name));
            self::assertNull($element->getAttribute($name));
        }
    }

    public function testParserFixtureSupportsBooleanAttributes(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<div class id></div>'));

        $element = $document->bodyElement()->elementsByTagName('div')[0];

        self::assertTrue($element->hasAttribute('class'));
        self::assertSame('', $element->getAttribute('class'));
        self::assertTrue($element->hasAttribute('id'));
        self::assertSame('', $element->getAttribute('id'));
    }

    public function testParserFixtureAllowsGreaterThanInQuotedAttributeValue(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<div title="a>b"></div>'));

        $element = $document->bodyElement()->elementsByTagName('div')[0];

        self::assertSame('a>b', $element->getAttribute('title'));
    }

    public function testParserFixtureKeepsFirstDuplicateAttribute(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<div id=first id=second ID=third></div>'));

        $element = $document->bodyElement()->elementsByTagName('div')[0];

        self::assertSame('first', $element->getAttribute('id'));
    }

    public function testParserFixtureDecodesCharacterReferencesInAttributeValues(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse("<div title=\"&amp;&lt;&gt;&quot;\u{00A0}\"></div>"));

        $element = $document->bodyElement()->elementsByTagName('div')[0];

        self::assertSame("&<>\"\u{00A0}", $element->getAttribute('title'));
    }

    public function testAttributeSerializationDoesNotEscapeSingleQuote(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<div title="&apos;"></div>'));

        $element = $document->bodyElement()->elementsByTagName('div')[0];

        self::assertSame("'", $element->getAttribute('title'));
        self::assertSame('<div title="\'"></div>', Serializer::serialize($element));
    }

    public function testDetachedAttrDoesNotRecreateRemovedAttribute(): void
    {
        $document = new Document();
        $element = $document->createElement('div');
        $attr = $element->setAttribute('id', 'before');

        $element->removeAttribute('id');
        self::assertSame(Status::Ok, $attr->setValue('after'));

        self::assertFalse($element->hasAttribute('id'));
        self::assertNull($element->getAttribute('id'));
    }
}

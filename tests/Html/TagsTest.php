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

    public function testUpstreamTagResourceNames(): void
    {
        $registry = new TagRegistry();

        foreach (self::upstreamTagResourceNames() as $name) {
            $tagId = $registry->idForName($name);

            self::assertLessThan(Tag::LAST_ENTRY, $tagId, "Tag resource lookup allocated dynamic ID for {$name}");
            self::assertSame($name, TagRegistry::nameById($tagId), "Tag resource lookup failed for {$name}");
        }
    }

    /**
     * @return list<string>
     */
    private static function upstreamTagResourceNames(): array
    {
        return [
            '!--',
            '!doctype',
            '#document',
            '#end-of-file',
            '#text',
            '#undef',
            'a',
            'abbr',
            'acronym',
            'address',
            'altglyph',
            'altglyphdef',
            'altglyphitem',
            'animatecolor',
            'animatemotion',
            'animatetransform',
            'annotation-xml',
            'applet',
            'area',
            'article',
            'aside',
            'audio',
            'b',
            'base',
            'basefont',
            'bdi',
            'bdo',
            'bgsound',
            'big',
            'blink',
            'blockquote',
            'body',
            'br',
            'button',
            'canvas',
            'caption',
            'center',
            'cite',
            'clippath',
            'code',
            'col',
            'colgroup',
            'data',
            'datalist',
            'dd',
            'del',
            'desc',
            'details',
            'dfn',
            'dialog',
            'dir',
            'div',
            'dl',
            'dt',
            'em',
            'embed',
            'feblend',
            'fecolormatrix',
            'fecomponenttransfer',
            'fecomposite',
            'feconvolvematrix',
            'fediffuselighting',
            'fedisplacementmap',
            'fedistantlight',
            'fedropshadow',
            'feflood',
            'fefunca',
            'fefuncb',
            'fefuncg',
            'fefuncr',
            'fegaussianblur',
            'feimage',
            'femerge',
            'femergenode',
            'femorphology',
            'feoffset',
            'fepointlight',
            'fespecularlighting',
            'fespotlight',
            'fetile',
            'feturbulence',
            'fieldset',
            'figcaption',
            'figure',
            'font',
            'footer',
            'foreignobject',
            'form',
            'frame',
            'frameset',
            'glyphref',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'head',
            'header',
            'hgroup',
            'hr',
            'html',
            'i',
            'iframe',
            'image',
            'img',
            'input',
            'ins',
            'isindex',
            'kbd',
            'keygen',
            'label',
            'legend',
            'li',
            'lineargradient',
            'link',
            'listing',
            'main',
            'malignmark',
            'map',
            'mark',
            'marquee',
            'math',
            'menu',
            'meta',
            'meter',
            'mfenced',
            'mglyph',
            'mi',
            'mn',
            'mo',
            'ms',
            'mtext',
            'multicol',
            'nav',
            'nextid',
            'nobr',
            'noembed',
            'noframes',
            'noscript',
            'object',
            'ol',
            'optgroup',
            'option',
            'output',
            'p',
            'param',
            'path',
            'picture',
            'plaintext',
            'pre',
            'progress',
            'q',
            'radialgradient',
            'rb',
            'rp',
            'rt',
            'rtc',
            'ruby',
            's',
            'samp',
            'script',
            'search',
            'section',
            'select',
            'selectedcontent',
            'slot',
            'small',
            'source',
            'spacer',
            'span',
            'strike',
            'strong',
            'style',
            'sub',
            'summary',
            'sup',
            'svg',
            'table',
            'tbody',
            'td',
            'template',
            'textarea',
            'textpath',
            'tfoot',
            'th',
            'thead',
            'time',
            'title',
            'tr',
            'track',
            'tt',
            'u',
            'ul',
            'var',
            'video',
            'wbr',
            'xmp',
        ];
    }
}

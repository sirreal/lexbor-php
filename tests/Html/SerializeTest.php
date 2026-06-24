<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Dom\Comment;
use Lexbor\Dom\Text;
use Lexbor\Html\Document;
use Lexbor\Html\Serializer;
use Lexbor\Html\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SerializeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function upstreamSerializeExtAttributeEntityProvider(): iterable
    {
        yield 'attributes.ton #6 ampersand' => ['<div title="a&amp;b"></div>', '<div title="a&amp;b"></div>'];
        yield 'attributes.ton #7 less-than' => ['<div title="a&lt;b"></div>', '<div title="a&lt;b"></div>'];
        yield 'attributes.ton #8 greater-than' => ['<div title="a&gt;b"></div>', '<div title="a&gt;b"></div>'];
        yield 'attributes.ton #9 quote' => ['<div title="a&quot;b"></div>', '<div title="a&quot;b"></div>'];
        yield 'attributes.ton #10 no-break space' => ["<div title=\"a\u{00A0}b\"></div>", '<div title="a&nbsp;b"></div>'];
        yield 'attributes.ton #11 mixed entities' => ['<div title="&amp;&lt;&gt;&quot;"></div>', '<div title="&amp;&lt;&gt;&quot;"></div>'];
        yield 'attributes.ton #12 entity at edges' => ['<div title="&amp;hello&amp;"></div>', '<div title="&amp;hello&amp;"></div>'];
        yield 'attributes.ton #13 ampersand only' => ['<div title="&amp;"></div>', '<div title="&amp;"></div>'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function upstreamSerializeExtCommentProvider(): iterable
    {
        yield 'comment.ton #1 simple comment' => ['<div><!-- hello --></div>', '<div><!-- hello --></div>'];
        yield 'comment.ton #2 empty comment' => ['<div><!----></div>', '<div><!----></div>'];
        yield 'comment.ton #3 space comment' => ['<div><!-- --></div>', '<div><!-- --></div>'];
        yield 'comment.ton #4 raw markup characters' => ['<div><!-- <div> & "quote" --></div>', '<div><!-- <div> & "quote" --></div>'];
        yield 'comment.ton #5 greater-than character' => ['<div><!-- a > b --></div>', '<div><!-- a > b --></div>'];
        yield 'comment.ton #6 multiline comment' => ["<div><!-- line1\nline2 --></div>", "<div><!-- line1\nline2 --></div>"];
        yield 'comment.ton #7 top-level comment' => ['x<!-- top level -->', 'x<!-- top level -->'];
        yield 'comment.ton #8 adjacent comments' => ['<div><!-- a --><!-- b --><!-- c --></div>', '<div><!-- a --><!-- b --><!-- c --></div>'];
        yield 'comment.ton #9 comment around text' => ['<div><!-- before -->text<!-- after --></div>', '<div><!-- before -->text<!-- after --></div>'];
        yield 'comment.ton #10 long comment' => [
            '<div><!-- This is a very long comment that goes on and on and on and contains lots of text to test serialization of lengthy comment nodes --></div>',
            '<div><!-- This is a very long comment that goes on and on and on and contains lots of text to test serialization of lengthy comment nodes --></div>',
        ];
        yield 'comment.ton #26 nested comments' => [
            '<div><!-- L1 --><p><!-- L2 --><span><!-- L3 --></span></p></div>',
            '<div><!-- L1 --><p><!-- L2 --><span><!-- L3 --></span></p></div>',
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function upstreamSerializeExtTextEntityProvider(): iterable
    {
        yield 'text.ton #1 paragraph text' => ['<p>hello</p>', '<p>hello</p>'];
        yield 'text.ton #2 paragraph words' => ['<p>hello world</p>', '<p>hello world</p>'];
        yield 'text.ton #3 top-level text' => ['just text', 'just text'];
        yield 'text.ton #4 ampersand' => ['<p>a &amp; b</p>', '<p>a &amp; b</p>'];
        yield 'text.ton #5 less-than' => ['<p>a &lt; b</p>', '<p>a &lt; b</p>'];
        yield 'text.ton #6 greater-than' => ['<p>a &gt; b</p>', '<p>a &gt; b</p>'];
        yield 'text.ton #7 no-break space' => ["<p>a\u{00A0}b</p>", '<p>a&nbsp;b</p>'];
        yield 'text.ton #8 spaced entities' => ['<p>&amp; &lt; &gt;</p>', '<p>&amp; &lt; &gt;</p>'];
        yield 'text.ton #9 adjacent entities' => ['<p>&amp;&lt;&gt;</p>', '<p>&amp;&lt;&gt;</p>'];
        yield 'text.ton #10 edge no-break spaces' => ["<p>\u{00A0}hello\u{00A0}</p>", '<p>&nbsp;hello&nbsp;</p>'];
        yield 'text.ton #11 only no-break spaces' => ["<p>\u{00A0}\u{00A0}\u{00A0}</p>", '<p>&nbsp;&nbsp;&nbsp;</p>'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokenizerCharacterReferenceProvider(): iterable
    {
        yield 'uppercase ampersand alias with semicolon' => ['<p>&AMP;</p>', '<p>&amp;</p>'];
        yield 'uppercase less-than alias with semicolon' => ['<p>&LT;</p>', '<p>&lt;</p>'];
        yield 'multi-codepoint named reference' => ['<p>&NotEqualTilde;</p>', '<p>≂̸</p>'];
        yield 'text ampersand without semicolon' => ['<p>&amp</p>', '<p>&amp;</p>'];
        yield 'text copyright without semicolon' => ['<p>&copy </p>', '<p>© </p>'];
        yield 'text windows-1252 numeric replacement' => ['<p>&#x80;</p>', '<p>€</p>'];
        yield 'text failed semicolon name falls back to amp prefix' => ['<p>&ampx;</p>', '<p>&amp;x;</p>'];
        yield 'text failed semicolon name falls back to copy prefix' => ['<p>&copyx;</p>', '<p>©x;</p>'];
        yield 'text failed semicolon name falls back to not prefix' => ['<p>&notit;</p>', '<p>¬it;</p>'];
        yield 'attribute ampersand without semicolon' => ['<div title="&amp"></div>', '<div title="&amp;"></div>'];
        yield 'attribute ambiguous ampersand' => ['<div title="&ampx"></div>', '<div title="&amp;ampx"></div>'];
        yield 'char_ref.ton CRLF is normalized to LF' => ["<p>\r\n</p>", "<p>\n</p>"];
        yield 'char_ref.ton CR CRLF is normalized to two LFs' => ["<p>\r\r\n</p>", "<p>\n\n</p>"];
        yield 'char_ref.ton LF CR is normalized to two LFs' => ["<p>\n\r</p>", "<p>\n\n</p>"];
        yield 'char_ref.ton comment CRLF is normalized to LF' => ["<div><!--\r\n--></div>", "<div><!--\n--></div>"];
        yield 'char_ref.ton raw text CRLF is normalized to LF' => ["<script>\r\n</script>", "<script>\n</script>"];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function upstreamSerializeExtElementProvider(): iterable
    {
        yield 'element.ton #1 div' => ['<div></div>', '<div></div>'];
        yield 'element.ton #2 span' => ['<span></span>', '<span></span>'];
        yield 'element.ton #3 self-closing div' => ['<div/>', '<div></div>'];
        yield 'element.ton #4 br' => ['<br>', '<br>'];
        yield 'element.ton #5 hr' => ['<hr>', '<hr>'];
        yield 'element.ton #6 img' => ['<img src="x.png">', '<img src="x.png">'];
        yield 'element.ton #7 input' => ['<input type="text">', '<input type="text">'];
        yield 'element.ton #8 area' => ['<area shape="rect">', '<area shape="rect">'];
        yield 'element.ton #10 embed' => ['<embed type="text/plain">', '<embed type="text/plain">'];
        yield 'element.ton #11 source' => ['<video><source src="a.mp3"></video>', '<video><source src="a.mp3"></video>'];
        yield 'element.ton #12 track' => ['<video><track src="t.vtt"></video>', '<video><track src="t.vtt"></video>'];
        yield 'element.ton #13 wbr' => ['<wbr>', '<wbr>'];
        yield 'element.ton #14 nested voids' => ['<div><br><hr><wbr><br></div>', '<div><br><hr><wbr><br></div>'];
        yield 'element.ton #15 img attributes' => ['<img src="a.png" alt="" width="100" height="50">', '<img src="a.png" alt="" width="100" height="50">'];
        yield 'element.ton #16 deep sectioning' => [
            '<div><section><article><main><aside><nav><header><footer>x</footer></header></nav></aside></main></article></section></div>',
            '<div><section><article><main><aside><nav><header><footer>x</footer></header></nav></aside></main></article></section></div>',
        ];
        yield 'element.ton #17 sibling phrasing' => [
            '<a href="#">a</a><b>b</b><i>i</i><u>u</u><s>s</s>',
            '<a href="#">a</a><b>b</b><i>i</i><u>u</u><s>s</s>',
        ];
        yield 'element.ton #18 mixed text and element' => [
            '<div>before<span>middle</span>after</div>',
            '<div>before<span>middle</span>after</div>',
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function upstreamLegacyVoidElementProvider(): iterable
    {
        yield 'node.h basefont' => ['basefont'];
        yield 'node.h bgsound' => ['bgsound'];
        yield 'node.h frame' => ['frame'];
        yield 'node.h keygen' => ['keygen'];
        yield 'node.h param' => ['param'];
    }

    #[DataProvider('upstreamSerializeExtAttributeEntityProvider')]
    public function testUpstreamSerializeExtAttributeEntityFixtures(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('upstreamSerializeExtCommentProvider')]
    public function testUpstreamSerializeExtCommentFixtures(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('upstreamSerializeExtTextEntityProvider')]
    public function testUpstreamSerializeExtTextEntityFixtures(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('tokenizerCharacterReferenceProvider')]
    public function testTokenizerCharacterReferenceRegressions(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('upstreamSerializeExtElementProvider')]
    public function testUpstreamSerializeExtElementFixtures(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('upstreamLegacyVoidElementProvider')]
    public function testUpstreamLegacyVoidElementSerializesWithoutClosingTag(string $tagName): void
    {
        $document = new Document();
        $element = $document->createElement($tagName);
        $element->appendChild($document->createTextNode('ignored'));

        self::assertSame(sprintf('<%s>', $tagName), Serializer::serialize($element));
    }

    public function testTextNodeWithoutParent(): void
    {
        $document = new Document();
        $text = $document->createTextNode('abc');

        self::assertInstanceOf(Text::class, $text);
        self::assertSame(Tag::TEXT, $text->localName);
        self::assertNull($text->parent);

        self::assertSame('abc', Serializer::serialize($text));
        self::assertSame('"abc"' . "\n", Serializer::serializePretty($text));
    }

    public function testTextNodeCreatedByTagInterface(): void
    {
        $document = new Document();
        $text = $document->createInterfaceByTagId(Tag::TEXT);

        self::assertInstanceOf(Text::class, $text);
        self::assertSame('', $text->data);
    }

    public function testCommentNodeCreatedByTagInterface(): void
    {
        $document = new Document();
        $comment = $document->createInterfaceByTagId(Tag::EM_COMMENT);

        self::assertInstanceOf(Comment::class, $comment);
        self::assertSame('', $comment->data);

        $comment->data = 'edited';

        self::assertSame('<!--edited-->', Serializer::serialize($comment));
    }

    public function testTextNodeSerializesEscapedMarkupWhenAppended(): void
    {
        $document = new Document();
        $body = $document->bodyElement();

        $body->appendChild($document->createTextNode('a < b & c > d'));

        self::assertSame('a &lt; b &amp; c &gt; d', Serializer::serializeDeep($body));
    }

    public function testRawTextParserKeepsCharacterReferencesLiteral(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<script>a &amp; b</script>'));

        self::assertSame('<script>a &amp; b</script>', Serializer::serializeDeep($document->bodyElement()));
    }

    public function testTextNodeSerializesNoBreakSpaceAsEntity(): void
    {
        $document = new Document();

        self::assertSame('a&nbsp;b', Serializer::serialize($document->createTextNode("a\u{00A0}b")));
        self::assertSame('"a&nbsp;b"' . "\n", Serializer::serializePretty($document->createTextNode("a\u{00A0}b")));
    }

    public function testTextNodeUnderRawTextParentDoesNotEscapeMarkup(): void
    {
        $document = new Document();
        $script = $document->createElement('script');
        $script->appendChild($document->createTextNode('if (a < b && c > d) {}'));

        self::assertSame('<script>if (a < b && c > d) {}</script>', Serializer::serialize($script));
        self::assertSame('"if (a < b && c > d) {}"' . "\n", Serializer::serializePretty($script->firstChild));
    }

    public function testNoscriptTextDependsOnScriptingFlag(): void
    {
        $document = new Document();
        $noscript = $document->createElement('noscript');
        $noscript->appendChild($document->createTextNode('a < b'));

        self::assertSame('<noscript>a &lt; b</noscript>', Serializer::serialize($noscript));

        $document->setScriptingEnabled(true);

        self::assertSame('<noscript>a < b</noscript>', Serializer::serialize($noscript));
    }

    public function testProgrammaticVoidElementSerializesWithoutClosingTag(): void
    {
        $document = new Document();
        $br = $document->createElement('br');
        $br->appendChild($document->createTextNode('ignored'));

        self::assertSame('<br>', Serializer::serialize($br));
    }
}

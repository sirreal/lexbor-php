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
    public static function upstreamSerializeExtProcessingInstructionProvider(): iterable
    {
        yield 'processing_instruction.ton #1 xml instruction becomes comment' => [
            '<div><?xml version="1.0"?></div>',
            '<div><!--?xml version="1.0"?--></div>',
        ];
        yield 'processing_instruction.ton #2 php instruction becomes comment' => [
            "<div><?php echo 'hello'; ?></div>",
            "<div><!--?php echo 'hello'; ?--></div>",
        ];
        yield 'processing_instruction.ton #5 adjacent instructions become comments' => [
            '<div><?xml version="1.0"?><?php echo 1; ?></div>',
            '<div><!--?xml version="1.0"?--><!--?php echo 1; ?--></div>',
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
     * @return iterable<string, array{string, string, bool}>
     */
    public static function upstreamSerializeExtDocumentTypeProvider(): iterable
    {
        yield 'document_type.ton #1 html doctype document' => [
            '<!DOCTYPE html><html><head></head><body></body></html>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'document_type.ton #2 html doctype with body content' => [
            '<!DOCTYPE html><html><head></head><body><p>text</p></body></html>',
            '<!DOCTYPE html><html><head></head><body><p>text</p></body></html>',
            false,
        ];
        yield 'document_type.ton #3 full plain html doctype' => [
            '<!DOCTYPE html><html><head></head><body></body></html>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            true,
        ];
        yield 'document_type.ton #4 full xhtml strict doctype' => [
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html><head></head><body></body></html>',
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html><head></head><body></body></html>',
            true,
        ];
        yield 'document_type.ton #5 full xhtml transitional doctype' => [
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html><head></head><body></body></html>',
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html><head></head><body></body></html>',
            true,
        ];
        yield 'document_type.ton #6 full html 4 strict doctype' => [
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"><html><head></head><body></body></html>',
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"><html><head></head><body></body></html>',
            true,
        ];
        yield 'document_type.ton #7 full html 4 transitional doctype' => [
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"><html><head></head><body></body></html>',
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd"><html><head></head><body></body></html>',
            true,
        ];
        yield 'document_type.ton #8 full html 4 frameset doctype' => [
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd"><html><head></head><body></body></html>',
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd"><html><head></head><body></body></html>',
            true,
        ];
        yield 'document_type.ton #9 full public doctype without system id' => [
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0//EN"><html><head></head><body></body></html>',
            '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0//EN"><html><head></head><body></body></html>',
            true,
        ];
        yield 'document_type.ton #10 full system doctype' => [
            '<!DOCTYPE html SYSTEM "about:legacy-compat"><html><head></head><body></body></html>',
            '<!DOCTYPE html SYSTEM "about:legacy-compat"><html><head></head><body></body></html>',
            true,
        ];
        yield 'document_type.ton #11 default xhtml strict doctype' => [
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html><head></head><body></body></html>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'document_type.ton #12 default system doctype' => [
            '<!DOCTYPE html SYSTEM "about:legacy-compat"><html><head></head><body></body></html>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'document_type.ton #17 document without doctype' => [
            '<html><head></head><body><p>no doctype</p></body></html>',
            '<html><head></head><body><p>no doctype</p></body></html>',
            false,
        ];
        yield 'document_type.ton #18 repeated html doctype document' => [
            '<!DOCTYPE html><html><head></head><body></body></html>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function tokenizerDoctypeProvider(): iterable
    {
        yield 'doctype.ton #4 html name' => [
            '<!DOCTYPE html>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #5 public identifier with double quotes' => [
            '<!DOCTYPE html PUBLIC "test public">',
            '<!DOCTYPE html PUBLIC "test public"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #6 public identifier with single quotes' => [
            "<!DOCTYPE html PUBLIC 'test public'>",
            '<!DOCTYPE html PUBLIC "test public"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #7 public and system identifiers' => [
            '<!DOCTYPE html PUBLIC "test public" "system identifier">',
            '<!DOCTYPE html PUBLIC "test public" "system identifier"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #8 system identifier with double quotes' => [
            '<!DOCTYPE html SYSTEM "test system">',
            '<!DOCTYPE html SYSTEM "test system"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #9 system identifier with single quotes' => [
            "<!DOCTYPE html SYSTEM 'test system'>",
            '<!DOCTYPE html SYSTEM "test system"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #10 EOF after non-html name' => [
            '<!DOCTYPE htm',
            '<!DOCTYPE htm><html><head></head><body></body></html>',
            true,
        ];
        yield 'doctype.ton #11 non-html name' => [
            '<!DOCTYPE htm>',
            '<!DOCTYPE htm><html><head></head><body></body></html>',
            true,
        ];
        yield 'doctype.ton #12 EOF in single-quoted public identifier' => [
            "<!DOCTYPE html PUBLIC 'test publi",
            '<!DOCTYPE html PUBLIC "test publi"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #13 duplicate EOF in single-quoted public identifier' => [
            "<!DOCTYPE html PUBLIC 'test publi",
            '<!DOCTYPE html PUBLIC "test publi"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #14 EOF in system identifier after public identifier' => [
            '<!DOCTYPE html PUBLIC \'test public\' "test syst',
            '<!DOCTYPE html PUBLIC "test public" "test syst"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #15 missing whitespace before EOF system identifier' => [
            '<!DOCTYPE html PUBLIC \'test public\'"test syst',
            '<!DOCTYPE html PUBLIC "test public" "test syst"><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #16 EOF after public keyword' => [
            '<!DOCTYPE html PUBLIC ',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'doctype.ton #17 EOF after opening public quote' => [
            '<!DOCTYPE html PUBLIC "',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokenizerCharacterReferenceProvider(): iterable
    {
        yield 'char_ref.ton #1 decimal reference before text' => ['abc&#1234cde', 'abcӒcde'];
        yield 'char_ref.ton #2 named reference with semicolon' => ['&AElig;', 'Æ'];
        yield 'char_ref.ton #3 legacy named reference without semicolon' => ['&AElig', 'Æ'];
        yield 'char_ref.ton #4 unknown named reference remains literal' => ['&AEli', '&amp;AEli'];
        yield 'char_ref.ton #5 adjacent named references' => ['&AElig;&Afr;', 'Æ𝔄'];
        yield 'char_ref.ton #6 failed legacy prefix before named reference' => ['&AElit&Afr;', '&amp;AElit𝔄'];
        yield 'char_ref.ton #7 named references separated by space' => ['&AElig; &Afr;', 'Æ 𝔄'];
        yield 'char_ref.ton #8 legacy named reference before space and semicolon' => ['&AElig ; &Afr;', 'Æ ; 𝔄'];
        yield 'char_ref.ton #9 legacy named reference before letters' => ['&AEligqwe', 'Æqwe'];
        yield 'char_ref.ton #10 legacy named reference before digits' => ['&AElig123', 'Æ123'];
        yield 'char_ref.ton #11 text before legacy named reference' => ['AElig&AEligAElig', 'AEligÆAElig'];
        yield 'char_ref.ton #12 down arrow reference' => ['&DownArrow;', '↓'];
        yield 'char_ref.ton #13 double down arrow reference' => ['&Downarrow;', '⇓'];
        yield 'char_ref.ton #14 down arrow bar reference' => ['&DownArrowBar;', '⤓'];
        yield 'char_ref.ton #15 named reference broken by space remains literal' => ['&DownArrow Bar;', '&amp;DownArrow Bar;'];
        yield 'char_ref.ton #16 multi-codepoint named reference' => ['&nLt;', '≪⃒'];
        yield 'char_ref.ton #17 multi-codepoint named reference before text' => ['&nLt;a', '≪⃒a'];
        yield 'char_ref.ton #18 text before multi-codepoint named reference' => ['b&nLt;a', 'b≪⃒a'];
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
    public static function tokenizerCommentProvider(): iterable
    {
        yield 'comment.ton #1 space comment' => ['<div><!-- --></div>', '<div><!-- --></div>'];
        yield 'comment.ton #2 repeated hyphen comment' => ['<div><!-------></div>', '<div><!-------></div>'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokenizerTagNameProvider(): iterable
    {
        yield 'tag_name.ton #1 NUL in middle of tag name' => ["<sEf\0sTf>", "<sef\u{FFFD}stf></sef\u{FFFD}stf>"];
        yield 'tag_name.ton #2 NUL at end of tag name' => ["<sEf\0>", "<sef\u{FFFD}></sef\u{FFFD}>"];
        yield 'tag_name.ton #3 NUL after first character' => ["<s\0Ef>", "<s\u{FFFD}ef></s\u{FFFD}ef>"];
        yield 'tag_name.ton #4 single-letter tag plus NUL' => ["<s\0>", "<s\u{FFFD}></s\u{FFFD}>"];
        yield 'tag_name.ton #5 repeated NULs in tag name' => ["<sEf\0\0\0sTf>", "<sef\u{FFFD}\u{FFFD}\u{FFFD}stf></sef\u{FFFD}\u{FFFD}\u{FFFD}stf>"];
        yield 'tag_name.ton #6 uppercase ASCII is lowercased' => ['<AAAAAA-aa>', '<aaaaaa-aa></aaaaaa-aa>'];
        yield 'body pre-scan does not consume NUL-suffixed body tag' => ["<body\0>x</body\0>", "<body\u{FFFD}>x</body\u{FFFD}>"];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tokenizerTagAttributeProvider(): iterable
    {
        yield 'tag_attr.ton #1 attribute name is lowercased' => [
            '<div SuperNaME=SuPerValue>',
            '<div supername="SuPerValue"></div>',
        ];
        yield 'tag_attr.ton #2 NUL in attribute name is replaced' => [
            "<div \0SuperNaME=SuPerValue>",
            "<div \u{FFFD}supername=\"SuPerValue\"></div>",
        ];
        yield 'tag_attr.ton #3 named attribute reference with semicolon' => [
            "<div n='&DownArrowBar;'>",
            '<div n="⤓"></div>',
        ];
        yield 'tag_attr.ton #4 attribute ambiguous ampersand before equals' => [
            "<div n='&acirc='>",
            '<div n="&amp;acirc="></div>',
        ];
        yield 'tag_attr.ton #5 unknown reference remains literal' => [
            "<div n='&acircr'>",
            '<div n="&amp;acircr"></div>',
        ];
        yield 'tag_attr.ton #6 legacy reference without semicolon' => [
            "<div n='&acirc'>",
            '<div n="â"></div>',
        ];
        yield 'tag_attr.ton #7 unknown query ampersand remains literal' => [
            "<div n='/ololo/?arg=1&redirect=123'>",
            '<div n="/ololo/?arg=1&amp;redirect=123"></div>',
        ];
        yield 'tag_attr.ton #8 legacy query reference without semicolon' => [
            "<div n='/ololo/?arg=1&acirc'>",
            '<div n="/ololo/?arg=1â"></div>',
        ];
        yield 'tag_attr.ton #9 short unknown reference acir remains literal' => [
            "<div n='&acir'>",
            '<div n="&amp;acir"></div>',
        ];
        yield 'tag_attr.ton #10 short unknown reference aci remains literal' => [
            "<div n='&aci'>",
            '<div n="&amp;aci"></div>',
        ];
        yield 'tag_attr.ton #11 short unknown reference ac remains literal' => [
            "<div n='&ac'>",
            '<div n="&amp;ac"></div>',
        ];
        yield 'tag_attr.ton #12 short unknown reference a remains literal' => [
            "<div n='&a'>",
            '<div n="&amp;a"></div>',
        ];
        yield 'tag_attr.ton #13 bare ampersand remains literal' => [
            "<div n='&'>",
            '<div n="&amp;"></div>',
        ];
        yield 'trailing NUL in boolean attribute name is replaced' => [
            "<div disabled\0>",
            "<div disabled\u{FFFD}=\"\"></div>",
        ];
        yield 'single NUL boolean attribute name is preserved' => [
            "<div \0>",
            "<div \u{FFFD}=\"\"></div>",
        ];
        yield 'trailing NUL survives self-closing slash removal' => [
            "<div disabled\0/>",
            "<div disabled\u{FFFD}=\"\"></div>",
        ];
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

    #[DataProvider('upstreamSerializeExtProcessingInstructionProvider')]
    public function testUpstreamSerializeExtProcessingInstructionFixtures(string $html, string $expected): void
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

    #[DataProvider('upstreamSerializeExtDocumentTypeProvider')]
    public function testUpstreamSerializeExtDocumentTypeFixtures(string $html, string $expected, bool $fullDoctype): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document, fullDoctype: $fullDoctype));
    }

    public function testRemovedParsedDocumentTypeIsNotSerialized(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<!DOCTYPE html><html><head></head><body><p>x</p></body></html>'));

        $doctype = $document->documentType();
        self::assertNotNull($doctype);

        $doctype->remove();

        self::assertNull($document->documentType());
        self::assertSame('<html><head></head><body><p>x</p></body></html>', Serializer::serializeDeep($document));
    }

    public function testWholeDocumentSerializationPreservesParsedBodyAttributes(): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse('<!DOCTYPE html><body class=x data-id=1><p>t</p></body>'));

        self::assertSame(
            '<!DOCTYPE html><html><head></head><body class="x" data-id="1"><p>t</p></body></html>',
            Serializer::serializeDeep($document),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyDocumentTypeIdentifierProvider(): iterable
    {
        yield 'empty system identifier' => ['<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>'];
        yield 'empty public identifier' => ['<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>'];
    }

    #[DataProvider('emptyDocumentTypeIdentifierProvider')]
    public function testFullDocumentTypeSerializationOmitsEmptyIdentifiers(string $html): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame(
            '<!DOCTYPE html><html><head></head><body></body></html>',
            Serializer::serializeDeep($document, fullDoctype: true),
        );
    }

    #[DataProvider('tokenizerDoctypeProvider')]
    public function testTokenizerDoctypeRegressions(string $html, string $expected, bool $quirksMode): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($quirksMode, $document->isQuirksMode());
        self::assertSame($expected, Serializer::serializeDeep($document, fullDoctype: true));
    }

    #[DataProvider('tokenizerCharacterReferenceProvider')]
    public function testTokenizerCharacterReferenceRegressions(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('tokenizerCommentProvider')]
    public function testTokenizerCommentRegressions(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('tokenizerTagNameProvider')]
    public function testTokenizerTagNameRegressions(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('tokenizerTagAttributeProvider')]
    public function testTokenizerTagAttributeRegressions(string $html, string $expected): void
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

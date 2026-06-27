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
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'html5lib test2 doctype without space before html name' => [
            '<!DOCTYPEhtml>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'html5lib test2 doctype without space before non-html name' => [
            '<!DOCTYPEfoo>',
            '<!DOCTYPE foo><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 NUL after doctype keyword' => [
            "<!DOCTYPE \0>",
            "<!DOCTYPE \u{FFFD}><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 NUL in doctype name at EOF' => [
            "<!DOCTYPE a\0",
            "<!DOCTYPE a\u{FFFD}><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 NUL in doctype name without whitespace' => [
            "<!DOCTYPEa\0>",
            "<!DOCTYPE a\u{FFFD}><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 vertical tab after doctype keyword is name at EOF' => [
            "<!DOCTYPE\v",
            "<!DOCTYPE \v><html><head></head><body></body></html>",
            true,
        ];
        yield 'vertical tab after doctype keyword is name before invalid sequence' => [
            "<!DOCTYPE\v html>",
            "<!DOCTYPE \v><html><head></head><body></body></html>",
            true,
        ];
        yield 'vertical tab before public-looking text remains in no-whitespace name' => [
            "<!DOCTYPE\vPUBLIC \"p\">",
            "<!DOCTYPE \vpublic><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 vertical tab after doctype keyword whitespace is name at EOF' => [
            "<!DOCTYPE \v",
            "<!DOCTYPE \v><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 vertical tab in doctype name at EOF' => [
            "<!DOCTYPE a\v",
            "<!DOCTYPE a\v><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 vertical tab in no-whitespace doctype name at EOF' => [
            "<!DOCTYPEa\v",
            "<!DOCTYPE a\v><html><head></head><body></body></html>",
            true,
        ];
        yield 'vertical tab in html doctype name forces quirks' => [
            "<!DOCTYPE html\v",
            "<!DOCTYPE html\v><html><head></head><body></body></html>",
            true,
        ];
        yield 'vertical tab before public keyword remains in doctype name' => [
            "<!DOCTYPE html\vPUBLIC \"p\"",
            "<!DOCTYPE html\vpublic><html><head></head><body></body></html>",
            true,
        ];
        yield 'vertical tab before system keyword remains in doctype name' => [
            "<!DOCTYPE html\vSYSTEM \"s\"",
            "<!DOCTYPE html\vsystem><html><head></head><body></body></html>",
            true,
        ];
        yield 'vertical tab before EOF public keyword remains in doctype name' => [
            "<!DOCTYPE html\vPUBLIC",
            "<!DOCTYPE html\vpublic><html><head></head><body></body></html>",
            true,
        ];
        yield 'vertical tab before EOF system keyword remains in doctype name' => [
            "<!DOCTYPE html\vSYSTEM",
            "<!DOCTYPE html\vsystem><html><head></head><body></body></html>",
            true,
        ];
        yield 'leading vertical tab before doctype is not stripped as whitespace' => [
            "\v<!DOCTYPE html><p>x</p>",
            "<html><head></head><body>&lt;!DOCTYPE html&gt;<p>x</p></body></html>",
            true,
        ];
        yield 'html5lib test4 invalid sequence after doctype name' => [
            '<!DOCTYPE html x>text',
            '<!DOCTYPE html><html><head></head><body>text</body></html>',
            false,
        ];
        foreach ([
            'uppercase Y' => '<!DOCTYPEa Y',
            'uppercase Z' => '<!DOCTYPEa Z',
            'backtick' => '<!DOCTYPEa `',
            'lowercase a' => '<!DOCTYPEa a',
            'lowercase a NUL' => "<!DOCTYPEa a\0",
            'lowercase a tab' => "<!DOCTYPEa a\t",
            'lowercase a line feed' => "<!DOCTYPEa a\n",
            'lowercase a vertical tab' => "<!DOCTYPEa a\v",
            'lowercase a form feed' => "<!DOCTYPEa a\f",
            'lowercase a space' => '<!DOCTYPEa a ',
            'lowercase a exclamation' => '<!DOCTYPEa a!',
            'lowercase a quote' => '<!DOCTYPEa a"',
            'lowercase a ampersand' => '<!DOCTYPEa a&',
            'lowercase a apostrophe' => "<!DOCTYPEa a'",
            'lowercase a dash' => '<!DOCTYPEa a-',
            'lowercase a slash' => '<!DOCTYPEa a/',
            'lowercase a zero' => '<!DOCTYPEa a0',
            'lowercase a one' => '<!DOCTYPEa a1',
            'lowercase a nine' => '<!DOCTYPEa a9',
            'lowercase a less-than' => '<!DOCTYPEa a<',
            'lowercase a equals' => '<!DOCTYPEa a=',
            'lowercase a terminator' => '<!DOCTYPEa a>',
            'lowercase a question mark' => '<!DOCTYPEa a?',
            'lowercase a at sign' => '<!DOCTYPEa a@',
            'lowercase a uppercase A' => '<!DOCTYPEa aA',
            'lowercase a uppercase B' => '<!DOCTYPEa aB',
            'lowercase a uppercase Y' => '<!DOCTYPEa aY',
            'lowercase a uppercase Z' => '<!DOCTYPEa aZ',
            'lowercase a backtick' => '<!DOCTYPEa a`',
            'double lowercase a' => '<!DOCTYPEa aa',
            'lowercase a lowercase b' => '<!DOCTYPEa ab',
            'lowercase a lowercase y' => '<!DOCTYPEa ay',
            'lowercase a lowercase z' => '<!DOCTYPEa az',
            'lowercase a opening brace' => '<!DOCTYPEa a{',
            'lowercase a non-BMP' => "<!DOCTYPEa a\u{100000}",
            'lowercase b' => '<!DOCTYPEa b',
            'lowercase y' => '<!DOCTYPEa y',
            'lowercase z' => '<!DOCTYPEa z',
            'opening brace' => '<!DOCTYPEa {',
            'non-BMP' => "<!DOCTYPEa \u{100000}",
        ] as $label => $html) {
            yield "html5lib test3 invalid sequence after no-whitespace doctype name $label" => [
                $html,
                '<!DOCTYPE a><html><head></head><body></body></html>',
                true,
            ];
        }
        yield 'invalid sequence after no-whitespace html name at EOF remains standards mode' => [
            '<!DOCTYPEhtml a',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'invalid sequence after no-whitespace html name terminator remains standards mode' => [
            '<!DOCTYPEhtml a>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'invalid sequence after no-whitespace html name vertical tab remains standards mode' => [
            "<!DOCTYPEhtml a\v",
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'invalid sequence after no-whitespace html name non-BMP remains standards mode' => [
            "<!DOCTYPEhtml \u{100000}",
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        foreach ([
            'exclamation' => ['<!DOCTYPEa!', '<!DOCTYPE a!><html><head></head><body></body></html>'],
            'double quote' => ['<!DOCTYPEa"', '<!DOCTYPE a"><html><head></head><body></body></html>'],
            'ampersand' => ['<!DOCTYPEa&', '<!DOCTYPE a&><html><head></head><body></body></html>'],
            'single quote' => ["<!DOCTYPEa'", "<!DOCTYPE a'><html><head></head><body></body></html>"],
            'dash' => ['<!DOCTYPEa-', '<!DOCTYPE a-><html><head></head><body></body></html>'],
            'slash' => ['<!DOCTYPEa/', '<!DOCTYPE a/><html><head></head><body></body></html>'],
            'zero' => ['<!DOCTYPEa0', '<!DOCTYPE a0><html><head></head><body></body></html>'],
            'one' => ['<!DOCTYPEa1', '<!DOCTYPE a1><html><head></head><body></body></html>'],
            'nine' => ['<!DOCTYPEa9', '<!DOCTYPE a9><html><head></head><body></body></html>'],
            'less-than' => ['<!DOCTYPEa<', '<!DOCTYPE a<><html><head></head><body></body></html>'],
            'equals' => ['<!DOCTYPEa=', '<!DOCTYPE a=><html><head></head><body></body></html>'],
            'terminator' => ['<!DOCTYPEa>', '<!DOCTYPE a><html><head></head><body></body></html>'],
            'question mark' => ['<!DOCTYPEa?', '<!DOCTYPE a?><html><head></head><body></body></html>'],
            'at sign' => ['<!DOCTYPEa@', '<!DOCTYPE a@><html><head></head><body></body></html>'],
            'uppercase A' => ['<!DOCTYPEaA', '<!DOCTYPE aa><html><head></head><body></body></html>'],
            'uppercase B' => ['<!DOCTYPEaB', '<!DOCTYPE ab><html><head></head><body></body></html>'],
            'uppercase Y' => ['<!DOCTYPEaY', '<!DOCTYPE ay><html><head></head><body></body></html>'],
            'uppercase Z' => ['<!DOCTYPEaZ', '<!DOCTYPE az><html><head></head><body></body></html>'],
            'left bracket' => ['<!DOCTYPEa[', '<!DOCTYPE a[><html><head></head><body></body></html>'],
            'backtick' => ['<!DOCTYPEa`', '<!DOCTYPE a`><html><head></head><body></body></html>'],
            'double lowercase a' => ['<!DOCTYPEaa', '<!DOCTYPE aa><html><head></head><body></body></html>'],
            'lowercase ab' => ['<!DOCTYPEab', '<!DOCTYPE ab><html><head></head><body></body></html>'],
            'lowercase ay' => ['<!DOCTYPEay', '<!DOCTYPE ay><html><head></head><body></body></html>'],
            'lowercase az' => ['<!DOCTYPEaz', '<!DOCTYPE az><html><head></head><body></body></html>'],
            'opening brace' => ['<!DOCTYPEa{', '<!DOCTYPE a{><html><head></head><body></body></html>'],
            'non-BMP after a' => ["<!DOCTYPEa\u{100000}", "<!DOCTYPE a\u{100000}><html><head></head><body></body></html>"],
            'single b' => ['<!DOCTYPEb', '<!DOCTYPE b><html><head></head><body></body></html>'],
            'single y' => ['<!DOCTYPEy', '<!DOCTYPE y><html><head></head><body></body></html>'],
            'single z' => ['<!DOCTYPEz', '<!DOCTYPE z><html><head></head><body></body></html>'],
            'single opening brace' => ['<!DOCTYPE{', '<!DOCTYPE {><html><head></head><body></body></html>'],
            'single non-BMP' => ["<!DOCTYPE\u{100000}", "<!DOCTYPE \u{100000}><html><head></head><body></body></html>"],
        ] as $label => [$html, $expected]) {
            yield "html5lib test3 EOF in no-whitespace doctype name $label" => [
                $html,
                $expected,
                true,
            ];
        }
        yield 'NUL in parsed public identifier is replaced' => [
            "<!DOCTYPE html PUBLIC \"a\0\">",
            "<!DOCTYPE html PUBLIC \"a\u{FFFD}\"><html><head></head><body></body></html>",
            false,
        ];
        yield 'NUL in parsed system identifier is replaced' => [
            "<!DOCTYPE html SYSTEM \"a\0\">",
            "<!DOCTYPE html SYSTEM \"a\u{FFFD}\"><html><head></head><body></body></html>",
            false,
        ];
        yield 'NUL in parsed public system identifier is replaced' => [
            "<!DOCTYPE html PUBLIC \"p\" \"s\0\">",
            "<!DOCTYPE html PUBLIC \"p\" \"s\u{FFFD}\"><html><head></head><body></body></html>",
            false,
        ];
        yield 'html5lib test3 public identifier quote without preceding whitespace at EOF' => [
            '<!DOCTYPE a PUBLIC"',
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier NUL without preceding whitespace' => [
            "<!DOCTYPE a PUBLIC\"\0",
            "<!DOCTYPE a PUBLIC \"\u{FFFD}\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 public identifier tab without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC\"\t",
            "<!DOCTYPE a PUBLIC \"\t\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 public identifier line feed without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC\"\n",
            "<!DOCTYPE a PUBLIC \"\n\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 public identifier form feed without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC\"\f",
            "<!DOCTYPE a PUBLIC \"\f\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 public identifier punctuation without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"!',
            '<!DOCTYPE a PUBLIC "!"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier hash without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"#',
            '<!DOCTYPE a PUBLIC "#"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier ampersand without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"&',
            '<!DOCTYPE a PUBLIC "&"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier less-than without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"<',
            '<!DOCTYPE a PUBLIC "<"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier equals without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"=',
            '<!DOCTYPE a PUBLIC "="><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier zero without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"0',
            '<!DOCTYPE a PUBLIC "0"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier uppercase letter without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"A',
            '<!DOCTYPE a PUBLIC "A"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier lowercase letter without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"z',
            '<!DOCTYPE a PUBLIC "z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier opening brace without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"{',
            '<!DOCTYPE a PUBLIC "{"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier dash without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"-',
            '<!DOCTYPE a PUBLIC "-"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier slash without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"/',
            '<!DOCTYPE a PUBLIC "/"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier one without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"1',
            '<!DOCTYPE a PUBLIC "1"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier nine without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"9',
            '<!DOCTYPE a PUBLIC "9"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier question mark without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"?',
            '<!DOCTYPE a PUBLIC "?"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier at sign without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"@',
            '<!DOCTYPE a PUBLIC "@"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier uppercase B without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"B',
            '<!DOCTYPE a PUBLIC "B"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier uppercase Y without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"Y',
            '<!DOCTYPE a PUBLIC "Y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier uppercase Z without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"Z',
            '<!DOCTYPE a PUBLIC "Z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier backtick without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"`',
            '<!DOCTYPE a PUBLIC "`"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier lowercase b without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"b',
            '<!DOCTYPE a PUBLIC "b"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier lowercase y without keyword whitespace' => [
            '<!DOCTYPE a PUBLIC"y',
            '<!DOCTYPE a PUBLIC "y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier vertical tab without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC\"\v",
            "<!DOCTYPE a PUBLIC \"\v\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 public identifier opening parenthesis without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"(',
            '<!DOCTYPE a PUBLIC "("><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier dash without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"-',
            '<!DOCTYPE a PUBLIC "-"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier slash without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"/',
            '<!DOCTYPE a PUBLIC "/"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier zero without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"0',
            '<!DOCTYPE a PUBLIC "0"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier one without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"1',
            '<!DOCTYPE a PUBLIC "1"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier nine without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"9',
            '<!DOCTYPE a PUBLIC "9"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier less-than without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"<',
            '<!DOCTYPE a PUBLIC "<"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier equals without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"=',
            '<!DOCTYPE a PUBLIC "="><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier question mark without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"?',
            '<!DOCTYPE a PUBLIC "?"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier at sign without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"@',
            '<!DOCTYPE a PUBLIC "@"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier uppercase B without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"B',
            '<!DOCTYPE a PUBLIC "B"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier uppercase Y without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"Y',
            '<!DOCTYPE a PUBLIC "Y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier uppercase Z without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"Z',
            '<!DOCTYPE a PUBLIC "Z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier backtick without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"`',
            '<!DOCTYPE a PUBLIC "`"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier lowercase b without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"b',
            '<!DOCTYPE a PUBLIC "b"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier lowercase y without preceding whitespace' => [
            '<!DOCTYPEa PUBLIC"y',
            '<!DOCTYPE a PUBLIC "y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier vertical tab without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'\v",
            "<!DOCTYPE a PUBLIC \"\v\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier opening parenthesis without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'(",
            '<!DOCTYPE a PUBLIC "("><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier dash without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'-",
            '<!DOCTYPE a PUBLIC "-"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier slash without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'/",
            '<!DOCTYPE a PUBLIC "/"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier zero without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'0",
            '<!DOCTYPE a PUBLIC "0"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier one without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'1",
            '<!DOCTYPE a PUBLIC "1"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier nine without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'9",
            '<!DOCTYPE a PUBLIC "9"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier less-than without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'<",
            '<!DOCTYPE a PUBLIC "<"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier equals without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'=",
            '<!DOCTYPE a PUBLIC "="><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier abrupt terminator without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'>",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier question mark without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'?",
            '<!DOCTYPE a PUBLIC "?"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier at sign without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'@",
            '<!DOCTYPE a PUBLIC "@"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier uppercase A without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'A",
            '<!DOCTYPE a PUBLIC "A"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier uppercase B without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'B",
            '<!DOCTYPE a PUBLIC "B"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier uppercase Y without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'Y",
            '<!DOCTYPE a PUBLIC "Y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier uppercase Z without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'Z",
            '<!DOCTYPE a PUBLIC "Z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier backtick without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'`",
            '<!DOCTYPE a PUBLIC "`"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier lowercase a without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'a",
            '<!DOCTYPE a PUBLIC "a"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier lowercase b without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'b",
            '<!DOCTYPE a PUBLIC "b"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier lowercase y without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'y",
            '<!DOCTYPE a PUBLIC "y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier lowercase z without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'z",
            '<!DOCTYPE a PUBLIC "z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier opening brace without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'{",
            '<!DOCTYPE a PUBLIC "{"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier non-BMP without preceding whitespace' => [
            "<!DOCTYPEa PUBLIC'\u{100000}",
            "<!DOCTYPE a PUBLIC \"\u{100000}\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 missing public identifier quote opening parenthesis without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC(',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote dash without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC-',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote slash without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC/',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote zero without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC0',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote one without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC1',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote nine without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC9',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote less-than without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC<',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote equals without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC=',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier before terminator without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC>',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote question mark without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC?',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote at sign without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC@',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote uppercase A without keyword whitespace' => [
            '<!DOCTYPEa PUBLICA',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote uppercase B without keyword whitespace' => [
            '<!DOCTYPEa PUBLICB',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote uppercase Y without keyword whitespace' => [
            '<!DOCTYPEa PUBLICY',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote uppercase Z without keyword whitespace' => [
            '<!DOCTYPEa PUBLICZ',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote backtick without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC`',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote lowercase a without keyword whitespace' => [
            '<!DOCTYPEa PUBLICa',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote lowercase b without keyword whitespace' => [
            '<!DOCTYPEa PUBLICb',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote lowercase y without keyword whitespace' => [
            '<!DOCTYPEa PUBLICy',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote lowercase z without keyword whitespace' => [
            '<!DOCTYPEa PUBLICz',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote opening brace without keyword whitespace' => [
            '<!DOCTYPEa PUBLIC{',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing public identifier quote non-BMP without keyword whitespace' => [
            "<!DOCTYPEa PUBLIC\u{100000}",
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier tab without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'\t",
            "<!DOCTYPE a PUBLIC \"\t\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier line feed without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'\n",
            "<!DOCTYPE a PUBLIC \"\n\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier form feed without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'\f",
            "<!DOCTYPE a PUBLIC \"\f\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier punctuation without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'!",
            '<!DOCTYPE a PUBLIC "!"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier ampersand without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'&",
            '<!DOCTYPE a PUBLIC "&"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier opening parenthesis without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'(",
            '<!DOCTYPE a PUBLIC "("><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier less-than without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'<",
            '<!DOCTYPE a PUBLIC "<"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier equals without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'=",
            '<!DOCTYPE a PUBLIC "="><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier zero without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'0",
            '<!DOCTYPE a PUBLIC "0"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier uppercase letter without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'A",
            '<!DOCTYPE a PUBLIC "A"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier lowercase letter without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'z",
            '<!DOCTYPE a PUBLIC "z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier opening brace without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'{",
            '<!DOCTYPE a PUBLIC "{"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier dash without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'-",
            '<!DOCTYPE a PUBLIC "-"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier slash without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'/",
            '<!DOCTYPE a PUBLIC "/"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier one without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'1",
            '<!DOCTYPE a PUBLIC "1"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier nine without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'9",
            '<!DOCTYPE a PUBLIC "9"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier question mark without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'?",
            '<!DOCTYPE a PUBLIC "?"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier at sign without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'@",
            '<!DOCTYPE a PUBLIC "@"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier uppercase B without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'B",
            '<!DOCTYPE a PUBLIC "B"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier uppercase Y without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'Y",
            '<!DOCTYPE a PUBLIC "Y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier uppercase Z without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'Z",
            '<!DOCTYPE a PUBLIC "Z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier backtick without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'`",
            '<!DOCTYPE a PUBLIC "`"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier lowercase b without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'b",
            '<!DOCTYPE a PUBLIC "b"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted public identifier lowercase y without keyword whitespace' => [
            "<!DOCTYPE a PUBLIC'y",
            '<!DOCTYPE a PUBLIC "y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed public identifier without preceding whitespace at EOF' => [
            '<!DOCTYPE a PUBLIC"x"',
            '<!DOCTYPE a PUBLIC "x"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier missing quote NUL at EOF' => [
            "<!DOCTYPE a PUBLIC\0",
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public identifier missing quote punctuation at EOF' => [
            '<!DOCTYPE a PUBLIC!',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier quote without preceding whitespace at EOF' => [
            '<!DOCTYPE a SYSTEM"',
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier NUL without preceding whitespace' => [
            "<!DOCTYPE a SYSTEM\"\0",
            "<!DOCTYPE a SYSTEM \"\u{FFFD}\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 system identifier tab without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM\"\t",
            "<!DOCTYPE a SYSTEM \"\t\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 system identifier line feed without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM\"\n",
            "<!DOCTYPE a SYSTEM \"\n\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 system identifier form feed without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM\"\f",
            "<!DOCTYPE a SYSTEM \"\f\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 system identifier punctuation without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"!',
            '<!DOCTYPE a SYSTEM "!"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier hash without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"#',
            '<!DOCTYPE a SYSTEM "#"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier ampersand without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"&',
            '<!DOCTYPE a SYSTEM "&"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier less-than without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"<',
            '<!DOCTYPE a SYSTEM "<"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier equals without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"=',
            '<!DOCTYPE a SYSTEM "="><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier zero without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"0',
            '<!DOCTYPE a SYSTEM "0"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier uppercase letter without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"A',
            '<!DOCTYPE a SYSTEM "A"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier lowercase letter without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"z',
            '<!DOCTYPE a SYSTEM "z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier opening brace without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"{',
            '<!DOCTYPE a SYSTEM "{"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier dash without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"-',
            '<!DOCTYPE a SYSTEM "-"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier slash without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"/',
            '<!DOCTYPE a SYSTEM "/"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier one without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"1',
            '<!DOCTYPE a SYSTEM "1"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier nine without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"9',
            '<!DOCTYPE a SYSTEM "9"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier question mark without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"?',
            '<!DOCTYPE a SYSTEM "?"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier at sign without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"@',
            '<!DOCTYPE a SYSTEM "@"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier uppercase B without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"B',
            '<!DOCTYPE a SYSTEM "B"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier uppercase Y without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"Y',
            '<!DOCTYPE a SYSTEM "Y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier uppercase Z without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"Z',
            '<!DOCTYPE a SYSTEM "Z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier backtick without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"`',
            '<!DOCTYPE a SYSTEM "`"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier lowercase b without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"b',
            '<!DOCTYPE a SYSTEM "b"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier lowercase y without keyword whitespace' => [
            '<!DOCTYPE a SYSTEM"y',
            '<!DOCTYPE a SYSTEM "y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier vertical tab without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM\"\v",
            "<!DOCTYPE a SYSTEM \"\v\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 system identifier opening parenthesis without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"(',
            '<!DOCTYPE a SYSTEM "("><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier dash without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"-',
            '<!DOCTYPE a SYSTEM "-"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier slash without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"/',
            '<!DOCTYPE a SYSTEM "/"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier zero without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"0',
            '<!DOCTYPE a SYSTEM "0"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier one without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"1',
            '<!DOCTYPE a SYSTEM "1"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier nine without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"9',
            '<!DOCTYPE a SYSTEM "9"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier less-than without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"<',
            '<!DOCTYPE a SYSTEM "<"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier equals without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"=',
            '<!DOCTYPE a SYSTEM "="><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier question mark without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"?',
            '<!DOCTYPE a SYSTEM "?"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier at sign without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"@',
            '<!DOCTYPE a SYSTEM "@"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier uppercase B without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"B',
            '<!DOCTYPE a SYSTEM "B"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier uppercase Y without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"Y',
            '<!DOCTYPE a SYSTEM "Y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier uppercase Z without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"Z',
            '<!DOCTYPE a SYSTEM "Z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier backtick without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"`',
            '<!DOCTYPE a SYSTEM "`"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier lowercase b without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"b',
            '<!DOCTYPE a SYSTEM "b"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier lowercase y without preceding whitespace' => [
            '<!DOCTYPEa SYSTEM"y',
            '<!DOCTYPE a SYSTEM "y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier vertical tab without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'\v",
            "<!DOCTYPE a SYSTEM \"\v\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier opening parenthesis without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'(",
            '<!DOCTYPE a SYSTEM "("><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier dash without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'-",
            '<!DOCTYPE a SYSTEM "-"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier slash without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'/",
            '<!DOCTYPE a SYSTEM "/"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier zero without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'0",
            '<!DOCTYPE a SYSTEM "0"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier one without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'1",
            '<!DOCTYPE a SYSTEM "1"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier nine without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'9",
            '<!DOCTYPE a SYSTEM "9"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier less-than without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'<",
            '<!DOCTYPE a SYSTEM "<"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier equals without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'=",
            '<!DOCTYPE a SYSTEM "="><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier abrupt terminator without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'>",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier question mark without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'?",
            '<!DOCTYPE a SYSTEM "?"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier at sign without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'@",
            '<!DOCTYPE a SYSTEM "@"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier uppercase A without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'A",
            '<!DOCTYPE a SYSTEM "A"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier uppercase B without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'B",
            '<!DOCTYPE a SYSTEM "B"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier uppercase Y without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'Y",
            '<!DOCTYPE a SYSTEM "Y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier uppercase Z without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'Z",
            '<!DOCTYPE a SYSTEM "Z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier backtick without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'`",
            '<!DOCTYPE a SYSTEM "`"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier lowercase a without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'a",
            '<!DOCTYPE a SYSTEM "a"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier lowercase b without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'b",
            '<!DOCTYPE a SYSTEM "b"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier lowercase y without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'y",
            '<!DOCTYPE a SYSTEM "y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier lowercase z without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'z",
            '<!DOCTYPE a SYSTEM "z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier opening brace without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'{",
            '<!DOCTYPE a SYSTEM "{"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier non-BMP without preceding whitespace' => [
            "<!DOCTYPEa SYSTEM'\u{100000}",
            "<!DOCTYPE a SYSTEM \"\u{100000}\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 missing system identifier quote opening parenthesis without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM(',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote dash without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM-',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote slash without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM/',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote zero without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM0',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote one without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM1',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote nine without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM9',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote less-than without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM<',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote equals without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM=',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier before terminator without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM>',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote question mark without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM?',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote at sign without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM@',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote uppercase A without keyword whitespace' => [
            '<!DOCTYPEa SYSTEMA',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote uppercase B without keyword whitespace' => [
            '<!DOCTYPEa SYSTEMB',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote uppercase Y without keyword whitespace' => [
            '<!DOCTYPEa SYSTEMY',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote uppercase Z without keyword whitespace' => [
            '<!DOCTYPEa SYSTEMZ',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote backtick without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM`',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote lowercase a without keyword whitespace' => [
            '<!DOCTYPEa SYSTEMa',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote lowercase b without keyword whitespace' => [
            '<!DOCTYPEa SYSTEMb',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote lowercase y without keyword whitespace' => [
            '<!DOCTYPEa SYSTEMy',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote lowercase z without keyword whitespace' => [
            '<!DOCTYPEa SYSTEMz',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote opening brace without keyword whitespace' => [
            '<!DOCTYPEa SYSTEM{',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system identifier quote non-BMP without keyword whitespace' => [
            "<!DOCTYPEa SYSTEM\u{100000}",
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier tab without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'\t",
            "<!DOCTYPE a SYSTEM \"\t\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier line feed without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'\n",
            "<!DOCTYPE a SYSTEM \"\n\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier form feed without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'\f",
            "<!DOCTYPE a SYSTEM \"\f\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier punctuation without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'!",
            '<!DOCTYPE a SYSTEM "!"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier ampersand without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'&",
            '<!DOCTYPE a SYSTEM "&"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier opening parenthesis without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'(",
            '<!DOCTYPE a SYSTEM "("><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier less-than without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'<",
            '<!DOCTYPE a SYSTEM "<"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier equals without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'=",
            '<!DOCTYPE a SYSTEM "="><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier zero without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'0",
            '<!DOCTYPE a SYSTEM "0"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier uppercase letter without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'A",
            '<!DOCTYPE a SYSTEM "A"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier lowercase letter without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'z",
            '<!DOCTYPE a SYSTEM "z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier opening brace without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'{",
            '<!DOCTYPE a SYSTEM "{"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier dash without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'-",
            '<!DOCTYPE a SYSTEM "-"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier slash without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'/",
            '<!DOCTYPE a SYSTEM "/"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier one without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'1",
            '<!DOCTYPE a SYSTEM "1"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier nine without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'9",
            '<!DOCTYPE a SYSTEM "9"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier question mark without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'?",
            '<!DOCTYPE a SYSTEM "?"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier at sign without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'@",
            '<!DOCTYPE a SYSTEM "@"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier uppercase B without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'B",
            '<!DOCTYPE a SYSTEM "B"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier uppercase Y without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'Y",
            '<!DOCTYPE a SYSTEM "Y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier uppercase Z without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'Z",
            '<!DOCTYPE a SYSTEM "Z"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier backtick without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'`",
            '<!DOCTYPE a SYSTEM "`"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier lowercase b without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'b",
            '<!DOCTYPE a SYSTEM "b"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 single-quoted system identifier lowercase y without keyword whitespace' => [
            "<!DOCTYPE a SYSTEM'y",
            '<!DOCTYPE a SYSTEM "y"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed system identifier without preceding whitespace at EOF' => [
            '<!DOCTYPE a SYSTEM"x"',
            '<!DOCTYPE a SYSTEM "x"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system keyword EOF after whitespace' => [
            '<!DOCTYPE a SYSTEM ',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier missing quote NUL after whitespace' => [
            "<!DOCTYPE a SYSTEM \0",
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier missing quote punctuation at EOF' => [
            '<!DOCTYPE a SYSTEM!',
            '<!DOCTYPE a><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public system identifier EOF with system NUL' => [
            "<!DOCTYPE a PUBLIC\"p\" \"s\0",
            "<!DOCTYPE a PUBLIC \"p\" \"s\u{FFFD}\"><html><head></head><body></body></html>",
            true,
        ];
        yield 'html5lib test3 system identifier trailing NUL forces quirks' => [
            "<!DOCTYPE html SYSTEM \"s\"\0",
            '<!DOCTYPE html SYSTEM "s"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier trailing garbage preserves body' => [
            '<!DOCTYPE html SYSTEM "s"x>body',
            '<!DOCTYPE html SYSTEM "s"><html><head></head><body>body</body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier same-quote trailing garbage preserves body' => [
            '<!DOCTYPE html SYSTEM "s"">body',
            '<!DOCTYPE html SYSTEM "s"><html><head></head><body>body</body></html>',
            true,
        ];
        yield 'html5lib test3 public system identifier trailing NUL forces quirks' => [
            "<!DOCTYPE html PUBLIC \"p\" \"s\"\0",
            '<!DOCTYPE html PUBLIC "p" "s"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public system identifier trailing garbage preserves body' => [
            '<!DOCTYPE html PUBLIC "p" "s"x>body',
            '<!DOCTYPE html PUBLIC "p" "s"><html><head></head><body>body</body></html>',
            true,
        ];
        yield 'html5lib test3 public system identifier same-quote trailing garbage preserves body' => [
            '<!DOCTYPE html PUBLIC "p" "s"">body',
            '<!DOCTYPE html PUBLIC "p" "s"><html><head></head><body>body</body></html>',
            true,
        ];
        yield 'html5lib test3 system identifier vertical tab trailing garbage forces quirks' => [
            "<!DOCTYPE html SYSTEM \"s\"\v",
            '<!DOCTYPE html SYSTEM "s"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 public system identifier vertical tab trailing garbage forces quirks' => [
            "<!DOCTYPE html PUBLIC \"p\" \"s\"\v",
            '<!DOCTYPE html PUBLIC "p" "s"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 empty system identifier vertical tab trailing garbage forces quirks' => [
            "<!DOCTYPE html SYSTEM''\v",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 empty public system identifier vertical tab trailing garbage forces quirks' => [
            "<!DOCTYPE html PUBLIC''''\v",
            '<!DOCTYPE html PUBLIC "" ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'system identifier trailing tab remains standards mode' => [
            "<!DOCTYPE html SYSTEM \"s\"\t",
            '<!DOCTYPE html SYSTEM "s"><html><head></head><body></body></html>',
            false,
        ];
        yield 'public system identifier trailing tab remains standards mode' => [
            "<!DOCTYPE html PUBLIC \"p\" \"s\"\t",
            '<!DOCTYPE html PUBLIC "p" "s"><html><head></head><body></body></html>',
            false,
        ];
        yield 'vertical tab after system keyword enters bogus doctype' => [
            "<!DOCTYPE html SYSTEM\v\"s\"\v",
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'vertical tab after public keyword enters bogus doctype' => [
            "<!DOCTYPE html PUBLIC\v\"p\"\v",
            '<!DOCTYPE html><html><head></head><body></body></html>',
            false,
        ];
        yield 'vertical tab before public system identifier enters bogus doctype' => [
            "<!DOCTYPE html PUBLIC \"p\"\v\"s\"\v",
            '<!DOCTYPE html PUBLIC "p"><html><head></head><body></body></html>',
            false,
        ];
        yield 'spaced vertical tab after system keyword enters bogus doctype' => [
            "<!DOCTYPE html SYSTEM \v\"s\">body",
            '<!DOCTYPE html><html><head></head><body>body</body></html>',
            false,
        ];
        yield 'spaced vertical tab after public keyword enters bogus doctype' => [
            "<!DOCTYPE html PUBLIC \v\"p\">body",
            '<!DOCTYPE html><html><head></head><body>body</body></html>',
            false,
        ];
        yield 'spaced vertical tab before public system identifier enters bogus doctype' => [
            "<!DOCTYPE html PUBLIC \"p\" \v\"s\">body",
            '<!DOCTYPE html PUBLIC "p"><html><head></head><body>body</body></html>',
            false,
        ];
        yield 'vertical tab guard does not cross abrupt public identifier boundary' => [
            "<!DOCTYPE html PUBLIC \">x\"\v>y",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body>x"&gt;y</body></html>',
            false,
        ];
        yield 'vertical tab guard does not cross abrupt single-quoted public identifier boundary' => [
            "<!DOCTYPE html PUBLIC '>x'\v>y",
            "<!DOCTYPE html PUBLIC \"\"><html><head></head><body>x'&gt;y</body></html>",
            false,
        ];
        yield 'trailing system identifier garbage does not leak fake body attributes' => [
            '<!DOCTYPE html SYSTEM "s"x<body class=a>body</body>',
            '<!DOCTYPE html SYSTEM "s"><html><head></head><body>body</body></html>',
            true,
        ];
        yield 'trailing public system identifier garbage does not leak fake body attributes' => [
            '<!DOCTYPE html PUBLIC "p" "s"x<body class=a>body</body>',
            '<!DOCTYPE html PUBLIC "p" "s"><html><head></head><body>body</body></html>',
            true,
        ];
        yield 'closed public system identifiers without preceding whitespace at EOF' => [
            '<!DOCTYPE a PUBLIC"p" "s"',
            '<!DOCTYPE a PUBLIC "p" "s"><html><head></head><body></body></html>',
            true,
        ];
        yield 'empty closed public system identifiers without preceding whitespace at EOF' => [
            '<!DOCTYPE a PUBLIC"" ""',
            '<!DOCTYPE a PUBLIC "" ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 empty public identifier without keyword whitespace forces quirks' => [
            '<!DOCTYPE html PUBLIC\'\'>',
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 empty system identifier without keyword whitespace forces quirks' => [
            '<!DOCTYPE html SYSTEM\'\'>',
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'spaced empty public identifier remains standards mode' => [
            '<!DOCTYPE html PUBLIC \'\'>',
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'spaced empty system identifier remains standards mode' => [
            '<!DOCTYPE html SYSTEM \'\'>',
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'empty public identifier with trailing whitespace remains standards mode' => [
            '<!DOCTYPE html PUBLIC"" >',
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'empty system identifier with trailing whitespace remains standards mode' => [
            '<!DOCTYPE html SYSTEM"" >',
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name at EOF' => [
            "<!DOCTYPEa PUBLIC''",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name NUL garbage' => [
            "<!DOCTYPEa PUBLIC''\0",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name backspace garbage' => [
            "<!DOCTYPEa PUBLIC''\x08",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name tab at EOF' => [
            "<!DOCTYPEa PUBLIC''\t",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name line feed at EOF' => [
            "<!DOCTYPEa PUBLIC''\n",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name vertical tab garbage' => [
            "<!DOCTYPEa PUBLIC''\v",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name form feed at EOF' => [
            "<!DOCTYPEa PUBLIC''\f",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name carriage return at EOF' => [
            "<!DOCTYPEa PUBLIC''\r",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name unit separator garbage' => [
            "<!DOCTYPEa PUBLIC''\x1F",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name space at EOF' => [
            "<!DOCTYPEa PUBLIC'' ",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name exclamation garbage' => [
            "<!DOCTYPEa PUBLIC''!",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name hash garbage' => [
            "<!DOCTYPEa PUBLIC''#",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name ampersand garbage' => [
            "<!DOCTYPEa PUBLIC''&",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name opening parenthesis garbage' => [
            "<!DOCTYPEa PUBLIC''(",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name dash garbage' => [
            "<!DOCTYPEa PUBLIC''-",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name slash garbage' => [
            "<!DOCTYPEa PUBLIC''/",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name zero garbage' => [
            "<!DOCTYPEa PUBLIC''0",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name one garbage' => [
            "<!DOCTYPEa PUBLIC''1",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name nine garbage' => [
            "<!DOCTYPEa PUBLIC''9",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name less-than garbage' => [
            "<!DOCTYPEa PUBLIC''<",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name equals garbage' => [
            "<!DOCTYPEa PUBLIC''=",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name question mark garbage' => [
            "<!DOCTYPEa PUBLIC''?",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name at sign garbage' => [
            "<!DOCTYPEa PUBLIC''@",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name uppercase B garbage' => [
            "<!DOCTYPEa PUBLIC''B",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name uppercase Y garbage' => [
            "<!DOCTYPEa PUBLIC''Y",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name uppercase A garbage' => [
            "<!DOCTYPEa PUBLIC''A",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name uppercase Z garbage' => [
            "<!DOCTYPEa PUBLIC''Z",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name backtick garbage' => [
            "<!DOCTYPEa PUBLIC''`",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name lowercase a garbage' => [
            "<!DOCTYPEa PUBLIC''a",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name lowercase b garbage' => [
            "<!DOCTYPEa PUBLIC''b",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name lowercase y garbage' => [
            "<!DOCTYPEa PUBLIC''y",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name lowercase z garbage' => [
            "<!DOCTYPEa PUBLIC''z",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name opening brace garbage' => [
            "<!DOCTYPEa PUBLIC''{",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name non-BMP garbage' => [
            "<!DOCTYPEa PUBLIC''\u{100000}",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public identifier no-whitespace name terminator' => [
            "<!DOCTYPEa PUBLIC''>",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public system identifiers no-whitespace name double quote at EOF' => [
            "<!DOCTYPEa PUBLIC''\"",
            '<!DOCTYPE a PUBLIC "" ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty public system identifiers no-whitespace name single quote at EOF' => [
            "<!DOCTYPEa PUBLIC'''",
            '<!DOCTYPE a PUBLIC "" ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name at EOF' => [
            "<!DOCTYPEa SYSTEM''",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name NUL garbage' => [
            "<!DOCTYPEa SYSTEM''\0",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name backspace garbage' => [
            "<!DOCTYPEa SYSTEM''\x08",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name tab at EOF' => [
            "<!DOCTYPEa SYSTEM''\t",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name line feed at EOF' => [
            "<!DOCTYPEa SYSTEM''\n",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name vertical tab garbage' => [
            "<!DOCTYPEa SYSTEM''\v",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name form feed at EOF' => [
            "<!DOCTYPEa SYSTEM''\f",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name carriage return at EOF' => [
            "<!DOCTYPEa SYSTEM''\r",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name unit separator garbage' => [
            "<!DOCTYPEa SYSTEM''\x1F",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name space at EOF' => [
            "<!DOCTYPEa SYSTEM'' ",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name exclamation garbage' => [
            "<!DOCTYPEa SYSTEM''!",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name double quote garbage' => [
            "<!DOCTYPEa SYSTEM''\"",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name ampersand garbage' => [
            "<!DOCTYPEa SYSTEM''&",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name single quote garbage' => [
            "<!DOCTYPEa SYSTEM'''",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name dash garbage' => [
            "<!DOCTYPEa SYSTEM''-",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name slash garbage' => [
            "<!DOCTYPEa SYSTEM''/",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name zero garbage' => [
            "<!DOCTYPEa SYSTEM''0",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name one garbage' => [
            "<!DOCTYPEa SYSTEM''1",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name nine garbage' => [
            "<!DOCTYPEa SYSTEM''9",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name less-than garbage' => [
            "<!DOCTYPEa SYSTEM''<",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name equals garbage' => [
            "<!DOCTYPEa SYSTEM''=",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name question mark garbage' => [
            "<!DOCTYPEa SYSTEM''?",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name at sign garbage' => [
            "<!DOCTYPEa SYSTEM''@",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name uppercase B garbage' => [
            "<!DOCTYPEa SYSTEM''B",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name uppercase Y garbage' => [
            "<!DOCTYPEa SYSTEM''Y",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name uppercase A garbage' => [
            "<!DOCTYPEa SYSTEM''A",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name uppercase Z garbage' => [
            "<!DOCTYPEa SYSTEM''Z",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name backtick garbage' => [
            "<!DOCTYPEa SYSTEM''`",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name lowercase a garbage' => [
            "<!DOCTYPEa SYSTEM''a",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name lowercase b garbage' => [
            "<!DOCTYPEa SYSTEM''b",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name lowercase y garbage' => [
            "<!DOCTYPEa SYSTEM''y",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name lowercase z garbage' => [
            "<!DOCTYPEa SYSTEM''z",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name opening brace garbage' => [
            "<!DOCTYPEa SYSTEM''{",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name non-BMP garbage' => [
            "<!DOCTYPEa SYSTEM''\u{100000}",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 closed empty system identifier no-whitespace name terminator' => [
            "<!DOCTYPEa SYSTEM''>",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name at EOF forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name NUL garbage forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''\0",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name tab at EOF forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''\t",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name vertical tab garbage forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''\v",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name space at EOF forces quirks' => [
            "<!DOCTYPEhtml PUBLIC'' ",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name exclamation garbage forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''!",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name less-than garbage forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''<",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name uppercase A garbage forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''A",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name non-BMP garbage forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''\u{100000}",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name trailing garbage forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''?",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public identifier no-whitespace html name terminator remains standards mode' => [
            "<!DOCTYPEhtml PUBLIC''>",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty public identifier no-whitespace html name whitespace terminator remains standards mode' => [
            "<!DOCTYPEhtml PUBLIC'' >",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty public system identifiers no-whitespace html name double quote at EOF forces quirks' => [
            "<!DOCTYPEhtml PUBLIC''\"",
            '<!DOCTYPE html PUBLIC "" ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public system identifiers no-whitespace html name single quote at EOF forces quirks' => [
            "<!DOCTYPEhtml PUBLIC'''",
            '<!DOCTYPE html PUBLIC "" ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty public system identifiers no-whitespace html name terminator remains standards mode' => [
            "<!DOCTYPEhtml PUBLIC''''>",
            '<!DOCTYPE html PUBLIC "" ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty public system identifiers no-whitespace html name trailing garbage remains standards mode' => [
            "<!DOCTYPEhtml PUBLIC''''?",
            '<!DOCTYPE html PUBLIC "" ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty public system identifiers no-whitespace html name abrupt boundary preserves body text' => [
            "<!DOCTYPEhtml PUBLIC''\">x",
            '<!DOCTYPE html PUBLIC "" ""><html><head></head><body>x</body></html>',
            true,
        ];
        yield 'closed empty system identifier no-whitespace html name at EOF forces quirks' => [
            "<!DOCTYPEhtml SYSTEM''",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty system identifier no-whitespace html name NUL garbage remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM''\0",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty system identifier no-whitespace html name tab at EOF forces quirks' => [
            "<!DOCTYPEhtml SYSTEM''\t",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty system identifier no-whitespace html name vertical tab garbage remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM''\v",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty system identifier no-whitespace html name space at EOF forces quirks' => [
            "<!DOCTYPEhtml SYSTEM'' ",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'closed empty system identifier no-whitespace html name exclamation garbage remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM''!",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty system identifier no-whitespace html name double quote garbage remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM''\"",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty system identifier no-whitespace html name uppercase A garbage remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM''A",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty system identifier no-whitespace html name non-BMP garbage remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM''\u{100000}",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty system identifier no-whitespace html name trailing garbage remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM''?",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty system identifier no-whitespace html name terminator remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM''>",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'closed empty system identifier no-whitespace html name whitespace terminator remains standards mode' => [
            "<!DOCTYPEhtml SYSTEM'' >",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body></body></html>',
            false,
        ];
        yield 'no-whitespace closed branch does not swallow abrupt public identifier boundary' => [
            "<!DOCTYPEa PUBLIC'x>y'",
            '<!DOCTYPE a PUBLIC "x"><html><head></head><body>y\'</body></html>',
            true,
        ];
        yield 'no-whitespace closed branch does not swallow abrupt system identifier boundary' => [
            "<!DOCTYPEa SYSTEM'x>y'",
            '<!DOCTYPE a SYSTEM "x"><html><head></head><body>y\'</body></html>',
            true,
        ];
        yield 'single-quoted public identifier no-whitespace html name at EOF forces quirks' => [
            "<!DOCTYPEhtml PUBLIC'A",
            '<!DOCTYPE html PUBLIC "A"><html><head></head><body></body></html>',
            true,
        ];
        yield 'single-quoted public identifier no-whitespace html name abrupt boundary forces quirks' => [
            "<!DOCTYPEhtml PUBLIC'>x",
            '<!DOCTYPE html PUBLIC ""><html><head></head><body>x</body></html>',
            true,
        ];
        yield 'single-quoted system identifier no-whitespace html name at EOF forces quirks' => [
            "<!DOCTYPEhtml SYSTEM'A",
            '<!DOCTYPE html SYSTEM "A"><html><head></head><body></body></html>',
            true,
        ];
        yield 'single-quoted system identifier no-whitespace html name abrupt boundary forces quirks' => [
            "<!DOCTYPEhtml SYSTEM'>x",
            '<!DOCTYPE html SYSTEM ""><html><head></head><body>x</body></html>',
            true,
        ];
        yield 'missing public identifier no-whitespace html name default character forces quirks' => [
            '<!DOCTYPEhtml PUBLIC(',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            true,
        ];
        yield 'missing public identifier no-whitespace html name terminator forces quirks' => [
            '<!DOCTYPEhtml PUBLIC>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            true,
        ];
        yield 'missing system identifier no-whitespace html name default character forces quirks' => [
            '<!DOCTYPEhtml SYSTEM(',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            true,
        ];
        yield 'missing system identifier no-whitespace html name terminator forces quirks' => [
            '<!DOCTYPEhtml SYSTEM>',
            '<!DOCTYPE html><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system quote after closed public identifier' => [
            '<!DOCTYPE a PUBLIC"x"!',
            '<!DOCTYPE a PUBLIC "x"><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test3 missing system quote after empty public identifier' => [
            "<!DOCTYPE a PUBLIC\"\"\0",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body></body></html>',
            true,
        ];
        yield 'html5lib test2 abrupt double-quoted public identifier' => [
            '<!DOCTYPE html PUBLIC ">x',
            '<!DOCTYPE html PUBLIC ""><html><head></head><body>x</body></html>',
            false,
        ];
        yield 'html5lib test2 abrupt single-quoted public identifier' => [
            '<!DOCTYPE html PUBLIC \'>x',
            '<!DOCTYPE html PUBLIC ""><html><head></head><body>x</body></html>',
            false,
        ];
        yield 'html5lib test2 abrupt double-quoted system identifier' => [
            '<!DOCTYPE html PUBLIC "foo" ">x',
            '<!DOCTYPE html PUBLIC "foo" ""><html><head></head><body>x</body></html>',
            false,
        ];
        yield 'html5lib test2 abrupt single-quoted system identifier' => [
            '<!DOCTYPE html PUBLIC \'foo\' \'>x',
            '<!DOCTYPE html PUBLIC "foo" ""><html><head></head><body>x</body></html>',
            false,
        ];
        yield 'abrupt public system identifier without whitespace preserves body quotes' => [
            '<!DOCTYPE html PUBLIC "foo"">x">y',
            '<!DOCTYPE html PUBLIC "foo" ""><html><head></head><body>x"&gt;y</body></html>',
            false,
        ];
        yield 'abrupt direct public identifier preserves later body quotes' => [
            '<!DOCTYPE html PUBLIC ">x "">y',
            '<!DOCTYPE html PUBLIC ""><html><head></head><body>x ""&gt;y</body></html>',
            false,
        ];
        yield 'abrupt direct system identifier preserves body quotes' => [
            '<!DOCTYPE html SYSTEM ">x">y',
            '<!DOCTYPE html SYSTEM ""><html><head></head><body>x"&gt;y</body></html>',
            false,
        ];
        yield 'abrupt no-whitespace public identifier preserves body text' => [
            '<!DOCTYPEa PUBLIC">x',
            '<!DOCTYPE a PUBLIC ""><html><head></head><body>x</body></html>',
            true,
        ];
        yield 'abrupt no-whitespace single-quoted public identifier preserves body text' => [
            "<!DOCTYPEa PUBLIC'>x",
            '<!DOCTYPE a PUBLIC ""><html><head></head><body>x</body></html>',
            true,
        ];
        yield 'abrupt no-whitespace system identifier preserves body text' => [
            '<!DOCTYPEa SYSTEM">x',
            '<!DOCTYPE a SYSTEM ""><html><head></head><body>x</body></html>',
            true,
        ];
        yield 'abrupt no-whitespace single-quoted system identifier preserves body text' => [
            "<!DOCTYPEa SYSTEM'>x",
            '<!DOCTYPE a SYSTEM ""><html><head></head><body>x</body></html>',
            true,
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function html5libTokenizerContentModelProvider(): iterable
    {
        yield 'contentModelFlags.test PLAINTEXT content model flag' => [
            '<plaintext><head>&body;',
            '<plaintext><head>&body;</plaintext>',
        ];
        yield 'contentModelFlags.test PLAINTEXT seeming close tag stays text' => [
            '<plaintext></plaintext>&body;',
            '<plaintext></plaintext>&body;</plaintext>',
        ];
        yield 'contentModelFlags.test RAWTEXT xmp end tag closes' => [
            '<xmp>foo</xmp>',
            '<xmp>foo</xmp>',
        ];
        yield 'contentModelFlags.test RAWTEXT xmp end tag is case-insensitive' => [
            '<xmp>foo</xMp>',
            '<xmp>foo</xmp>',
        ];
        yield 'contentModelFlags.test RAWTEXT xmp EOF partial end tag remains text' => [
            '<xmp>foo</xmp',
            '<xmp>foo</xmp</xmp>',
        ];
        yield 'contentModelFlags.test RAWTEXT xmp angle after partial end tag remains text' => [
            '<xmp>foo</xmp<',
            '<xmp>foo</xmp<</xmp>',
        ];
        yield 'contentModelFlags.test RAWTEXT xmp incorrect end tag remains text until xmp close' => [
            '<xmp></foo>bar</xmp>',
            '<xmp></foo>bar</xmp>',
        ];
        yield 'contentModelFlags.test RAWTEXT xmp repeated partial end tags close at final complete tag' => [
            '<xmp></xmp</xmp</xmp>',
            '<xmp></xmp</xmp</xmp>',
        ];
        yield 'contentModelFlags.test RAWTEXT xmp prefixed incorrect end tag remains text' => [
            '<xmp></foo>bar</xmpaar>',
            '<xmp></foo>bar</xmpaar></xmp>',
        ];
        yield 'contentModelFlags.test RAWTEXT xmp entity-looking text is not decoded' => [
            '<xmp>&foo;</xmp>',
            '<xmp>&foo;</xmp>',
        ];
        yield 'contentModelFlags.test RCDATA textarea end tag closes' => [
            '<textarea>foo</textarea>',
            '<textarea>foo</textarea>',
        ];
        yield 'contentModelFlags.test RCDATA textarea end tag is case-insensitive' => [
            '<textarea>foo</tExTaReA>',
            '<textarea>foo</textarea>',
        ];
        yield 'contentModelFlags.test RCDATA textarea decodes character reference' => [
            '<textarea>&lt;</textarea>',
            '<textarea>&lt;</textarea>',
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
        foreach ([
            'AElig without semicolon' => ['&AElig', 'Æ'],
            'AElig with semicolon' => ['&AElig;', 'Æ'],
            'AMP without semicolon' => ['&AMP', '&amp;'],
            'AMP with semicolon' => ['&AMP;', '&amp;'],
            'Aacute without semicolon' => ['&Aacute', 'Á'],
            'Aacute with semicolon' => ['&Aacute;', 'Á'],
            'Abreve without semicolon remains literal' => ['&Abreve', '&amp;Abreve'],
            'Abreve with semicolon' => ['&Abreve;', 'Ă'],
            'Acirc without semicolon' => ['&Acirc', 'Â'],
            'Acirc with semicolon' => ['&Acirc;', 'Â'],
            'Acy without semicolon remains literal' => ['&Acy', '&amp;Acy'],
            'Acy with semicolon' => ['&Acy;', 'А'],
            'Afr without semicolon remains literal' => ['&Afr', '&amp;Afr'],
            'Afr with semicolon' => ['&Afr;', '𝔄'],
            'Agrave without semicolon' => ['&Agrave', 'À'],
            'Agrave with semicolon' => ['&Agrave;', 'À'],
            'Alpha without semicolon remains literal' => ['&Alpha', '&amp;Alpha'],
            'Alpha with semicolon' => ['&Alpha;', 'Α'],
            'Amacr without semicolon remains literal' => ['&Amacr', '&amp;Amacr'],
            'Amacr with semicolon' => ['&Amacr;', 'Ā'],
            'And without semicolon remains literal' => ['&And', '&amp;And'],
            'And with semicolon' => ['&And;', '⩓'],
            'Aogon without semicolon remains literal' => ['&Aogon', '&amp;Aogon'],
            'Aogon with semicolon' => ['&Aogon;', 'Ą'],
            'Aopf without semicolon remains literal' => ['&Aopf', '&amp;Aopf'],
            'Aopf with semicolon' => ['&Aopf;', '𝔸'],
            'ApplyFunction without semicolon remains literal' => ['&ApplyFunction', '&amp;ApplyFunction'],
            'ApplyFunction with semicolon' => ['&ApplyFunction;', '⁡'],
            'Aring without semicolon' => ['&Aring', 'Å'],
            'Aring with semicolon' => ['&Aring;', 'Å'],
            'Ascr without semicolon remains literal' => ['&Ascr', '&amp;Ascr'],
            'Ascr with semicolon' => ['&Ascr;', '𝒜'],
            'Assign without semicolon remains literal' => ['&Assign', '&amp;Assign'],
            'Assign with semicolon' => ['&Assign;', '≔'],
            'Atilde without semicolon' => ['&Atilde', 'Ã'],
            'Atilde with semicolon' => ['&Atilde;', 'Ã'],
            'Auml without semicolon' => ['&Auml', 'Ä'],
            'Auml with semicolon' => ['&Auml;', 'Ä'],
            'Backslash without semicolon remains literal' => ['&Backslash', '&amp;Backslash'],
            'Backslash with semicolon' => ['&Backslash;', '∖'],
            'Barv without semicolon remains literal' => ['&Barv', '&amp;Barv'],
            'Barv with semicolon' => ['&Barv;', '⫧'],
            'Barwed without semicolon remains literal' => ['&Barwed', '&amp;Barwed'],
            'Barwed with semicolon' => ['&Barwed;', '⌆'],
            'Bcy without semicolon remains literal' => ['&Bcy', '&amp;Bcy'],
            'Bcy with semicolon' => ['&Bcy;', 'Б'],
            'Because without semicolon remains literal' => ['&Because', '&amp;Because'],
            'Because with semicolon' => ['&Because;', '∵'],
            'Bernoullis without semicolon remains literal' => ['&Bernoullis', '&amp;Bernoullis'],
            'Bernoullis with semicolon' => ['&Bernoullis;', 'ℬ'],
            'Beta without semicolon remains literal' => ['&Beta', '&amp;Beta'],
            'Beta with semicolon' => ['&Beta;', 'Β'],
            'Bfr without semicolon remains literal' => ['&Bfr', '&amp;Bfr'],
            'Bfr with semicolon' => ['&Bfr;', '𝔅'],
            'Bopf without semicolon remains literal' => ['&Bopf', '&amp;Bopf'],
            'Bopf with semicolon' => ['&Bopf;', '𝔹'],
            'Breve without semicolon remains literal' => ['&Breve', '&amp;Breve'],
            'Breve with semicolon' => ['&Breve;', '˘'],
            'Bscr without semicolon remains literal' => ['&Bscr', '&amp;Bscr'],
            'Bscr with semicolon' => ['&Bscr;', 'ℬ'],
            'Bumpeq without semicolon remains literal' => ['&Bumpeq', '&amp;Bumpeq'],
            'Bumpeq with semicolon' => ['&Bumpeq;', '≎'],
            'CHcy without semicolon remains literal' => ['&CHcy', '&amp;CHcy'],
            'CHcy with semicolon' => ['&CHcy;', 'Ч'],
            'COPY without semicolon' => ['&COPY', '©'],
            'COPY with semicolon' => ['&COPY;', '©'],
            'Cacute without semicolon remains literal' => ['&Cacute', '&amp;Cacute'],
            'Cacute with semicolon' => ['&Cacute;', 'Ć'],
            'Cap without semicolon remains literal' => ['&Cap', '&amp;Cap'],
            'Cap with semicolon' => ['&Cap;', '⋒'],
            'CapitalDifferentialD without semicolon remains literal' => ['&CapitalDifferentialD', '&amp;CapitalDifferentialD'],
            'CapitalDifferentialD with semicolon' => ['&CapitalDifferentialD;', 'ⅅ'],
            'Cayleys without semicolon remains literal' => ['&Cayleys', '&amp;Cayleys'],
            'Cayleys with semicolon' => ['&Cayleys;', 'ℭ'],
            'Ccaron without semicolon remains literal' => ['&Ccaron', '&amp;Ccaron'],
            'Ccaron with semicolon' => ['&Ccaron;', 'Č'],
            'Ccedil without semicolon' => ['&Ccedil', 'Ç'],
            'Ccedil with semicolon' => ['&Ccedil;', 'Ç'],
            'Ccirc without semicolon remains literal' => ['&Ccirc', '&amp;Ccirc'],
            'Ccirc with semicolon' => ['&Ccirc;', 'Ĉ'],
            'Cconint without semicolon remains literal' => ['&Cconint', '&amp;Cconint'],
            'Cconint with semicolon' => ['&Cconint;', '∰'],
            'Cdot without semicolon remains literal' => ['&Cdot', '&amp;Cdot'],
            'Cdot with semicolon' => ['&Cdot;', 'Ċ'],
            'Cedilla without semicolon remains literal' => ['&Cedilla', '&amp;Cedilla'],
            'Cedilla with semicolon' => ['&Cedilla;', '¸'],
            'CenterDot without semicolon remains literal' => ['&CenterDot', '&amp;CenterDot'],
            'CenterDot with semicolon' => ['&CenterDot;', '·'],
            'Cfr without semicolon remains literal' => ['&Cfr', '&amp;Cfr'],
            'Cfr with semicolon' => ['&Cfr;', 'ℭ'],
            'Chi without semicolon remains literal' => ['&Chi', '&amp;Chi'],
            'Chi with semicolon' => ['&Chi;', 'Χ'],
            'CircleDot without semicolon remains literal' => ['&CircleDot', '&amp;CircleDot'],
            'CircleDot with semicolon' => ['&CircleDot;', '⊙'],
            'CircleMinus without semicolon remains literal' => ['&CircleMinus', '&amp;CircleMinus'],
            'CircleMinus with semicolon' => ['&CircleMinus;', '⊖'],
            'CirclePlus without semicolon remains literal' => ['&CirclePlus', '&amp;CirclePlus'],
            'CirclePlus with semicolon' => ['&CirclePlus;', '⊕'],
            'CircleTimes without semicolon remains literal' => ['&CircleTimes', '&amp;CircleTimes'],
            'CircleTimes with semicolon' => ['&CircleTimes;', '⊗'],
            'ClockwiseContourIntegral without semicolon remains literal' => ['&ClockwiseContourIntegral', '&amp;ClockwiseContourIntegral'],
            'ClockwiseContourIntegral with semicolon' => ['&ClockwiseContourIntegral;', '∲'],
            'CloseCurlyDoubleQuote without semicolon remains literal' => ['&CloseCurlyDoubleQuote', '&amp;CloseCurlyDoubleQuote'],
            'CloseCurlyDoubleQuote with semicolon' => ['&CloseCurlyDoubleQuote;', '”'],
            'CloseCurlyQuote without semicolon remains literal' => ['&CloseCurlyQuote', '&amp;CloseCurlyQuote'],
            'CloseCurlyQuote with semicolon' => ['&CloseCurlyQuote;', '’'],
            'Colon without semicolon remains literal' => ['&Colon', '&amp;Colon'],
            'Colon with semicolon' => ['&Colon;', '∷'],
            'Colone without semicolon remains literal' => ['&Colone', '&amp;Colone'],
            'Colone with semicolon' => ['&Colone;', '⩴'],
            'Congruent without semicolon remains literal' => ['&Congruent', '&amp;Congruent'],
            'Congruent with semicolon' => ['&Congruent;', '≡'],
            'Conint without semicolon remains literal' => ['&Conint', '&amp;Conint'],
            'Conint with semicolon' => ['&Conint;', '∯'],
            'ContourIntegral without semicolon remains literal' => ['&ContourIntegral', '&amp;ContourIntegral'],
            'ContourIntegral with semicolon' => ['&ContourIntegral;', '∮'],
            'Copf without semicolon remains literal' => ['&Copf', '&amp;Copf'],
            'Copf with semicolon' => ['&Copf;', 'ℂ'],
            'Coproduct without semicolon remains literal' => ['&Coproduct', '&amp;Coproduct'],
            'Coproduct with semicolon' => ['&Coproduct;', '∐'],
            'CounterClockwiseContourIntegral without semicolon remains literal' => ['&CounterClockwiseContourIntegral', '&amp;CounterClockwiseContourIntegral'],
            'CounterClockwiseContourIntegral with semicolon' => ['&CounterClockwiseContourIntegral;', '∳'],
            'Cross without semicolon remains literal' => ['&Cross', '&amp;Cross'],
            'Cross with semicolon' => ['&Cross;', '⨯'],
            'Cscr without semicolon remains literal' => ['&Cscr', '&amp;Cscr'],
            'Cscr with semicolon' => ['&Cscr;', '𝒞'],
            'Cup without semicolon remains literal' => ['&Cup', '&amp;Cup'],
            'Cup with semicolon' => ['&Cup;', '⋓'],
            'CupCap without semicolon remains literal' => ['&CupCap', '&amp;CupCap'],
            'CupCap with semicolon' => ['&CupCap;', '≍'],
            'DD without semicolon remains literal' => ['&DD', '&amp;DD'],
            'DD with semicolon' => ['&DD;', 'ⅅ'],
            'DDotrahd without semicolon remains literal' => ['&DDotrahd', '&amp;DDotrahd'],
            'DDotrahd with semicolon' => ['&DDotrahd;', '⤑'],
            'DJcy without semicolon remains literal' => ['&DJcy', '&amp;DJcy'],
            'DJcy with semicolon' => ['&DJcy;', 'Ђ'],
            'DScy without semicolon remains literal' => ['&DScy', '&amp;DScy'],
            'DScy with semicolon' => ['&DScy;', 'Ѕ'],
            'DZcy without semicolon remains literal' => ['&DZcy', '&amp;DZcy'],
            'DZcy with semicolon' => ['&DZcy;', 'Џ'],
            'Dagger without semicolon remains literal' => ['&Dagger', '&amp;Dagger'],
            'Dagger with semicolon' => ['&Dagger;', '‡'],
            'Darr without semicolon remains literal' => ['&Darr', '&amp;Darr'],
            'Darr with semicolon' => ['&Darr;', '↡'],
            'Dashv without semicolon remains literal' => ['&Dashv', '&amp;Dashv'],
            'Dashv with semicolon' => ['&Dashv;', '⫤'],
            'Dcaron without semicolon remains literal' => ['&Dcaron', '&amp;Dcaron'],
            'Dcaron with semicolon' => ['&Dcaron;', 'Ď'],
            'Dcy without semicolon remains literal' => ['&Dcy', '&amp;Dcy'],
            'Dcy with semicolon' => ['&Dcy;', 'Д'],
            'Del without semicolon remains literal' => ['&Del', '&amp;Del'],
            'Del with semicolon' => ['&Del;', '∇'],
            'Delta without semicolon remains literal' => ['&Delta', '&amp;Delta'],
            'Delta with semicolon' => ['&Delta;', 'Δ'],
            'Dfr without semicolon remains literal' => ['&Dfr', '&amp;Dfr'],
            'Dfr with semicolon' => ['&Dfr;', '𝔇'],
            'DiacriticalAcute without semicolon remains literal' => ['&DiacriticalAcute', '&amp;DiacriticalAcute'],
            'DiacriticalAcute with semicolon' => ['&DiacriticalAcute;', '´'],
            'DiacriticalDot without semicolon remains literal' => ['&DiacriticalDot', '&amp;DiacriticalDot'],
            'DiacriticalDot with semicolon' => ['&DiacriticalDot;', '˙'],
            'DiacriticalDoubleAcute without semicolon remains literal' => ['&DiacriticalDoubleAcute', '&amp;DiacriticalDoubleAcute'],
            'DiacriticalDoubleAcute with semicolon' => ['&DiacriticalDoubleAcute;', '˝'],
            'DiacriticalGrave without semicolon remains literal' => ['&DiacriticalGrave', '&amp;DiacriticalGrave'],
            'DiacriticalGrave with semicolon' => ['&DiacriticalGrave;', '`'],
            'DiacriticalTilde without semicolon remains literal' => ['&DiacriticalTilde', '&amp;DiacriticalTilde'],
            'DiacriticalTilde with semicolon' => ['&DiacriticalTilde;', '˜'],
            'Diamond without semicolon remains literal' => ['&Diamond', '&amp;Diamond'],
            'Diamond with semicolon' => ['&Diamond;', '⋄'],
            'DifferentialD without semicolon remains literal' => ['&DifferentialD', '&amp;DifferentialD'],
            'DifferentialD with semicolon' => ['&DifferentialD;', 'ⅆ'],
            'Dopf without semicolon remains literal' => ['&Dopf', '&amp;Dopf'],
            'Dopf with semicolon' => ['&Dopf;', '𝔻'],
            'Dot without semicolon remains literal' => ['&Dot', '&amp;Dot'],
            'Dot with semicolon' => ['&Dot;', '¨'],
            'DotDot without semicolon remains literal' => ['&DotDot', '&amp;DotDot'],
            'DotDot with semicolon' => ['&DotDot;', '⃜'],
            'DotEqual without semicolon remains literal' => ['&DotEqual', '&amp;DotEqual'],
            'DotEqual with semicolon' => ['&DotEqual;', '≐'],
            'DoubleContourIntegral without semicolon remains literal' => ['&DoubleContourIntegral', '&amp;DoubleContourIntegral'],
            'DoubleContourIntegral with semicolon' => ['&DoubleContourIntegral;', '∯'],
            'DoubleDot without semicolon remains literal' => ['&DoubleDot', '&amp;DoubleDot'],
            'DoubleDot with semicolon' => ['&DoubleDot;', '¨'],
            'DoubleDownArrow without semicolon remains literal' => ['&DoubleDownArrow', '&amp;DoubleDownArrow'],
            'DoubleDownArrow with semicolon' => ['&DoubleDownArrow;', '⇓'],
            'DoubleLeftArrow without semicolon remains literal' => ['&DoubleLeftArrow', '&amp;DoubleLeftArrow'],
            'DoubleLeftArrow with semicolon' => ['&DoubleLeftArrow;', '⇐'],
            'DoubleLeftRightArrow without semicolon remains literal' => ['&DoubleLeftRightArrow', '&amp;DoubleLeftRightArrow'],
            'DoubleLeftRightArrow with semicolon' => ['&DoubleLeftRightArrow;', '⇔'],
            'DoubleLeftTee without semicolon remains literal' => ['&DoubleLeftTee', '&amp;DoubleLeftTee'],
            'DoubleLeftTee with semicolon' => ['&DoubleLeftTee;', '⫤'],
            'DoubleLongLeftArrow without semicolon remains literal' => ['&DoubleLongLeftArrow', '&amp;DoubleLongLeftArrow'],
            'DoubleLongLeftArrow with semicolon' => ['&DoubleLongLeftArrow;', '⟸'],
            'DoubleLongLeftRightArrow without semicolon remains literal' => ['&DoubleLongLeftRightArrow', '&amp;DoubleLongLeftRightArrow'],
            'DoubleLongLeftRightArrow with semicolon' => ['&DoubleLongLeftRightArrow;', '⟺'],
            'DoubleLongRightArrow without semicolon remains literal' => ['&DoubleLongRightArrow', '&amp;DoubleLongRightArrow'],
            'DoubleLongRightArrow with semicolon' => ['&DoubleLongRightArrow;', '⟹'],
            'DoubleRightArrow without semicolon remains literal' => ['&DoubleRightArrow', '&amp;DoubleRightArrow'],
            'DoubleRightArrow with semicolon' => ['&DoubleRightArrow;', '⇒'],
            'DoubleRightTee without semicolon remains literal' => ['&DoubleRightTee', '&amp;DoubleRightTee'],
            'DoubleRightTee with semicolon' => ['&DoubleRightTee;', '⊨'],
            'DoubleUpArrow without semicolon remains literal' => ['&DoubleUpArrow', '&amp;DoubleUpArrow'],
            'DoubleUpArrow with semicolon' => ['&DoubleUpArrow;', '⇑'],
            'DoubleUpDownArrow without semicolon remains literal' => ['&DoubleUpDownArrow', '&amp;DoubleUpDownArrow'],
            'DoubleUpDownArrow with semicolon' => ['&DoubleUpDownArrow;', '⇕'],
            'DoubleVerticalBar without semicolon remains literal' => ['&DoubleVerticalBar', '&amp;DoubleVerticalBar'],
            'DoubleVerticalBar with semicolon' => ['&DoubleVerticalBar;', '∥'],
            'DownArrow without semicolon remains literal' => ['&DownArrow', '&amp;DownArrow'],
            'DownArrow with semicolon' => ['&DownArrow;', '↓'],
            'DownArrowBar without semicolon remains literal' => ['&DownArrowBar', '&amp;DownArrowBar'],
            'DownArrowBar with semicolon' => ['&DownArrowBar;', '⤓'],
            'DownArrowUpArrow without semicolon remains literal' => ['&DownArrowUpArrow', '&amp;DownArrowUpArrow'],
            'DownArrowUpArrow with semicolon' => ['&DownArrowUpArrow;', '⇵'],
            'DownBreve without semicolon remains literal' => ['&DownBreve', '&amp;DownBreve'],
            'DownBreve with semicolon' => ['&DownBreve;', '̑'],
            'DownLeftRightVector without semicolon remains literal' => ['&DownLeftRightVector', '&amp;DownLeftRightVector'],
            'DownLeftRightVector with semicolon' => ['&DownLeftRightVector;', '⥐'],
            'DownLeftTeeVector without semicolon remains literal' => ['&DownLeftTeeVector', '&amp;DownLeftTeeVector'],
            'DownLeftTeeVector with semicolon' => ['&DownLeftTeeVector;', '⥞'],
            'DownLeftVector without semicolon remains literal' => ['&DownLeftVector', '&amp;DownLeftVector'],
            'DownLeftVector with semicolon' => ['&DownLeftVector;', '↽'],
            'DownLeftVectorBar without semicolon remains literal' => ['&DownLeftVectorBar', '&amp;DownLeftVectorBar'],
            'DownLeftVectorBar with semicolon' => ['&DownLeftVectorBar;', '⥖'],
            'DownRightTeeVector without semicolon remains literal' => ['&DownRightTeeVector', '&amp;DownRightTeeVector'],
            'DownRightTeeVector with semicolon' => ['&DownRightTeeVector;', '⥟'],
            'DownRightVector without semicolon remains literal' => ['&DownRightVector', '&amp;DownRightVector'],
            'DownRightVector with semicolon' => ['&DownRightVector;', '⇁'],
            'DownRightVectorBar without semicolon remains literal' => ['&DownRightVectorBar', '&amp;DownRightVectorBar'],
            'DownRightVectorBar with semicolon' => ['&DownRightVectorBar;', '⥗'],
            'DownTee without semicolon remains literal' => ['&DownTee', '&amp;DownTee'],
            'DownTee with semicolon' => ['&DownTee;', '⊤'],
            'DownTeeArrow without semicolon remains literal' => ['&DownTeeArrow', '&amp;DownTeeArrow'],
            'DownTeeArrow with semicolon' => ['&DownTeeArrow;', '↧'],
            'Downarrow without semicolon remains literal' => ['&Downarrow', '&amp;Downarrow'],
            'Downarrow with semicolon' => ['&Downarrow;', '⇓'],
            'Dscr without semicolon remains literal' => ['&Dscr', '&amp;Dscr'],
            'Dscr with semicolon' => ['&Dscr;', '𝒟'],
            'Dstrok without semicolon remains literal' => ['&Dstrok', '&amp;Dstrok'],
            'Dstrok with semicolon' => ['&Dstrok;', 'Đ'],
            'ENG without semicolon remains literal' => ['&ENG', '&amp;ENG'],
            'ENG with semicolon' => ['&ENG;', 'Ŋ'],
            'ETH without semicolon' => ['&ETH', 'Ð'],
            'ETH with semicolon' => ['&ETH;', 'Ð'],
            'Eacute without semicolon' => ['&Eacute', 'É'],
            'Eacute with semicolon' => ['&Eacute;', 'É'],
            'Ecaron without semicolon remains literal' => ['&Ecaron', '&amp;Ecaron'],
            'Ecaron with semicolon' => ['&Ecaron;', 'Ě'],
            'Ecirc without semicolon' => ['&Ecirc', 'Ê'],
            'Ecirc with semicolon' => ['&Ecirc;', 'Ê'],
            'Ecy without semicolon remains literal' => ['&Ecy', '&amp;Ecy'],
            'Ecy with semicolon' => ['&Ecy;', 'Э'],
            'Edot without semicolon remains literal' => ['&Edot', '&amp;Edot'],
            'Edot with semicolon' => ['&Edot;', 'Ė'],
            'Efr without semicolon remains literal' => ['&Efr', '&amp;Efr'],
            'Efr with semicolon' => ['&Efr;', '𝔈'],
            'Egrave without semicolon' => ['&Egrave', 'È'],
            'Egrave with semicolon' => ['&Egrave;', 'È'],
            'Element without semicolon remains literal' => ['&Element', '&amp;Element'],
            'Element with semicolon' => ['&Element;', '∈'],
            'Emacr without semicolon remains literal' => ['&Emacr', '&amp;Emacr'],
            'Emacr with semicolon' => ['&Emacr;', 'Ē'],
            'EmptySmallSquare without semicolon remains literal' => ['&EmptySmallSquare', '&amp;EmptySmallSquare'],
            'EmptySmallSquare with semicolon' => ['&EmptySmallSquare;', '◻'],
            'EmptyVerySmallSquare without semicolon remains literal' => ['&EmptyVerySmallSquare', '&amp;EmptyVerySmallSquare'],
            'EmptyVerySmallSquare with semicolon' => ['&EmptyVerySmallSquare;', '▫'],
            'Eogon without semicolon remains literal' => ['&Eogon', '&amp;Eogon'],
            'Eogon with semicolon' => ['&Eogon;', 'Ę'],
            'Eopf without semicolon remains literal' => ['&Eopf', '&amp;Eopf'],
            'Eopf with semicolon' => ['&Eopf;', '𝔼'],
            'Epsilon without semicolon remains literal' => ['&Epsilon', '&amp;Epsilon'],
            'Epsilon with semicolon' => ['&Epsilon;', 'Ε'],
            'Equal without semicolon remains literal' => ['&Equal', '&amp;Equal'],
            'Equal with semicolon' => ['&Equal;', '⩵'],
            'EqualTilde without semicolon remains literal' => ['&EqualTilde', '&amp;EqualTilde'],
            'EqualTilde with semicolon' => ['&EqualTilde;', '≂'],
            'Equilibrium without semicolon remains literal' => ['&Equilibrium', '&amp;Equilibrium'],
            'Equilibrium with semicolon' => ['&Equilibrium;', '⇌'],
            'Escr without semicolon remains literal' => ['&Escr', '&amp;Escr'],
            'Escr with semicolon' => ['&Escr;', 'ℰ'],
            'Esim without semicolon remains literal' => ['&Esim', '&amp;Esim'],
            'Esim with semicolon' => ['&Esim;', '⩳'],
            'Eta without semicolon remains literal' => ['&Eta', '&amp;Eta'],
            'Eta with semicolon' => ['&Eta;', 'Η'],
            'Euml without semicolon' => ['&Euml', 'Ë'],
            'Euml with semicolon' => ['&Euml;', 'Ë'],
            'Exists without semicolon remains literal' => ['&Exists', '&amp;Exists'],
            'Exists with semicolon' => ['&Exists;', '∃'],
            'ExponentialE without semicolon remains literal' => ['&ExponentialE', '&amp;ExponentialE'],
            'ExponentialE with semicolon' => ['&ExponentialE;', 'ⅇ'],
            'Fcy without semicolon remains literal' => ['&Fcy', '&amp;Fcy'],
            'Fcy with semicolon' => ['&Fcy;', 'Ф'],
            'Ffr without semicolon remains literal' => ['&Ffr', '&amp;Ffr'],
            'Ffr with semicolon' => ['&Ffr;', '𝔉'],
            'FilledSmallSquare without semicolon remains literal' => ['&FilledSmallSquare', '&amp;FilledSmallSquare'],
            'FilledSmallSquare with semicolon' => ['&FilledSmallSquare;', '◼'],
            'FilledVerySmallSquare without semicolon remains literal' => ['&FilledVerySmallSquare', '&amp;FilledVerySmallSquare'],
            'FilledVerySmallSquare with semicolon' => ['&FilledVerySmallSquare;', '▪'],
            'Fopf without semicolon remains literal' => ['&Fopf', '&amp;Fopf'],
            'Fopf with semicolon' => ['&Fopf;', '𝔽'],
            'ForAll without semicolon remains literal' => ['&ForAll', '&amp;ForAll'],
            'ForAll with semicolon' => ['&ForAll;', '∀'],
            'Fouriertrf without semicolon remains literal' => ['&Fouriertrf', '&amp;Fouriertrf'],
            'Fouriertrf with semicolon' => ['&Fouriertrf;', 'ℱ'],
            'Fscr without semicolon remains literal' => ['&Fscr', '&amp;Fscr'],
            'Fscr with semicolon' => ['&Fscr;', 'ℱ'],
            'GJcy without semicolon remains literal' => ['&GJcy', '&amp;GJcy'],
            'GJcy with semicolon' => ['&GJcy;', 'Ѓ'],
            'GT without semicolon' => ['&GT', '&gt;'],
            'GT with semicolon' => ['&GT;', '&gt;'],
            'Gamma without semicolon remains literal' => ['&Gamma', '&amp;Gamma'],
            'Gamma with semicolon' => ['&Gamma;', 'Γ'],
            'Gammad without semicolon remains literal' => ['&Gammad', '&amp;Gammad'],
            'Gammad with semicolon' => ['&Gammad;', 'Ϝ'],
            'Gbreve without semicolon remains literal' => ['&Gbreve', '&amp;Gbreve'],
            'Gbreve with semicolon' => ['&Gbreve;', 'Ğ'],
            'Gcedil without semicolon remains literal' => ['&Gcedil', '&amp;Gcedil'],
            'Gcedil with semicolon' => ['&Gcedil;', 'Ģ'],
            'Gcirc without semicolon remains literal' => ['&Gcirc', '&amp;Gcirc'],
            'Gcirc with semicolon' => ['&Gcirc;', 'Ĝ'],
            'Gcy without semicolon remains literal' => ['&Gcy', '&amp;Gcy'],
            'Gcy with semicolon' => ['&Gcy;', 'Г'],
            'Gdot without semicolon remains literal' => ['&Gdot', '&amp;Gdot'],
            'Gdot with semicolon' => ['&Gdot;', 'Ġ'],
            'Gfr without semicolon remains literal' => ['&Gfr', '&amp;Gfr'],
            'Gfr with semicolon' => ['&Gfr;', '𝔊'],
            'Gg without semicolon remains literal' => ['&Gg', '&amp;Gg'],
            'Gg with semicolon' => ['&Gg;', '⋙'],
            'Gopf without semicolon remains literal' => ['&Gopf', '&amp;Gopf'],
            'Gopf with semicolon' => ['&Gopf;', '𝔾'],
            'GreaterEqual without semicolon remains literal' => ['&GreaterEqual', '&amp;GreaterEqual'],
            'GreaterEqual with semicolon' => ['&GreaterEqual;', '≥'],
            'GreaterEqualLess without semicolon remains literal' => ['&GreaterEqualLess', '&amp;GreaterEqualLess'],
            'GreaterEqualLess with semicolon' => ['&GreaterEqualLess;', '⋛'],
            'GreaterFullEqual without semicolon remains literal' => ['&GreaterFullEqual', '&amp;GreaterFullEqual'],
            'GreaterFullEqual with semicolon' => ['&GreaterFullEqual;', '≧'],
            'GreaterGreater without semicolon remains literal' => ['&GreaterGreater', '&amp;GreaterGreater'],
            'GreaterGreater with semicolon' => ['&GreaterGreater;', '⪢'],
            'GreaterLess without semicolon remains literal' => ['&GreaterLess', '&amp;GreaterLess'],
            'GreaterLess with semicolon' => ['&GreaterLess;', '≷'],
            'GreaterSlantEqual without semicolon remains literal' => ['&GreaterSlantEqual', '&amp;GreaterSlantEqual'],
            'GreaterSlantEqual with semicolon' => ['&GreaterSlantEqual;', '⩾'],
            'GreaterTilde without semicolon remains literal' => ['&GreaterTilde', '&amp;GreaterTilde'],
            'GreaterTilde with semicolon' => ['&GreaterTilde;', '≳'],
            'Gscr without semicolon remains literal' => ['&Gscr', '&amp;Gscr'],
            'Gscr with semicolon' => ['&Gscr;', '𝒢'],
            'Gt without semicolon remains literal' => ['&Gt', '&amp;Gt'],
            'Gt with semicolon' => ['&Gt;', '≫'],
            'HARDcy without semicolon remains literal' => ['&HARDcy', '&amp;HARDcy'],
            'HARDcy with semicolon' => ['&HARDcy;', 'Ъ'],
            'Hacek without semicolon remains literal' => ['&Hacek', '&amp;Hacek'],
            'Hacek with semicolon' => ['&Hacek;', 'ˇ'],
            'Hat without semicolon remains literal' => ['&Hat', '&amp;Hat'],
            'Hat with semicolon' => ['&Hat;', '^'],
            'Hcirc without semicolon remains literal' => ['&Hcirc', '&amp;Hcirc'],
            'Hcirc with semicolon' => ['&Hcirc;', 'Ĥ'],
            'Hfr without semicolon remains literal' => ['&Hfr', '&amp;Hfr'],
            'Hfr with semicolon' => ['&Hfr;', 'ℌ'],
            'HilbertSpace without semicolon remains literal' => ['&HilbertSpace', '&amp;HilbertSpace'],
            'HilbertSpace with semicolon' => ['&HilbertSpace;', 'ℋ'],
            'Hopf without semicolon remains literal' => ['&Hopf', '&amp;Hopf'],
            'Hopf with semicolon' => ['&Hopf;', 'ℍ'],
            'HorizontalLine without semicolon remains literal' => ['&HorizontalLine', '&amp;HorizontalLine'],
            'HorizontalLine with semicolon' => ['&HorizontalLine;', '─'],
            'Hscr without semicolon remains literal' => ['&Hscr', '&amp;Hscr'],
            'Hscr with semicolon' => ['&Hscr;', 'ℋ'],
            'Hstrok without semicolon remains literal' => ['&Hstrok', '&amp;Hstrok'],
            'Hstrok with semicolon' => ['&Hstrok;', 'Ħ'],
            'HumpDownHump without semicolon remains literal' => ['&HumpDownHump', '&amp;HumpDownHump'],
            'HumpDownHump with semicolon' => ['&HumpDownHump;', '≎'],
            'HumpEqual without semicolon remains literal' => ['&HumpEqual', '&amp;HumpEqual'],
            'HumpEqual with semicolon' => ['&HumpEqual;', '≏'],
            'IEcy without semicolon remains literal' => ['&IEcy', '&amp;IEcy'],
            'IEcy with semicolon' => ['&IEcy;', 'Е'],
            'IJlig without semicolon remains literal' => ['&IJlig', '&amp;IJlig'],
            'IJlig with semicolon' => ['&IJlig;', 'Ĳ'],
            'IOcy without semicolon remains literal' => ['&IOcy', '&amp;IOcy'],
            'IOcy with semicolon' => ['&IOcy;', 'Ё'],
            'Iacute without semicolon' => ['&Iacute', 'Í'],
            'Iacute with semicolon' => ['&Iacute;', 'Í'],
            'Icirc without semicolon' => ['&Icirc', 'Î'],
            'Icirc with semicolon' => ['&Icirc;', 'Î'],
            'Icy without semicolon remains literal' => ['&Icy', '&amp;Icy'],
            'Icy with semicolon' => ['&Icy;', 'И'],
            'Idot without semicolon remains literal' => ['&Idot', '&amp;Idot'],
            'Idot with semicolon' => ['&Idot;', 'İ'],
            'Ifr without semicolon remains literal' => ['&Ifr', '&amp;Ifr'],
            'Ifr with semicolon' => ['&Ifr;', 'ℑ'],
            'Igrave without semicolon' => ['&Igrave', 'Ì'],
            'Igrave with semicolon' => ['&Igrave;', 'Ì'],
            'Im without semicolon remains literal' => ['&Im', '&amp;Im'],
            'Im with semicolon' => ['&Im;', 'ℑ'],
            'Imacr without semicolon remains literal' => ['&Imacr', '&amp;Imacr'],
            'Imacr with semicolon' => ['&Imacr;', 'Ī'],
            'ImaginaryI without semicolon remains literal' => ['&ImaginaryI', '&amp;ImaginaryI'],
            'ImaginaryI with semicolon' => ['&ImaginaryI;', 'ⅈ'],
            'Implies without semicolon remains literal' => ['&Implies', '&amp;Implies'],
            'Implies with semicolon' => ['&Implies;', '⇒'],
            'Int without semicolon remains literal' => ['&Int', '&amp;Int'],
            'Int with semicolon' => ['&Int;', '∬'],
            'Integral without semicolon remains literal' => ['&Integral', '&amp;Integral'],
            'Integral with semicolon' => ['&Integral;', '∫'],
            'Intersection without semicolon remains literal' => ['&Intersection', '&amp;Intersection'],
            'Intersection with semicolon' => ['&Intersection;', '⋂'],
            'InvisibleComma without semicolon remains literal' => ['&InvisibleComma', '&amp;InvisibleComma'],
            'InvisibleComma with semicolon' => ['&InvisibleComma;', '⁣'],
            'InvisibleTimes without semicolon remains literal' => ['&InvisibleTimes', '&amp;InvisibleTimes'],
            'InvisibleTimes with semicolon' => ['&InvisibleTimes;', '⁢'],
            'Iogon without semicolon remains literal' => ['&Iogon', '&amp;Iogon'],
            'Iogon with semicolon' => ['&Iogon;', 'Į'],
            'Iopf without semicolon remains literal' => ['&Iopf', '&amp;Iopf'],
            'Iopf with semicolon' => ['&Iopf;', '𝕀'],
            'Iota without semicolon remains literal' => ['&Iota', '&amp;Iota'],
            'Iota with semicolon' => ['&Iota;', 'Ι'],
            'Iscr without semicolon remains literal' => ['&Iscr', '&amp;Iscr'],
            'Iscr with semicolon' => ['&Iscr;', 'ℐ'],
            'Itilde without semicolon remains literal' => ['&Itilde', '&amp;Itilde'],
            'Itilde with semicolon' => ['&Itilde;', 'Ĩ'],
            'Iukcy without semicolon remains literal' => ['&Iukcy', '&amp;Iukcy'],
            'Iukcy with semicolon' => ['&Iukcy;', 'І'],
            'Iuml without semicolon' => ['&Iuml', 'Ï'],
            'Iuml with semicolon' => ['&Iuml;', 'Ï'],
            'Jcirc without semicolon remains literal' => ['&Jcirc', '&amp;Jcirc'],
            'Jcirc with semicolon' => ['&Jcirc;', 'Ĵ'],
            'Jcy without semicolon remains literal' => ['&Jcy', '&amp;Jcy'],
            'Jcy with semicolon' => ['&Jcy;', 'Й'],
            'Jfr without semicolon remains literal' => ['&Jfr', '&amp;Jfr'],
            'Jfr with semicolon' => ['&Jfr;', '𝔍'],
            'Jopf without semicolon remains literal' => ['&Jopf', '&amp;Jopf'],
            'Jopf with semicolon' => ['&Jopf;', '𝕁'],
            'Jscr without semicolon remains literal' => ['&Jscr', '&amp;Jscr'],
            'Jscr with semicolon' => ['&Jscr;', '𝒥'],
            'Jsercy without semicolon remains literal' => ['&Jsercy', '&amp;Jsercy'],
            'Jsercy with semicolon' => ['&Jsercy;', 'Ј'],
            'Jukcy without semicolon remains literal' => ['&Jukcy', '&amp;Jukcy'],
            'Jukcy with semicolon' => ['&Jukcy;', 'Є'],
            'KHcy without semicolon remains literal' => ['&KHcy', '&amp;KHcy'],
            'KHcy with semicolon' => ['&KHcy;', 'Х'],
            'KJcy without semicolon remains literal' => ['&KJcy', '&amp;KJcy'],
            'KJcy with semicolon' => ['&KJcy;', 'Ќ'],
            'Kappa without semicolon remains literal' => ['&Kappa', '&amp;Kappa'],
            'Kappa with semicolon' => ['&Kappa;', 'Κ'],
            'Kcedil without semicolon remains literal' => ['&Kcedil', '&amp;Kcedil'],
            'Kcedil with semicolon' => ['&Kcedil;', 'Ķ'],
            'Kcy without semicolon remains literal' => ['&Kcy', '&amp;Kcy'],
            'Kcy with semicolon' => ['&Kcy;', 'К'],
            'Kfr without semicolon remains literal' => ['&Kfr', '&amp;Kfr'],
            'Kfr with semicolon' => ['&Kfr;', '𝔎'],
            'Kopf without semicolon remains literal' => ['&Kopf', '&amp;Kopf'],
            'Kopf with semicolon' => ['&Kopf;', '𝕂'],
            'Kscr without semicolon remains literal' => ['&Kscr', '&amp;Kscr'],
            'Kscr with semicolon' => ['&Kscr;', '𝒦'],
            'LJcy without semicolon remains literal' => ['&LJcy', '&amp;LJcy'],
            'LJcy with semicolon' => ['&LJcy;', 'Љ'],
            'LT without semicolon' => ['&LT', '&lt;'],
            'LT with semicolon' => ['&LT;', '&lt;'],
            'Lacute without semicolon remains literal' => ['&Lacute', '&amp;Lacute'],
            'Lacute with semicolon' => ['&Lacute;', 'Ĺ'],
            'Lambda without semicolon remains literal' => ['&Lambda', '&amp;Lambda'],
            'Lambda with semicolon' => ['&Lambda;', 'Λ'],
            'Lang without semicolon remains literal' => ['&Lang', '&amp;Lang'],
            'Lang with semicolon' => ['&Lang;', '⟪'],
            'Laplacetrf without semicolon remains literal' => ['&Laplacetrf', '&amp;Laplacetrf'],
            'Laplacetrf with semicolon' => ['&Laplacetrf;', 'ℒ'],
            'Larr without semicolon remains literal' => ['&Larr', '&amp;Larr'],
            'Larr with semicolon' => ['&Larr;', '↞'],
            'Lcaron without semicolon remains literal' => ['&Lcaron', '&amp;Lcaron'],
            'Lcaron with semicolon' => ['&Lcaron;', 'Ľ'],
            'Lcedil without semicolon remains literal' => ['&Lcedil', '&amp;Lcedil'],
            'Lcedil with semicolon' => ['&Lcedil;', 'Ļ'],
            'Lcy without semicolon remains literal' => ['&Lcy', '&amp;Lcy'],
            'Lcy with semicolon' => ['&Lcy;', 'Л'],
            'LeftAngleBracket without semicolon remains literal' => ['&LeftAngleBracket', '&amp;LeftAngleBracket'],
            'LeftAngleBracket with semicolon' => ['&LeftAngleBracket;', '⟨'],
            'LeftArrow without semicolon remains literal' => ['&LeftArrow', '&amp;LeftArrow'],
            'LeftArrow with semicolon' => ['&LeftArrow;', '←'],
            'LeftArrowBar without semicolon remains literal' => ['&LeftArrowBar', '&amp;LeftArrowBar'],
            'LeftArrowBar with semicolon' => ['&LeftArrowBar;', '⇤'],
            'LeftArrowRightArrow without semicolon remains literal' => ['&LeftArrowRightArrow', '&amp;LeftArrowRightArrow'],
            'LeftArrowRightArrow with semicolon' => ['&LeftArrowRightArrow;', '⇆'],
            'LeftCeiling without semicolon remains literal' => ['&LeftCeiling', '&amp;LeftCeiling'],
            'LeftCeiling with semicolon' => ['&LeftCeiling;', '⌈'],
            'LeftDoubleBracket without semicolon remains literal' => ['&LeftDoubleBracket', '&amp;LeftDoubleBracket'],
            'LeftDoubleBracket with semicolon' => ['&LeftDoubleBracket;', '⟦'],
            'LeftDownTeeVector without semicolon remains literal' => ['&LeftDownTeeVector', '&amp;LeftDownTeeVector'],
            'LeftDownTeeVector with semicolon' => ['&LeftDownTeeVector;', '⥡'],
            'LeftDownVector without semicolon remains literal' => ['&LeftDownVector', '&amp;LeftDownVector'],
            'LeftDownVector with semicolon' => ['&LeftDownVector;', '⇃'],
            'LeftDownVectorBar without semicolon remains literal' => ['&LeftDownVectorBar', '&amp;LeftDownVectorBar'],
            'LeftDownVectorBar with semicolon' => ['&LeftDownVectorBar;', '⥙'],
            'LeftFloor without semicolon remains literal' => ['&LeftFloor', '&amp;LeftFloor'],
            'LeftFloor with semicolon' => ['&LeftFloor;', '⌊'],
            'LeftRightArrow without semicolon remains literal' => ['&LeftRightArrow', '&amp;LeftRightArrow'],
            'LeftRightArrow with semicolon' => ['&LeftRightArrow;', '↔'],
            'LeftRightVector without semicolon remains literal' => ['&LeftRightVector', '&amp;LeftRightVector'],
            'LeftRightVector with semicolon' => ['&LeftRightVector;', '⥎'],
            'LeftTee without semicolon remains literal' => ['&LeftTee', '&amp;LeftTee'],
            'LeftTee with semicolon' => ['&LeftTee;', '⊣'],
            'LeftTeeArrow without semicolon remains literal' => ['&LeftTeeArrow', '&amp;LeftTeeArrow'],
            'LeftTeeArrow with semicolon' => ['&LeftTeeArrow;', '↤'],
            'LeftTeeVector without semicolon remains literal' => ['&LeftTeeVector', '&amp;LeftTeeVector'],
            'LeftTeeVector with semicolon' => ['&LeftTeeVector;', '⥚'],
            'LeftTriangle without semicolon remains literal' => ['&LeftTriangle', '&amp;LeftTriangle'],
            'LeftTriangle with semicolon' => ['&LeftTriangle;', '⊲'],
            'LeftTriangleBar without semicolon remains literal' => ['&LeftTriangleBar', '&amp;LeftTriangleBar'],
            'LeftTriangleBar with semicolon' => ['&LeftTriangleBar;', '⧏'],
            'LeftTriangleEqual without semicolon remains literal' => ['&LeftTriangleEqual', '&amp;LeftTriangleEqual'],
            'LeftTriangleEqual with semicolon' => ['&LeftTriangleEqual;', '⊴'],
            'LeftUpDownVector without semicolon remains literal' => ['&LeftUpDownVector', '&amp;LeftUpDownVector'],
            'LeftUpDownVector with semicolon' => ['&LeftUpDownVector;', '⥑'],
            'LeftUpTeeVector without semicolon remains literal' => ['&LeftUpTeeVector', '&amp;LeftUpTeeVector'],
            'LeftUpTeeVector with semicolon' => ['&LeftUpTeeVector;', '⥠'],
            'LeftUpVector without semicolon remains literal' => ['&LeftUpVector', '&amp;LeftUpVector'],
            'LeftUpVector with semicolon' => ['&LeftUpVector;', '↿'],
            'LeftUpVectorBar without semicolon remains literal' => ['&LeftUpVectorBar', '&amp;LeftUpVectorBar'],
            'LeftUpVectorBar with semicolon' => ['&LeftUpVectorBar;', '⥘'],
            'LeftVector without semicolon remains literal' => ['&LeftVector', '&amp;LeftVector'],
            'LeftVector with semicolon' => ['&LeftVector;', '↼'],
            'LeftVectorBar without semicolon remains literal' => ['&LeftVectorBar', '&amp;LeftVectorBar'],
            'LeftVectorBar with semicolon' => ['&LeftVectorBar;', '⥒'],
            'Leftarrow without semicolon remains literal' => ['&Leftarrow', '&amp;Leftarrow'],
            'Leftarrow with semicolon' => ['&Leftarrow;', '⇐'],
            'Leftrightarrow without semicolon remains literal' => ['&Leftrightarrow', '&amp;Leftrightarrow'],
            'Leftrightarrow with semicolon' => ['&Leftrightarrow;', '⇔'],
            'LessEqualGreater without semicolon remains literal' => ['&LessEqualGreater', '&amp;LessEqualGreater'],
            'LessEqualGreater with semicolon' => ['&LessEqualGreater;', '⋚'],
            'LessFullEqual without semicolon remains literal' => ['&LessFullEqual', '&amp;LessFullEqual'],
            'LessFullEqual with semicolon' => ['&LessFullEqual;', '≦'],
            'LessGreater without semicolon remains literal' => ['&LessGreater', '&amp;LessGreater'],
            'LessGreater with semicolon' => ['&LessGreater;', '≶'],
            'LessLess without semicolon remains literal' => ['&LessLess', '&amp;LessLess'],
            'LessLess with semicolon' => ['&LessLess;', '⪡'],
            'LessSlantEqual without semicolon remains literal' => ['&LessSlantEqual', '&amp;LessSlantEqual'],
            'LessSlantEqual with semicolon' => ['&LessSlantEqual;', '⩽'],
            'LessTilde without semicolon remains literal' => ['&LessTilde', '&amp;LessTilde'],
            'LessTilde with semicolon' => ['&LessTilde;', '≲'],
            'Lfr without semicolon remains literal' => ['&Lfr', '&amp;Lfr'],
            'Lfr with semicolon' => ['&Lfr;', '𝔏'],
            'Ll without semicolon remains literal' => ['&Ll', '&amp;Ll'],
            'Ll with semicolon' => ['&Ll;', '⋘'],
            'Lleftarrow without semicolon remains literal' => ['&Lleftarrow', '&amp;Lleftarrow'],
            'Lleftarrow with semicolon' => ['&Lleftarrow;', '⇚'],
            'Lmidot without semicolon remains literal' => ['&Lmidot', '&amp;Lmidot'],
            'Lmidot with semicolon' => ['&Lmidot;', 'Ŀ'],
            'LongLeftArrow without semicolon remains literal' => ['&LongLeftArrow', '&amp;LongLeftArrow'],
            'LongLeftArrow with semicolon' => ['&LongLeftArrow;', '⟵'],
            'LongLeftRightArrow without semicolon remains literal' => ['&LongLeftRightArrow', '&amp;LongLeftRightArrow'],
            'LongLeftRightArrow with semicolon' => ['&LongLeftRightArrow;', '⟷'],
            'LongRightArrow without semicolon remains literal' => ['&LongRightArrow', '&amp;LongRightArrow'],
            'LongRightArrow with semicolon' => ['&LongRightArrow;', '⟶'],
            'Longleftarrow without semicolon remains literal' => ['&Longleftarrow', '&amp;Longleftarrow'],
            'Longleftarrow with semicolon' => ['&Longleftarrow;', '⟸'],
            'Longleftrightarrow without semicolon remains literal' => ['&Longleftrightarrow', '&amp;Longleftrightarrow'],
            'Longleftrightarrow with semicolon' => ['&Longleftrightarrow;', '⟺'],
            'Longrightarrow without semicolon remains literal' => ['&Longrightarrow', '&amp;Longrightarrow'],
            'Longrightarrow with semicolon' => ['&Longrightarrow;', '⟹'],
            'Lopf without semicolon remains literal' => ['&Lopf', '&amp;Lopf'],
            'Lopf with semicolon' => ['&Lopf;', '𝕃'],
            'LowerLeftArrow without semicolon remains literal' => ['&LowerLeftArrow', '&amp;LowerLeftArrow'],
            'LowerLeftArrow with semicolon' => ['&LowerLeftArrow;', '↙'],
            'LowerRightArrow without semicolon remains literal' => ['&LowerRightArrow', '&amp;LowerRightArrow'],
            'LowerRightArrow with semicolon' => ['&LowerRightArrow;', '↘'],
            'Lscr without semicolon remains literal' => ['&Lscr', '&amp;Lscr'],
            'Lscr with semicolon' => ['&Lscr;', 'ℒ'],
            'Lsh without semicolon remains literal' => ['&Lsh', '&amp;Lsh'],
            'Lsh with semicolon' => ['&Lsh;', '↰'],
            'Lstrok without semicolon remains literal' => ['&Lstrok', '&amp;Lstrok'],
            'Lstrok with semicolon' => ['&Lstrok;', 'Ł'],
            'Lt without semicolon remains literal' => ['&Lt', '&amp;Lt'],
            'Lt with semicolon' => ['&Lt;', '≪'],
            'Map without semicolon remains literal' => ['&Map', '&amp;Map'],
            'Map with semicolon' => ['&Map;', '⤅'],
            'Mcy without semicolon remains literal' => ['&Mcy', '&amp;Mcy'],
            'Mcy with semicolon' => ['&Mcy;', 'М'],
            'MediumSpace without semicolon remains literal' => ['&MediumSpace', '&amp;MediumSpace'],
            'MediumSpace with semicolon' => ['&MediumSpace;', ' '],
            'Mellintrf without semicolon remains literal' => ['&Mellintrf', '&amp;Mellintrf'],
            'Mellintrf with semicolon' => ['&Mellintrf;', 'ℳ'],
            'Mfr without semicolon remains literal' => ['&Mfr', '&amp;Mfr'],
            'Mfr with semicolon' => ['&Mfr;', '𝔐'],
            'MinusPlus without semicolon remains literal' => ['&MinusPlus', '&amp;MinusPlus'],
            'MinusPlus with semicolon' => ['&MinusPlus;', '∓'],
            'Mopf without semicolon remains literal' => ['&Mopf', '&amp;Mopf'],
            'Mopf with semicolon' => ['&Mopf;', '𝕄'],
            'Mscr without semicolon remains literal' => ['&Mscr', '&amp;Mscr'],
            'Mscr with semicolon' => ['&Mscr;', 'ℳ'],
            'Mu without semicolon remains literal' => ['&Mu', '&amp;Mu'],
            'Mu with semicolon' => ['&Mu;', 'Μ'],
            'NJcy without semicolon remains literal' => ['&NJcy', '&amp;NJcy'],
            'NJcy with semicolon' => ['&NJcy;', 'Њ'],
            'Nacute without semicolon remains literal' => ['&Nacute', '&amp;Nacute'],
            'Nacute with semicolon' => ['&Nacute;', 'Ń'],
            'Ncaron without semicolon remains literal' => ['&Ncaron', '&amp;Ncaron'],
            'Ncaron with semicolon' => ['&Ncaron;', 'Ň'],
            'Ncedil without semicolon remains literal' => ['&Ncedil', '&amp;Ncedil'],
            'Ncedil with semicolon' => ['&Ncedil;', 'Ņ'],
            'Ncy without semicolon remains literal' => ['&Ncy', '&amp;Ncy'],
            'Ncy with semicolon' => ['&Ncy;', 'Н'],
            'NegativeMediumSpace without semicolon remains literal' => ['&NegativeMediumSpace', '&amp;NegativeMediumSpace'],
            'NegativeMediumSpace with semicolon' => ['&NegativeMediumSpace;', "\u{200B}"],
            'NegativeThickSpace without semicolon remains literal' => ['&NegativeThickSpace', '&amp;NegativeThickSpace'],
            'NegativeThickSpace with semicolon' => ['&NegativeThickSpace;', "\u{200B}"],
            'NegativeThinSpace without semicolon remains literal' => ['&NegativeThinSpace', '&amp;NegativeThinSpace'],
            'NegativeThinSpace with semicolon' => ['&NegativeThinSpace;', "\u{200B}"],
            'NegativeVeryThinSpace without semicolon remains literal' => ['&NegativeVeryThinSpace', '&amp;NegativeVeryThinSpace'],
            'NegativeVeryThinSpace with semicolon' => ['&NegativeVeryThinSpace;', "\u{200B}"],
            'NestedGreaterGreater without semicolon remains literal' => ['&NestedGreaterGreater', '&amp;NestedGreaterGreater'],
            'NestedGreaterGreater with semicolon' => ['&NestedGreaterGreater;', '≫'],
            'NestedLessLess without semicolon remains literal' => ['&NestedLessLess', '&amp;NestedLessLess'],
            'NestedLessLess with semicolon' => ['&NestedLessLess;', '≪'],
            'NewLine without semicolon remains literal' => ['&NewLine', '&amp;NewLine'],
            'NewLine with semicolon' => ['&NewLine;', "\n"],
            'Nfr without semicolon remains literal' => ['&Nfr', '&amp;Nfr'],
            'Nfr with semicolon' => ['&Nfr;', '𝔑'],
            'NoBreak without semicolon remains literal' => ['&NoBreak', '&amp;NoBreak'],
            'NoBreak with semicolon' => ['&NoBreak;', "\u{2060}"],
            'NonBreakingSpace without semicolon remains literal' => ['&NonBreakingSpace', '&amp;NonBreakingSpace'],
            'NonBreakingSpace with semicolon' => ['&NonBreakingSpace;', '&nbsp;'],
            'Nopf without semicolon remains literal' => ['&Nopf', '&amp;Nopf'],
            'Nopf with semicolon' => ['&Nopf;', 'ℕ'],
            'Not without semicolon remains literal' => ['&Not', '&amp;Not'],
            'Not with semicolon' => ['&Not;', '⫬'],
            'NotCongruent without semicolon remains literal' => ['&NotCongruent', '&amp;NotCongruent'],
            'NotCongruent with semicolon' => ['&NotCongruent;', '≢'],
            'NotCupCap without semicolon remains literal' => ['&NotCupCap', '&amp;NotCupCap'],
            'NotCupCap with semicolon' => ['&NotCupCap;', '≭'],
            'NotDoubleVerticalBar without semicolon remains literal' => ['&NotDoubleVerticalBar', '&amp;NotDoubleVerticalBar'],
            'NotDoubleVerticalBar with semicolon' => ['&NotDoubleVerticalBar;', '∦'],
            'NotElement without semicolon remains literal' => ['&NotElement', '&amp;NotElement'],
            'NotElement with semicolon' => ['&NotElement;', '∉'],
            'NotEqual without semicolon remains literal' => ['&NotEqual', '&amp;NotEqual'],
            'NotEqual with semicolon' => ['&NotEqual;', '≠'],
            'NotEqualTilde without semicolon remains literal' => ['&NotEqualTilde', '&amp;NotEqualTilde'],
            'NotEqualTilde with semicolon' => ['&NotEqualTilde;', "\u{2242}\u{0338}"],
            'NotExists without semicolon remains literal' => ['&NotExists', '&amp;NotExists'],
            'NotExists with semicolon' => ['&NotExists;', '∄'],
            'NotGreater without semicolon remains literal' => ['&NotGreater', '&amp;NotGreater'],
            'NotGreater with semicolon' => ['&NotGreater;', '≯'],
            'NotGreaterEqual without semicolon remains literal' => ['&NotGreaterEqual', '&amp;NotGreaterEqual'],
            'NotGreaterEqual with semicolon' => ['&NotGreaterEqual;', '≱'],
            'NotGreaterFullEqual without semicolon remains literal' => ['&NotGreaterFullEqual', '&amp;NotGreaterFullEqual'],
            'NotGreaterFullEqual with semicolon' => ['&NotGreaterFullEqual;', "\u{2267}\u{0338}"],
            'NotGreaterGreater without semicolon remains literal' => ['&NotGreaterGreater', '&amp;NotGreaterGreater'],
            'NotGreaterGreater with semicolon' => ['&NotGreaterGreater;', "\u{226B}\u{0338}"],
            'NotGreaterLess without semicolon remains literal' => ['&NotGreaterLess', '&amp;NotGreaterLess'],
            'NotGreaterLess with semicolon' => ['&NotGreaterLess;', '≹'],
            'NotGreaterSlantEqual without semicolon remains literal' => ['&NotGreaterSlantEqual', '&amp;NotGreaterSlantEqual'],
            'NotGreaterSlantEqual with semicolon' => ['&NotGreaterSlantEqual;', "\u{2A7E}\u{0338}"],
            'NotGreaterTilde without semicolon remains literal' => ['&NotGreaterTilde', '&amp;NotGreaterTilde'],
            'NotGreaterTilde with semicolon' => ['&NotGreaterTilde;', '≵'],
            'NotHumpDownHump without semicolon remains literal' => ['&NotHumpDownHump', '&amp;NotHumpDownHump'],
            'NotHumpDownHump with semicolon' => ['&NotHumpDownHump;', "\u{224E}\u{0338}"],
            'NotHumpEqual without semicolon remains literal' => ['&NotHumpEqual', '&amp;NotHumpEqual'],
            'NotHumpEqual with semicolon' => ['&NotHumpEqual;', "\u{224F}\u{0338}"],
            'NotLeftTriangle without semicolon remains literal' => ['&NotLeftTriangle', '&amp;NotLeftTriangle'],
            'NotLeftTriangle with semicolon' => ['&NotLeftTriangle;', '⋪'],
            'NotLeftTriangleBar without semicolon remains literal' => ['&NotLeftTriangleBar', '&amp;NotLeftTriangleBar'],
            'NotLeftTriangleBar with semicolon' => ['&NotLeftTriangleBar;', "\u{29CF}\u{0338}"],
            'NotLeftTriangleEqual without semicolon remains literal' => ['&NotLeftTriangleEqual', '&amp;NotLeftTriangleEqual'],
            'NotLeftTriangleEqual with semicolon' => ['&NotLeftTriangleEqual;', '⋬'],
            'NotLess without semicolon remains literal' => ['&NotLess', '&amp;NotLess'],
            'NotLess with semicolon' => ['&NotLess;', '≮'],
            'NotLessEqual without semicolon remains literal' => ['&NotLessEqual', '&amp;NotLessEqual'],
            'NotLessEqual with semicolon' => ['&NotLessEqual;', '≰'],
            'NotLessGreater without semicolon remains literal' => ['&NotLessGreater', '&amp;NotLessGreater'],
            'NotLessGreater with semicolon' => ['&NotLessGreater;', '≸'],
            'NotLessLess without semicolon remains literal' => ['&NotLessLess', '&amp;NotLessLess'],
            'NotLessLess with semicolon' => ['&NotLessLess;', "\u{226A}\u{0338}"],
            'NotLessSlantEqual without semicolon remains literal' => ['&NotLessSlantEqual', '&amp;NotLessSlantEqual'],
            'NotLessSlantEqual with semicolon' => ['&NotLessSlantEqual;', "\u{2A7D}\u{0338}"],
            'NotLessTilde without semicolon remains literal' => ['&NotLessTilde', '&amp;NotLessTilde'],
            'NotLessTilde with semicolon' => ['&NotLessTilde;', '≴'],
            'NotNestedGreaterGreater without semicolon remains literal' => ['&NotNestedGreaterGreater', '&amp;NotNestedGreaterGreater'],
            'NotNestedGreaterGreater with semicolon' => ['&NotNestedGreaterGreater;', "\u{2AA2}\u{0338}"],
            'NotNestedLessLess without semicolon remains literal' => ['&NotNestedLessLess', '&amp;NotNestedLessLess'],
            'NotNestedLessLess with semicolon' => ['&NotNestedLessLess;', "\u{2AA1}\u{0338}"],
            'NotPrecedes without semicolon remains literal' => ['&NotPrecedes', '&amp;NotPrecedes'],
            'NotPrecedes with semicolon' => ['&NotPrecedes;', '⊀'],
            'NotPrecedesEqual without semicolon remains literal' => ['&NotPrecedesEqual', '&amp;NotPrecedesEqual'],
            'NotPrecedesEqual with semicolon' => ['&NotPrecedesEqual;', "\u{2AAF}\u{0338}"],
            'NotPrecedesSlantEqual without semicolon remains literal' => ['&NotPrecedesSlantEqual', '&amp;NotPrecedesSlantEqual'],
            'NotPrecedesSlantEqual with semicolon' => ['&NotPrecedesSlantEqual;', '⋠'],
            'NotReverseElement without semicolon remains literal' => ['&NotReverseElement', '&amp;NotReverseElement'],
            'NotReverseElement with semicolon' => ['&NotReverseElement;', '∌'],
            'NotRightTriangle without semicolon remains literal' => ['&NotRightTriangle', '&amp;NotRightTriangle'],
            'NotRightTriangle with semicolon' => ['&NotRightTriangle;', '⋫'],
            'NotRightTriangleBar without semicolon remains literal' => ['&NotRightTriangleBar', '&amp;NotRightTriangleBar'],
            'NotRightTriangleBar with semicolon' => ['&NotRightTriangleBar;', "\u{29D0}\u{0338}"],
            'NotRightTriangleEqual without semicolon remains literal' => ['&NotRightTriangleEqual', '&amp;NotRightTriangleEqual'],
            'NotRightTriangleEqual with semicolon' => ['&NotRightTriangleEqual;', '⋭'],
            'NotSquareSubset without semicolon remains literal' => ['&NotSquareSubset', '&amp;NotSquareSubset'],
            'NotSquareSubset with semicolon' => ['&NotSquareSubset;', "\u{228F}\u{0338}"],
            'NotSquareSubsetEqual without semicolon remains literal' => ['&NotSquareSubsetEqual', '&amp;NotSquareSubsetEqual'],
            'NotSquareSubsetEqual with semicolon' => ['&NotSquareSubsetEqual;', '⋢'],
            'NotSquareSuperset without semicolon remains literal' => ['&NotSquareSuperset', '&amp;NotSquareSuperset'],
            'NotSquareSuperset with semicolon' => ['&NotSquareSuperset;', "\u{2290}\u{0338}"],
            'NotSquareSupersetEqual without semicolon remains literal' => ['&NotSquareSupersetEqual', '&amp;NotSquareSupersetEqual'],
            'NotSquareSupersetEqual with semicolon' => ['&NotSquareSupersetEqual;', '⋣'],
            'NotSubset without semicolon remains literal' => ['&NotSubset', '&amp;NotSubset'],
            'NotSubset with semicolon' => ['&NotSubset;', "\u{2282}\u{20D2}"],
            'NotSubsetEqual without semicolon remains literal' => ['&NotSubsetEqual', '&amp;NotSubsetEqual'],
            'NotSubsetEqual with semicolon' => ['&NotSubsetEqual;', '⊈'],
            'NotSucceeds without semicolon remains literal' => ['&NotSucceeds', '&amp;NotSucceeds'],
            'NotSucceeds with semicolon' => ['&NotSucceeds;', '⊁'],
            'NotSucceedsEqual without semicolon remains literal' => ['&NotSucceedsEqual', '&amp;NotSucceedsEqual'],
            'NotSucceedsEqual with semicolon' => ['&NotSucceedsEqual;', "\u{2AB0}\u{0338}"],
            'NotSucceedsSlantEqual without semicolon remains literal' => ['&NotSucceedsSlantEqual', '&amp;NotSucceedsSlantEqual'],
            'NotSucceedsSlantEqual with semicolon' => ['&NotSucceedsSlantEqual;', '⋡'],
            'NotSucceedsTilde without semicolon remains literal' => ['&NotSucceedsTilde', '&amp;NotSucceedsTilde'],
            'NotSucceedsTilde with semicolon' => ['&NotSucceedsTilde;', "\u{227F}\u{0338}"],
            'NotSuperset without semicolon remains literal' => ['&NotSuperset', '&amp;NotSuperset'],
            'NotSuperset with semicolon' => ['&NotSuperset;', "\u{2283}\u{20D2}"],
            'NotSupersetEqual without semicolon remains literal' => ['&NotSupersetEqual', '&amp;NotSupersetEqual'],
            'NotSupersetEqual with semicolon' => ['&NotSupersetEqual;', '⊉'],
            'NotTilde without semicolon remains literal' => ['&NotTilde', '&amp;NotTilde'],
            'NotTilde with semicolon' => ['&NotTilde;', '≁'],
            'NotTildeEqual without semicolon remains literal' => ['&NotTildeEqual', '&amp;NotTildeEqual'],
            'NotTildeEqual with semicolon' => ['&NotTildeEqual;', '≄'],
            'NotTildeFullEqual without semicolon remains literal' => ['&NotTildeFullEqual', '&amp;NotTildeFullEqual'],
            'NotTildeFullEqual with semicolon' => ['&NotTildeFullEqual;', '≇'],
            'NotTildeTilde without semicolon remains literal' => ['&NotTildeTilde', '&amp;NotTildeTilde'],
            'NotTildeTilde with semicolon' => ['&NotTildeTilde;', '≉'],
            'NotVerticalBar without semicolon remains literal' => ['&NotVerticalBar', '&amp;NotVerticalBar'],
            'NotVerticalBar with semicolon' => ['&NotVerticalBar;', '∤'],
            'Nscr without semicolon remains literal' => ['&Nscr', '&amp;Nscr'],
            'Nscr with semicolon' => ['&Nscr;', '𝒩'],
            'Ntilde without semicolon' => ['&Ntilde', 'Ñ'],
            'Ntilde with semicolon' => ['&Ntilde;', 'Ñ'],
            'Nu without semicolon remains literal' => ['&Nu', '&amp;Nu'],
            'Nu with semicolon' => ['&Nu;', 'Ν'],
            'OElig without semicolon remains literal' => ['&OElig', '&amp;OElig'],
            'OElig with semicolon' => ['&OElig;', 'Œ'],
            'Oacute without semicolon' => ['&Oacute', 'Ó'],
            'Oacute with semicolon' => ['&Oacute;', 'Ó'],
            'Ocirc without semicolon' => ['&Ocirc', 'Ô'],
            'Ocirc with semicolon' => ['&Ocirc;', 'Ô'],
            'Ocy without semicolon remains literal' => ['&Ocy', '&amp;Ocy'],
            'Ocy with semicolon' => ['&Ocy;', 'О'],
            'Odblac without semicolon remains literal' => ['&Odblac', '&amp;Odblac'],
            'Odblac with semicolon' => ['&Odblac;', 'Ő'],
            'Ofr without semicolon remains literal' => ['&Ofr', '&amp;Ofr'],
            'Ofr with semicolon' => ['&Ofr;', '𝔒'],
            'Ograve without semicolon' => ['&Ograve', 'Ò'],
            'Ograve with semicolon' => ['&Ograve;', 'Ò'],
            'Omacr without semicolon remains literal' => ['&Omacr', '&amp;Omacr'],
            'Omacr with semicolon' => ['&Omacr;', 'Ō'],
            'Omega without semicolon remains literal' => ['&Omega', '&amp;Omega'],
            'Omega with semicolon' => ['&Omega;', 'Ω'],
            'Omicron without semicolon remains literal' => ['&Omicron', '&amp;Omicron'],
            'Omicron with semicolon' => ['&Omicron;', 'Ο'],
            'Oopf without semicolon remains literal' => ['&Oopf', '&amp;Oopf'],
            'Oopf with semicolon' => ['&Oopf;', '𝕆'],
            'OpenCurlyDoubleQuote without semicolon remains literal' => ['&OpenCurlyDoubleQuote', '&amp;OpenCurlyDoubleQuote'],
            'OpenCurlyDoubleQuote with semicolon' => ['&OpenCurlyDoubleQuote;', '“'],
            'OpenCurlyQuote without semicolon remains literal' => ['&OpenCurlyQuote', '&amp;OpenCurlyQuote'],
            'OpenCurlyQuote with semicolon' => ['&OpenCurlyQuote;', '‘'],
            'Or without semicolon remains literal' => ['&Or', '&amp;Or'],
            'Or with semicolon' => ['&Or;', '⩔'],
            'Oscr without semicolon remains literal' => ['&Oscr', '&amp;Oscr'],
            'Oscr with semicolon' => ['&Oscr;', '𝒪'],
            'Oslash without semicolon' => ['&Oslash', 'Ø'],
            'Oslash with semicolon' => ['&Oslash;', 'Ø'],
            'Otilde without semicolon' => ['&Otilde', 'Õ'],
            'Otilde with semicolon' => ['&Otilde;', 'Õ'],
            'Otimes without semicolon remains literal' => ['&Otimes', '&amp;Otimes'],
            'Otimes with semicolon' => ['&Otimes;', '⨷'],
            'Ouml without semicolon' => ['&Ouml', 'Ö'],
            'Ouml with semicolon' => ['&Ouml;', 'Ö'],
            'OverBar without semicolon remains literal' => ['&OverBar', '&amp;OverBar'],
            'OverBar with semicolon' => ['&OverBar;', '‾'],
            'OverBrace without semicolon remains literal' => ['&OverBrace', '&amp;OverBrace'],
            'OverBrace with semicolon' => ['&OverBrace;', '⏞'],
            'OverBracket without semicolon remains literal' => ['&OverBracket', '&amp;OverBracket'],
            'OverBracket with semicolon' => ['&OverBracket;', '⎴'],
            'OverParenthesis without semicolon remains literal' => ['&OverParenthesis', '&amp;OverParenthesis'],
            'OverParenthesis with semicolon' => ['&OverParenthesis;', '⏜'],
            'PartialD without semicolon remains literal' => ['&PartialD', '&amp;PartialD'],
            'PartialD with semicolon' => ['&PartialD;', '∂'],
            'Pcy without semicolon remains literal' => ['&Pcy', '&amp;Pcy'],
            'Pcy with semicolon' => ['&Pcy;', 'П'],
            'Pfr without semicolon remains literal' => ['&Pfr', '&amp;Pfr'],
            'Pfr with semicolon' => ['&Pfr;', '𝔓'],
            'Phi without semicolon remains literal' => ['&Phi', '&amp;Phi'],
            'Phi with semicolon' => ['&Phi;', 'Φ'],
            'Pi without semicolon remains literal' => ['&Pi', '&amp;Pi'],
            'Pi with semicolon' => ['&Pi;', 'Π'],
            'PlusMinus without semicolon remains literal' => ['&PlusMinus', '&amp;PlusMinus'],
            'PlusMinus with semicolon' => ['&PlusMinus;', '±'],
            'Poincareplane without semicolon remains literal' => ['&Poincareplane', '&amp;Poincareplane'],
            'Poincareplane with semicolon' => ['&Poincareplane;', 'ℌ'],
            'Popf without semicolon remains literal' => ['&Popf', '&amp;Popf'],
            'Popf with semicolon' => ['&Popf;', 'ℙ'],
            'Pr without semicolon remains literal' => ['&Pr', '&amp;Pr'],
            'Pr with semicolon' => ['&Pr;', '⪻'],
            'Precedes without semicolon remains literal' => ['&Precedes', '&amp;Precedes'],
            'Precedes with semicolon' => ['&Precedes;', '≺'],
            'PrecedesEqual without semicolon remains literal' => ['&PrecedesEqual', '&amp;PrecedesEqual'],
            'PrecedesEqual with semicolon' => ['&PrecedesEqual;', '⪯'],
            'PrecedesSlantEqual without semicolon remains literal' => ['&PrecedesSlantEqual', '&amp;PrecedesSlantEqual'],
            'PrecedesSlantEqual with semicolon' => ['&PrecedesSlantEqual;', '≼'],
            'PrecedesTilde without semicolon remains literal' => ['&PrecedesTilde', '&amp;PrecedesTilde'],
            'PrecedesTilde with semicolon' => ['&PrecedesTilde;', '≾'],
            'Prime without semicolon remains literal' => ['&Prime', '&amp;Prime'],
            'Prime with semicolon' => ['&Prime;', '″'],
            'Product without semicolon remains literal' => ['&Product', '&amp;Product'],
            'Product with semicolon' => ['&Product;', '∏'],
            'Proportion without semicolon remains literal' => ['&Proportion', '&amp;Proportion'],
            'Proportion with semicolon' => ['&Proportion;', '∷'],
            'Proportional without semicolon remains literal' => ['&Proportional', '&amp;Proportional'],
            'Proportional with semicolon' => ['&Proportional;', '∝'],
            'Pscr without semicolon remains literal' => ['&Pscr', '&amp;Pscr'],
            'Pscr with semicolon' => ['&Pscr;', '𝒫'],
            'Psi without semicolon remains literal' => ['&Psi', '&amp;Psi'],
            'Psi with semicolon' => ['&Psi;', 'Ψ'],
            'QUOT without semicolon' => ['&QUOT', '"'],
            'QUOT with semicolon' => ['&QUOT;', '"'],
            'Qfr without semicolon remains literal' => ['&Qfr', '&amp;Qfr'],
            'Qfr with semicolon' => ['&Qfr;', '𝔔'],
            'Qopf without semicolon remains literal' => ['&Qopf', '&amp;Qopf'],
            'Qopf with semicolon' => ['&Qopf;', 'ℚ'],
            'Qscr without semicolon remains literal' => ['&Qscr', '&amp;Qscr'],
            'Qscr with semicolon' => ['&Qscr;', '𝒬'],
            'RBarr without semicolon remains literal' => ['&RBarr', '&amp;RBarr'],
            'RBarr with semicolon' => ['&RBarr;', '⤐'],
            'REG without semicolon' => ['&REG', '®'],
            'REG with semicolon' => ['&REG;', '®'],
            'Racute without semicolon remains literal' => ['&Racute', '&amp;Racute'],
            'Racute with semicolon' => ['&Racute;', 'Ŕ'],
            'Rang without semicolon remains literal' => ['&Rang', '&amp;Rang'],
            'Rang with semicolon' => ['&Rang;', '⟫'],
            'Rarr without semicolon remains literal' => ['&Rarr', '&amp;Rarr'],
            'Rarr with semicolon' => ['&Rarr;', '↠'],
            'Rarrtl without semicolon remains literal' => ['&Rarrtl', '&amp;Rarrtl'],
            'Rarrtl with semicolon' => ['&Rarrtl;', '⤖'],
            'Rcaron without semicolon remains literal' => ['&Rcaron', '&amp;Rcaron'],
            'Rcaron with semicolon' => ['&Rcaron;', 'Ř'],
            'Rcedil without semicolon remains literal' => ['&Rcedil', '&amp;Rcedil'],
            'Rcedil with semicolon' => ['&Rcedil;', 'Ŗ'],
            'Rcy without semicolon remains literal' => ['&Rcy', '&amp;Rcy'],
            'Rcy with semicolon' => ['&Rcy;', 'Р'],
            'Re without semicolon remains literal' => ['&Re', '&amp;Re'],
            'Re with semicolon' => ['&Re;', 'ℜ'],
            'ReverseElement without semicolon remains literal' => ['&ReverseElement', '&amp;ReverseElement'],
            'ReverseElement with semicolon' => ['&ReverseElement;', '∋'],
            'ReverseEquilibrium without semicolon remains literal' => ['&ReverseEquilibrium', '&amp;ReverseEquilibrium'],
            'ReverseEquilibrium with semicolon' => ['&ReverseEquilibrium;', '⇋'],
            'ReverseUpEquilibrium without semicolon remains literal' => ['&ReverseUpEquilibrium', '&amp;ReverseUpEquilibrium'],
            'ReverseUpEquilibrium with semicolon' => ['&ReverseUpEquilibrium;', '⥯'],
            'Rfr without semicolon remains literal' => ['&Rfr', '&amp;Rfr'],
            'Rfr with semicolon' => ['&Rfr;', 'ℜ'],
            'Rho without semicolon remains literal' => ['&Rho', '&amp;Rho'],
            'Rho with semicolon' => ['&Rho;', 'Ρ'],
            'RightAngleBracket without semicolon remains literal' => ['&RightAngleBracket', '&amp;RightAngleBracket'],
            'RightAngleBracket with semicolon' => ['&RightAngleBracket;', '⟩'],
            'RightArrow without semicolon remains literal' => ['&RightArrow', '&amp;RightArrow'],
            'RightArrow with semicolon' => ['&RightArrow;', '→'],
            'RightArrowBar without semicolon remains literal' => ['&RightArrowBar', '&amp;RightArrowBar'],
            'RightArrowBar with semicolon' => ['&RightArrowBar;', '⇥'],
            'RightArrowLeftArrow without semicolon remains literal' => ['&RightArrowLeftArrow', '&amp;RightArrowLeftArrow'],
            'RightArrowLeftArrow with semicolon' => ['&RightArrowLeftArrow;', '⇄'],
            'RightCeiling without semicolon remains literal' => ['&RightCeiling', '&amp;RightCeiling'],
            'RightCeiling with semicolon' => ['&RightCeiling;', '⌉'],
            'RightDoubleBracket without semicolon remains literal' => ['&RightDoubleBracket', '&amp;RightDoubleBracket'],
            'RightDoubleBracket with semicolon' => ['&RightDoubleBracket;', '⟧'],
            'RightDownTeeVector without semicolon remains literal' => ['&RightDownTeeVector', '&amp;RightDownTeeVector'],
            'RightDownTeeVector with semicolon' => ['&RightDownTeeVector;', '⥝'],
            'RightDownVector without semicolon remains literal' => ['&RightDownVector', '&amp;RightDownVector'],
            'RightDownVector with semicolon' => ['&RightDownVector;', '⇂'],
            'RightDownVectorBar without semicolon remains literal' => ['&RightDownVectorBar', '&amp;RightDownVectorBar'],
            'RightDownVectorBar with semicolon' => ['&RightDownVectorBar;', '⥕'],
            'RightFloor without semicolon remains literal' => ['&RightFloor', '&amp;RightFloor'],
            'RightFloor with semicolon' => ['&RightFloor;', '⌋'],
            'RightTee without semicolon remains literal' => ['&RightTee', '&amp;RightTee'],
            'RightTee with semicolon' => ['&RightTee;', '⊢'],
            'RightTeeArrow without semicolon remains literal' => ['&RightTeeArrow', '&amp;RightTeeArrow'],
            'RightTeeArrow with semicolon' => ['&RightTeeArrow;', '↦'],
            'RightTeeVector without semicolon remains literal' => ['&RightTeeVector', '&amp;RightTeeVector'],
            'RightTeeVector with semicolon' => ['&RightTeeVector;', '⥛'],
            'RightTriangle without semicolon remains literal' => ['&RightTriangle', '&amp;RightTriangle'],
            'RightTriangle with semicolon' => ['&RightTriangle;', '⊳'],
            'RightTriangleBar without semicolon remains literal' => ['&RightTriangleBar', '&amp;RightTriangleBar'],
            'RightTriangleBar with semicolon' => ['&RightTriangleBar;', '⧐'],
            'RightTriangleEqual without semicolon remains literal' => ['&RightTriangleEqual', '&amp;RightTriangleEqual'],
            'RightTriangleEqual with semicolon' => ['&RightTriangleEqual;', '⊵'],
            'RightUpDownVector without semicolon remains literal' => ['&RightUpDownVector', '&amp;RightUpDownVector'],
            'RightUpDownVector with semicolon' => ['&RightUpDownVector;', '⥏'],
            'RightUpTeeVector without semicolon remains literal' => ['&RightUpTeeVector', '&amp;RightUpTeeVector'],
            'RightUpTeeVector with semicolon' => ['&RightUpTeeVector;', '⥜'],
            'RightUpVector without semicolon remains literal' => ['&RightUpVector', '&amp;RightUpVector'],
            'RightUpVector with semicolon' => ['&RightUpVector;', '↾'],
            'RightUpVectorBar without semicolon remains literal' => ['&RightUpVectorBar', '&amp;RightUpVectorBar'],
            'RightUpVectorBar with semicolon' => ['&RightUpVectorBar;', '⥔'],
            'RightVector without semicolon remains literal' => ['&RightVector', '&amp;RightVector'],
            'RightVector with semicolon' => ['&RightVector;', '⇀'],
            'RightVectorBar without semicolon remains literal' => ['&RightVectorBar', '&amp;RightVectorBar'],
            'RightVectorBar with semicolon' => ['&RightVectorBar;', '⥓'],
            'Rightarrow without semicolon remains literal' => ['&Rightarrow', '&amp;Rightarrow'],
            'Rightarrow with semicolon' => ['&Rightarrow;', '⇒'],
            'Ropf without semicolon remains literal' => ['&Ropf', '&amp;Ropf'],
            'Ropf with semicolon' => ['&Ropf;', 'ℝ'],
            'RoundImplies without semicolon remains literal' => ['&RoundImplies', '&amp;RoundImplies'],
            'RoundImplies with semicolon' => ['&RoundImplies;', '⥰'],
            'Rrightarrow without semicolon remains literal' => ['&Rrightarrow', '&amp;Rrightarrow'],
            'Rrightarrow with semicolon' => ['&Rrightarrow;', '⇛'],
            'Rscr without semicolon remains literal' => ['&Rscr', '&amp;Rscr'],
            'Rscr with semicolon' => ['&Rscr;', 'ℛ'],
            'Rsh without semicolon remains literal' => ['&Rsh', '&amp;Rsh'],
            'Rsh with semicolon' => ['&Rsh;', '↱'],
            'RuleDelayed without semicolon remains literal' => ['&RuleDelayed', '&amp;RuleDelayed'],
            'RuleDelayed with semicolon' => ['&RuleDelayed;', '⧴'],
            'SHCHcy without semicolon remains literal' => ['&SHCHcy', '&amp;SHCHcy'],
            'SHCHcy with semicolon' => ['&SHCHcy;', 'Щ'],
            'SHcy without semicolon remains literal' => ['&SHcy', '&amp;SHcy'],
            'SHcy with semicolon' => ['&SHcy;', 'Ш'],
            'SOFTcy without semicolon remains literal' => ['&SOFTcy', '&amp;SOFTcy'],
            'SOFTcy with semicolon' => ['&SOFTcy;', 'Ь'],
            'Sacute without semicolon remains literal' => ['&Sacute', '&amp;Sacute'],
            'Sacute with semicolon' => ['&Sacute;', 'Ś'],
            'Sc without semicolon remains literal' => ['&Sc', '&amp;Sc'],
            'Sc with semicolon' => ['&Sc;', '⪼'],
            'Scaron without semicolon remains literal' => ['&Scaron', '&amp;Scaron'],
            'Scaron with semicolon' => ['&Scaron;', 'Š'],
            'Scedil without semicolon remains literal' => ['&Scedil', '&amp;Scedil'],
            'Scedil with semicolon' => ['&Scedil;', 'Ş'],
            'Scirc without semicolon remains literal' => ['&Scirc', '&amp;Scirc'],
            'Scirc with semicolon' => ['&Scirc;', 'Ŝ'],
            'Scy without semicolon remains literal' => ['&Scy', '&amp;Scy'],
            'Scy with semicolon' => ['&Scy;', 'С'],
            'Sfr without semicolon remains literal' => ['&Sfr', '&amp;Sfr'],
            'Sfr with semicolon' => ['&Sfr;', '𝔖'],
            'ShortDownArrow without semicolon remains literal' => ['&ShortDownArrow', '&amp;ShortDownArrow'],
            'ShortDownArrow with semicolon' => ['&ShortDownArrow;', '↓'],
            'ShortLeftArrow without semicolon remains literal' => ['&ShortLeftArrow', '&amp;ShortLeftArrow'],
            'ShortLeftArrow with semicolon' => ['&ShortLeftArrow;', '←'],
            'ShortRightArrow without semicolon remains literal' => ['&ShortRightArrow', '&amp;ShortRightArrow'],
            'ShortRightArrow with semicolon' => ['&ShortRightArrow;', '→'],
            'ShortUpArrow without semicolon remains literal' => ['&ShortUpArrow', '&amp;ShortUpArrow'],
            'ShortUpArrow with semicolon' => ['&ShortUpArrow;', '↑'],
            'Sigma without semicolon remains literal' => ['&Sigma', '&amp;Sigma'],
            'Sigma with semicolon' => ['&Sigma;', 'Σ'],
            'SmallCircle without semicolon remains literal' => ['&SmallCircle', '&amp;SmallCircle'],
            'SmallCircle with semicolon' => ['&SmallCircle;', '∘'],
            'Sopf without semicolon remains literal' => ['&Sopf', '&amp;Sopf'],
            'Sopf with semicolon' => ['&Sopf;', '𝕊'],
            'Sqrt without semicolon remains literal' => ['&Sqrt', '&amp;Sqrt'],
            'Sqrt with semicolon' => ['&Sqrt;', '√'],
            'Square without semicolon remains literal' => ['&Square', '&amp;Square'],
            'Square with semicolon' => ['&Square;', '□'],
            'SquareIntersection without semicolon remains literal' => ['&SquareIntersection', '&amp;SquareIntersection'],
            'SquareIntersection with semicolon' => ['&SquareIntersection;', '⊓'],
            'SquareSubset without semicolon remains literal' => ['&SquareSubset', '&amp;SquareSubset'],
            'SquareSubset with semicolon' => ['&SquareSubset;', '⊏'],
            'SquareSubsetEqual without semicolon remains literal' => ['&SquareSubsetEqual', '&amp;SquareSubsetEqual'],
            'SquareSubsetEqual with semicolon' => ['&SquareSubsetEqual;', '⊑'],
            'SquareSuperset without semicolon remains literal' => ['&SquareSuperset', '&amp;SquareSuperset'],
            'SquareSuperset with semicolon' => ['&SquareSuperset;', '⊐'],
            'SquareSupersetEqual without semicolon remains literal' => ['&SquareSupersetEqual', '&amp;SquareSupersetEqual'],
            'SquareSupersetEqual with semicolon' => ['&SquareSupersetEqual;', '⊒'],
            'SquareUnion without semicolon remains literal' => ['&SquareUnion', '&amp;SquareUnion'],
            'SquareUnion with semicolon' => ['&SquareUnion;', '⊔'],
            'Sscr without semicolon remains literal' => ['&Sscr', '&amp;Sscr'],
            'Sscr with semicolon' => ['&Sscr;', '𝒮'],
            'Star without semicolon remains literal' => ['&Star', '&amp;Star'],
            'Star with semicolon' => ['&Star;', '⋆'],
            'Sub without semicolon remains literal' => ['&Sub', '&amp;Sub'],
            'Sub with semicolon' => ['&Sub;', '⋐'],
            'Subset without semicolon remains literal' => ['&Subset', '&amp;Subset'],
            'Subset with semicolon' => ['&Subset;', '⋐'],
            'SubsetEqual without semicolon remains literal' => ['&SubsetEqual', '&amp;SubsetEqual'],
            'SubsetEqual with semicolon' => ['&SubsetEqual;', '⊆'],
            'Succeeds without semicolon remains literal' => ['&Succeeds', '&amp;Succeeds'],
            'Succeeds with semicolon' => ['&Succeeds;', '≻'],
            'SucceedsEqual without semicolon remains literal' => ['&SucceedsEqual', '&amp;SucceedsEqual'],
            'SucceedsEqual with semicolon' => ['&SucceedsEqual;', '⪰'],
            'SucceedsSlantEqual without semicolon remains literal' => ['&SucceedsSlantEqual', '&amp;SucceedsSlantEqual'],
            'SucceedsSlantEqual with semicolon' => ['&SucceedsSlantEqual;', '≽'],
            'SucceedsTilde without semicolon remains literal' => ['&SucceedsTilde', '&amp;SucceedsTilde'],
            'SucceedsTilde with semicolon' => ['&SucceedsTilde;', '≿'],
            'SuchThat without semicolon remains literal' => ['&SuchThat', '&amp;SuchThat'],
            'SuchThat with semicolon' => ['&SuchThat;', '∋'],
            'Sum without semicolon remains literal' => ['&Sum', '&amp;Sum'],
            'Sum with semicolon' => ['&Sum;', '∑'],
            'Sup without semicolon remains literal' => ['&Sup', '&amp;Sup'],
            'Sup with semicolon' => ['&Sup;', '⋑'],
            'Superset without semicolon remains literal' => ['&Superset', '&amp;Superset'],
            'Superset with semicolon' => ['&Superset;', '⊃'],
            'SupersetEqual without semicolon remains literal' => ['&SupersetEqual', '&amp;SupersetEqual'],
            'SupersetEqual with semicolon' => ['&SupersetEqual;', '⊇'],
            'Supset without semicolon remains literal' => ['&Supset', '&amp;Supset'],
            'Supset with semicolon' => ['&Supset;', '⋑'],
            'THORN without semicolon' => ['&THORN', 'Þ'],
            'THORN with semicolon' => ['&THORN;', 'Þ'],
            'TRADE without semicolon remains literal' => ['&TRADE', '&amp;TRADE'],
            'TRADE with semicolon' => ['&TRADE;', '™'],
            'TSHcy without semicolon remains literal' => ['&TSHcy', '&amp;TSHcy'],
            'TSHcy with semicolon' => ['&TSHcy;', 'Ћ'],
            'TScy without semicolon remains literal' => ['&TScy', '&amp;TScy'],
            'TScy with semicolon' => ['&TScy;', 'Ц'],
            'Tab without semicolon remains literal' => ['&Tab', '&amp;Tab'],
            'Tab with semicolon' => ['&Tab;', "\t"],
            'Tau without semicolon remains literal' => ['&Tau', '&amp;Tau'],
            'Tau with semicolon' => ['&Tau;', 'Τ'],
            'Tcaron without semicolon remains literal' => ['&Tcaron', '&amp;Tcaron'],
            'Tcaron with semicolon' => ['&Tcaron;', 'Ť'],
            'Tcedil without semicolon remains literal' => ['&Tcedil', '&amp;Tcedil'],
            'Tcedil with semicolon' => ['&Tcedil;', 'Ţ'],
            'Tcy without semicolon remains literal' => ['&Tcy', '&amp;Tcy'],
            'Tcy with semicolon' => ['&Tcy;', 'Т'],
            'Tfr without semicolon remains literal' => ['&Tfr', '&amp;Tfr'],
            'Tfr with semicolon' => ['&Tfr;', '𝔗'],
            'Therefore without semicolon remains literal' => ['&Therefore', '&amp;Therefore'],
            'Therefore with semicolon' => ['&Therefore;', '∴'],
            'Theta without semicolon remains literal' => ['&Theta', '&amp;Theta'],
            'Theta with semicolon' => ['&Theta;', 'Θ'],
            'ThickSpace without semicolon remains literal' => ['&ThickSpace', '&amp;ThickSpace'],
            'ThickSpace with semicolon' => ['&ThickSpace;', '  '],
            'ThinSpace without semicolon remains literal' => ['&ThinSpace', '&amp;ThinSpace'],
            'ThinSpace with semicolon' => ['&ThinSpace;', ' '],
            'Tilde without semicolon remains literal' => ['&Tilde', '&amp;Tilde'],
            'Tilde with semicolon' => ['&Tilde;', '∼'],
            'TildeEqual without semicolon remains literal' => ['&TildeEqual', '&amp;TildeEqual'],
            'TildeEqual with semicolon' => ['&TildeEqual;', '≃'],
            'TildeFullEqual without semicolon remains literal' => ['&TildeFullEqual', '&amp;TildeFullEqual'],
            'TildeFullEqual with semicolon' => ['&TildeFullEqual;', '≅'],
            'TildeTilde without semicolon remains literal' => ['&TildeTilde', '&amp;TildeTilde'],
            'TildeTilde with semicolon' => ['&TildeTilde;', '≈'],
            'Topf without semicolon remains literal' => ['&Topf', '&amp;Topf'],
            'Topf with semicolon' => ['&Topf;', '𝕋'],
            'TripleDot without semicolon remains literal' => ['&TripleDot', '&amp;TripleDot'],
            'TripleDot with semicolon' => ['&TripleDot;', '⃛'],
            'Tscr without semicolon remains literal' => ['&Tscr', '&amp;Tscr'],
            'Tscr with semicolon' => ['&Tscr;', '𝒯'],
            'Tstrok without semicolon remains literal' => ['&Tstrok', '&amp;Tstrok'],
            'Tstrok with semicolon' => ['&Tstrok;', 'Ŧ'],
            'Uacute without semicolon' => ['&Uacute', 'Ú'],
            'Uacute with semicolon' => ['&Uacute;', 'Ú'],
            'Uarr without semicolon remains literal' => ['&Uarr', '&amp;Uarr'],
            'Uarr with semicolon' => ['&Uarr;', '↟'],
            'Uarrocir without semicolon remains literal' => ['&Uarrocir', '&amp;Uarrocir'],
            'Uarrocir with semicolon' => ['&Uarrocir;', '⥉'],
            'Ubrcy without semicolon remains literal' => ['&Ubrcy', '&amp;Ubrcy'],
            'Ubrcy with semicolon' => ['&Ubrcy;', 'Ў'],
            'Ubreve without semicolon remains literal' => ['&Ubreve', '&amp;Ubreve'],
            'Ubreve with semicolon' => ['&Ubreve;', 'Ŭ'],
            'Ucirc without semicolon' => ['&Ucirc', 'Û'],
            'Ucirc with semicolon' => ['&Ucirc;', 'Û'],
            'Ucy without semicolon remains literal' => ['&Ucy', '&amp;Ucy'],
            'Ucy with semicolon' => ['&Ucy;', 'У'],
            'Udblac without semicolon remains literal' => ['&Udblac', '&amp;Udblac'],
            'Udblac with semicolon' => ['&Udblac;', 'Ű'],
            'Ufr without semicolon remains literal' => ['&Ufr', '&amp;Ufr'],
            'Ufr with semicolon' => ['&Ufr;', '𝔘'],
            'Ugrave without semicolon' => ['&Ugrave', 'Ù'],
            'Ugrave with semicolon' => ['&Ugrave;', 'Ù'],
            'Umacr without semicolon remains literal' => ['&Umacr', '&amp;Umacr'],
            'Umacr with semicolon' => ['&Umacr;', 'Ū'],
            'UnderBar without semicolon remains literal' => ['&UnderBar', '&amp;UnderBar'],
            'UnderBar with semicolon' => ['&UnderBar;', '_'],
            'UnderBrace without semicolon remains literal' => ['&UnderBrace', '&amp;UnderBrace'],
            'UnderBrace with semicolon' => ['&UnderBrace;', '⏟'],
            'UnderBracket without semicolon remains literal' => ['&UnderBracket', '&amp;UnderBracket'],
            'UnderBracket with semicolon' => ['&UnderBracket;', '⎵'],
            'UnderParenthesis without semicolon remains literal' => ['&UnderParenthesis', '&amp;UnderParenthesis'],
            'UnderParenthesis with semicolon' => ['&UnderParenthesis;', '⏝'],
            'Union without semicolon remains literal' => ['&Union', '&amp;Union'],
            'Union with semicolon' => ['&Union;', '⋃'],
            'UnionPlus without semicolon remains literal' => ['&UnionPlus', '&amp;UnionPlus'],
            'UnionPlus with semicolon' => ['&UnionPlus;', '⊎'],
            'Uogon without semicolon remains literal' => ['&Uogon', '&amp;Uogon'],
            'Uogon with semicolon' => ['&Uogon;', 'Ų'],
            'Uopf without semicolon remains literal' => ['&Uopf', '&amp;Uopf'],
            'Uopf with semicolon' => ['&Uopf;', '𝕌'],
            'UpArrow without semicolon remains literal' => ['&UpArrow', '&amp;UpArrow'],
            'UpArrow with semicolon' => ['&UpArrow;', '↑'],
            'UpArrowBar without semicolon remains literal' => ['&UpArrowBar', '&amp;UpArrowBar'],
            'UpArrowBar with semicolon' => ['&UpArrowBar;', '⤒'],
            'UpArrowDownArrow without semicolon remains literal' => ['&UpArrowDownArrow', '&amp;UpArrowDownArrow'],
            'UpArrowDownArrow with semicolon' => ['&UpArrowDownArrow;', '⇅'],
            'UpDownArrow without semicolon remains literal' => ['&UpDownArrow', '&amp;UpDownArrow'],
            'UpDownArrow with semicolon' => ['&UpDownArrow;', '↕'],
            'UpEquilibrium without semicolon remains literal' => ['&UpEquilibrium', '&amp;UpEquilibrium'],
            'UpEquilibrium with semicolon' => ['&UpEquilibrium;', '⥮'],
            'UpTee without semicolon remains literal' => ['&UpTee', '&amp;UpTee'],
            'UpTee with semicolon' => ['&UpTee;', '⊥'],
            'UpTeeArrow without semicolon remains literal' => ['&UpTeeArrow', '&amp;UpTeeArrow'],
            'UpTeeArrow with semicolon' => ['&UpTeeArrow;', '↥'],
            'Uparrow without semicolon remains literal' => ['&Uparrow', '&amp;Uparrow'],
            'Uparrow with semicolon' => ['&Uparrow;', '⇑'],
            'Updownarrow without semicolon remains literal' => ['&Updownarrow', '&amp;Updownarrow'],
            'Updownarrow with semicolon' => ['&Updownarrow;', '⇕'],
            'UpperLeftArrow without semicolon remains literal' => ['&UpperLeftArrow', '&amp;UpperLeftArrow'],
            'UpperLeftArrow with semicolon' => ['&UpperLeftArrow;', '↖'],
            'UpperRightArrow without semicolon remains literal' => ['&UpperRightArrow', '&amp;UpperRightArrow'],
            'UpperRightArrow with semicolon' => ['&UpperRightArrow;', '↗'],
            'Upsi without semicolon remains literal' => ['&Upsi', '&amp;Upsi'],
            'Upsi with semicolon' => ['&Upsi;', 'ϒ'],
            'Upsilon without semicolon remains literal' => ['&Upsilon', '&amp;Upsilon'],
            'Upsilon with semicolon' => ['&Upsilon;', 'Υ'],
            'Uring without semicolon remains literal' => ['&Uring', '&amp;Uring'],
            'Uring with semicolon' => ['&Uring;', 'Ů'],
            'Uscr without semicolon remains literal' => ['&Uscr', '&amp;Uscr'],
            'Uscr with semicolon' => ['&Uscr;', '𝒰'],
            'Utilde without semicolon remains literal' => ['&Utilde', '&amp;Utilde'],
            'Utilde with semicolon' => ['&Utilde;', 'Ũ'],
            'Uuml without semicolon' => ['&Uuml', 'Ü'],
            'Uuml with semicolon' => ['&Uuml;', 'Ü'],
            'VDash without semicolon remains literal' => ['&VDash', '&amp;VDash'],
            'VDash with semicolon' => ['&VDash;', '⊫'],
            'Vbar without semicolon remains literal' => ['&Vbar', '&amp;Vbar'],
            'Vbar with semicolon' => ['&Vbar;', '⫫'],
            'Vcy without semicolon remains literal' => ['&Vcy', '&amp;Vcy'],
            'Vcy with semicolon' => ['&Vcy;', 'В'],
            'Vdash without semicolon remains literal' => ['&Vdash', '&amp;Vdash'],
            'Vdash with semicolon' => ['&Vdash;', '⊩'],
            'Vdashl without semicolon remains literal' => ['&Vdashl', '&amp;Vdashl'],
            'Vdashl with semicolon' => ['&Vdashl;', '⫦'],
            'Vee without semicolon remains literal' => ['&Vee', '&amp;Vee'],
            'Vee with semicolon' => ['&Vee;', '⋁'],
            'Verbar without semicolon remains literal' => ['&Verbar', '&amp;Verbar'],
            'Verbar with semicolon' => ['&Verbar;', '‖'],
            'Vert without semicolon remains literal' => ['&Vert', '&amp;Vert'],
            'Vert with semicolon' => ['&Vert;', '‖'],
            'VerticalBar without semicolon remains literal' => ['&VerticalBar', '&amp;VerticalBar'],
            'VerticalBar with semicolon' => ['&VerticalBar;', '∣'],
            'VerticalLine without semicolon remains literal' => ['&VerticalLine', '&amp;VerticalLine'],
            'VerticalLine with semicolon' => ['&VerticalLine;', '|'],
            'VerticalSeparator without semicolon remains literal' => ['&VerticalSeparator', '&amp;VerticalSeparator'],
            'VerticalSeparator with semicolon' => ['&VerticalSeparator;', '❘'],
            'VerticalTilde without semicolon remains literal' => ['&VerticalTilde', '&amp;VerticalTilde'],
            'VerticalTilde with semicolon' => ['&VerticalTilde;', '≀'],
            'VeryThinSpace without semicolon remains literal' => ['&VeryThinSpace', '&amp;VeryThinSpace'],
            'VeryThinSpace with semicolon' => ['&VeryThinSpace;', ' '],
            'Vfr without semicolon remains literal' => ['&Vfr', '&amp;Vfr'],
            'Vfr with semicolon' => ['&Vfr;', '𝔙'],
            'Vopf without semicolon remains literal' => ['&Vopf', '&amp;Vopf'],
            'Vopf with semicolon' => ['&Vopf;', '𝕍'],
            'Vscr without semicolon remains literal' => ['&Vscr', '&amp;Vscr'],
            'Vscr with semicolon' => ['&Vscr;', '𝒱'],
            'Vvdash without semicolon remains literal' => ['&Vvdash', '&amp;Vvdash'],
            'Vvdash with semicolon' => ['&Vvdash;', '⊪'],
            'Wcirc without semicolon remains literal' => ['&Wcirc', '&amp;Wcirc'],
            'Wcirc with semicolon' => ['&Wcirc;', 'Ŵ'],
            'Wedge without semicolon remains literal' => ['&Wedge', '&amp;Wedge'],
            'Wedge with semicolon' => ['&Wedge;', '⋀'],
            'Wfr without semicolon remains literal' => ['&Wfr', '&amp;Wfr'],
            'Wfr with semicolon' => ['&Wfr;', '𝔚'],
            'Wopf without semicolon remains literal' => ['&Wopf', '&amp;Wopf'],
            'Wopf with semicolon' => ['&Wopf;', '𝕎'],
            'Wscr without semicolon remains literal' => ['&Wscr', '&amp;Wscr'],
            'Wscr with semicolon' => ['&Wscr;', '𝒲'],
            'Xfr without semicolon remains literal' => ['&Xfr', '&amp;Xfr'],
            'Xfr with semicolon' => ['&Xfr;', '𝔛'],
            'Xi without semicolon remains literal' => ['&Xi', '&amp;Xi'],
            'Xi with semicolon' => ['&Xi;', 'Ξ'],
            'Xopf without semicolon remains literal' => ['&Xopf', '&amp;Xopf'],
            'Xopf with semicolon' => ['&Xopf;', '𝕏'],
            'Xscr without semicolon remains literal' => ['&Xscr', '&amp;Xscr'],
            'Xscr with semicolon' => ['&Xscr;', '𝒳'],
            'YAcy without semicolon remains literal' => ['&YAcy', '&amp;YAcy'],
            'YAcy with semicolon' => ['&YAcy;', 'Я'],
            'YIcy without semicolon remains literal' => ['&YIcy', '&amp;YIcy'],
            'YIcy with semicolon' => ['&YIcy;', 'Ї'],
            'YUcy without semicolon remains literal' => ['&YUcy', '&amp;YUcy'],
            'YUcy with semicolon' => ['&YUcy;', 'Ю'],
            'Yacute without semicolon' => ['&Yacute', 'Ý'],
            'Yacute with semicolon' => ['&Yacute;', 'Ý'],
            'Ycirc without semicolon remains literal' => ['&Ycirc', '&amp;Ycirc'],
            'Ycirc with semicolon' => ['&Ycirc;', 'Ŷ'],
            'Ycy without semicolon remains literal' => ['&Ycy', '&amp;Ycy'],
            'Ycy with semicolon' => ['&Ycy;', 'Ы'],
            'Yfr without semicolon remains literal' => ['&Yfr', '&amp;Yfr'],
            'Yfr with semicolon' => ['&Yfr;', '𝔜'],
            'Yopf without semicolon remains literal' => ['&Yopf', '&amp;Yopf'],
            'Yopf with semicolon' => ['&Yopf;', '𝕐'],
            'Yscr without semicolon remains literal' => ['&Yscr', '&amp;Yscr'],
            'Yscr with semicolon' => ['&Yscr;', '𝒴'],
            'Yuml without semicolon remains literal' => ['&Yuml', '&amp;Yuml'],
            'Yuml with semicolon' => ['&Yuml;', 'Ÿ'],
            'ZHcy without semicolon remains literal' => ['&ZHcy', '&amp;ZHcy'],
            'ZHcy with semicolon' => ['&ZHcy;', 'Ж'],
            'Zacute without semicolon remains literal' => ['&Zacute', '&amp;Zacute'],
            'Zacute with semicolon' => ['&Zacute;', 'Ź'],
            'Zcaron without semicolon remains literal' => ['&Zcaron', '&amp;Zcaron'],
            'Zcaron with semicolon' => ['&Zcaron;', 'Ž'],
            'Zcy without semicolon remains literal' => ['&Zcy', '&amp;Zcy'],
            'Zcy with semicolon' => ['&Zcy;', 'З'],
            'Zdot without semicolon remains literal' => ['&Zdot', '&amp;Zdot'],
            'Zdot with semicolon' => ['&Zdot;', 'Ż'],
            'ZeroWidthSpace without semicolon remains literal' => ['&ZeroWidthSpace', '&amp;ZeroWidthSpace'],
            'ZeroWidthSpace with semicolon' => ['&ZeroWidthSpace;', "\u{200B}"],
            'Zeta without semicolon remains literal' => ['&Zeta', '&amp;Zeta'],
            'Zeta with semicolon' => ['&Zeta;', 'Ζ'],
            'Zfr without semicolon remains literal' => ['&Zfr', '&amp;Zfr'],
            'Zfr with semicolon' => ['&Zfr;', 'ℨ'],
            'Zopf without semicolon remains literal' => ['&Zopf', '&amp;Zopf'],
            'Zopf with semicolon' => ['&Zopf;', 'ℤ'],
            'Zscr without semicolon remains literal' => ['&Zscr', '&amp;Zscr'],
            'Zscr with semicolon' => ['&Zscr;', '𝒵'],
            'aacute without semicolon' => ['&aacute', 'á'],
            'aacute with semicolon' => ['&aacute;', 'á'],
            'abreve without semicolon remains literal' => ['&abreve', '&amp;abreve'],
            'abreve with semicolon' => ['&abreve;', 'ă'],
            'ac without semicolon remains literal' => ['&ac', '&amp;ac'],
            'ac with semicolon' => ['&ac;', '∾'],
            'acE without semicolon remains literal' => ['&acE', '&amp;acE'],
            'acE with semicolon' => ['&acE;', "\u{223E}\u{0333}"],
            'acd without semicolon remains literal' => ['&acd', '&amp;acd'],
            'acd with semicolon' => ['&acd;', '∿'],
            'acirc without semicolon' => ['&acirc', 'â'],
            'acirc with semicolon' => ['&acirc;', 'â'],
            'acute without semicolon' => ['&acute', '´'],
            'acute with semicolon' => ['&acute;', '´'],
            'acy without semicolon remains literal' => ['&acy', '&amp;acy'],
            'acy with semicolon' => ['&acy;', 'а'],
            'aelig without semicolon' => ['&aelig', 'æ'],
            'aelig with semicolon' => ['&aelig;', 'æ'],
            'af without semicolon remains literal' => ['&af', '&amp;af'],
            'af with semicolon' => ['&af;', "\u{2061}"],
            'afr without semicolon remains literal' => ['&afr', '&amp;afr'],
            'afr with semicolon' => ['&afr;', '𝔞'],
            'agrave without semicolon' => ['&agrave', 'à'],
            'agrave with semicolon' => ['&agrave;', 'à'],
            'alefsym without semicolon remains literal' => ['&alefsym', '&amp;alefsym'],
            'alefsym with semicolon' => ['&alefsym;', 'ℵ'],
            'aleph without semicolon remains literal' => ['&aleph', '&amp;aleph'],
            'aleph with semicolon' => ['&aleph;', 'ℵ'],
            'alpha without semicolon remains literal' => ['&alpha', '&amp;alpha'],
            'alpha with semicolon' => ['&alpha;', 'α'],
            'amacr without semicolon remains literal' => ['&amacr', '&amp;amacr'],
            'amacr with semicolon' => ['&amacr;', 'ā'],
            'amalg without semicolon remains literal' => ['&amalg', '&amp;amalg'],
            'amalg with semicolon' => ['&amalg;', '⨿'],
            'amp without semicolon' => ['&amp', '&amp;'],
            'amp with semicolon' => ['&amp;', '&amp;'],
            'and without semicolon remains literal' => ['&and', '&amp;and'],
            'and with semicolon' => ['&and;', '∧'],
            'andand without semicolon remains literal' => ['&andand', '&amp;andand'],
            'andand with semicolon' => ['&andand;', '⩕'],
            'andd without semicolon remains literal' => ['&andd', '&amp;andd'],
            'andd with semicolon' => ['&andd;', '⩜'],
            'andslope without semicolon remains literal' => ['&andslope', '&amp;andslope'],
            'andslope with semicolon' => ['&andslope;', '⩘'],
            'andv without semicolon remains literal' => ['&andv', '&amp;andv'],
            'andv with semicolon' => ['&andv;', '⩚'],
            'ang without semicolon remains literal' => ['&ang', '&amp;ang'],
            'ang with semicolon' => ['&ang;', '∠'],
            'ange without semicolon remains literal' => ['&ange', '&amp;ange'],
            'ange with semicolon' => ['&ange;', '⦤'],
            'angle without semicolon remains literal' => ['&angle', '&amp;angle'],
            'angle with semicolon' => ['&angle;', '∠'],
            'angmsd without semicolon remains literal' => ['&angmsd', '&amp;angmsd'],
            'angmsd with semicolon' => ['&angmsd;', '∡'],
            'angmsdaa without semicolon remains literal' => ['&angmsdaa', '&amp;angmsdaa'],
            'angmsdaa with semicolon' => ['&angmsdaa;', '⦨'],
            'angmsdab without semicolon remains literal' => ['&angmsdab', '&amp;angmsdab'],
            'angmsdab with semicolon' => ['&angmsdab;', '⦩'],
            'angmsdac without semicolon remains literal' => ['&angmsdac', '&amp;angmsdac'],
            'angmsdac with semicolon' => ['&angmsdac;', '⦪'],
            'angmsdad without semicolon remains literal' => ['&angmsdad', '&amp;angmsdad'],
            'angmsdad with semicolon' => ['&angmsdad;', '⦫'],
            'angmsdae without semicolon remains literal' => ['&angmsdae', '&amp;angmsdae'],
            'angmsdae with semicolon' => ['&angmsdae;', '⦬'],
            'angmsdaf without semicolon remains literal' => ['&angmsdaf', '&amp;angmsdaf'],
            'angmsdaf with semicolon' => ['&angmsdaf;', '⦭'],
            'angmsdag without semicolon remains literal' => ['&angmsdag', '&amp;angmsdag'],
            'angmsdag with semicolon' => ['&angmsdag;', '⦮'],
            'angmsdah without semicolon remains literal' => ['&angmsdah', '&amp;angmsdah'],
            'angmsdah with semicolon' => ['&angmsdah;', '⦯'],
            'angrt without semicolon remains literal' => ['&angrt', '&amp;angrt'],
            'angrt with semicolon' => ['&angrt;', '∟'],
            'angrtvb without semicolon remains literal' => ['&angrtvb', '&amp;angrtvb'],
            'angrtvb with semicolon' => ['&angrtvb;', '⊾'],
            'angrtvbd without semicolon remains literal' => ['&angrtvbd', '&amp;angrtvbd'],
            'angrtvbd with semicolon' => ['&angrtvbd;', '⦝'],
            'angsph without semicolon remains literal' => ['&angsph', '&amp;angsph'],
            'angsph with semicolon' => ['&angsph;', '∢'],
            'angst without semicolon remains literal' => ['&angst', '&amp;angst'],
            'angst with semicolon' => ['&angst;', 'Å'],
            'angzarr without semicolon remains literal' => ['&angzarr', '&amp;angzarr'],
            'angzarr with semicolon' => ['&angzarr;', '⍼'],
            'aogon without semicolon remains literal' => ['&aogon', '&amp;aogon'],
            'aogon with semicolon' => ['&aogon;', 'ą'],
            'aopf without semicolon remains literal' => ['&aopf', '&amp;aopf'],
            'aopf with semicolon' => ['&aopf;', '𝕒'],
            'ap without semicolon remains literal' => ['&ap', '&amp;ap'],
            'ap with semicolon' => ['&ap;', '≈'],
            'apE without semicolon remains literal' => ['&apE', '&amp;apE'],
            'apE with semicolon' => ['&apE;', '⩰'],
            'apacir without semicolon remains literal' => ['&apacir', '&amp;apacir'],
            'apacir with semicolon' => ['&apacir;', '⩯'],
            'ape without semicolon remains literal' => ['&ape', '&amp;ape'],
            'ape with semicolon' => ['&ape;', '≊'],
            'apid without semicolon remains literal' => ['&apid', '&amp;apid'],
            'apid with semicolon' => ['&apid;', '≋'],
            'apos without semicolon remains literal' => ['&apos', '&amp;apos'],
            'apos with semicolon' => ['&apos;', "'"],
            'approx without semicolon remains literal' => ['&approx', '&amp;approx'],
            'approx with semicolon' => ['&approx;', '≈'],
            'approxeq without semicolon remains literal' => ['&approxeq', '&amp;approxeq'],
            'approxeq with semicolon' => ['&approxeq;', '≊'],
            'aring without semicolon' => ['&aring', 'å'],
            'aring with semicolon' => ['&aring;', 'å'],
            'ascr without semicolon remains literal' => ['&ascr', '&amp;ascr'],
            'ascr with semicolon' => ['&ascr;', '𝒶'],
            'ast without semicolon remains literal' => ['&ast', '&amp;ast'],
            'ast with semicolon' => ['&ast;', '*'],
            'asymp without semicolon remains literal' => ['&asymp', '&amp;asymp'],
            'asymp with semicolon' => ['&asymp;', '≈'],
            'asympeq without semicolon remains literal' => ['&asympeq', '&amp;asympeq'],
            'asympeq with semicolon' => ['&asympeq;', '≍'],
            'atilde without semicolon' => ['&atilde', 'ã'],
            'atilde with semicolon' => ['&atilde;', 'ã'],
            'auml without semicolon' => ['&auml', 'ä'],
            'auml with semicolon' => ['&auml;', 'ä'],
            'awconint without semicolon remains literal' => ['&awconint', '&amp;awconint'],
            'awconint with semicolon' => ['&awconint;', '∳'],
            'awint without semicolon remains literal' => ['&awint', '&amp;awint'],
            'awint with semicolon' => ['&awint;', '⨑'],
            'bNot without semicolon remains literal' => ['&bNot', '&amp;bNot'],
            'bNot with semicolon' => ['&bNot;', '⫭'],
            'backcong without semicolon remains literal' => ['&backcong', '&amp;backcong'],
            'backcong with semicolon' => ['&backcong;', '≌'],
            'backepsilon without semicolon remains literal' => ['&backepsilon', '&amp;backepsilon'],
            'backepsilon with semicolon' => ['&backepsilon;', '϶'],
            'backprime without semicolon remains literal' => ['&backprime', '&amp;backprime'],
            'backprime with semicolon' => ['&backprime;', '‵'],
            'backsim without semicolon remains literal' => ['&backsim', '&amp;backsim'],
            'backsim with semicolon' => ['&backsim;', '∽'],
            'backsimeq without semicolon remains literal' => ['&backsimeq', '&amp;backsimeq'],
            'backsimeq with semicolon' => ['&backsimeq;', '⋍'],
            'barvee without semicolon remains literal' => ['&barvee', '&amp;barvee'],
            'barvee with semicolon' => ['&barvee;', '⊽'],
            'barwed without semicolon remains literal' => ['&barwed', '&amp;barwed'],
            'barwed with semicolon' => ['&barwed;', '⌅'],
            'barwedge without semicolon remains literal' => ['&barwedge', '&amp;barwedge'],
            'barwedge with semicolon' => ['&barwedge;', '⌅'],
            'bbrk without semicolon remains literal' => ['&bbrk', '&amp;bbrk'],
            'bbrk with semicolon' => ['&bbrk;', '⎵'],
            'bbrktbrk without semicolon remains literal' => ['&bbrktbrk', '&amp;bbrktbrk'],
            'bbrktbrk with semicolon' => ['&bbrktbrk;', '⎶'],
            'bcong without semicolon remains literal' => ['&bcong', '&amp;bcong'],
            'bcong with semicolon' => ['&bcong;', '≌'],
            'bcy without semicolon remains literal' => ['&bcy', '&amp;bcy'],
            'bcy with semicolon' => ['&bcy;', 'б'],
            'bdquo without semicolon remains literal' => ['&bdquo', '&amp;bdquo'],
            'bdquo with semicolon' => ['&bdquo;', '„'],
            'becaus without semicolon remains literal' => ['&becaus', '&amp;becaus'],
            'becaus with semicolon' => ['&becaus;', '∵'],
            'because without semicolon remains literal' => ['&because', '&amp;because'],
            'because with semicolon' => ['&because;', '∵'],
            'bemptyv without semicolon remains literal' => ['&bemptyv', '&amp;bemptyv'],
            'bemptyv with semicolon' => ['&bemptyv;', '⦰'],
            'bepsi without semicolon remains literal' => ['&bepsi', '&amp;bepsi'],
            'bepsi with semicolon' => ['&bepsi;', '϶'],
            'bernou without semicolon remains literal' => ['&bernou', '&amp;bernou'],
            'bernou with semicolon' => ['&bernou;', 'ℬ'],
            'beta without semicolon remains literal' => ['&beta', '&amp;beta'],
            'beta with semicolon' => ['&beta;', 'β'],
            'beth without semicolon remains literal' => ['&beth', '&amp;beth'],
            'beth with semicolon' => ['&beth;', 'ℶ'],
            'between without semicolon remains literal' => ['&between', '&amp;between'],
            'between with semicolon' => ['&between;', '≬'],
            'bfr without semicolon remains literal' => ['&bfr', '&amp;bfr'],
            'bfr with semicolon' => ['&bfr;', '𝔟'],
            'bigcap without semicolon remains literal' => ['&bigcap', '&amp;bigcap'],
            'bigcap with semicolon' => ['&bigcap;', '⋂'],
            'bigcirc without semicolon remains literal' => ['&bigcirc', '&amp;bigcirc'],
            'bigcirc with semicolon' => ['&bigcirc;', '◯'],
            'bigcup without semicolon remains literal' => ['&bigcup', '&amp;bigcup'],
            'bigcup with semicolon' => ['&bigcup;', '⋃'],
            'bigodot without semicolon remains literal' => ['&bigodot', '&amp;bigodot'],
            'bigodot with semicolon' => ['&bigodot;', '⨀'],
            'bigoplus without semicolon remains literal' => ['&bigoplus', '&amp;bigoplus'],
            'bigoplus with semicolon' => ['&bigoplus;', '⨁'],
            'bigotimes without semicolon remains literal' => ['&bigotimes', '&amp;bigotimes'],
            'bigotimes with semicolon' => ['&bigotimes;', '⨂'],
            'bigsqcup without semicolon remains literal' => ['&bigsqcup', '&amp;bigsqcup'],
            'bigsqcup with semicolon' => ['&bigsqcup;', '⨆'],
            'bigstar without semicolon remains literal' => ['&bigstar', '&amp;bigstar'],
            'bigstar with semicolon' => ['&bigstar;', '★'],
            'bigtriangledown without semicolon remains literal' => ['&bigtriangledown', '&amp;bigtriangledown'],
            'bigtriangledown with semicolon' => ['&bigtriangledown;', '▽'],
            'bigtriangleup without semicolon remains literal' => ['&bigtriangleup', '&amp;bigtriangleup'],
            'bigtriangleup with semicolon' => ['&bigtriangleup;', '△'],
            'biguplus without semicolon remains literal' => ['&biguplus', '&amp;biguplus'],
            'biguplus with semicolon' => ['&biguplus;', '⨄'],
            'bigvee without semicolon remains literal' => ['&bigvee', '&amp;bigvee'],
            'bigvee with semicolon' => ['&bigvee;', '⋁'],
            'bigwedge without semicolon remains literal' => ['&bigwedge', '&amp;bigwedge'],
            'bigwedge with semicolon' => ['&bigwedge;', '⋀'],
            'bkarow without semicolon remains literal' => ['&bkarow', '&amp;bkarow'],
            'bkarow with semicolon' => ['&bkarow;', '⤍'],
            'blacklozenge without semicolon remains literal' => ['&blacklozenge', '&amp;blacklozenge'],
            'blacklozenge with semicolon' => ['&blacklozenge;', '⧫'],
            'blacksquare without semicolon remains literal' => ['&blacksquare', '&amp;blacksquare'],
            'blacksquare with semicolon' => ['&blacksquare;', '▪'],
            'blacktriangle without semicolon remains literal' => ['&blacktriangle', '&amp;blacktriangle'],
            'blacktriangle with semicolon' => ['&blacktriangle;', '▴'],
            'blacktriangledown without semicolon remains literal' => ['&blacktriangledown', '&amp;blacktriangledown'],
            'blacktriangledown with semicolon' => ['&blacktriangledown;', '▾'],
            'blacktriangleleft without semicolon remains literal' => ['&blacktriangleleft', '&amp;blacktriangleleft'],
            'blacktriangleleft with semicolon' => ['&blacktriangleleft;', '◂'],
            'blacktriangleright without semicolon remains literal' => ['&blacktriangleright', '&amp;blacktriangleright'],
            'blacktriangleright with semicolon' => ['&blacktriangleright;', '▸'],
            'blank without semicolon remains literal' => ['&blank', '&amp;blank'],
            'blank with semicolon' => ['&blank;', '␣'],
            'blk12 without semicolon remains literal' => ['&blk12', '&amp;blk12'],
            'blk12 with semicolon' => ['&blk12;', '▒'],
            'blk14 without semicolon remains literal' => ['&blk14', '&amp;blk14'],
            'blk14 with semicolon' => ['&blk14;', '░'],
            'blk34 without semicolon remains literal' => ['&blk34', '&amp;blk34'],
            'blk34 with semicolon' => ['&blk34;', '▓'],
            'block without semicolon remains literal' => ['&block', '&amp;block'],
            'block with semicolon' => ['&block;', '█'],
            'bne without semicolon remains literal' => ['&bne', '&amp;bne'],
            'bne with semicolon' => ['&bne;', '=⃥'],
            'bnequiv without semicolon remains literal' => ['&bnequiv', '&amp;bnequiv'],
            'bnequiv with semicolon' => ['&bnequiv;', '≡⃥'],
            'bnot without semicolon remains literal' => ['&bnot', '&amp;bnot'],
            'bnot with semicolon' => ['&bnot;', '⌐'],
            'bopf without semicolon remains literal' => ['&bopf', '&amp;bopf'],
            'bopf with semicolon' => ['&bopf;', '𝕓'],
            'bot without semicolon remains literal' => ['&bot', '&amp;bot'],
            'bot with semicolon' => ['&bot;', '⊥'],
            'bottom without semicolon remains literal' => ['&bottom', '&amp;bottom'],
            'bottom with semicolon' => ['&bottom;', '⊥'],
            'bowtie without semicolon remains literal' => ['&bowtie', '&amp;bowtie'],
            'bowtie with semicolon' => ['&bowtie;', '⋈'],
            'boxDL without semicolon remains literal' => ['&boxDL', '&amp;boxDL'],
            'boxDL with semicolon' => ['&boxDL;', '╗'],
            'boxDR without semicolon remains literal' => ['&boxDR', '&amp;boxDR'],
            'boxDR with semicolon' => ['&boxDR;', '╔'],
            'boxDl without semicolon remains literal' => ['&boxDl', '&amp;boxDl'],
            'boxDl with semicolon' => ['&boxDl;', '╖'],
            'boxDr without semicolon remains literal' => ['&boxDr', '&amp;boxDr'],
            'boxDr with semicolon' => ['&boxDr;', '╓'],
            'boxH without semicolon remains literal' => ['&boxH', '&amp;boxH'],
            'boxH with semicolon' => ['&boxH;', '═'],
            'boxHD without semicolon remains literal' => ['&boxHD', '&amp;boxHD'],
            'boxHD with semicolon' => ['&boxHD;', '╦'],
            'boxHU without semicolon remains literal' => ['&boxHU', '&amp;boxHU'],
            'boxHU with semicolon' => ['&boxHU;', '╩'],
            'boxHd without semicolon remains literal' => ['&boxHd', '&amp;boxHd'],
            'boxHd with semicolon' => ['&boxHd;', '╤'],
            'boxHu without semicolon remains literal' => ['&boxHu', '&amp;boxHu'],
            'boxHu with semicolon' => ['&boxHu;', '╧'],
            'boxUL without semicolon remains literal' => ['&boxUL', '&amp;boxUL'],
            'boxUL with semicolon' => ['&boxUL;', '╝'],
            'boxUR without semicolon remains literal' => ['&boxUR', '&amp;boxUR'],
            'boxUR with semicolon' => ['&boxUR;', '╚'],
            'boxUl without semicolon remains literal' => ['&boxUl', '&amp;boxUl'],
            'boxUl with semicolon' => ['&boxUl;', '╜'],
            'boxUr without semicolon remains literal' => ['&boxUr', '&amp;boxUr'],
            'boxUr with semicolon' => ['&boxUr;', '╙'],
            'boxV without semicolon remains literal' => ['&boxV', '&amp;boxV'],
            'boxV with semicolon' => ['&boxV;', '║'],
            'boxVH without semicolon remains literal' => ['&boxVH', '&amp;boxVH'],
            'boxVH with semicolon' => ['&boxVH;', '╬'],
            'boxVL without semicolon remains literal' => ['&boxVL', '&amp;boxVL'],
            'boxVL with semicolon' => ['&boxVL;', '╣'],
            'boxVR without semicolon remains literal' => ['&boxVR', '&amp;boxVR'],
            'boxVR with semicolon' => ['&boxVR;', '╠'],
            'boxVh without semicolon remains literal' => ['&boxVh', '&amp;boxVh'],
            'boxVh with semicolon' => ['&boxVh;', '╫'],
            'boxVl without semicolon remains literal' => ['&boxVl', '&amp;boxVl'],
            'boxVl with semicolon' => ['&boxVl;', '╢'],
            'boxVr without semicolon remains literal' => ['&boxVr', '&amp;boxVr'],
            'boxVr with semicolon' => ['&boxVr;', '╟'],
            'boxbox without semicolon remains literal' => ['&boxbox', '&amp;boxbox'],
            'boxbox with semicolon' => ['&boxbox;', '⧉'],
            'boxdL without semicolon remains literal' => ['&boxdL', '&amp;boxdL'],
            'boxdL with semicolon' => ['&boxdL;', '╕'],
            'boxdR without semicolon remains literal' => ['&boxdR', '&amp;boxdR'],
            'boxdR with semicolon' => ['&boxdR;', '╒'],
            'boxdl without semicolon remains literal' => ['&boxdl', '&amp;boxdl'],
            'boxdl with semicolon' => ['&boxdl;', '┐'],
            'boxdr without semicolon remains literal' => ['&boxdr', '&amp;boxdr'],
            'boxdr with semicolon' => ['&boxdr;', '┌'],
            'boxh without semicolon remains literal' => ['&boxh', '&amp;boxh'],
            'boxh with semicolon' => ['&boxh;', '─'],
            'boxhD without semicolon remains literal' => ['&boxhD', '&amp;boxhD'],
            'boxhD with semicolon' => ['&boxhD;', '╥'],
            'boxhU without semicolon remains literal' => ['&boxhU', '&amp;boxhU'],
            'boxhU with semicolon' => ['&boxhU;', '╨'],
            'boxhd without semicolon remains literal' => ['&boxhd', '&amp;boxhd'],
            'boxhd with semicolon' => ['&boxhd;', '┬'],
            'boxhu without semicolon remains literal' => ['&boxhu', '&amp;boxhu'],
            'boxhu with semicolon' => ['&boxhu;', '┴'],
            'boxminus without semicolon remains literal' => ['&boxminus', '&amp;boxminus'],
            'boxminus with semicolon' => ['&boxminus;', '⊟'],
            'boxplus without semicolon remains literal' => ['&boxplus', '&amp;boxplus'],
            'boxplus with semicolon' => ['&boxplus;', '⊞'],
            'boxtimes without semicolon remains literal' => ['&boxtimes', '&amp;boxtimes'],
            'boxtimes with semicolon' => ['&boxtimes;', '⊠'],
            'boxuL without semicolon remains literal' => ['&boxuL', '&amp;boxuL'],
            'boxuL with semicolon' => ['&boxuL;', '╛'],
            'boxuR without semicolon remains literal' => ['&boxuR', '&amp;boxuR'],
            'boxuR with semicolon' => ['&boxuR;', '╘'],
            'boxul without semicolon remains literal' => ['&boxul', '&amp;boxul'],
            'boxul with semicolon' => ['&boxul;', '┘'],
            'boxur without semicolon remains literal' => ['&boxur', '&amp;boxur'],
            'boxur with semicolon' => ['&boxur;', '└'],
            'boxv without semicolon remains literal' => ['&boxv', '&amp;boxv'],
            'boxv with semicolon' => ['&boxv;', '│'],
            'boxvH without semicolon remains literal' => ['&boxvH', '&amp;boxvH'],
            'boxvH with semicolon' => ['&boxvH;', '╪'],
            'boxvL without semicolon remains literal' => ['&boxvL', '&amp;boxvL'],
            'boxvL with semicolon' => ['&boxvL;', '╡'],
            'boxvR without semicolon remains literal' => ['&boxvR', '&amp;boxvR'],
            'boxvR with semicolon' => ['&boxvR;', '╞'],
            'boxvh without semicolon remains literal' => ['&boxvh', '&amp;boxvh'],
            'boxvh with semicolon' => ['&boxvh;', '┼'],
            'boxvl without semicolon remains literal' => ['&boxvl', '&amp;boxvl'],
            'boxvl with semicolon' => ['&boxvl;', '┤'],
            'boxvr without semicolon remains literal' => ['&boxvr', '&amp;boxvr'],
            'boxvr with semicolon' => ['&boxvr;', '├'],
            'bprime without semicolon remains literal' => ['&bprime', '&amp;bprime'],
            'bprime with semicolon' => ['&bprime;', '‵'],
            'breve without semicolon remains literal' => ['&breve', '&amp;breve'],
            'breve with semicolon' => ['&breve;', '˘'],
            'brvbar without semicolon' => ['&brvbar', '¦'],
            'brvbar with semicolon' => ['&brvbar;', '¦'],
            'bscr without semicolon remains literal' => ['&bscr', '&amp;bscr'],
            'bscr with semicolon' => ['&bscr;', '𝒷'],
            'bsemi without semicolon remains literal' => ['&bsemi', '&amp;bsemi'],
            'bsemi with semicolon' => ['&bsemi;', '⁏'],
            'bsim without semicolon remains literal' => ['&bsim', '&amp;bsim'],
            'bsim with semicolon' => ['&bsim;', '∽'],
            'bsime without semicolon remains literal' => ['&bsime', '&amp;bsime'],
            'bsime with semicolon' => ['&bsime;', '⋍'],
            'bsol without semicolon remains literal' => ['&bsol', '&amp;bsol'],
            'bsol with semicolon' => ['&bsol;', '\\'],
            'bsolb without semicolon remains literal' => ['&bsolb', '&amp;bsolb'],
            'bsolb with semicolon' => ['&bsolb;', '⧅'],
            'bsolhsub without semicolon remains literal' => ['&bsolhsub', '&amp;bsolhsub'],
            'bsolhsub with semicolon' => ['&bsolhsub;', '⟈'],
            'bull without semicolon remains literal' => ['&bull', '&amp;bull'],
            'bull with semicolon' => ['&bull;', '•'],
            'bullet without semicolon remains literal' => ['&bullet', '&amp;bullet'],
            'bullet with semicolon' => ['&bullet;', '•'],
            'bump without semicolon remains literal' => ['&bump', '&amp;bump'],
            'bump with semicolon' => ['&bump;', '≎'],
            'bumpE without semicolon remains literal' => ['&bumpE', '&amp;bumpE'],
            'bumpE with semicolon' => ['&bumpE;', '⪮'],
            'bumpe without semicolon remains literal' => ['&bumpe', '&amp;bumpe'],
            'bumpe with semicolon' => ['&bumpe;', '≏'],
            'bumpeq without semicolon remains literal' => ['&bumpeq', '&amp;bumpeq'],
            'bumpeq with semicolon' => ['&bumpeq;', '≏'],
            'cacute without semicolon remains literal' => ['&cacute', '&amp;cacute'],
            'cacute with semicolon' => ['&cacute;', 'ć'],
            'cap without semicolon remains literal' => ['&cap', '&amp;cap'],
            'cap with semicolon' => ['&cap;', '∩'],
            'capand without semicolon remains literal' => ['&capand', '&amp;capand'],
            'capand with semicolon' => ['&capand;', '⩄'],
            'capbrcup without semicolon remains literal' => ['&capbrcup', '&amp;capbrcup'],
            'capbrcup with semicolon' => ['&capbrcup;', '⩉'],
            'capcap without semicolon remains literal' => ['&capcap', '&amp;capcap'],
            'capcap with semicolon' => ['&capcap;', '⩋'],
            'capcup without semicolon remains literal' => ['&capcup', '&amp;capcup'],
            'capcup with semicolon' => ['&capcup;', '⩇'],
            'capdot without semicolon remains literal' => ['&capdot', '&amp;capdot'],
            'capdot with semicolon' => ['&capdot;', '⩀'],
            'caps without semicolon remains literal' => ['&caps', '&amp;caps'],
            'caps with semicolon' => ['&caps;', '∩︀'],
            'caret without semicolon remains literal' => ['&caret', '&amp;caret'],
            'caret with semicolon' => ['&caret;', '⁁'],
            'caron without semicolon remains literal' => ['&caron', '&amp;caron'],
            'caron with semicolon' => ['&caron;', 'ˇ'],
            'ccaps without semicolon remains literal' => ['&ccaps', '&amp;ccaps'],
            'ccaps with semicolon' => ['&ccaps;', '⩍'],
            'ccaron without semicolon remains literal' => ['&ccaron', '&amp;ccaron'],
            'ccaron with semicolon' => ['&ccaron;', 'č'],
            'ccedil without semicolon' => ['&ccedil', 'ç'],
            'ccedil with semicolon' => ['&ccedil;', 'ç'],
            'ccirc without semicolon remains literal' => ['&ccirc', '&amp;ccirc'],
            'ccirc with semicolon' => ['&ccirc;', 'ĉ'],
            'ccups without semicolon remains literal' => ['&ccups', '&amp;ccups'],
            'ccups with semicolon' => ['&ccups;', '⩌'],
            'ccupssm without semicolon remains literal' => ['&ccupssm', '&amp;ccupssm'],
            'ccupssm with semicolon' => ['&ccupssm;', '⩐'],
            'cdot without semicolon remains literal' => ['&cdot', '&amp;cdot'],
            'cdot with semicolon' => ['&cdot;', 'ċ'],
            'cedil without semicolon' => ['&cedil', '¸'],
            'cedil with semicolon' => ['&cedil;', '¸'],
            'cemptyv without semicolon remains literal' => ['&cemptyv', '&amp;cemptyv'],
            'cemptyv with semicolon' => ['&cemptyv;', '⦲'],
            'cent without semicolon' => ['&cent', '¢'],
            'cent with semicolon' => ['&cent;', '¢'],
            'centerdot with semicolon' => ['&centerdot;', '·'],
            'cfr without semicolon remains literal' => ['&cfr', '&amp;cfr'],
            'cfr with semicolon' => ['&cfr;', '𝔠'],
            'chcy without semicolon remains literal' => ['&chcy', '&amp;chcy'],
            'chcy with semicolon' => ['&chcy;', 'ч'],
            'check without semicolon remains literal' => ['&check', '&amp;check'],
            'check with semicolon' => ['&check;', '✓'],
            'checkmark without semicolon remains literal' => ['&checkmark', '&amp;checkmark'],
            'checkmark with semicolon' => ['&checkmark;', '✓'],
            'chi without semicolon remains literal' => ['&chi', '&amp;chi'],
            'chi with semicolon' => ['&chi;', 'χ'],
            'cir without semicolon remains literal' => ['&cir', '&amp;cir'],
            'cir with semicolon' => ['&cir;', '○'],
            'cirE without semicolon remains literal' => ['&cirE', '&amp;cirE'],
            'cirE with semicolon' => ['&cirE;', '⧃'],
            'circ without semicolon remains literal' => ['&circ', '&amp;circ'],
            'circ with semicolon' => ['&circ;', 'ˆ'],
            'circeq without semicolon remains literal' => ['&circeq', '&amp;circeq'],
            'circeq with semicolon' => ['&circeq;', '≗'],
            'circlearrowleft without semicolon remains literal' => ['&circlearrowleft', '&amp;circlearrowleft'],
            'circlearrowleft with semicolon' => ['&circlearrowleft;', '↺'],
            'circlearrowright without semicolon remains literal' => ['&circlearrowright', '&amp;circlearrowright'],
            'circlearrowright with semicolon' => ['&circlearrowright;', '↻'],
            'circledR without semicolon remains literal' => ['&circledR', '&amp;circledR'],
            'circledR with semicolon' => ['&circledR;', '®'],
            'circledS without semicolon remains literal' => ['&circledS', '&amp;circledS'],
            'circledS with semicolon' => ['&circledS;', 'Ⓢ'],
            'circledast without semicolon remains literal' => ['&circledast', '&amp;circledast'],
            'circledast with semicolon' => ['&circledast;', '⊛'],
            'circledcirc without semicolon remains literal' => ['&circledcirc', '&amp;circledcirc'],
            'circledcirc with semicolon' => ['&circledcirc;', '⊚'],
            'circleddash without semicolon remains literal' => ['&circleddash', '&amp;circleddash'],
            'circleddash with semicolon' => ['&circleddash;', '⊝'],
            'cire without semicolon remains literal' => ['&cire', '&amp;cire'],
            'cire with semicolon' => ['&cire;', '≗'],
            'cirfnint without semicolon remains literal' => ['&cirfnint', '&amp;cirfnint'],
            'cirfnint with semicolon' => ['&cirfnint;', '⨐'],
            'cirmid without semicolon remains literal' => ['&cirmid', '&amp;cirmid'],
            'cirmid with semicolon' => ['&cirmid;', '⫯'],
            'cirscir without semicolon remains literal' => ['&cirscir', '&amp;cirscir'],
            'cirscir with semicolon' => ['&cirscir;', '⧂'],
            'clubs without semicolon remains literal' => ['&clubs', '&amp;clubs'],
            'clubs with semicolon' => ['&clubs;', '♣'],
            'clubsuit without semicolon remains literal' => ['&clubsuit', '&amp;clubsuit'],
            'clubsuit with semicolon' => ['&clubsuit;', '♣'],
            'colon without semicolon remains literal' => ['&colon', '&amp;colon'],
            'colon with semicolon' => ['&colon;', ':'],
            'colone without semicolon remains literal' => ['&colone', '&amp;colone'],
            'colone with semicolon' => ['&colone;', '≔'],
            'coloneq without semicolon remains literal' => ['&coloneq', '&amp;coloneq'],
            'coloneq with semicolon' => ['&coloneq;', '≔'],
            'comma without semicolon remains literal' => ['&comma', '&amp;comma'],
            'comma with semicolon' => ['&comma;', ','],
            'commat without semicolon remains literal' => ['&commat', '&amp;commat'],
            'commat with semicolon' => ['&commat;', '@'],
            'comp without semicolon remains literal' => ['&comp', '&amp;comp'],
            'comp with semicolon' => ['&comp;', '∁'],
            'compfn without semicolon remains literal' => ['&compfn', '&amp;compfn'],
            'compfn with semicolon' => ['&compfn;', '∘'],
            'complement without semicolon remains literal' => ['&complement', '&amp;complement'],
            'complement with semicolon' => ['&complement;', '∁'],
            'complexes without semicolon remains literal' => ['&complexes', '&amp;complexes'],
            'complexes with semicolon' => ['&complexes;', 'ℂ'],
            'cong without semicolon remains literal' => ['&cong', '&amp;cong'],
            'cong with semicolon' => ['&cong;', '≅'],
            'congdot without semicolon remains literal' => ['&congdot', '&amp;congdot'],
            'congdot with semicolon' => ['&congdot;', '⩭'],
            'conint without semicolon remains literal' => ['&conint', '&amp;conint'],
            'conint with semicolon' => ['&conint;', '∮'],
            'copf without semicolon remains literal' => ['&copf', '&amp;copf'],
            'copf with semicolon' => ['&copf;', '𝕔'],
            'coprod without semicolon remains literal' => ['&coprod', '&amp;coprod'],
            'coprod with semicolon' => ['&coprod;', '∐'],
            'copy without semicolon' => ['&copy', '©'],
            'copy with semicolon' => ['&copy;', '©'],
            'copysr with semicolon' => ['&copysr;', '℗'],
            'crarr without semicolon remains literal' => ['&crarr', '&amp;crarr'],
            'crarr with semicolon' => ['&crarr;', '↵'],
            'cross without semicolon remains literal' => ['&cross', '&amp;cross'],
            'cross with semicolon' => ['&cross;', '✗'],
            'cscr without semicolon remains literal' => ['&cscr', '&amp;cscr'],
            'cscr with semicolon' => ['&cscr;', '𝒸'],
            'csub without semicolon remains literal' => ['&csub', '&amp;csub'],
            'csub with semicolon' => ['&csub;', '⫏'],
            'csube without semicolon remains literal' => ['&csube', '&amp;csube'],
            'csube with semicolon' => ['&csube;', '⫑'],
            'csup without semicolon remains literal' => ['&csup', '&amp;csup'],
            'csup with semicolon' => ['&csup;', '⫐'],
            'csupe without semicolon remains literal' => ['&csupe', '&amp;csupe'],
            'csupe with semicolon' => ['&csupe;', '⫒'],
            'ctdot without semicolon remains literal' => ['&ctdot', '&amp;ctdot'],
            'ctdot with semicolon' => ['&ctdot;', '⋯'],
            'cudarrl without semicolon remains literal' => ['&cudarrl', '&amp;cudarrl'],
            'cudarrl with semicolon' => ['&cudarrl;', '⤸'],
            'cudarrr without semicolon remains literal' => ['&cudarrr', '&amp;cudarrr'],
            'cudarrr with semicolon' => ['&cudarrr;', '⤵'],
            'cuepr without semicolon remains literal' => ['&cuepr', '&amp;cuepr'],
            'cuepr with semicolon' => ['&cuepr;', '⋞'],
            'cuesc without semicolon remains literal' => ['&cuesc', '&amp;cuesc'],
            'cuesc with semicolon' => ['&cuesc;', '⋟'],
            'cularr without semicolon remains literal' => ['&cularr', '&amp;cularr'],
            'cularr with semicolon' => ['&cularr;', '↶'],
            'cularrp without semicolon remains literal' => ['&cularrp', '&amp;cularrp'],
            'cularrp with semicolon' => ['&cularrp;', '⤽'],
            'cup without semicolon remains literal' => ['&cup', '&amp;cup'],
            'cup with semicolon' => ['&cup;', '∪'],
            'cupbrcap without semicolon remains literal' => ['&cupbrcap', '&amp;cupbrcap'],
            'cupbrcap with semicolon' => ['&cupbrcap;', '⩈'],
            'cupcap without semicolon remains literal' => ['&cupcap', '&amp;cupcap'],
            'cupcap with semicolon' => ['&cupcap;', '⩆'],
            'cupcup without semicolon remains literal' => ['&cupcup', '&amp;cupcup'],
            'cupcup with semicolon' => ['&cupcup;', '⩊'],
            'cupdot without semicolon remains literal' => ['&cupdot', '&amp;cupdot'],
            'cupdot with semicolon' => ['&cupdot;', '⊍'],
            'cupor without semicolon remains literal' => ['&cupor', '&amp;cupor'],
            'cupor with semicolon' => ['&cupor;', '⩅'],
            'cups without semicolon remains literal' => ['&cups', '&amp;cups'],
            'cups with semicolon' => ['&cups;', '∪︀'],
            'curarr without semicolon remains literal' => ['&curarr', '&amp;curarr'],
            'curarr with semicolon' => ['&curarr;', '↷'],
            'curarrm without semicolon remains literal' => ['&curarrm', '&amp;curarrm'],
            'curarrm with semicolon' => ['&curarrm;', '⤼'],
            'curlyeqprec without semicolon remains literal' => ['&curlyeqprec', '&amp;curlyeqprec'],
            'curlyeqprec with semicolon' => ['&curlyeqprec;', '⋞'],
            'curlyeqsucc without semicolon remains literal' => ['&curlyeqsucc', '&amp;curlyeqsucc'],
            'curlyeqsucc with semicolon' => ['&curlyeqsucc;', '⋟'],
            'curlyvee without semicolon remains literal' => ['&curlyvee', '&amp;curlyvee'],
            'curlyvee with semicolon' => ['&curlyvee;', '⋎'],
            'curlywedge without semicolon remains literal' => ['&curlywedge', '&amp;curlywedge'],
            'curlywedge with semicolon' => ['&curlywedge;', '⋏'],
            'curren without semicolon' => ['&curren', '¤'],
            'curren with semicolon' => ['&curren;', '¤'],
            'curvearrowleft without semicolon remains literal' => ['&curvearrowleft', '&amp;curvearrowleft'],
            'curvearrowleft with semicolon' => ['&curvearrowleft;', '↶'],
            'curvearrowright without semicolon remains literal' => ['&curvearrowright', '&amp;curvearrowright'],
            'curvearrowright with semicolon' => ['&curvearrowright;', '↷'],
            'cuvee without semicolon remains literal' => ['&cuvee', '&amp;cuvee'],
            'cuvee with semicolon' => ['&cuvee;', '⋎'],
            'cuwed without semicolon remains literal' => ['&cuwed', '&amp;cuwed'],
            'cuwed with semicolon' => ['&cuwed;', '⋏'],
            'cwconint without semicolon remains literal' => ['&cwconint', '&amp;cwconint'],
            'cwconint with semicolon' => ['&cwconint;', '∲'],
            'cwint without semicolon remains literal' => ['&cwint', '&amp;cwint'],
            'cwint with semicolon' => ['&cwint;', '∱'],
            'cylcty without semicolon remains literal' => ['&cylcty', '&amp;cylcty'],
            'cylcty with semicolon' => ['&cylcty;', '⌭'],
            'dArr without semicolon remains literal' => ['&dArr', '&amp;dArr'],
            'dArr with semicolon' => ['&dArr;', '⇓'],
            'dHar without semicolon remains literal' => ['&dHar', '&amp;dHar'],
            'dHar with semicolon' => ['&dHar;', '⥥'],
            'dagger without semicolon remains literal' => ['&dagger', '&amp;dagger'],
            'dagger with semicolon' => ['&dagger;', '†'],
            'daleth without semicolon remains literal' => ['&daleth', '&amp;daleth'],
            'daleth with semicolon' => ['&daleth;', 'ℸ'],
            'darr without semicolon remains literal' => ['&darr', '&amp;darr'],
            'darr with semicolon' => ['&darr;', '↓'],
            'dash without semicolon remains literal' => ['&dash', '&amp;dash'],
            'dash with semicolon' => ['&dash;', '‐'],
            'dashv without semicolon remains literal' => ['&dashv', '&amp;dashv'],
            'dashv with semicolon' => ['&dashv;', '⊣'],
            'dbkarow without semicolon remains literal' => ['&dbkarow', '&amp;dbkarow'],
            'dbkarow with semicolon' => ['&dbkarow;', '⤏'],
            'dblac without semicolon remains literal' => ['&dblac', '&amp;dblac'],
            'dblac with semicolon' => ['&dblac;', '˝'],
            'dcaron without semicolon remains literal' => ['&dcaron', '&amp;dcaron'],
            'dcaron with semicolon' => ['&dcaron;', 'ď'],
            'dcy without semicolon remains literal' => ['&dcy', '&amp;dcy'],
            'dcy with semicolon' => ['&dcy;', 'д'],
            'dd without semicolon remains literal' => ['&dd', '&amp;dd'],
            'dd with semicolon' => ['&dd;', 'ⅆ'],
            'ddagger without semicolon remains literal' => ['&ddagger', '&amp;ddagger'],
            'ddagger with semicolon' => ['&ddagger;', '‡'],
            'ddarr without semicolon remains literal' => ['&ddarr', '&amp;ddarr'],
            'ddarr with semicolon' => ['&ddarr;', '⇊'],
            'ddotseq without semicolon remains literal' => ['&ddotseq', '&amp;ddotseq'],
            'ddotseq with semicolon' => ['&ddotseq;', '⩷'],
            'deg without semicolon' => ['&deg', '°'],
            'deg with semicolon' => ['&deg;', '°'],
            'delta without semicolon remains literal' => ['&delta', '&amp;delta'],
            'delta with semicolon' => ['&delta;', 'δ'],
            'demptyv without semicolon remains literal' => ['&demptyv', '&amp;demptyv'],
            'demptyv with semicolon' => ['&demptyv;', '⦱'],
            'dfisht without semicolon remains literal' => ['&dfisht', '&amp;dfisht'],
            'dfisht with semicolon' => ['&dfisht;', '⥿'],
            'dfr without semicolon remains literal' => ['&dfr', '&amp;dfr'],
            'dfr with semicolon' => ['&dfr;', '𝔡'],
            'dharl without semicolon remains literal' => ['&dharl', '&amp;dharl'],
            'dharl with semicolon' => ['&dharl;', '⇃'],
            'dharr without semicolon remains literal' => ['&dharr', '&amp;dharr'],
            'dharr with semicolon' => ['&dharr;', '⇂'],
            'diam without semicolon remains literal' => ['&diam', '&amp;diam'],
            'diam with semicolon' => ['&diam;', '⋄'],
            'diamond without semicolon remains literal' => ['&diamond', '&amp;diamond'],
            'diamond with semicolon' => ['&diamond;', '⋄'],
            'diamondsuit without semicolon remains literal' => ['&diamondsuit', '&amp;diamondsuit'],
            'diamondsuit with semicolon' => ['&diamondsuit;', '♦'],
            'diams without semicolon remains literal' => ['&diams', '&amp;diams'],
            'diams with semicolon' => ['&diams;', '♦'],
            'die without semicolon remains literal' => ['&die', '&amp;die'],
            'die with semicolon' => ['&die;', '¨'],
            'digamma without semicolon remains literal' => ['&digamma', '&amp;digamma'],
            'digamma with semicolon' => ['&digamma;', 'ϝ'],
            'disin without semicolon remains literal' => ['&disin', '&amp;disin'],
            'disin with semicolon' => ['&disin;', '⋲'],
            'div without semicolon remains literal' => ['&div', '&amp;div'],
            'div with semicolon' => ['&div;', '÷'],
            'divide without semicolon' => ['&divide', '÷'],
            'divide with semicolon' => ['&divide;', '÷'],
            'divideontimes with semicolon' => ['&divideontimes;', '⋇'],
            'divonx without semicolon remains literal' => ['&divonx', '&amp;divonx'],
            'divonx with semicolon' => ['&divonx;', '⋇'],
            'djcy without semicolon remains literal' => ['&djcy', '&amp;djcy'],
            'djcy with semicolon' => ['&djcy;', 'ђ'],
            'dlcorn without semicolon remains literal' => ['&dlcorn', '&amp;dlcorn'],
            'dlcorn with semicolon' => ['&dlcorn;', '⌞'],
            'dlcrop without semicolon remains literal' => ['&dlcrop', '&amp;dlcrop'],
            'dlcrop with semicolon' => ['&dlcrop;', '⌍'],
            'dollar without semicolon remains literal' => ['&dollar', '&amp;dollar'],
            'dollar with semicolon' => ['&dollar;', '$'],
            'dopf without semicolon remains literal' => ['&dopf', '&amp;dopf'],
            'dopf with semicolon' => ['&dopf;', '𝕕'],
            'dot without semicolon remains literal' => ['&dot', '&amp;dot'],
            'dot with semicolon' => ['&dot;', '˙'],
            'doteq without semicolon remains literal' => ['&doteq', '&amp;doteq'],
            'doteq with semicolon' => ['&doteq;', '≐'],
            'doteqdot without semicolon remains literal' => ['&doteqdot', '&amp;doteqdot'],
            'doteqdot with semicolon' => ['&doteqdot;', '≑'],
            'dotminus without semicolon remains literal' => ['&dotminus', '&amp;dotminus'],
            'dotminus with semicolon' => ['&dotminus;', '∸'],
            'dotplus without semicolon remains literal' => ['&dotplus', '&amp;dotplus'],
            'dotplus with semicolon' => ['&dotplus;', '∔'],
            'dotsquare without semicolon remains literal' => ['&dotsquare', '&amp;dotsquare'],
            'dotsquare with semicolon' => ['&dotsquare;', '⊡'],
            'doublebarwedge without semicolon remains literal' => ['&doublebarwedge', '&amp;doublebarwedge'],
            'doublebarwedge with semicolon' => ['&doublebarwedge;', '⌆'],
            'downarrow without semicolon remains literal' => ['&downarrow', '&amp;downarrow'],
            'downarrow with semicolon' => ['&downarrow;', '↓'],
            'downdownarrows without semicolon remains literal' => ['&downdownarrows', '&amp;downdownarrows'],
            'downdownarrows with semicolon' => ['&downdownarrows;', '⇊'],
            'downharpoonleft without semicolon remains literal' => ['&downharpoonleft', '&amp;downharpoonleft'],
            'downharpoonleft with semicolon' => ['&downharpoonleft;', '⇃'],
            'downharpoonright without semicolon remains literal' => ['&downharpoonright', '&amp;downharpoonright'],
            'downharpoonright with semicolon' => ['&downharpoonright;', '⇂'],
            'drbkarow without semicolon remains literal' => ['&drbkarow', '&amp;drbkarow'],
            'drbkarow with semicolon' => ['&drbkarow;', '⤐'],
            'drcorn without semicolon remains literal' => ['&drcorn', '&amp;drcorn'],
            'drcorn with semicolon' => ['&drcorn;', '⌟'],
            'drcrop without semicolon remains literal' => ['&drcrop', '&amp;drcrop'],
            'drcrop with semicolon' => ['&drcrop;', '⌌'],
            'dscr without semicolon remains literal' => ['&dscr', '&amp;dscr'],
            'dscr with semicolon' => ['&dscr;', '𝒹'],
            'dscy without semicolon remains literal' => ['&dscy', '&amp;dscy'],
            'dscy with semicolon' => ['&dscy;', 'ѕ'],
            'dsol without semicolon remains literal' => ['&dsol', '&amp;dsol'],
            'dsol with semicolon' => ['&dsol;', '⧶'],
            'dstrok without semicolon remains literal' => ['&dstrok', '&amp;dstrok'],
            'dstrok with semicolon' => ['&dstrok;', 'đ'],
            'dtdot without semicolon remains literal' => ['&dtdot', '&amp;dtdot'],
            'dtdot with semicolon' => ['&dtdot;', '⋱'],
            'dtri without semicolon remains literal' => ['&dtri', '&amp;dtri'],
            'dtri with semicolon' => ['&dtri;', '▿'],
            'dtrif without semicolon remains literal' => ['&dtrif', '&amp;dtrif'],
            'dtrif with semicolon' => ['&dtrif;', '▾'],
            'duarr without semicolon remains literal' => ['&duarr', '&amp;duarr'],
            'duarr with semicolon' => ['&duarr;', '⇵'],
            'duhar without semicolon remains literal' => ['&duhar', '&amp;duhar'],
            'duhar with semicolon' => ['&duhar;', '⥯'],
            'dwangle without semicolon remains literal' => ['&dwangle', '&amp;dwangle'],
            'dwangle with semicolon' => ['&dwangle;', '⦦'],
            'dzcy without semicolon remains literal' => ['&dzcy', '&amp;dzcy'],
            'dzcy with semicolon' => ['&dzcy;', 'џ'],
            'dzigrarr without semicolon remains literal' => ['&dzigrarr', '&amp;dzigrarr'],
            'dzigrarr with semicolon' => ['&dzigrarr;', '⟿'],
            'eDDot without semicolon remains literal' => ['&eDDot', '&amp;eDDot'],
            'eDDot with semicolon' => ['&eDDot;', '⩷'],
            'eDot without semicolon remains literal' => ['&eDot', '&amp;eDot'],
            'eDot with semicolon' => ['&eDot;', '≑'],
            'eacute without semicolon' => ['&eacute', 'é'],
            'eacute with semicolon' => ['&eacute;', 'é'],
            'easter without semicolon remains literal' => ['&easter', '&amp;easter'],
            'easter with semicolon' => ['&easter;', '⩮'],
            'ecaron without semicolon remains literal' => ['&ecaron', '&amp;ecaron'],
            'ecaron with semicolon' => ['&ecaron;', 'ě'],
            'ecir without semicolon remains literal' => ['&ecir', '&amp;ecir'],
            'ecir with semicolon' => ['&ecir;', '≖'],
            'ecirc without semicolon' => ['&ecirc', 'ê'],
            'ecirc with semicolon' => ['&ecirc;', 'ê'],
            'ecolon without semicolon remains literal' => ['&ecolon', '&amp;ecolon'],
            'ecolon with semicolon' => ['&ecolon;', '≕'],
            'ecy without semicolon remains literal' => ['&ecy', '&amp;ecy'],
            'ecy with semicolon' => ['&ecy;', 'э'],
            'edot without semicolon remains literal' => ['&edot', '&amp;edot'],
            'edot with semicolon' => ['&edot;', 'ė'],
            'ee without semicolon remains literal' => ['&ee', '&amp;ee'],
            'ee with semicolon' => ['&ee;', 'ⅇ'],
            'efDot without semicolon remains literal' => ['&efDot', '&amp;efDot'],
            'efDot with semicolon' => ['&efDot;', '≒'],
            'efr without semicolon remains literal' => ['&efr', '&amp;efr'],
            'efr with semicolon' => ['&efr;', '𝔢'],
            'eg without semicolon remains literal' => ['&eg', '&amp;eg'],
            'eg with semicolon' => ['&eg;', '⪚'],
            'egrave without semicolon' => ['&egrave', 'è'],
            'egrave with semicolon' => ['&egrave;', 'è'],
            'egs without semicolon remains literal' => ['&egs', '&amp;egs'],
            'egs with semicolon' => ['&egs;', '⪖'],
            'egsdot without semicolon remains literal' => ['&egsdot', '&amp;egsdot'],
            'egsdot with semicolon' => ['&egsdot;', '⪘'],
            'el without semicolon remains literal' => ['&el', '&amp;el'],
            'el with semicolon' => ['&el;', '⪙'],
            'elinters without semicolon remains literal' => ['&elinters', '&amp;elinters'],
            'elinters with semicolon' => ['&elinters;', '⏧'],
            'ell without semicolon remains literal' => ['&ell', '&amp;ell'],
            'ell with semicolon' => ['&ell;', 'ℓ'],
            'els without semicolon remains literal' => ['&els', '&amp;els'],
            'els with semicolon' => ['&els;', '⪕'],
            'elsdot without semicolon remains literal' => ['&elsdot', '&amp;elsdot'],
            'elsdot with semicolon' => ['&elsdot;', '⪗'],
            'emacr without semicolon remains literal' => ['&emacr', '&amp;emacr'],
            'emacr with semicolon' => ['&emacr;', 'ē'],
            'empty without semicolon remains literal' => ['&empty', '&amp;empty'],
            'empty with semicolon' => ['&empty;', '∅'],
            'emptyset without semicolon remains literal' => ['&emptyset', '&amp;emptyset'],
            'emptyset with semicolon' => ['&emptyset;', '∅'],
            'emptyv without semicolon remains literal' => ['&emptyv', '&amp;emptyv'],
            'emptyv with semicolon' => ['&emptyv;', '∅'],
            'emsp without semicolon remains literal' => ['&emsp', '&amp;emsp'],
            'emsp13 without semicolon remains literal' => ['&emsp13', '&amp;emsp13'],
            'emsp13 with semicolon' => ['&emsp13;', ' '],
            'emsp14 without semicolon remains literal' => ['&emsp14', '&amp;emsp14'],
            'emsp14 with semicolon' => ['&emsp14;', ' '],
            'emsp with semicolon' => ['&emsp;', ' '],
            'eng without semicolon remains literal' => ['&eng', '&amp;eng'],
            'eng with semicolon' => ['&eng;', 'ŋ'],
            'ensp without semicolon remains literal' => ['&ensp', '&amp;ensp'],
            'ensp with semicolon' => ['&ensp;', ' '],
            'eogon without semicolon remains literal' => ['&eogon', '&amp;eogon'],
            'eogon with semicolon' => ['&eogon;', 'ę'],
            'eopf without semicolon remains literal' => ['&eopf', '&amp;eopf'],
            'eopf with semicolon' => ['&eopf;', '𝕖'],
            'epar without semicolon remains literal' => ['&epar', '&amp;epar'],
            'epar with semicolon' => ['&epar;', '⋕'],
            'eparsl without semicolon remains literal' => ['&eparsl', '&amp;eparsl'],
            'eparsl with semicolon' => ['&eparsl;', '⧣'],
            'eplus without semicolon remains literal' => ['&eplus', '&amp;eplus'],
            'eplus with semicolon' => ['&eplus;', '⩱'],
            'epsi without semicolon remains literal' => ['&epsi', '&amp;epsi'],
            'epsi with semicolon' => ['&epsi;', 'ε'],
            'epsilon without semicolon remains literal' => ['&epsilon', '&amp;epsilon'],
            'epsilon with semicolon' => ['&epsilon;', 'ε'],
            'epsiv without semicolon remains literal' => ['&epsiv', '&amp;epsiv'],
            'epsiv with semicolon' => ['&epsiv;', 'ϵ'],
            'eqcirc without semicolon remains literal' => ['&eqcirc', '&amp;eqcirc'],
            'eqcirc with semicolon' => ['&eqcirc;', '≖'],
            'eqcolon without semicolon remains literal' => ['&eqcolon', '&amp;eqcolon'],
            'eqcolon with semicolon' => ['&eqcolon;', '≕'],
            'eqsim without semicolon remains literal' => ['&eqsim', '&amp;eqsim'],
            'eqsim with semicolon' => ['&eqsim;', '≂'],
            'eqslantgtr without semicolon remains literal' => ['&eqslantgtr', '&amp;eqslantgtr'],
            'eqslantgtr with semicolon' => ['&eqslantgtr;', '⪖'],
            'eqslantless without semicolon remains literal' => ['&eqslantless', '&amp;eqslantless'],
            'eqslantless with semicolon' => ['&eqslantless;', '⪕'],
            'equals without semicolon remains literal' => ['&equals', '&amp;equals'],
            'equals with semicolon' => ['&equals;', '='],
            'equest without semicolon remains literal' => ['&equest', '&amp;equest'],
            'equest with semicolon' => ['&equest;', '≟'],
            'equiv without semicolon remains literal' => ['&equiv', '&amp;equiv'],
            'equiv with semicolon' => ['&equiv;', '≡'],
            'equivDD without semicolon remains literal' => ['&equivDD', '&amp;equivDD'],
            'equivDD with semicolon' => ['&equivDD;', '⩸'],
            'eqvparsl without semicolon remains literal' => ['&eqvparsl', '&amp;eqvparsl'],
            'eqvparsl with semicolon' => ['&eqvparsl;', '⧥'],
            'erDot without semicolon remains literal' => ['&erDot', '&amp;erDot'],
            'erDot with semicolon' => ['&erDot;', '≓'],
            'erarr without semicolon remains literal' => ['&erarr', '&amp;erarr'],
            'erarr with semicolon' => ['&erarr;', '⥱'],
            'escr without semicolon remains literal' => ['&escr', '&amp;escr'],
            'escr with semicolon' => ['&escr;', 'ℯ'],
            'esdot without semicolon remains literal' => ['&esdot', '&amp;esdot'],
            'esdot with semicolon' => ['&esdot;', '≐'],
            'esim without semicolon remains literal' => ['&esim', '&amp;esim'],
            'esim with semicolon' => ['&esim;', '≂'],
            'eta without semicolon remains literal' => ['&eta', '&amp;eta'],
            'eta with semicolon' => ['&eta;', 'η'],
            'eth without semicolon' => ['&eth', 'ð'],
            'eth with semicolon' => ['&eth;', 'ð'],
            'euml without semicolon' => ['&euml', 'ë'],
            'euml with semicolon' => ['&euml;', 'ë'],
            'euro without semicolon remains literal' => ['&euro', '&amp;euro'],
            'euro with semicolon' => ['&euro;', '€'],
            'excl without semicolon remains literal' => ['&excl', '&amp;excl'],
            'excl with semicolon' => ['&excl;', '!'],
            'exist without semicolon remains literal' => ['&exist', '&amp;exist'],
            'exist with semicolon' => ['&exist;', '∃'],
            'expectation without semicolon remains literal' => ['&expectation', '&amp;expectation'],
            'expectation with semicolon' => ['&expectation;', 'ℰ'],
            'exponentiale without semicolon remains literal' => ['&exponentiale', '&amp;exponentiale'],
            'exponentiale with semicolon' => ['&exponentiale;', 'ⅇ'],
            'fallingdotseq without semicolon remains literal' => ['&fallingdotseq', '&amp;fallingdotseq'],
            'fallingdotseq with semicolon' => ['&fallingdotseq;', '≒'],
            'fcy without semicolon remains literal' => ['&fcy', '&amp;fcy'],
            'fcy with semicolon' => ['&fcy;', 'ф'],
            'female without semicolon remains literal' => ['&female', '&amp;female'],
            'female with semicolon' => ['&female;', '♀'],
            'ffilig without semicolon remains literal' => ['&ffilig', '&amp;ffilig'],
            'ffilig with semicolon' => ['&ffilig;', 'ﬃ'],
            'fflig without semicolon remains literal' => ['&fflig', '&amp;fflig'],
            'fflig with semicolon' => ['&fflig;', 'ﬀ'],
            'ffllig without semicolon remains literal' => ['&ffllig', '&amp;ffllig'],
            'ffllig with semicolon' => ['&ffllig;', 'ﬄ'],
            'ffr without semicolon remains literal' => ['&ffr', '&amp;ffr'],
            'ffr with semicolon' => ['&ffr;', '𝔣'],
            'filig without semicolon remains literal' => ['&filig', '&amp;filig'],
            'filig with semicolon' => ['&filig;', 'ﬁ'],
            'fjlig without semicolon remains literal' => ['&fjlig', '&amp;fjlig'],
            'fjlig with semicolon' => ['&fjlig;', 'fj'],
            'flat without semicolon remains literal' => ['&flat', '&amp;flat'],
            'flat with semicolon' => ['&flat;', '♭'],
            'fllig without semicolon remains literal' => ['&fllig', '&amp;fllig'],
            'fllig with semicolon' => ['&fllig;', 'ﬂ'],
            'fltns without semicolon remains literal' => ['&fltns', '&amp;fltns'],
            'fltns with semicolon' => ['&fltns;', '▱'],
            'fnof without semicolon remains literal' => ['&fnof', '&amp;fnof'],
            'fnof with semicolon' => ['&fnof;', 'ƒ'],
            'fopf without semicolon remains literal' => ['&fopf', '&amp;fopf'],
            'fopf with semicolon' => ['&fopf;', '𝕗'],
            'forall without semicolon remains literal' => ['&forall', '&amp;forall'],
            'forall with semicolon' => ['&forall;', '∀'],
            'fork without semicolon remains literal' => ['&fork', '&amp;fork'],
            'fork with semicolon' => ['&fork;', '⋔'],
            'forkv without semicolon remains literal' => ['&forkv', '&amp;forkv'],
            'forkv with semicolon' => ['&forkv;', '⫙'],
            'fpartint without semicolon remains literal' => ['&fpartint', '&amp;fpartint'],
            'fpartint with semicolon' => ['&fpartint;', '⨍'],
            'frac12 without semicolon' => ['&frac12', '½'],
            'frac12 with semicolon' => ['&frac12;', '½'],
            'frac13 without semicolon remains literal' => ['&frac13', '&amp;frac13'],
            'frac13 with semicolon' => ['&frac13;', '⅓'],
            'frac14 without semicolon' => ['&frac14', '¼'],
            'frac14 with semicolon' => ['&frac14;', '¼'],
            'frac15 without semicolon remains literal' => ['&frac15', '&amp;frac15'],
            'frac15 with semicolon' => ['&frac15;', '⅕'],
            'frac16 without semicolon remains literal' => ['&frac16', '&amp;frac16'],
            'frac16 with semicolon' => ['&frac16;', '⅙'],
            'frac18 without semicolon remains literal' => ['&frac18', '&amp;frac18'],
            'frac18 with semicolon' => ['&frac18;', '⅛'],
            'frac23 without semicolon remains literal' => ['&frac23', '&amp;frac23'],
            'frac23 with semicolon' => ['&frac23;', '⅔'],
            'frac25 without semicolon remains literal' => ['&frac25', '&amp;frac25'],
            'frac25 with semicolon' => ['&frac25;', '⅖'],
            'frac34 without semicolon' => ['&frac34', '¾'],
            'frac34 with semicolon' => ['&frac34;', '¾'],
            'frac35 without semicolon remains literal' => ['&frac35', '&amp;frac35'],
            'frac35 with semicolon' => ['&frac35;', '⅗'],
            'frac38 without semicolon remains literal' => ['&frac38', '&amp;frac38'],
            'frac38 with semicolon' => ['&frac38;', '⅜'],
            'frac45 without semicolon remains literal' => ['&frac45', '&amp;frac45'],
            'frac45 with semicolon' => ['&frac45;', '⅘'],
            'frac56 without semicolon remains literal' => ['&frac56', '&amp;frac56'],
            'frac56 with semicolon' => ['&frac56;', '⅚'],
            'frac58 without semicolon remains literal' => ['&frac58', '&amp;frac58'],
            'frac58 with semicolon' => ['&frac58;', '⅝'],
            'frac78 without semicolon remains literal' => ['&frac78', '&amp;frac78'],
            'frac78 with semicolon' => ['&frac78;', '⅞'],
            'frasl without semicolon remains literal' => ['&frasl', '&amp;frasl'],
            'frasl with semicolon' => ['&frasl;', '⁄'],
            'frown without semicolon remains literal' => ['&frown', '&amp;frown'],
            'frown with semicolon' => ['&frown;', '⌢'],
            'fscr without semicolon remains literal' => ['&fscr', '&amp;fscr'],
            'fscr with semicolon' => ['&fscr;', '𝒻'],
            'gE without semicolon remains literal' => ['&gE', '&amp;gE'],
            'gE with semicolon' => ['&gE;', '≧'],
            'gEl without semicolon remains literal' => ['&gEl', '&amp;gEl'],
            'gEl with semicolon' => ['&gEl;', '⪌'],
            'gacute without semicolon remains literal' => ['&gacute', '&amp;gacute'],
            'gacute with semicolon' => ['&gacute;', 'ǵ'],
            'gamma without semicolon remains literal' => ['&gamma', '&amp;gamma'],
            'gamma with semicolon' => ['&gamma;', 'γ'],
            'gammad without semicolon remains literal' => ['&gammad', '&amp;gammad'],
            'gammad with semicolon' => ['&gammad;', 'ϝ'],
            'gap without semicolon remains literal' => ['&gap', '&amp;gap'],
            'gap with semicolon' => ['&gap;', '⪆'],
            'gbreve without semicolon remains literal' => ['&gbreve', '&amp;gbreve'],
            'gbreve with semicolon' => ['&gbreve;', 'ğ'],
            'gcirc without semicolon remains literal' => ['&gcirc', '&amp;gcirc'],
            'gcirc with semicolon' => ['&gcirc;', 'ĝ'],
            'gcy without semicolon remains literal' => ['&gcy', '&amp;gcy'],
            'gcy with semicolon' => ['&gcy;', 'г'],
            'gdot without semicolon remains literal' => ['&gdot', '&amp;gdot'],
            'gdot with semicolon' => ['&gdot;', 'ġ'],
            'ge without semicolon remains literal' => ['&ge', '&amp;ge'],
            'ge with semicolon' => ['&ge;', '≥'],
            'gel without semicolon remains literal' => ['&gel', '&amp;gel'],
            'gel with semicolon' => ['&gel;', '⋛'],
            'geq without semicolon remains literal' => ['&geq', '&amp;geq'],
            'geq with semicolon' => ['&geq;', '≥'],
            'geqq without semicolon remains literal' => ['&geqq', '&amp;geqq'],
            'geqq with semicolon' => ['&geqq;', '≧'],
            'geqslant without semicolon remains literal' => ['&geqslant', '&amp;geqslant'],
            'geqslant with semicolon' => ['&geqslant;', '⩾'],
            'ges without semicolon remains literal' => ['&ges', '&amp;ges'],
            'ges with semicolon' => ['&ges;', '⩾'],
            'gescc without semicolon remains literal' => ['&gescc', '&amp;gescc'],
            'gescc with semicolon' => ['&gescc;', '⪩'],
            'gesdot without semicolon remains literal' => ['&gesdot', '&amp;gesdot'],
            'gesdot with semicolon' => ['&gesdot;', '⪀'],
            'gesdoto without semicolon remains literal' => ['&gesdoto', '&amp;gesdoto'],
            'gesdoto with semicolon' => ['&gesdoto;', '⪂'],
            'gesdotol without semicolon remains literal' => ['&gesdotol', '&amp;gesdotol'],
            'gesdotol with semicolon' => ['&gesdotol;', '⪄'],
            'gesl without semicolon remains literal' => ['&gesl', '&amp;gesl'],
            'gesl with semicolon' => ['&gesl;', '⋛︀'],
            'gesles without semicolon remains literal' => ['&gesles', '&amp;gesles'],
            'gesles with semicolon' => ['&gesles;', '⪔'],
            'gfr without semicolon remains literal' => ['&gfr', '&amp;gfr'],
            'gfr with semicolon' => ['&gfr;', '𝔤'],
            'gg without semicolon remains literal' => ['&gg', '&amp;gg'],
            'gg with semicolon' => ['&gg;', '≫'],
            'ggg without semicolon remains literal' => ['&ggg', '&amp;ggg'],
            'ggg with semicolon' => ['&ggg;', '⋙'],
            'gimel without semicolon remains literal' => ['&gimel', '&amp;gimel'],
            'gimel with semicolon' => ['&gimel;', 'ℷ'],
            'gjcy without semicolon remains literal' => ['&gjcy', '&amp;gjcy'],
            'gjcy with semicolon' => ['&gjcy;', 'ѓ'],
            'gl without semicolon remains literal' => ['&gl', '&amp;gl'],
            'gl with semicolon' => ['&gl;', '≷'],
            'glE without semicolon remains literal' => ['&glE', '&amp;glE'],
            'glE with semicolon' => ['&glE;', '⪒'],
            'gla without semicolon remains literal' => ['&gla', '&amp;gla'],
            'gla with semicolon' => ['&gla;', '⪥'],
            'glj without semicolon remains literal' => ['&glj', '&amp;glj'],
            'glj with semicolon' => ['&glj;', '⪤'],
            'gnE without semicolon remains literal' => ['&gnE', '&amp;gnE'],
            'gnE with semicolon' => ['&gnE;', '≩'],
            'gnap without semicolon remains literal' => ['&gnap', '&amp;gnap'],
            'gnap with semicolon' => ['&gnap;', '⪊'],
            'gnapprox without semicolon remains literal' => ['&gnapprox', '&amp;gnapprox'],
            'gnapprox with semicolon' => ['&gnapprox;', '⪊'],
            'gne without semicolon remains literal' => ['&gne', '&amp;gne'],
            'gne with semicolon' => ['&gne;', '⪈'],
            'gneq without semicolon remains literal' => ['&gneq', '&amp;gneq'],
            'gneq with semicolon' => ['&gneq;', '⪈'],
            'gneqq without semicolon remains literal' => ['&gneqq', '&amp;gneqq'],
            'gneqq with semicolon' => ['&gneqq;', '≩'],
            'gnsim without semicolon remains literal' => ['&gnsim', '&amp;gnsim'],
            'gnsim with semicolon' => ['&gnsim;', '⋧'],
            'gopf without semicolon remains literal' => ['&gopf', '&amp;gopf'],
            'gopf with semicolon' => ['&gopf;', '𝕘'],
            'grave without semicolon remains literal' => ['&grave', '&amp;grave'],
            'grave with semicolon' => ['&grave;', '`'],
            'gscr without semicolon remains literal' => ['&gscr', '&amp;gscr'],
            'gscr with semicolon' => ['&gscr;', 'ℊ'],
            'gsim without semicolon remains literal' => ['&gsim', '&amp;gsim'],
            'gsim with semicolon' => ['&gsim;', '≳'],
            'gsime without semicolon remains literal' => ['&gsime', '&amp;gsime'],
            'gsime with semicolon' => ['&gsime;', '⪎'],
            'gsiml without semicolon remains literal' => ['&gsiml', '&amp;gsiml'],
            'gsiml with semicolon' => ['&gsiml;', '⪐'],
            'gt without semicolon' => ['&gt', '&gt;'],
            'gt with semicolon' => ['&gt;', '&gt;'],
            'gtcc with semicolon' => ['&gtcc;', '⪧'],
            'gtcir with semicolon' => ['&gtcir;', '⩺'],
            'gtdot with semicolon' => ['&gtdot;', '⋗'],
            'gtlPar with semicolon' => ['&gtlPar;', '⦕'],
            'gtquest with semicolon' => ['&gtquest;', '⩼'],
            'gtrapprox with semicolon' => ['&gtrapprox;', '⪆'],
            'gtrarr with semicolon' => ['&gtrarr;', '⥸'],
            'gtrdot with semicolon' => ['&gtrdot;', '⋗'],
            'gtreqless with semicolon' => ['&gtreqless;', '⋛'],
            'gtreqqless with semicolon' => ['&gtreqqless;', '⪌'],
            'gtrless with semicolon' => ['&gtrless;', '≷'],
            'gtrsim with semicolon' => ['&gtrsim;', '≳'],
            'gvertneqq without semicolon remains literal' => ['&gvertneqq', '&amp;gvertneqq'],
            'gvertneqq with semicolon' => ['&gvertneqq;', '≩︀'],
            'gvnE without semicolon remains literal' => ['&gvnE', '&amp;gvnE'],
            'gvnE with semicolon' => ['&gvnE;', '≩︀'],
            'hArr without semicolon remains literal' => ['&hArr', '&amp;hArr'],
            'hArr with semicolon' => ['&hArr;', '⇔'],
            'hairsp without semicolon remains literal' => ['&hairsp', '&amp;hairsp'],
            'hairsp with semicolon' => ['&hairsp;', ' '],
            'half without semicolon remains literal' => ['&half', '&amp;half'],
            'half with semicolon' => ['&half;', '½'],
            'hamilt without semicolon remains literal' => ['&hamilt', '&amp;hamilt'],
            'hamilt with semicolon' => ['&hamilt;', 'ℋ'],
            'hardcy without semicolon remains literal' => ['&hardcy', '&amp;hardcy'],
            'hardcy with semicolon' => ['&hardcy;', 'ъ'],
            'harr without semicolon remains literal' => ['&harr', '&amp;harr'],
            'harr with semicolon' => ['&harr;', '↔'],
            'harrcir without semicolon remains literal' => ['&harrcir', '&amp;harrcir'],
            'harrcir with semicolon' => ['&harrcir;', '⥈'],
            'harrw without semicolon remains literal' => ['&harrw', '&amp;harrw'],
            'harrw with semicolon' => ['&harrw;', '↭'],
            'hbar without semicolon remains literal' => ['&hbar', '&amp;hbar'],
            'hbar with semicolon' => ['&hbar;', 'ℏ'],
            'hcirc without semicolon remains literal' => ['&hcirc', '&amp;hcirc'],
            'hcirc with semicolon' => ['&hcirc;', 'ĥ'],
            'hearts without semicolon remains literal' => ['&hearts', '&amp;hearts'],
            'hearts with semicolon' => ['&hearts;', '♥'],
            'heartsuit without semicolon remains literal' => ['&heartsuit', '&amp;heartsuit'],
            'heartsuit with semicolon' => ['&heartsuit;', '♥'],
            'hellip without semicolon remains literal' => ['&hellip', '&amp;hellip'],
            'hellip with semicolon' => ['&hellip;', '…'],
            'hercon without semicolon remains literal' => ['&hercon', '&amp;hercon'],
            'hercon with semicolon' => ['&hercon;', '⊹'],
            'hfr without semicolon remains literal' => ['&hfr', '&amp;hfr'],
            'hfr with semicolon' => ['&hfr;', '𝔥'],
            'hksearow without semicolon remains literal' => ['&hksearow', '&amp;hksearow'],
            'hksearow with semicolon' => ['&hksearow;', '⤥'],
            'hkswarow without semicolon remains literal' => ['&hkswarow', '&amp;hkswarow'],
            'hkswarow with semicolon' => ['&hkswarow;', '⤦'],
            'hoarr without semicolon remains literal' => ['&hoarr', '&amp;hoarr'],
            'hoarr with semicolon' => ['&hoarr;', '⇿'],
            'homtht without semicolon remains literal' => ['&homtht', '&amp;homtht'],
            'homtht with semicolon' => ['&homtht;', '∻'],
            'hookleftarrow without semicolon remains literal' => ['&hookleftarrow', '&amp;hookleftarrow'],
            'hookleftarrow with semicolon' => ['&hookleftarrow;', '↩'],
            'hookrightarrow without semicolon remains literal' => ['&hookrightarrow', '&amp;hookrightarrow'],
            'hookrightarrow with semicolon' => ['&hookrightarrow;', '↪'],
            'hopf without semicolon remains literal' => ['&hopf', '&amp;hopf'],
            'hopf with semicolon' => ['&hopf;', '𝕙'],
            'horbar without semicolon remains literal' => ['&horbar', '&amp;horbar'],
            'horbar with semicolon' => ['&horbar;', '―'],
            'hscr without semicolon remains literal' => ['&hscr', '&amp;hscr'],
            'hscr with semicolon' => ['&hscr;', '𝒽'],
            'hslash without semicolon remains literal' => ['&hslash', '&amp;hslash'],
            'hslash with semicolon' => ['&hslash;', 'ℏ'],
            'hstrok without semicolon remains literal' => ['&hstrok', '&amp;hstrok'],
            'hstrok with semicolon' => ['&hstrok;', 'ħ'],
            'hybull without semicolon remains literal' => ['&hybull', '&amp;hybull'],
            'hybull with semicolon' => ['&hybull;', '⁃'],
            'hyphen without semicolon remains literal' => ['&hyphen', '&amp;hyphen'],
            'hyphen with semicolon' => ['&hyphen;', '‐'],
            'iacute without semicolon' => ['&iacute', 'í'],
            'iacute with semicolon' => ['&iacute;', 'í'],
            'ic without semicolon remains literal' => ['&ic', '&amp;ic'],
            'ic with semicolon' => ['&ic;', '⁣'],
            'icirc without semicolon' => ['&icirc', 'î'],
            'icirc with semicolon' => ['&icirc;', 'î'],
            'icy without semicolon remains literal' => ['&icy', '&amp;icy'],
            'icy with semicolon' => ['&icy;', 'и'],
            'iecy without semicolon remains literal' => ['&iecy', '&amp;iecy'],
            'iecy with semicolon' => ['&iecy;', 'е'],
            'iexcl without semicolon' => ['&iexcl', '¡'],
            'iexcl with semicolon' => ['&iexcl;', '¡'],
            'iff without semicolon remains literal' => ['&iff', '&amp;iff'],
            'iff with semicolon' => ['&iff;', '⇔'],
            'ifr without semicolon remains literal' => ['&ifr', '&amp;ifr'],
            'ifr with semicolon' => ['&ifr;', '𝔦'],
            'igrave without semicolon' => ['&igrave', 'ì'],
            'igrave with semicolon' => ['&igrave;', 'ì'],
            'ii without semicolon remains literal' => ['&ii', '&amp;ii'],
            'ii with semicolon' => ['&ii;', 'ⅈ'],
            'iiiint without semicolon remains literal' => ['&iiiint', '&amp;iiiint'],
            'iiiint with semicolon' => ['&iiiint;', '⨌'],
            'iiint without semicolon remains literal' => ['&iiint', '&amp;iiint'],
            'iiint with semicolon' => ['&iiint;', '∭'],
            'iinfin without semicolon remains literal' => ['&iinfin', '&amp;iinfin'],
            'iinfin with semicolon' => ['&iinfin;', '⧜'],
            'iiota without semicolon remains literal' => ['&iiota', '&amp;iiota'],
            'iiota with semicolon' => ['&iiota;', '℩'],
            'ijlig without semicolon remains literal' => ['&ijlig', '&amp;ijlig'],
            'ijlig with semicolon' => ['&ijlig;', 'ĳ'],
            'imacr without semicolon remains literal' => ['&imacr', '&amp;imacr'],
            'imacr with semicolon' => ['&imacr;', 'ī'],
            'image without semicolon remains literal' => ['&image', '&amp;image'],
            'image with semicolon' => ['&image;', 'ℑ'],
            'imagline without semicolon remains literal' => ['&imagline', '&amp;imagline'],
            'imagline with semicolon' => ['&imagline;', 'ℐ'],
            'imagpart without semicolon remains literal' => ['&imagpart', '&amp;imagpart'],
            'imagpart with semicolon' => ['&imagpart;', 'ℑ'],
            'imath without semicolon remains literal' => ['&imath', '&amp;imath'],
            'imath with semicolon' => ['&imath;', 'ı'],
            'imof without semicolon remains literal' => ['&imof', '&amp;imof'],
            'imof with semicolon' => ['&imof;', '⊷'],
            'imped without semicolon remains literal' => ['&imped', '&amp;imped'],
            'imped with semicolon' => ['&imped;', 'Ƶ'],
            'in without semicolon remains literal' => ['&in', '&amp;in'],
            'in with semicolon' => ['&in;', '∈'],
            'incare without semicolon remains literal' => ['&incare', '&amp;incare'],
            'incare with semicolon' => ['&incare;', '℅'],
            'infin without semicolon remains literal' => ['&infin', '&amp;infin'],
            'infin with semicolon' => ['&infin;', '∞'],
            'infintie without semicolon remains literal' => ['&infintie', '&amp;infintie'],
            'infintie with semicolon' => ['&infintie;', '⧝'],
            'inodot without semicolon remains literal' => ['&inodot', '&amp;inodot'],
            'inodot with semicolon' => ['&inodot;', 'ı'],
            'int without semicolon remains literal' => ['&int', '&amp;int'],
            'int with semicolon' => ['&int;', '∫'],
            'intcal without semicolon remains literal' => ['&intcal', '&amp;intcal'],
            'intcal with semicolon' => ['&intcal;', '⊺'],
            'integers without semicolon remains literal' => ['&integers', '&amp;integers'],
            'integers with semicolon' => ['&integers;', 'ℤ'],
            'intercal without semicolon remains literal' => ['&intercal', '&amp;intercal'],
            'intercal with semicolon' => ['&intercal;', '⊺'],
            'intlarhk without semicolon remains literal' => ['&intlarhk', '&amp;intlarhk'],
            'intlarhk with semicolon' => ['&intlarhk;', '⨗'],
            'intprod without semicolon remains literal' => ['&intprod', '&amp;intprod'],
            'intprod with semicolon' => ['&intprod;', '⨼'],
            'iocy without semicolon remains literal' => ['&iocy', '&amp;iocy'],
            'iocy with semicolon' => ['&iocy;', 'ё'],
            'iogon without semicolon remains literal' => ['&iogon', '&amp;iogon'],
            'iogon with semicolon' => ['&iogon;', 'į'],
            'iopf without semicolon remains literal' => ['&iopf', '&amp;iopf'],
            'iopf with semicolon' => ['&iopf;', '𝕚'],
            'iota without semicolon remains literal' => ['&iota', '&amp;iota'],
            'iota with semicolon' => ['&iota;', 'ι'],
            'iprod without semicolon remains literal' => ['&iprod', '&amp;iprod'],
            'iprod with semicolon' => ['&iprod;', '⨼'],
            'iquest without semicolon' => ['&iquest', '¿'],
            'iquest with semicolon' => ['&iquest;', '¿'],
            'iscr without semicolon remains literal' => ['&iscr', '&amp;iscr'],
            'iscr with semicolon' => ['&iscr;', '𝒾'],
            'isin without semicolon remains literal' => ['&isin', '&amp;isin'],
            'isin with semicolon' => ['&isin;', '∈'],
            'isinE without semicolon remains literal' => ['&isinE', '&amp;isinE'],
            'isinE with semicolon' => ['&isinE;', '⋹'],
            'isindot without semicolon remains literal' => ['&isindot', '&amp;isindot'],
            'isindot with semicolon' => ['&isindot;', '⋵'],
            'isins without semicolon remains literal' => ['&isins', '&amp;isins'],
            'isins with semicolon' => ['&isins;', '⋴'],
            'isinsv without semicolon remains literal' => ['&isinsv', '&amp;isinsv'],
            'isinsv with semicolon' => ['&isinsv;', '⋳'],
            'isinv without semicolon remains literal' => ['&isinv', '&amp;isinv'],
            'isinv with semicolon' => ['&isinv;', '∈'],
            'it without semicolon remains literal' => ['&it', '&amp;it'],
            'it with semicolon' => ['&it;', '⁢'],
            'itilde without semicolon remains literal' => ['&itilde', '&amp;itilde'],
            'itilde with semicolon' => ['&itilde;', 'ĩ'],
            'iukcy without semicolon remains literal' => ['&iukcy', '&amp;iukcy'],
            'iukcy with semicolon' => ['&iukcy;', 'і'],
            'iuml without semicolon' => ['&iuml', 'ï'],
            'iuml with semicolon' => ['&iuml;', 'ï'],
            'jcirc without semicolon remains literal' => ['&jcirc', '&amp;jcirc'],
            'jcirc with semicolon' => ['&jcirc;', 'ĵ'],
            'jcy without semicolon remains literal' => ['&jcy', '&amp;jcy'],
            'jcy with semicolon' => ['&jcy;', 'й'],
            'jfr without semicolon remains literal' => ['&jfr', '&amp;jfr'],
            'jfr with semicolon' => ['&jfr;', '𝔧'],
            'jmath without semicolon remains literal' => ['&jmath', '&amp;jmath'],
            'jmath with semicolon' => ['&jmath;', 'ȷ'],
            'jopf without semicolon remains literal' => ['&jopf', '&amp;jopf'],
            'jopf with semicolon' => ['&jopf;', '𝕛'],
            'jscr without semicolon remains literal' => ['&jscr', '&amp;jscr'],
            'jscr with semicolon' => ['&jscr;', '𝒿'],
            'jsercy without semicolon remains literal' => ['&jsercy', '&amp;jsercy'],
            'jsercy with semicolon' => ['&jsercy;', 'ј'],
            'jukcy without semicolon remains literal' => ['&jukcy', '&amp;jukcy'],
            'jukcy with semicolon' => ['&jukcy;', 'є'],
            'kappa without semicolon remains literal' => ['&kappa', '&amp;kappa'],
            'kappa with semicolon' => ['&kappa;', 'κ'],
            'kappav without semicolon remains literal' => ['&kappav', '&amp;kappav'],
            'kappav with semicolon' => ['&kappav;', 'ϰ'],
            'kcedil without semicolon remains literal' => ['&kcedil', '&amp;kcedil'],
            'kcedil with semicolon' => ['&kcedil;', 'ķ'],
            'kcy without semicolon remains literal' => ['&kcy', '&amp;kcy'],
            'kcy with semicolon' => ['&kcy;', 'к'],
            'kfr without semicolon remains literal' => ['&kfr', '&amp;kfr'],
            'kfr with semicolon' => ['&kfr;', '𝔨'],
            'kgreen without semicolon remains literal' => ['&kgreen', '&amp;kgreen'],
            'kgreen with semicolon' => ['&kgreen;', 'ĸ'],
            'khcy without semicolon remains literal' => ['&khcy', '&amp;khcy'],
            'khcy with semicolon' => ['&khcy;', 'х'],
            'kjcy without semicolon remains literal' => ['&kjcy', '&amp;kjcy'],
            'kjcy with semicolon' => ['&kjcy;', 'ќ'],
            'kopf without semicolon remains literal' => ['&kopf', '&amp;kopf'],
            'kopf with semicolon' => ['&kopf;', '𝕜'],
            'kscr without semicolon remains literal' => ['&kscr', '&amp;kscr'],
            'kscr with semicolon' => ['&kscr;', '𝓀'],
            'lAarr without semicolon remains literal' => ['&lAarr', '&amp;lAarr'],
            'lAarr with semicolon' => ['&lAarr;', '⇚'],
            'lArr without semicolon remains literal' => ['&lArr', '&amp;lArr'],
            'lArr with semicolon' => ['&lArr;', '⇐'],
            'lAtail without semicolon remains literal' => ['&lAtail', '&amp;lAtail'],
            'lAtail with semicolon' => ['&lAtail;', '⤛'],
            'lBarr without semicolon remains literal' => ['&lBarr', '&amp;lBarr'],
            'lBarr with semicolon' => ['&lBarr;', '⤎'],
            'lE without semicolon remains literal' => ['&lE', '&amp;lE'],
            'lE with semicolon' => ['&lE;', '≦'],
            'lEg without semicolon remains literal' => ['&lEg', '&amp;lEg'],
            'lEg with semicolon' => ['&lEg;', '⪋'],
            'lHar without semicolon remains literal' => ['&lHar', '&amp;lHar'],
            'lHar with semicolon' => ['&lHar;', '⥢'],
            'lacute without semicolon remains literal' => ['&lacute', '&amp;lacute'],
            'lacute with semicolon' => ['&lacute;', 'ĺ'],
            'laemptyv without semicolon remains literal' => ['&laemptyv', '&amp;laemptyv'],
            'laemptyv with semicolon' => ['&laemptyv;', '⦴'],
            'lagran without semicolon remains literal' => ['&lagran', '&amp;lagran'],
            'lagran with semicolon' => ['&lagran;', 'ℒ'],
            'lambda without semicolon remains literal' => ['&lambda', '&amp;lambda'],
            'lambda with semicolon' => ['&lambda;', 'λ'],
            'lang without semicolon remains literal' => ['&lang', '&amp;lang'],
            'lang with semicolon' => ['&lang;', '⟨'],
            'langd without semicolon remains literal' => ['&langd', '&amp;langd'],
            'langd with semicolon' => ['&langd;', '⦑'],
            'langle without semicolon remains literal' => ['&langle', '&amp;langle'],
            'langle with semicolon' => ['&langle;', '⟨'],
            'lap without semicolon remains literal' => ['&lap', '&amp;lap'],
            'lap with semicolon' => ['&lap;', '⪅'],
            'laquo without semicolon' => ['&laquo', '«'],
            'laquo with semicolon' => ['&laquo;', '«'],
            'larr without semicolon remains literal' => ['&larr', '&amp;larr'],
            'larr with semicolon' => ['&larr;', '←'],
            'larrb without semicolon remains literal' => ['&larrb', '&amp;larrb'],
            'larrb with semicolon' => ['&larrb;', '⇤'],
            'larrbfs without semicolon remains literal' => ['&larrbfs', '&amp;larrbfs'],
            'larrbfs with semicolon' => ['&larrbfs;', '⤟'],
            'larrfs without semicolon remains literal' => ['&larrfs', '&amp;larrfs'],
            'larrfs with semicolon' => ['&larrfs;', '⤝'],
            'larrhk without semicolon remains literal' => ['&larrhk', '&amp;larrhk'],
            'larrhk with semicolon' => ['&larrhk;', '↩'],
            'larrlp without semicolon remains literal' => ['&larrlp', '&amp;larrlp'],
            'larrlp with semicolon' => ['&larrlp;', '↫'],
            'larrpl without semicolon remains literal' => ['&larrpl', '&amp;larrpl'],
            'larrpl with semicolon' => ['&larrpl;', '⤹'],
            'larrsim without semicolon remains literal' => ['&larrsim', '&amp;larrsim'],
            'larrsim with semicolon' => ['&larrsim;', '⥳'],
            'larrtl without semicolon remains literal' => ['&larrtl', '&amp;larrtl'],
            'larrtl with semicolon' => ['&larrtl;', '↢'],
            'lat without semicolon remains literal' => ['&lat', '&amp;lat'],
            'lat with semicolon' => ['&lat;', '⪫'],
            'latail without semicolon remains literal' => ['&latail', '&amp;latail'],
            'latail with semicolon' => ['&latail;', '⤙'],
            'late without semicolon remains literal' => ['&late', '&amp;late'],
            'late with semicolon' => ['&late;', '⪭'],
            'lates without semicolon remains literal' => ['&lates', '&amp;lates'],
            'lates with semicolon' => ['&lates;', '⪭︀'],
            'lbarr without semicolon remains literal' => ['&lbarr', '&amp;lbarr'],
            'lbarr with semicolon' => ['&lbarr;', '⤌'],
            'lbbrk without semicolon remains literal' => ['&lbbrk', '&amp;lbbrk'],
            'lbbrk with semicolon' => ['&lbbrk;', '❲'],
            'lbrace without semicolon remains literal' => ['&lbrace', '&amp;lbrace'],
            'lbrace with semicolon' => ['&lbrace;', '{'],
            'lbrack without semicolon remains literal' => ['&lbrack', '&amp;lbrack'],
            'lbrack with semicolon' => ['&lbrack;', '['],
            'lbrke without semicolon remains literal' => ['&lbrke', '&amp;lbrke'],
            'lbrke with semicolon' => ['&lbrke;', '⦋'],
            'lbrksld without semicolon remains literal' => ['&lbrksld', '&amp;lbrksld'],
            'lbrksld with semicolon' => ['&lbrksld;', '⦏'],
            'lbrkslu without semicolon remains literal' => ['&lbrkslu', '&amp;lbrkslu'],
            'lbrkslu with semicolon' => ['&lbrkslu;', '⦍'],
            'lcaron without semicolon remains literal' => ['&lcaron', '&amp;lcaron'],
            'lcaron with semicolon' => ['&lcaron;', 'ľ'],
            'lcedil without semicolon remains literal' => ['&lcedil', '&amp;lcedil'],
            'lcedil with semicolon' => ['&lcedil;', 'ļ'],
            'lceil without semicolon remains literal' => ['&lceil', '&amp;lceil'],
            'lceil with semicolon' => ['&lceil;', '⌈'],
            'lcub without semicolon remains literal' => ['&lcub', '&amp;lcub'],
            'lcub with semicolon' => ['&lcub;', '{'],
            'lcy without semicolon remains literal' => ['&lcy', '&amp;lcy'],
            'lcy with semicolon' => ['&lcy;', 'л'],
            'ldca without semicolon remains literal' => ['&ldca', '&amp;ldca'],
            'ldca with semicolon' => ['&ldca;', '⤶'],
            'ldquo without semicolon remains literal' => ['&ldquo', '&amp;ldquo'],
            'ldquo with semicolon' => ['&ldquo;', '“'],
            'ldquor without semicolon remains literal' => ['&ldquor', '&amp;ldquor'],
            'ldquor with semicolon' => ['&ldquor;', '„'],
            'ldrdhar without semicolon remains literal' => ['&ldrdhar', '&amp;ldrdhar'],
            'ldrdhar with semicolon' => ['&ldrdhar;', '⥧'],
            'ldrushar without semicolon remains literal' => ['&ldrushar', '&amp;ldrushar'],
            'ldrushar with semicolon' => ['&ldrushar;', '⥋'],
            'ldsh without semicolon remains literal' => ['&ldsh', '&amp;ldsh'],
            'ldsh with semicolon' => ['&ldsh;', '↲'],
            'le without semicolon remains literal' => ['&le', '&amp;le'],
            'le with semicolon' => ['&le;', '≤'],
            'leftarrow without semicolon remains literal' => ['&leftarrow', '&amp;leftarrow'],
            'leftarrow with semicolon' => ['&leftarrow;', '←'],
            'leftarrowtail without semicolon remains literal' => ['&leftarrowtail', '&amp;leftarrowtail'],
            'leftarrowtail with semicolon' => ['&leftarrowtail;', '↢'],
            'leftharpoondown without semicolon remains literal' => ['&leftharpoondown', '&amp;leftharpoondown'],
            'leftharpoondown with semicolon' => ['&leftharpoondown;', '↽'],
            'leftharpoonup without semicolon remains literal' => ['&leftharpoonup', '&amp;leftharpoonup'],
            'leftharpoonup with semicolon' => ['&leftharpoonup;', '↼'],
            'leftleftarrows without semicolon remains literal' => ['&leftleftarrows', '&amp;leftleftarrows'],
            'leftleftarrows with semicolon' => ['&leftleftarrows;', '⇇'],
            'leftrightarrow without semicolon remains literal' => ['&leftrightarrow', '&amp;leftrightarrow'],
            'leftrightarrow with semicolon' => ['&leftrightarrow;', '↔'],
            'leftrightarrows without semicolon remains literal' => ['&leftrightarrows', '&amp;leftrightarrows'],
            'leftrightarrows with semicolon' => ['&leftrightarrows;', '⇆'],
            'leftrightharpoons without semicolon remains literal' => ['&leftrightharpoons', '&amp;leftrightharpoons'],
            'leftrightharpoons with semicolon' => ['&leftrightharpoons;', '⇋'],
            'leftrightsquigarrow without semicolon remains literal' => ['&leftrightsquigarrow', '&amp;leftrightsquigarrow'],
            'leftrightsquigarrow with semicolon' => ['&leftrightsquigarrow;', '↭'],
            'leftthreetimes without semicolon remains literal' => ['&leftthreetimes', '&amp;leftthreetimes'],
            'leftthreetimes with semicolon' => ['&leftthreetimes;', '⋋'],
            'leg without semicolon remains literal' => ['&leg', '&amp;leg'],
            'leg with semicolon' => ['&leg;', '⋚'],
            'leq without semicolon remains literal' => ['&leq', '&amp;leq'],
            'leq with semicolon' => ['&leq;', '≤'],
            'leqq without semicolon remains literal' => ['&leqq', '&amp;leqq'],
            'leqq with semicolon' => ['&leqq;', '≦'],
            'leqslant without semicolon remains literal' => ['&leqslant', '&amp;leqslant'],
            'leqslant with semicolon' => ['&leqslant;', '⩽'],
            'les without semicolon remains literal' => ['&les', '&amp;les'],
            'les with semicolon' => ['&les;', '⩽'],
            'lescc without semicolon remains literal' => ['&lescc', '&amp;lescc'],
            'lescc with semicolon' => ['&lescc;', '⪨'],
            'lesdot without semicolon remains literal' => ['&lesdot', '&amp;lesdot'],
            'lesdot with semicolon' => ['&lesdot;', '⩿'],
            'lesdoto without semicolon remains literal' => ['&lesdoto', '&amp;lesdoto'],
            'lesdoto with semicolon' => ['&lesdoto;', '⪁'],
            'lesdotor without semicolon remains literal' => ['&lesdotor', '&amp;lesdotor'],
            'lesdotor with semicolon' => ['&lesdotor;', '⪃'],
            'lesg without semicolon remains literal' => ['&lesg', '&amp;lesg'],
            'lesg with semicolon' => ['&lesg;', '⋚︀'],
            'lesges without semicolon remains literal' => ['&lesges', '&amp;lesges'],
            'lesges with semicolon' => ['&lesges;', '⪓'],
            'lessapprox without semicolon remains literal' => ['&lessapprox', '&amp;lessapprox'],
            'lessapprox with semicolon' => ['&lessapprox;', '⪅'],
            'lessdot without semicolon remains literal' => ['&lessdot', '&amp;lessdot'],
            'lessdot with semicolon' => ['&lessdot;', '⋖'],
            'lesseqgtr without semicolon remains literal' => ['&lesseqgtr', '&amp;lesseqgtr'],
            'lesseqgtr with semicolon' => ['&lesseqgtr;', '⋚'],
            'lesseqqgtr without semicolon remains literal' => ['&lesseqqgtr', '&amp;lesseqqgtr'],
            'lesseqqgtr with semicolon' => ['&lesseqqgtr;', '⪋'],
            'lessgtr without semicolon remains literal' => ['&lessgtr', '&amp;lessgtr'],
            'lessgtr with semicolon' => ['&lessgtr;', '≶'],
            'lesssim without semicolon remains literal' => ['&lesssim', '&amp;lesssim'],
            'lesssim with semicolon' => ['&lesssim;', '≲'],
            'lfisht without semicolon remains literal' => ['&lfisht', '&amp;lfisht'],
            'lfisht with semicolon' => ['&lfisht;', '⥼'],
            'lfloor without semicolon remains literal' => ['&lfloor', '&amp;lfloor'],
            'lfloor with semicolon' => ['&lfloor;', '⌊'],
            'lfr without semicolon remains literal' => ['&lfr', '&amp;lfr'],
            'lfr with semicolon' => ['&lfr;', '𝔩'],
            'lg without semicolon remains literal' => ['&lg', '&amp;lg'],
            'lg with semicolon' => ['&lg;', '≶'],
            'lgE without semicolon remains literal' => ['&lgE', '&amp;lgE'],
            'lgE with semicolon' => ['&lgE;', '⪑'],
            'lhard without semicolon remains literal' => ['&lhard', '&amp;lhard'],
            'lhard with semicolon' => ['&lhard;', '↽'],
            'lharu without semicolon remains literal' => ['&lharu', '&amp;lharu'],
            'lharu with semicolon' => ['&lharu;', '↼'],
            'lharul without semicolon remains literal' => ['&lharul', '&amp;lharul'],
            'lharul with semicolon' => ['&lharul;', '⥪'],
            'lhblk without semicolon remains literal' => ['&lhblk', '&amp;lhblk'],
            'lhblk with semicolon' => ['&lhblk;', '▄'],
            'ljcy without semicolon remains literal' => ['&ljcy', '&amp;ljcy'],
            'ljcy with semicolon' => ['&ljcy;', 'љ'],
            'll without semicolon remains literal' => ['&ll', '&amp;ll'],
            'll with semicolon' => ['&ll;', '≪'],
            'llarr without semicolon remains literal' => ['&llarr', '&amp;llarr'],
            'llarr with semicolon' => ['&llarr;', '⇇'],
            'llcorner without semicolon remains literal' => ['&llcorner', '&amp;llcorner'],
            'llcorner with semicolon' => ['&llcorner;', '⌞'],
            'llhard without semicolon remains literal' => ['&llhard', '&amp;llhard'],
            'llhard with semicolon' => ['&llhard;', '⥫'],
            'lltri without semicolon remains literal' => ['&lltri', '&amp;lltri'],
            'lltri with semicolon' => ['&lltri;', '◺'],
            'lmidot without semicolon remains literal' => ['&lmidot', '&amp;lmidot'],
            'lmidot with semicolon' => ['&lmidot;', 'ŀ'],
            'lmoust without semicolon remains literal' => ['&lmoust', '&amp;lmoust'],
            'lmoust with semicolon' => ['&lmoust;', '⎰'],
            'lmoustache without semicolon remains literal' => ['&lmoustache', '&amp;lmoustache'],
            'lmoustache with semicolon' => ['&lmoustache;', '⎰'],
            'lnE without semicolon remains literal' => ['&lnE', '&amp;lnE'],
            'lnE with semicolon' => ['&lnE;', '≨'],
            'lnap without semicolon remains literal' => ['&lnap', '&amp;lnap'],
            'lnap with semicolon' => ['&lnap;', '⪉'],
            'lnapprox without semicolon remains literal' => ['&lnapprox', '&amp;lnapprox'],
            'lnapprox with semicolon' => ['&lnapprox;', '⪉'],
            'lne without semicolon remains literal' => ['&lne', '&amp;lne'],
            'lne with semicolon' => ['&lne;', '⪇'],
            'lneq without semicolon remains literal' => ['&lneq', '&amp;lneq'],
            'lneq with semicolon' => ['&lneq;', '⪇'],
            'lneqq without semicolon remains literal' => ['&lneqq', '&amp;lneqq'],
            'lneqq with semicolon' => ['&lneqq;', '≨'],
            'lnsim without semicolon remains literal' => ['&lnsim', '&amp;lnsim'],
            'lnsim with semicolon' => ['&lnsim;', '⋦'],
            'loang without semicolon remains literal' => ['&loang', '&amp;loang'],
            'loang with semicolon' => ['&loang;', '⟬'],
            'loarr without semicolon remains literal' => ['&loarr', '&amp;loarr'],
            'loarr with semicolon' => ['&loarr;', '⇽'],
            'lobrk without semicolon remains literal' => ['&lobrk', '&amp;lobrk'],
            'lobrk with semicolon' => ['&lobrk;', '⟦'],
            'longleftarrow without semicolon remains literal' => ['&longleftarrow', '&amp;longleftarrow'],
            'longleftarrow with semicolon' => ['&longleftarrow;', '⟵'],
            'longleftrightarrow without semicolon remains literal' => ['&longleftrightarrow', '&amp;longleftrightarrow'],
            'longleftrightarrow with semicolon' => ['&longleftrightarrow;', '⟷'],
            'longmapsto without semicolon remains literal' => ['&longmapsto', '&amp;longmapsto'],
            'longmapsto with semicolon' => ['&longmapsto;', '⟼'],
            'longrightarrow without semicolon remains literal' => ['&longrightarrow', '&amp;longrightarrow'],
            'longrightarrow with semicolon' => ['&longrightarrow;', '⟶'],
            'looparrowleft without semicolon remains literal' => ['&looparrowleft', '&amp;looparrowleft'],
            'looparrowleft with semicolon' => ['&looparrowleft;', '↫'],
            'looparrowright without semicolon remains literal' => ['&looparrowright', '&amp;looparrowright'],
            'looparrowright with semicolon' => ['&looparrowright;', '↬'],
            'lopar without semicolon remains literal' => ['&lopar', '&amp;lopar'],
            'lopar with semicolon' => ['&lopar;', '⦅'],
            'lopf without semicolon remains literal' => ['&lopf', '&amp;lopf'],
            'lopf with semicolon' => ['&lopf;', '𝕝'],
            'loplus without semicolon remains literal' => ['&loplus', '&amp;loplus'],
            'loplus with semicolon' => ['&loplus;', '⨭'],
            'lotimes without semicolon remains literal' => ['&lotimes', '&amp;lotimes'],
            'lotimes with semicolon' => ['&lotimes;', '⨴'],
            'lowast without semicolon remains literal' => ['&lowast', '&amp;lowast'],
            'lowast with semicolon' => ['&lowast;', '∗'],
            'lowbar without semicolon remains literal' => ['&lowbar', '&amp;lowbar'],
            'lowbar with semicolon' => ['&lowbar;', '_'],
            'loz without semicolon remains literal' => ['&loz', '&amp;loz'],
            'loz with semicolon' => ['&loz;', '◊'],
            'lozenge without semicolon remains literal' => ['&lozenge', '&amp;lozenge'],
            'lozenge with semicolon' => ['&lozenge;', '◊'],
            'lozf without semicolon remains literal' => ['&lozf', '&amp;lozf'],
            'lozf with semicolon' => ['&lozf;', '⧫'],
            'lpar without semicolon remains literal' => ['&lpar', '&amp;lpar'],
            'lpar with semicolon' => ['&lpar;', '('],
            'lparlt without semicolon remains literal' => ['&lparlt', '&amp;lparlt'],
            'lparlt with semicolon' => ['&lparlt;', '⦓'],
            'lrarr without semicolon remains literal' => ['&lrarr', '&amp;lrarr'],
            'lrarr with semicolon' => ['&lrarr;', '⇆'],
            'lrcorner without semicolon remains literal' => ['&lrcorner', '&amp;lrcorner'],
            'lrcorner with semicolon' => ['&lrcorner;', '⌟'],
            'lrhar without semicolon remains literal' => ['&lrhar', '&amp;lrhar'],
            'lrhar with semicolon' => ['&lrhar;', '⇋'],
            'lrhard without semicolon remains literal' => ['&lrhard', '&amp;lrhard'],
            'lrhard with semicolon' => ['&lrhard;', '⥭'],
            'lrm without semicolon remains literal' => ['&lrm', '&amp;lrm'],
            'lrm with semicolon' => ['&lrm;', "\u{200E}"],
            'lrtri without semicolon remains literal' => ['&lrtri', '&amp;lrtri'],
            'lrtri with semicolon' => ['&lrtri;', '⊿'],
            'lsaquo without semicolon remains literal' => ['&lsaquo', '&amp;lsaquo'],
            'lsaquo with semicolon' => ['&lsaquo;', '‹'],
            'lscr without semicolon remains literal' => ['&lscr', '&amp;lscr'],
            'lscr with semicolon' => ['&lscr;', '𝓁'],
            'lsh without semicolon remains literal' => ['&lsh', '&amp;lsh'],
            'lsh with semicolon' => ['&lsh;', '↰'],
            'lsim without semicolon remains literal' => ['&lsim', '&amp;lsim'],
            'lsim with semicolon' => ['&lsim;', '≲'],
            'lsime without semicolon remains literal' => ['&lsime', '&amp;lsime'],
            'lsime with semicolon' => ['&lsime;', '⪍'],
            'lsimg without semicolon remains literal' => ['&lsimg', '&amp;lsimg'],
            'lsimg with semicolon' => ['&lsimg;', '⪏'],
            'lsqb without semicolon remains literal' => ['&lsqb', '&amp;lsqb'],
            'lsqb with semicolon' => ['&lsqb;', '['],
            'lsquo without semicolon remains literal' => ['&lsquo', '&amp;lsquo'],
            'lsquo with semicolon' => ['&lsquo;', '‘'],
            'lsquor without semicolon remains literal' => ['&lsquor', '&amp;lsquor'],
            'lsquor with semicolon' => ['&lsquor;', '‚'],
            'lstrok without semicolon remains literal' => ['&lstrok', '&amp;lstrok'],
            'lstrok with semicolon' => ['&lstrok;', 'ł'],
            'lt without semicolon' => ['&lt', '&lt;'],
            'lt with semicolon' => ['&lt;', '&lt;'],
            'ltcc with semicolon' => ['&ltcc;', '⪦'],
            'ltcir with semicolon' => ['&ltcir;', '⩹'],
            'ltdot with semicolon' => ['&ltdot;', '⋖'],
            'lthree with semicolon' => ['&lthree;', '⋋'],
            'ltimes with semicolon' => ['&ltimes;', '⋉'],
            'ltlarr with semicolon' => ['&ltlarr;', '⥶'],
            'ltquest with semicolon' => ['&ltquest;', '⩻'],
            'ltrPar with semicolon' => ['&ltrPar;', '⦖'],
            'ltri with semicolon' => ['&ltri;', '◃'],
            'ltrie with semicolon' => ['&ltrie;', '⊴'],
            'ltrif with semicolon' => ['&ltrif;', '◂'],
            'lurdshar without semicolon remains literal' => ['&lurdshar', '&amp;lurdshar'],
            'lurdshar with semicolon' => ['&lurdshar;', '⥊'],
            'luruhar without semicolon remains literal' => ['&luruhar', '&amp;luruhar'],
            'luruhar with semicolon' => ['&luruhar;', '⥦'],
            'lvertneqq without semicolon remains literal' => ['&lvertneqq', '&amp;lvertneqq'],
            'lvertneqq with semicolon' => ['&lvertneqq;', '≨︀'],
            'lvnE without semicolon remains literal' => ['&lvnE', '&amp;lvnE'],
            'lvnE with semicolon' => ['&lvnE;', '≨︀'],
            'mDDot without semicolon remains literal' => ['&mDDot', '&amp;mDDot'],
            'mDDot with semicolon' => ['&mDDot;', '∺'],
            'macr without semicolon' => ['&macr', '¯'],
            'macr with semicolon' => ['&macr;', '¯'],
            'male without semicolon remains literal' => ['&male', '&amp;male'],
            'male with semicolon' => ['&male;', '♂'],
            'malt without semicolon remains literal' => ['&malt', '&amp;malt'],
            'malt with semicolon' => ['&malt;', '✠'],
            'maltese without semicolon remains literal' => ['&maltese', '&amp;maltese'],
            'maltese with semicolon' => ['&maltese;', '✠'],
            'map without semicolon remains literal' => ['&map', '&amp;map'],
            'map with semicolon' => ['&map;', '↦'],
            'mapsto without semicolon remains literal' => ['&mapsto', '&amp;mapsto'],
            'mapsto with semicolon' => ['&mapsto;', '↦'],
            'mapstodown without semicolon remains literal' => ['&mapstodown', '&amp;mapstodown'],
            'mapstodown with semicolon' => ['&mapstodown;', '↧'],
            'mapstoleft without semicolon remains literal' => ['&mapstoleft', '&amp;mapstoleft'],
            'mapstoleft with semicolon' => ['&mapstoleft;', '↤'],
            'mapstoup without semicolon remains literal' => ['&mapstoup', '&amp;mapstoup'],
            'mapstoup with semicolon' => ['&mapstoup;', '↥'],
            'marker without semicolon remains literal' => ['&marker', '&amp;marker'],
            'marker with semicolon' => ['&marker;', '▮'],
            'mcomma without semicolon remains literal' => ['&mcomma', '&amp;mcomma'],
            'mcomma with semicolon' => ['&mcomma;', '⨩'],
            'mcy without semicolon remains literal' => ['&mcy', '&amp;mcy'],
            'mcy with semicolon' => ['&mcy;', 'м'],
            'mdash without semicolon remains literal' => ['&mdash', '&amp;mdash'],
            'mdash with semicolon' => ['&mdash;', '—'],
            'measuredangle without semicolon remains literal' => ['&measuredangle', '&amp;measuredangle'],
            'measuredangle with semicolon' => ['&measuredangle;', '∡'],
            'mfr without semicolon remains literal' => ['&mfr', '&amp;mfr'],
            'mfr with semicolon' => ['&mfr;', '𝔪'],
            'mho without semicolon remains literal' => ['&mho', '&amp;mho'],
            'mho with semicolon' => ['&mho;', '℧'],
            'micro without semicolon' => ['&micro', 'µ'],
            'micro with semicolon' => ['&micro;', 'µ'],
            'mid without semicolon remains literal' => ['&mid', '&amp;mid'],
            'mid with semicolon' => ['&mid;', '∣'],
            'midast without semicolon remains literal' => ['&midast', '&amp;midast'],
            'midast with semicolon' => ['&midast;', '*'],
            'midcir without semicolon remains literal' => ['&midcir', '&amp;midcir'],
            'midcir with semicolon' => ['&midcir;', '⫰'],
            'middot without semicolon' => ['&middot', '·'],
            'middot with semicolon' => ['&middot;', '·'],
            'minus without semicolon remains literal' => ['&minus', '&amp;minus'],
            'minus with semicolon' => ['&minus;', '−'],
            'minusb without semicolon remains literal' => ['&minusb', '&amp;minusb'],
            'minusb with semicolon' => ['&minusb;', '⊟'],
            'minusd without semicolon remains literal' => ['&minusd', '&amp;minusd'],
            'minusd with semicolon' => ['&minusd;', '∸'],
            'minusdu without semicolon remains literal' => ['&minusdu', '&amp;minusdu'],
            'minusdu with semicolon' => ['&minusdu;', '⨪'],
            'mlcp without semicolon remains literal' => ['&mlcp', '&amp;mlcp'],
            'mlcp with semicolon' => ['&mlcp;', '⫛'],
            'mldr without semicolon remains literal' => ['&mldr', '&amp;mldr'],
            'mldr with semicolon' => ['&mldr;', '…'],
            'mnplus without semicolon remains literal' => ['&mnplus', '&amp;mnplus'],
            'mnplus with semicolon' => ['&mnplus;', '∓'],
            'models without semicolon remains literal' => ['&models', '&amp;models'],
            'models with semicolon' => ['&models;', '⊧'],
            'mopf without semicolon remains literal' => ['&mopf', '&amp;mopf'],
            'mopf with semicolon' => ['&mopf;', '𝕞'],
            'mp without semicolon remains literal' => ['&mp', '&amp;mp'],
            'mp with semicolon' => ['&mp;', '∓'],
            'mscr without semicolon remains literal' => ['&mscr', '&amp;mscr'],
            'mscr with semicolon' => ['&mscr;', '𝓂'],
            'mstpos without semicolon remains literal' => ['&mstpos', '&amp;mstpos'],
            'mstpos with semicolon' => ['&mstpos;', '∾'],
            'mu without semicolon remains literal' => ['&mu', '&amp;mu'],
            'mu with semicolon' => ['&mu;', 'μ'],
            'multimap without semicolon remains literal' => ['&multimap', '&amp;multimap'],
            'multimap with semicolon' => ['&multimap;', '⊸'],
            'mumap without semicolon remains literal' => ['&mumap', '&amp;mumap'],
            'mumap with semicolon' => ['&mumap;', '⊸'],
            'nGg without semicolon remains literal' => ['&nGg', '&amp;nGg'],
            'nGg with semicolon' => ['&nGg;', '⋙̸'],
            'nGt without semicolon remains literal' => ['&nGt', '&amp;nGt'],
            'nGt with semicolon' => ['&nGt;', '≫⃒'],
            'nGtv without semicolon remains literal' => ['&nGtv', '&amp;nGtv'],
            'nGtv with semicolon' => ['&nGtv;', '≫̸'],
            'nLeftarrow without semicolon remains literal' => ['&nLeftarrow', '&amp;nLeftarrow'],
            'nLeftarrow with semicolon' => ['&nLeftarrow;', '⇍'],
            'nLeftrightarrow without semicolon remains literal' => ['&nLeftrightarrow', '&amp;nLeftrightarrow'],
            'nLeftrightarrow with semicolon' => ['&nLeftrightarrow;', '⇎'],
            'nLl without semicolon remains literal' => ['&nLl', '&amp;nLl'],
            'nLl with semicolon' => ['&nLl;', '⋘̸'],
            'nLt without semicolon remains literal' => ['&nLt', '&amp;nLt'],
            'nLt with semicolon' => ['&nLt;', '≪⃒'],
            'nLtv without semicolon remains literal' => ['&nLtv', '&amp;nLtv'],
            'nLtv with semicolon' => ['&nLtv;', '≪̸'],
            'nRightarrow without semicolon remains literal' => ['&nRightarrow', '&amp;nRightarrow'],
            'nRightarrow with semicolon' => ['&nRightarrow;', '⇏'],
            'nVDash without semicolon remains literal' => ['&nVDash', '&amp;nVDash'],
            'nVDash with semicolon' => ['&nVDash;', '⊯'],
            'nVdash without semicolon remains literal' => ['&nVdash', '&amp;nVdash'],
            'nVdash with semicolon' => ['&nVdash;', '⊮'],
            'nabla without semicolon remains literal' => ['&nabla', '&amp;nabla'],
            'nabla with semicolon' => ['&nabla;', '∇'],
            'nacute without semicolon remains literal' => ['&nacute', '&amp;nacute'],
            'nacute with semicolon' => ['&nacute;', 'ń'],
            'nang without semicolon remains literal' => ['&nang', '&amp;nang'],
            'nang with semicolon' => ['&nang;', '∠⃒'],
            'nap without semicolon remains literal' => ['&nap', '&amp;nap'],
            'nap with semicolon' => ['&nap;', '≉'],
            'napE without semicolon remains literal' => ['&napE', '&amp;napE'],
            'napE with semicolon' => ['&napE;', '⩰̸'],
            'napid without semicolon remains literal' => ['&napid', '&amp;napid'],
            'napid with semicolon' => ['&napid;', '≋̸'],
            'napos without semicolon remains literal' => ['&napos', '&amp;napos'],
            'napos with semicolon' => ['&napos;', 'ŉ'],
            'napprox without semicolon remains literal' => ['&napprox', '&amp;napprox'],
            'napprox with semicolon' => ['&napprox;', '≉'],
            'natur without semicolon remains literal' => ['&natur', '&amp;natur'],
            'natur with semicolon' => ['&natur;', '♮'],
            'natural without semicolon remains literal' => ['&natural', '&amp;natural'],
            'natural with semicolon' => ['&natural;', '♮'],
            'naturals without semicolon remains literal' => ['&naturals', '&amp;naturals'],
            'naturals with semicolon' => ['&naturals;', 'ℕ'],
            'nbsp without semicolon' => ['&nbsp', '&nbsp;'],
            'nbsp with semicolon' => ['&nbsp;', '&nbsp;'],
            'nbump without semicolon remains literal' => ['&nbump', '&amp;nbump'],
            'nbump with semicolon' => ['&nbump;', '≎̸'],
            'nbumpe without semicolon remains literal' => ['&nbumpe', '&amp;nbumpe'],
            'nbumpe with semicolon' => ['&nbumpe;', '≏̸'],
            'ncap without semicolon remains literal' => ['&ncap', '&amp;ncap'],
            'ncap with semicolon' => ['&ncap;', '⩃'],
            'ncaron without semicolon remains literal' => ['&ncaron', '&amp;ncaron'],
            'ncaron with semicolon' => ['&ncaron;', 'ň'],
            'ncedil without semicolon remains literal' => ['&ncedil', '&amp;ncedil'],
            'ncedil with semicolon' => ['&ncedil;', 'ņ'],
            'ncong without semicolon remains literal' => ['&ncong', '&amp;ncong'],
            'ncong with semicolon' => ['&ncong;', '≇'],
            'ncongdot without semicolon remains literal' => ['&ncongdot', '&amp;ncongdot'],
            'ncongdot with semicolon' => ['&ncongdot;', '⩭̸'],
            'ncup without semicolon remains literal' => ['&ncup', '&amp;ncup'],
            'ncup with semicolon' => ['&ncup;', '⩂'],
            'ncy without semicolon remains literal' => ['&ncy', '&amp;ncy'],
            'ncy with semicolon' => ['&ncy;', 'н'],
            'ndash without semicolon remains literal' => ['&ndash', '&amp;ndash'],
            'ndash with semicolon' => ['&ndash;', '–'],
            'ne without semicolon remains literal' => ['&ne', '&amp;ne'],
            'ne with semicolon' => ['&ne;', '≠'],
            'neArr without semicolon remains literal' => ['&neArr', '&amp;neArr'],
            'neArr with semicolon' => ['&neArr;', '⇗'],
            'nearhk without semicolon remains literal' => ['&nearhk', '&amp;nearhk'],
            'nearhk with semicolon' => ['&nearhk;', '⤤'],
            'nearr without semicolon remains literal' => ['&nearr', '&amp;nearr'],
            'nearr with semicolon' => ['&nearr;', '↗'],
            'nearrow without semicolon remains literal' => ['&nearrow', '&amp;nearrow'],
            'nearrow with semicolon' => ['&nearrow;', '↗'],
            'nedot without semicolon remains literal' => ['&nedot', '&amp;nedot'],
            'nedot with semicolon' => ['&nedot;', '≐̸'],
            'nequiv without semicolon remains literal' => ['&nequiv', '&amp;nequiv'],
            'nequiv with semicolon' => ['&nequiv;', '≢'],
            'nesear without semicolon remains literal' => ['&nesear', '&amp;nesear'],
            'nesear with semicolon' => ['&nesear;', '⤨'],
            'nesim without semicolon remains literal' => ['&nesim', '&amp;nesim'],
            'nesim with semicolon' => ['&nesim;', '≂̸'],
            'nexist without semicolon remains literal' => ['&nexist', '&amp;nexist'],
            'nexist with semicolon' => ['&nexist;', '∄'],
            'nexists without semicolon remains literal' => ['&nexists', '&amp;nexists'],
            'nexists with semicolon' => ['&nexists;', '∄'],
            'nfr without semicolon remains literal' => ['&nfr', '&amp;nfr'],
            'nfr with semicolon' => ['&nfr;', '𝔫'],
            'ngE without semicolon remains literal' => ['&ngE', '&amp;ngE'],
            'ngE with semicolon' => ['&ngE;', '≧̸'],
            'nge without semicolon remains literal' => ['&nge', '&amp;nge'],
            'nge with semicolon' => ['&nge;', '≱'],
            'ngeq without semicolon remains literal' => ['&ngeq', '&amp;ngeq'],
            'ngeq with semicolon' => ['&ngeq;', '≱'],
            'ngeqq without semicolon remains literal' => ['&ngeqq', '&amp;ngeqq'],
            'ngeqq with semicolon' => ['&ngeqq;', '≧̸'],
            'ngeqslant without semicolon remains literal' => ['&ngeqslant', '&amp;ngeqslant'],
            'ngeqslant with semicolon' => ['&ngeqslant;', '⩾̸'],
            'nges without semicolon remains literal' => ['&nges', '&amp;nges'],
            'nges with semicolon' => ['&nges;', '⩾̸'],
            'ngsim without semicolon remains literal' => ['&ngsim', '&amp;ngsim'],
            'ngsim with semicolon' => ['&ngsim;', '≵'],
            'ngt without semicolon remains literal' => ['&ngt', '&amp;ngt'],
            'ngt with semicolon' => ['&ngt;', '≯'],
            'ngtr without semicolon remains literal' => ['&ngtr', '&amp;ngtr'],
            'ngtr with semicolon' => ['&ngtr;', '≯'],
            'nhArr without semicolon remains literal' => ['&nhArr', '&amp;nhArr'],
            'nhArr with semicolon' => ['&nhArr;', '⇎'],
            'nharr without semicolon remains literal' => ['&nharr', '&amp;nharr'],
            'nharr with semicolon' => ['&nharr;', '↮'],
            'nhpar without semicolon remains literal' => ['&nhpar', '&amp;nhpar'],
            'nhpar with semicolon' => ['&nhpar;', '⫲'],
            'ni without semicolon remains literal' => ['&ni', '&amp;ni'],
            'ni with semicolon' => ['&ni;', '∋'],
            'nis without semicolon remains literal' => ['&nis', '&amp;nis'],
            'nis with semicolon' => ['&nis;', '⋼'],
            'nisd without semicolon remains literal' => ['&nisd', '&amp;nisd'],
            'nisd with semicolon' => ['&nisd;', '⋺'],
            'niv without semicolon remains literal' => ['&niv', '&amp;niv'],
            'niv with semicolon' => ['&niv;', '∋'],
            'njcy without semicolon remains literal' => ['&njcy', '&amp;njcy'],
            'njcy with semicolon' => ['&njcy;', 'њ'],
            'nlArr without semicolon remains literal' => ['&nlArr', '&amp;nlArr'],
            'nlArr with semicolon' => ['&nlArr;', '⇍'],
            'nlE without semicolon remains literal' => ['&nlE', '&amp;nlE'],
            'nlE with semicolon' => ['&nlE;', '≦̸'],
            'nlarr without semicolon remains literal' => ['&nlarr', '&amp;nlarr'],
            'nlarr with semicolon' => ['&nlarr;', '↚'],
            'nldr without semicolon remains literal' => ['&nldr', '&amp;nldr'],
            'nldr with semicolon' => ['&nldr;', '‥'],
            'nle without semicolon remains literal' => ['&nle', '&amp;nle'],
            'nle with semicolon' => ['&nle;', '≰'],
            'nleftarrow without semicolon remains literal' => ['&nleftarrow', '&amp;nleftarrow'],
            'nleftarrow with semicolon' => ['&nleftarrow;', '↚'],
            'nleftrightarrow without semicolon remains literal' => ['&nleftrightarrow', '&amp;nleftrightarrow'],
            'nleftrightarrow with semicolon' => ['&nleftrightarrow;', '↮'],
            'nleq without semicolon remains literal' => ['&nleq', '&amp;nleq'],
            'nleq with semicolon' => ['&nleq;', '≰'],
            'nleqq without semicolon remains literal' => ['&nleqq', '&amp;nleqq'],
            'nleqq with semicolon' => ['&nleqq;', '≦̸'],
            'nleqslant without semicolon remains literal' => ['&nleqslant', '&amp;nleqslant'],
            'nleqslant with semicolon' => ['&nleqslant;', '⩽̸'],
            'nles without semicolon remains literal' => ['&nles', '&amp;nles'],
            'nles with semicolon' => ['&nles;', '⩽̸'],
            'nless without semicolon remains literal' => ['&nless', '&amp;nless'],
            'nless with semicolon' => ['&nless;', '≮'],
            'nlsim without semicolon remains literal' => ['&nlsim', '&amp;nlsim'],
            'nlsim with semicolon' => ['&nlsim;', '≴'],
            'nlt without semicolon remains literal' => ['&nlt', '&amp;nlt'],
            'nlt with semicolon' => ['&nlt;', '≮'],
            'nltri without semicolon remains literal' => ['&nltri', '&amp;nltri'],
            'nltri with semicolon' => ['&nltri;', '⋪'],
            'nltrie without semicolon remains literal' => ['&nltrie', '&amp;nltrie'],
            'nltrie with semicolon' => ['&nltrie;', '⋬'],
            'nmid without semicolon remains literal' => ['&nmid', '&amp;nmid'],
            'nmid with semicolon' => ['&nmid;', '∤'],
            'nopf without semicolon remains literal' => ['&nopf', '&amp;nopf'],
            'nopf with semicolon' => ['&nopf;', '𝕟'],
            'not without semicolon' => ['&not', '¬'],
            'not with semicolon' => ['&not;', '¬'],
            'notin with semicolon' => ['&notin;', '∉'],
            'notinE with semicolon' => ['&notinE;', '⋹̸'],
            'notindot with semicolon' => ['&notindot;', '⋵̸'],
            'notinva with semicolon' => ['&notinva;', '∉'],
            'notinvb with semicolon' => ['&notinvb;', '⋷'],
            'notinvc with semicolon' => ['&notinvc;', '⋶'],
            'notni with semicolon' => ['&notni;', '∌'],
            'notniva with semicolon' => ['&notniva;', '∌'],
            'notnivb with semicolon' => ['&notnivb;', '⋾'],
            'notnivc with semicolon' => ['&notnivc;', '⋽'],
            'npar without semicolon remains literal' => ['&npar', '&amp;npar'],
            'npar with semicolon' => ['&npar;', '∦'],
            'nparallel without semicolon remains literal' => ['&nparallel', '&amp;nparallel'],
            'nparallel with semicolon' => ['&nparallel;', '∦'],
            'nparsl without semicolon remains literal' => ['&nparsl', '&amp;nparsl'],
            'nparsl with semicolon' => ['&nparsl;', '⫽⃥'],
            'npart without semicolon remains literal' => ['&npart', '&amp;npart'],
            'npart with semicolon' => ['&npart;', '∂̸'],
            'npolint without semicolon remains literal' => ['&npolint', '&amp;npolint'],
            'npolint with semicolon' => ['&npolint;', '⨔'],
            'npr without semicolon remains literal' => ['&npr', '&amp;npr'],
            'npr with semicolon' => ['&npr;', '⊀'],
            'nprcue without semicolon remains literal' => ['&nprcue', '&amp;nprcue'],
            'nprcue with semicolon' => ['&nprcue;', '⋠'],
            'npre without semicolon remains literal' => ['&npre', '&amp;npre'],
            'npre with semicolon' => ['&npre;', '⪯̸'],
            'nprec without semicolon remains literal' => ['&nprec', '&amp;nprec'],
            'nprec with semicolon' => ['&nprec;', '⊀'],
            'npreceq without semicolon remains literal' => ['&npreceq', '&amp;npreceq'],
            'npreceq with semicolon' => ['&npreceq;', '⪯̸'],
            'nrArr without semicolon remains literal' => ['&nrArr', '&amp;nrArr'],
            'nrArr with semicolon' => ['&nrArr;', '⇏'],
            'nrarr without semicolon remains literal' => ['&nrarr', '&amp;nrarr'],
            'nrarr with semicolon' => ['&nrarr;', '↛'],
            'nrarrc without semicolon remains literal' => ['&nrarrc', '&amp;nrarrc'],
            'nrarrc with semicolon' => ['&nrarrc;', '⤳̸'],
            'nrarrw without semicolon remains literal' => ['&nrarrw', '&amp;nrarrw'],
            'nrarrw with semicolon' => ['&nrarrw;', '↝̸'],
            'nrightarrow without semicolon remains literal' => ['&nrightarrow', '&amp;nrightarrow'],
            'nrightarrow with semicolon' => ['&nrightarrow;', '↛'],
            'nrtri without semicolon remains literal' => ['&nrtri', '&amp;nrtri'],
            'nrtri with semicolon' => ['&nrtri;', '⋫'],
            'nrtrie without semicolon remains literal' => ['&nrtrie', '&amp;nrtrie'],
            'nrtrie with semicolon' => ['&nrtrie;', '⋭'],
            'nsc without semicolon remains literal' => ['&nsc', '&amp;nsc'],
            'nsc with semicolon' => ['&nsc;', '⊁'],
            'nsccue without semicolon remains literal' => ['&nsccue', '&amp;nsccue'],
            'nsccue with semicolon' => ['&nsccue;', '⋡'],
            'nsce without semicolon remains literal' => ['&nsce', '&amp;nsce'],
            'nsce with semicolon' => ['&nsce;', '⪰̸'],
            'nscr without semicolon remains literal' => ['&nscr', '&amp;nscr'],
            'nscr with semicolon' => ['&nscr;', '𝓃'],
            'nshortmid without semicolon remains literal' => ['&nshortmid', '&amp;nshortmid'],
            'nshortmid with semicolon' => ['&nshortmid;', '∤'],
            'nshortparallel without semicolon remains literal' => ['&nshortparallel', '&amp;nshortparallel'],
            'nshortparallel with semicolon' => ['&nshortparallel;', '∦'],
            'nsim without semicolon remains literal' => ['&nsim', '&amp;nsim'],
            'nsim with semicolon' => ['&nsim;', '≁'],
            'nsime without semicolon remains literal' => ['&nsime', '&amp;nsime'],
            'nsime with semicolon' => ['&nsime;', '≄'],
            'nsimeq without semicolon remains literal' => ['&nsimeq', '&amp;nsimeq'],
            'nsimeq with semicolon' => ['&nsimeq;', '≄'],
            'nsmid without semicolon remains literal' => ['&nsmid', '&amp;nsmid'],
            'nsmid with semicolon' => ['&nsmid;', '∤'],
            'nspar without semicolon remains literal' => ['&nspar', '&amp;nspar'],
            'nspar with semicolon' => ['&nspar;', '∦'],
            'nsqsube without semicolon remains literal' => ['&nsqsube', '&amp;nsqsube'],
            'nsqsube with semicolon' => ['&nsqsube;', '⋢'],
            'nsqsupe without semicolon remains literal' => ['&nsqsupe', '&amp;nsqsupe'],
            'nsqsupe with semicolon' => ['&nsqsupe;', '⋣'],
            'nsub without semicolon remains literal' => ['&nsub', '&amp;nsub'],
            'nsub with semicolon' => ['&nsub;', '⊄'],
            'nsubE without semicolon remains literal' => ['&nsubE', '&amp;nsubE'],
            'nsubE with semicolon' => ['&nsubE;', '⫅̸'],
            'nsube without semicolon remains literal' => ['&nsube', '&amp;nsube'],
            'nsube with semicolon' => ['&nsube;', '⊈'],
            'nsubset without semicolon remains literal' => ['&nsubset', '&amp;nsubset'],
            'nsubset with semicolon' => ['&nsubset;', '⊂⃒'],
            'nsubseteq without semicolon remains literal' => ['&nsubseteq', '&amp;nsubseteq'],
            'nsubseteq with semicolon' => ['&nsubseteq;', '⊈'],
            'nsubseteqq without semicolon remains literal' => ['&nsubseteqq', '&amp;nsubseteqq'],
            'nsubseteqq with semicolon' => ['&nsubseteqq;', '⫅̸'],
            'nsucc without semicolon remains literal' => ['&nsucc', '&amp;nsucc'],
            'nsucc with semicolon' => ['&nsucc;', '⊁'],
            'nsucceq without semicolon remains literal' => ['&nsucceq', '&amp;nsucceq'],
            'nsucceq with semicolon' => ['&nsucceq;', '⪰̸'],
            'nsup without semicolon remains literal' => ['&nsup', '&amp;nsup'],
            'nsup with semicolon' => ['&nsup;', '⊅'],
            'nsupE without semicolon remains literal' => ['&nsupE', '&amp;nsupE'],
            'nsupE with semicolon' => ['&nsupE;', '⫆̸'],
            'nsupe without semicolon remains literal' => ['&nsupe', '&amp;nsupe'],
            'nsupe with semicolon' => ['&nsupe;', '⊉'],
            'nsupset without semicolon remains literal' => ['&nsupset', '&amp;nsupset'],
            'nsupset with semicolon' => ['&nsupset;', '⊃⃒'],
            'nsupseteq without semicolon remains literal' => ['&nsupseteq', '&amp;nsupseteq'],
            'nsupseteq with semicolon' => ['&nsupseteq;', '⊉'],
            'nsupseteqq without semicolon remains literal' => ['&nsupseteqq', '&amp;nsupseteqq'],
            'nsupseteqq with semicolon' => ['&nsupseteqq;', '⫆̸'],
            'ntgl without semicolon remains literal' => ['&ntgl', '&amp;ntgl'],
            'ntgl with semicolon' => ['&ntgl;', '≹'],
            'ntilde without semicolon' => ['&ntilde', 'ñ'],
            'ntilde with semicolon' => ['&ntilde;', 'ñ'],
            'ntlg without semicolon remains literal' => ['&ntlg', '&amp;ntlg'],
            'ntlg with semicolon' => ['&ntlg;', '≸'],
            'ntriangleleft without semicolon remains literal' => ['&ntriangleleft', '&amp;ntriangleleft'],
            'ntriangleleft with semicolon' => ['&ntriangleleft;', '⋪'],
            'ntrianglelefteq without semicolon remains literal' => ['&ntrianglelefteq', '&amp;ntrianglelefteq'],
            'ntrianglelefteq with semicolon' => ['&ntrianglelefteq;', '⋬'],
            'ntriangleright without semicolon remains literal' => ['&ntriangleright', '&amp;ntriangleright'],
            'ntriangleright with semicolon' => ['&ntriangleright;', '⋫'],
            'ntrianglerighteq without semicolon remains literal' => ['&ntrianglerighteq', '&amp;ntrianglerighteq'],
            'ntrianglerighteq with semicolon' => ['&ntrianglerighteq;', '⋭'],
            'nu without semicolon remains literal' => ['&nu', '&amp;nu'],
            'nu with semicolon' => ['&nu;', 'ν'],
            'num without semicolon remains literal' => ['&num', '&amp;num'],
            'num with semicolon' => ['&num;', '#'],
            'numero without semicolon remains literal' => ['&numero', '&amp;numero'],
            'numero with semicolon' => ['&numero;', '№'],
            'numsp without semicolon remains literal' => ['&numsp', '&amp;numsp'],
            'numsp with semicolon' => ['&numsp;', ' '],
            'nvDash without semicolon remains literal' => ['&nvDash', '&amp;nvDash'],
            'nvDash with semicolon' => ['&nvDash;', '⊭'],
            'nvHarr without semicolon remains literal' => ['&nvHarr', '&amp;nvHarr'],
            'nvHarr with semicolon' => ['&nvHarr;', '⤄'],
            'nvap without semicolon remains literal' => ['&nvap', '&amp;nvap'],
            'nvap with semicolon' => ['&nvap;', '≍⃒'],
            'nvdash without semicolon remains literal' => ['&nvdash', '&amp;nvdash'],
            'nvdash with semicolon' => ['&nvdash;', '⊬'],
            'nvge without semicolon remains literal' => ['&nvge', '&amp;nvge'],
            'nvge with semicolon' => ['&nvge;', '≥⃒'],
            'nvgt without semicolon remains literal' => ['&nvgt', '&amp;nvgt'],
            'nvgt with semicolon' => ['&nvgt;', '&gt;⃒'],
            'nvinfin without semicolon remains literal' => ['&nvinfin', '&amp;nvinfin'],
            'nvinfin with semicolon' => ['&nvinfin;', '⧞'],
            'nvlArr without semicolon remains literal' => ['&nvlArr', '&amp;nvlArr'],
            'nvlArr with semicolon' => ['&nvlArr;', '⤂'],
            'nvle without semicolon remains literal' => ['&nvle', '&amp;nvle'],
            'nvle with semicolon' => ['&nvle;', '≤⃒'],
            'nvlt without semicolon remains literal' => ['&nvlt', '&amp;nvlt'],
            'nvlt with semicolon' => ['&nvlt;', '&lt;⃒'],
            'nvltrie without semicolon remains literal' => ['&nvltrie', '&amp;nvltrie'],
            'nvltrie with semicolon' => ['&nvltrie;', '⊴⃒'],
            'nvrArr without semicolon remains literal' => ['&nvrArr', '&amp;nvrArr'],
            'nvrArr with semicolon' => ['&nvrArr;', '⤃'],
            'nvrtrie without semicolon remains literal' => ['&nvrtrie', '&amp;nvrtrie'],
            'nvrtrie with semicolon' => ['&nvrtrie;', '⊵⃒'],
            'nvsim without semicolon remains literal' => ['&nvsim', '&amp;nvsim'],
            'nvsim with semicolon' => ['&nvsim;', '∼⃒'],
            'nwArr without semicolon remains literal' => ['&nwArr', '&amp;nwArr'],
            'nwArr with semicolon' => ['&nwArr;', '⇖'],
            'nwarhk without semicolon remains literal' => ['&nwarhk', '&amp;nwarhk'],
            'nwarhk with semicolon' => ['&nwarhk;', '⤣'],
            'nwarr without semicolon remains literal' => ['&nwarr', '&amp;nwarr'],
            'nwarr with semicolon' => ['&nwarr;', '↖'],
            'nwarrow without semicolon remains literal' => ['&nwarrow', '&amp;nwarrow'],
            'nwarrow with semicolon' => ['&nwarrow;', '↖'],
            'nwnear without semicolon remains literal' => ['&nwnear', '&amp;nwnear'],
            'nwnear with semicolon' => ['&nwnear;', '⤧'],
            'oS without semicolon remains literal' => ['&oS', '&amp;oS'],
            'oS with semicolon' => ['&oS;', 'Ⓢ'],
            'oacute without semicolon' => ['&oacute', 'ó'],
            'oacute with semicolon' => ['&oacute;', 'ó'],
            'oast without semicolon remains literal' => ['&oast', '&amp;oast'],
            'oast with semicolon' => ['&oast;', '⊛'],
            'ocir without semicolon remains literal' => ['&ocir', '&amp;ocir'],
            'ocir with semicolon' => ['&ocir;', '⊚'],
            'ocirc without semicolon' => ['&ocirc', 'ô'],
            'ocirc with semicolon' => ['&ocirc;', 'ô'],
            'ocy without semicolon remains literal' => ['&ocy', '&amp;ocy'],
            'ocy with semicolon' => ['&ocy;', 'о'],
            'odash without semicolon remains literal' => ['&odash', '&amp;odash'],
            'odash with semicolon' => ['&odash;', '⊝'],
            'odblac without semicolon remains literal' => ['&odblac', '&amp;odblac'],
            'odblac with semicolon' => ['&odblac;', 'ő'],
            'odiv without semicolon remains literal' => ['&odiv', '&amp;odiv'],
            'odiv with semicolon' => ['&odiv;', '⨸'],
            'odot without semicolon remains literal' => ['&odot', '&amp;odot'],
            'odot with semicolon' => ['&odot;', '⊙'],
            'odsold without semicolon remains literal' => ['&odsold', '&amp;odsold'],
            'odsold with semicolon' => ['&odsold;', '⦼'],
            'oelig without semicolon remains literal' => ['&oelig', '&amp;oelig'],
            'oelig with semicolon' => ['&oelig;', 'œ'],
            'ofcir without semicolon remains literal' => ['&ofcir', '&amp;ofcir'],
            'ofcir with semicolon' => ['&ofcir;', '⦿'],
            'ofr without semicolon remains literal' => ['&ofr', '&amp;ofr'],
            'ofr with semicolon' => ['&ofr;', '𝔬'],
            'ogon without semicolon remains literal' => ['&ogon', '&amp;ogon'],
            'ogon with semicolon' => ['&ogon;', '˛'],
            'ograve without semicolon' => ['&ograve', 'ò'],
            'ograve with semicolon' => ['&ograve;', 'ò'],
            'ogt without semicolon remains literal' => ['&ogt', '&amp;ogt'],
            'ogt with semicolon' => ['&ogt;', '⧁'],
            'ohbar without semicolon remains literal' => ['&ohbar', '&amp;ohbar'],
            'ohbar with semicolon' => ['&ohbar;', '⦵'],
            'ohm without semicolon remains literal' => ['&ohm', '&amp;ohm'],
            'ohm with semicolon' => ['&ohm;', 'Ω'],
            'oint without semicolon remains literal' => ['&oint', '&amp;oint'],
            'oint with semicolon' => ['&oint;', '∮'],
            'olarr without semicolon remains literal' => ['&olarr', '&amp;olarr'],
            'olarr with semicolon' => ['&olarr;', '↺'],
            'olcir without semicolon remains literal' => ['&olcir', '&amp;olcir'],
            'olcir with semicolon' => ['&olcir;', '⦾'],
            'olcross without semicolon remains literal' => ['&olcross', '&amp;olcross'],
            'olcross with semicolon' => ['&olcross;', '⦻'],
            'oline without semicolon remains literal' => ['&oline', '&amp;oline'],
            'oline with semicolon' => ['&oline;', '‾'],
            'olt without semicolon remains literal' => ['&olt', '&amp;olt'],
            'olt with semicolon' => ['&olt;', '⧀'],
            'omacr without semicolon remains literal' => ['&omacr', '&amp;omacr'],
            'omacr with semicolon' => ['&omacr;', 'ō'],
            'omega without semicolon remains literal' => ['&omega', '&amp;omega'],
            'omega with semicolon' => ['&omega;', 'ω'],
            'omicron without semicolon remains literal' => ['&omicron', '&amp;omicron'],
            'omicron with semicolon' => ['&omicron;', 'ο'],
            'omid without semicolon remains literal' => ['&omid', '&amp;omid'],
            'omid with semicolon' => ['&omid;', '⦶'],
            'ominus without semicolon remains literal' => ['&ominus', '&amp;ominus'],
            'ominus with semicolon' => ['&ominus;', '⊖'],
            'oopf without semicolon remains literal' => ['&oopf', '&amp;oopf'],
            'oopf with semicolon' => ['&oopf;', '𝕠'],
            'opar without semicolon remains literal' => ['&opar', '&amp;opar'],
            'opar with semicolon' => ['&opar;', '⦷'],
            'operp without semicolon remains literal' => ['&operp', '&amp;operp'],
            'operp with semicolon' => ['&operp;', '⦹'],
            'oplus without semicolon remains literal' => ['&oplus', '&amp;oplus'],
            'oplus with semicolon' => ['&oplus;', '⊕'],
            'or without semicolon remains literal' => ['&or', '&amp;or'],
            'or with semicolon' => ['&or;', '∨'],
            'orarr without semicolon remains literal' => ['&orarr', '&amp;orarr'],
            'orarr with semicolon' => ['&orarr;', '↻'],
            'ord without semicolon remains literal' => ['&ord', '&amp;ord'],
            'ord with semicolon' => ['&ord;', '⩝'],
            'order without semicolon remains literal' => ['&order', '&amp;order'],
            'order with semicolon' => ['&order;', 'ℴ'],
            'orderof without semicolon remains literal' => ['&orderof', '&amp;orderof'],
            'orderof with semicolon' => ['&orderof;', 'ℴ'],
            'ordf without semicolon' => ['&ordf', 'ª'],
            'ordf with semicolon' => ['&ordf;', 'ª'],
            'ordm without semicolon' => ['&ordm', 'º'],
            'ordm with semicolon' => ['&ordm;', 'º'],
            'origof without semicolon remains literal' => ['&origof', '&amp;origof'],
            'origof with semicolon' => ['&origof;', '⊶'],
            'oror without semicolon remains literal' => ['&oror', '&amp;oror'],
            'oror with semicolon' => ['&oror;', '⩖'],
            'orslope without semicolon remains literal' => ['&orslope', '&amp;orslope'],
            'orslope with semicolon' => ['&orslope;', '⩗'],
            'orv without semicolon remains literal' => ['&orv', '&amp;orv'],
            'orv with semicolon' => ['&orv;', '⩛'],
            'oscr without semicolon remains literal' => ['&oscr', '&amp;oscr'],
            'oscr with semicolon' => ['&oscr;', 'ℴ'],
            'oslash without semicolon' => ['&oslash', 'ø'],
            'oslash with semicolon' => ['&oslash;', 'ø'],
            'osol without semicolon remains literal' => ['&osol', '&amp;osol'],
            'osol with semicolon' => ['&osol;', '⊘'],
            'otilde without semicolon' => ['&otilde', 'õ'],
            'otilde with semicolon' => ['&otilde;', 'õ'],
            'otimes without semicolon remains literal' => ['&otimes', '&amp;otimes'],
            'otimes with semicolon' => ['&otimes;', '⊗'],
            'otimesas without semicolon remains literal' => ['&otimesas', '&amp;otimesas'],
            'otimesas with semicolon' => ['&otimesas;', '⨶'],
            'ouml without semicolon' => ['&ouml', 'ö'],
            'ouml with semicolon' => ['&ouml;', 'ö'],
            'ovbar without semicolon remains literal' => ['&ovbar', '&amp;ovbar'],
            'ovbar with semicolon' => ['&ovbar;', '⌽'],
            'par without semicolon remains literal' => ['&par', '&amp;par'],
            'par with semicolon' => ['&par;', '∥'],
            'para without semicolon' => ['&para', '¶'],
            'para with semicolon' => ['&para;', '¶'],
            'parallel with semicolon' => ['&parallel;', '∥'],
            'parsim without semicolon remains literal' => ['&parsim', '&amp;parsim'],
            'parsim with semicolon' => ['&parsim;', '⫳'],
            'parsl without semicolon remains literal' => ['&parsl', '&amp;parsl'],
            'parsl with semicolon' => ['&parsl;', '⫽'],
            'part without semicolon remains literal' => ['&part', '&amp;part'],
            'part with semicolon' => ['&part;', '∂'],
            'pcy without semicolon remains literal' => ['&pcy', '&amp;pcy'],
            'pcy with semicolon' => ['&pcy;', 'п'],
            'percnt without semicolon remains literal' => ['&percnt', '&amp;percnt'],
            'percnt with semicolon' => ['&percnt;', '%'],
            'period without semicolon remains literal' => ['&period', '&amp;period'],
            'period with semicolon' => ['&period;', '.'],
            'permil without semicolon remains literal' => ['&permil', '&amp;permil'],
            'permil with semicolon' => ['&permil;', '‰'],
            'perp without semicolon remains literal' => ['&perp', '&amp;perp'],
            'perp with semicolon' => ['&perp;', '⊥'],
            'pertenk without semicolon remains literal' => ['&pertenk', '&amp;pertenk'],
            'pertenk with semicolon' => ['&pertenk;', '‱'],
            'pfr without semicolon remains literal' => ['&pfr', '&amp;pfr'],
            'pfr with semicolon' => ['&pfr;', '𝔭'],
            'phi without semicolon remains literal' => ['&phi', '&amp;phi'],
            'phi with semicolon' => ['&phi;', 'φ'],
            'phiv without semicolon remains literal' => ['&phiv', '&amp;phiv'],
            'phiv with semicolon' => ['&phiv;', 'ϕ'],
            'phmmat without semicolon remains literal' => ['&phmmat', '&amp;phmmat'],
            'phmmat with semicolon' => ['&phmmat;', 'ℳ'],
            'phone without semicolon remains literal' => ['&phone', '&amp;phone'],
            'phone with semicolon' => ['&phone;', '☎'],
            'pi without semicolon remains literal' => ['&pi', '&amp;pi'],
            'pi with semicolon' => ['&pi;', 'π'],
            'pitchfork without semicolon remains literal' => ['&pitchfork', '&amp;pitchfork'],
            'pitchfork with semicolon' => ['&pitchfork;', '⋔'],
            'piv without semicolon remains literal' => ['&piv', '&amp;piv'],
            'piv with semicolon' => ['&piv;', 'ϖ'],
            'planck without semicolon remains literal' => ['&planck', '&amp;planck'],
            'planck with semicolon' => ['&planck;', 'ℏ'],
            'planckh without semicolon remains literal' => ['&planckh', '&amp;planckh'],
            'planckh with semicolon' => ['&planckh;', 'ℎ'],
            'plankv without semicolon remains literal' => ['&plankv', '&amp;plankv'],
            'plankv with semicolon' => ['&plankv;', 'ℏ'],
            'plus without semicolon remains literal' => ['&plus', '&amp;plus'],
            'plus with semicolon' => ['&plus;', '+'],
            'plusacir without semicolon remains literal' => ['&plusacir', '&amp;plusacir'],
            'plusacir with semicolon' => ['&plusacir;', '⨣'],
            'plusb without semicolon remains literal' => ['&plusb', '&amp;plusb'],
            'plusb with semicolon' => ['&plusb;', '⊞'],
            'pluscir without semicolon remains literal' => ['&pluscir', '&amp;pluscir'],
            'pluscir with semicolon' => ['&pluscir;', '⨢'],
            'plusdo without semicolon remains literal' => ['&plusdo', '&amp;plusdo'],
            'plusdo with semicolon' => ['&plusdo;', '∔'],
            'plusdu without semicolon remains literal' => ['&plusdu', '&amp;plusdu'],
            'plusdu with semicolon' => ['&plusdu;', '⨥'],
            'pluse without semicolon remains literal' => ['&pluse', '&amp;pluse'],
            'pluse with semicolon' => ['&pluse;', '⩲'],
            'plusmn without semicolon' => ['&plusmn', '±'],
            'plusmn with semicolon' => ['&plusmn;', '±'],
            'plussim without semicolon remains literal' => ['&plussim', '&amp;plussim'],
            'plussim with semicolon' => ['&plussim;', '⨦'],
            'plustwo without semicolon remains literal' => ['&plustwo', '&amp;plustwo'],
            'plustwo with semicolon' => ['&plustwo;', '⨧'],
            'pm without semicolon remains literal' => ['&pm', '&amp;pm'],
            'pm with semicolon' => ['&pm;', '±'],
            'pointint without semicolon remains literal' => ['&pointint', '&amp;pointint'],
            'pointint with semicolon' => ['&pointint;', '⨕'],
            'popf without semicolon remains literal' => ['&popf', '&amp;popf'],
            'popf with semicolon' => ['&popf;', '𝕡'],
            'pound without semicolon' => ['&pound', '£'],
            'pound with semicolon' => ['&pound;', '£'],
            'pr without semicolon remains literal' => ['&pr', '&amp;pr'],
            'pr with semicolon' => ['&pr;', '≺'],
            'prE without semicolon remains literal' => ['&prE', '&amp;prE'],
            'prE with semicolon' => ['&prE;', '⪳'],
            'prap without semicolon remains literal' => ['&prap', '&amp;prap'],
            'prap with semicolon' => ['&prap;', '⪷'],
            'prcue without semicolon remains literal' => ['&prcue', '&amp;prcue'],
            'prcue with semicolon' => ['&prcue;', '≼'],
            'pre without semicolon remains literal' => ['&pre', '&amp;pre'],
            'pre with semicolon' => ['&pre;', '⪯'],
            'prec without semicolon remains literal' => ['&prec', '&amp;prec'],
            'prec with semicolon' => ['&prec;', '≺'],
            'precapprox without semicolon remains literal' => ['&precapprox', '&amp;precapprox'],
            'precapprox with semicolon' => ['&precapprox;', '⪷'],
            'preccurlyeq without semicolon remains literal' => ['&preccurlyeq', '&amp;preccurlyeq'],
            'preccurlyeq with semicolon' => ['&preccurlyeq;', '≼'],
            'preceq without semicolon remains literal' => ['&preceq', '&amp;preceq'],
            'preceq with semicolon' => ['&preceq;', '⪯'],
            'precnapprox without semicolon remains literal' => ['&precnapprox', '&amp;precnapprox'],
            'precnapprox with semicolon' => ['&precnapprox;', '⪹'],
            'precneqq without semicolon remains literal' => ['&precneqq', '&amp;precneqq'],
            'precneqq with semicolon' => ['&precneqq;', '⪵'],
            'precnsim without semicolon remains literal' => ['&precnsim', '&amp;precnsim'],
            'precnsim with semicolon' => ['&precnsim;', '⋨'],
            'precsim without semicolon remains literal' => ['&precsim', '&amp;precsim'],
            'precsim with semicolon' => ['&precsim;', '≾'],
            'prime without semicolon remains literal' => ['&prime', '&amp;prime'],
            'prime with semicolon' => ['&prime;', '′'],
            'primes without semicolon remains literal' => ['&primes', '&amp;primes'],
            'primes with semicolon' => ['&primes;', 'ℙ'],
            'prnE without semicolon remains literal' => ['&prnE', '&amp;prnE'],
            'prnE with semicolon' => ['&prnE;', '⪵'],
            'prnap without semicolon remains literal' => ['&prnap', '&amp;prnap'],
            'prnap with semicolon' => ['&prnap;', '⪹'],
            'prnsim without semicolon remains literal' => ['&prnsim', '&amp;prnsim'],
            'prnsim with semicolon' => ['&prnsim;', '⋨'],
            'prod without semicolon remains literal' => ['&prod', '&amp;prod'],
            'prod with semicolon' => ['&prod;', '∏'],
            'profalar without semicolon remains literal' => ['&profalar', '&amp;profalar'],
            'profalar with semicolon' => ['&profalar;', '⌮'],
            'profline without semicolon remains literal' => ['&profline', '&amp;profline'],
            'profline with semicolon' => ['&profline;', '⌒'],
            'profsurf without semicolon remains literal' => ['&profsurf', '&amp;profsurf'],
            'profsurf with semicolon' => ['&profsurf;', '⌓'],
            'prop without semicolon remains literal' => ['&prop', '&amp;prop'],
            'prop with semicolon' => ['&prop;', '∝'],
            'propto without semicolon remains literal' => ['&propto', '&amp;propto'],
            'propto with semicolon' => ['&propto;', '∝'],
            'prsim without semicolon remains literal' => ['&prsim', '&amp;prsim'],
            'prsim with semicolon' => ['&prsim;', '≾'],
            'prurel without semicolon remains literal' => ['&prurel', '&amp;prurel'],
            'prurel with semicolon' => ['&prurel;', '⊰'],
            'pscr without semicolon remains literal' => ['&pscr', '&amp;pscr'],
            'pscr with semicolon' => ['&pscr;', '𝓅'],
            'psi without semicolon remains literal' => ['&psi', '&amp;psi'],
            'psi with semicolon' => ['&psi;', 'ψ'],
            'puncsp without semicolon remains literal' => ['&puncsp', '&amp;puncsp'],
            'puncsp with semicolon' => ['&puncsp;', ' '],
            'qfr without semicolon remains literal' => ['&qfr', '&amp;qfr'],
            'qfr with semicolon' => ['&qfr;', '𝔮'],
            'qint without semicolon remains literal' => ['&qint', '&amp;qint'],
            'qint with semicolon' => ['&qint;', '⨌'],
            'qopf without semicolon remains literal' => ['&qopf', '&amp;qopf'],
            'qopf with semicolon' => ['&qopf;', '𝕢'],
            'qprime without semicolon remains literal' => ['&qprime', '&amp;qprime'],
            'qprime with semicolon' => ['&qprime;', '⁗'],
            'qscr without semicolon remains literal' => ['&qscr', '&amp;qscr'],
            'qscr with semicolon' => ['&qscr;', '𝓆'],
            'quaternions without semicolon remains literal' => ['&quaternions', '&amp;quaternions'],
            'quaternions with semicolon' => ['&quaternions;', 'ℍ'],
            'quatint without semicolon remains literal' => ['&quatint', '&amp;quatint'],
            'quatint with semicolon' => ['&quatint;', '⨖'],
            'quest without semicolon remains literal' => ['&quest', '&amp;quest'],
            'quest with semicolon' => ['&quest;', '?'],
            'questeq without semicolon remains literal' => ['&questeq', '&amp;questeq'],
            'questeq with semicolon' => ['&questeq;', '≟'],
            'quot without semicolon' => ['&quot', '"'],
            'quot with semicolon' => ['&quot;', '"'],
            'rAarr without semicolon remains literal' => ['&rAarr', '&amp;rAarr'],
            'rAarr with semicolon' => ['&rAarr;', '⇛'],
            'rArr without semicolon remains literal' => ['&rArr', '&amp;rArr'],
            'rArr with semicolon' => ['&rArr;', '⇒'],
            'rAtail without semicolon remains literal' => ['&rAtail', '&amp;rAtail'],
            'rAtail with semicolon' => ['&rAtail;', '⤜'],
            'rBarr without semicolon remains literal' => ['&rBarr', '&amp;rBarr'],
            'rBarr with semicolon' => ['&rBarr;', '⤏'],
            'rHar without semicolon remains literal' => ['&rHar', '&amp;rHar'],
            'rHar with semicolon' => ['&rHar;', '⥤'],
            'race without semicolon remains literal' => ['&race', '&amp;race'],
            'race with semicolon' => ['&race;', '∽̱'],
            'racute without semicolon remains literal' => ['&racute', '&amp;racute'],
            'racute with semicolon' => ['&racute;', 'ŕ'],
            'radic without semicolon remains literal' => ['&radic', '&amp;radic'],
            'radic with semicolon' => ['&radic;', '√'],
            'raemptyv without semicolon remains literal' => ['&raemptyv', '&amp;raemptyv'],
            'raemptyv with semicolon' => ['&raemptyv;', '⦳'],
            'rang without semicolon remains literal' => ['&rang', '&amp;rang'],
            'rang with semicolon' => ['&rang;', '⟩'],
            'rangd without semicolon remains literal' => ['&rangd', '&amp;rangd'],
            'rangd with semicolon' => ['&rangd;', '⦒'],
            'range without semicolon remains literal' => ['&range', '&amp;range'],
            'range with semicolon' => ['&range;', '⦥'],
            'rangle without semicolon remains literal' => ['&rangle', '&amp;rangle'],
            'rangle with semicolon' => ['&rangle;', '⟩'],
            'raquo without semicolon' => ['&raquo', '»'],
            'raquo with semicolon' => ['&raquo;', '»'],
            'rarr without semicolon remains literal' => ['&rarr', '&amp;rarr'],
            'rarr with semicolon' => ['&rarr;', '→'],
            'rarrap without semicolon remains literal' => ['&rarrap', '&amp;rarrap'],
            'rarrap with semicolon' => ['&rarrap;', '⥵'],
            'rarrb without semicolon remains literal' => ['&rarrb', '&amp;rarrb'],
            'rarrb with semicolon' => ['&rarrb;', '⇥'],
            'rarrbfs without semicolon remains literal' => ['&rarrbfs', '&amp;rarrbfs'],
            'rarrbfs with semicolon' => ['&rarrbfs;', '⤠'],
            'rarrc without semicolon remains literal' => ['&rarrc', '&amp;rarrc'],
            'rarrc with semicolon' => ['&rarrc;', '⤳'],
            'rarrfs without semicolon remains literal' => ['&rarrfs', '&amp;rarrfs'],
            'rarrfs with semicolon' => ['&rarrfs;', '⤞'],
            'rarrhk without semicolon remains literal' => ['&rarrhk', '&amp;rarrhk'],
            'rarrhk with semicolon' => ['&rarrhk;', '↪'],
            'rarrlp without semicolon remains literal' => ['&rarrlp', '&amp;rarrlp'],
            'rarrlp with semicolon' => ['&rarrlp;', '↬'],
            'rarrpl without semicolon remains literal' => ['&rarrpl', '&amp;rarrpl'],
            'rarrpl with semicolon' => ['&rarrpl;', '⥅'],
            'rarrsim without semicolon remains literal' => ['&rarrsim', '&amp;rarrsim'],
            'rarrsim with semicolon' => ['&rarrsim;', '⥴'],
            'rarrtl without semicolon remains literal' => ['&rarrtl', '&amp;rarrtl'],
            'rarrtl with semicolon' => ['&rarrtl;', '↣'],
            'rarrw without semicolon remains literal' => ['&rarrw', '&amp;rarrw'],
            'rarrw with semicolon' => ['&rarrw;', '↝'],
            'ratail without semicolon remains literal' => ['&ratail', '&amp;ratail'],
            'ratail with semicolon' => ['&ratail;', '⤚'],
            'ratio without semicolon remains literal' => ['&ratio', '&amp;ratio'],
            'ratio with semicolon' => ['&ratio;', '∶'],
            'rationals without semicolon remains literal' => ['&rationals', '&amp;rationals'],
            'rationals with semicolon' => ['&rationals;', 'ℚ'],
            'rbarr without semicolon remains literal' => ['&rbarr', '&amp;rbarr'],
            'rbarr with semicolon' => ['&rbarr;', '⤍'],
            'rbbrk without semicolon remains literal' => ['&rbbrk', '&amp;rbbrk'],
            'rbbrk with semicolon' => ['&rbbrk;', '❳'],
            'rbrace without semicolon remains literal' => ['&rbrace', '&amp;rbrace'],
            'rbrace with semicolon' => ['&rbrace;', '}'],
            'rbrack without semicolon remains literal' => ['&rbrack', '&amp;rbrack'],
            'rbrack with semicolon' => ['&rbrack;', ']'],
            'rbrke without semicolon remains literal' => ['&rbrke', '&amp;rbrke'],
            'rbrke with semicolon' => ['&rbrke;', '⦌'],
            'rbrksld without semicolon remains literal' => ['&rbrksld', '&amp;rbrksld'],
            'rbrksld with semicolon' => ['&rbrksld;', '⦎'],
            'rbrkslu without semicolon remains literal' => ['&rbrkslu', '&amp;rbrkslu'],
            'rbrkslu with semicolon' => ['&rbrkslu;', '⦐'],
            'rcaron without semicolon remains literal' => ['&rcaron', '&amp;rcaron'],
            'rcaron with semicolon' => ['&rcaron;', 'ř'],
            'rcedil without semicolon remains literal' => ['&rcedil', '&amp;rcedil'],
            'rcedil with semicolon' => ['&rcedil;', 'ŗ'],
            'rceil without semicolon remains literal' => ['&rceil', '&amp;rceil'],
            'rceil with semicolon' => ['&rceil;', '⌉'],
            'rcub without semicolon remains literal' => ['&rcub', '&amp;rcub'],
            'rcub with semicolon' => ['&rcub;', '}'],
            'rcy without semicolon remains literal' => ['&rcy', '&amp;rcy'],
            'rcy with semicolon' => ['&rcy;', 'р'],
            'rdca without semicolon remains literal' => ['&rdca', '&amp;rdca'],
            'rdca with semicolon' => ['&rdca;', '⤷'],
            'rdldhar without semicolon remains literal' => ['&rdldhar', '&amp;rdldhar'],
            'rdldhar with semicolon' => ['&rdldhar;', '⥩'],
            'rdquo without semicolon remains literal' => ['&rdquo', '&amp;rdquo'],
            'rdquo with semicolon' => ['&rdquo;', '”'],
            'rdquor without semicolon remains literal' => ['&rdquor', '&amp;rdquor'],
            'rdquor with semicolon' => ['&rdquor;', '”'],
            'rdsh without semicolon remains literal' => ['&rdsh', '&amp;rdsh'],
            'rdsh with semicolon' => ['&rdsh;', '↳'],
            'real without semicolon remains literal' => ['&real', '&amp;real'],
            'real with semicolon' => ['&real;', 'ℜ'],
            'realine without semicolon remains literal' => ['&realine', '&amp;realine'],
            'realine with semicolon' => ['&realine;', 'ℛ'],
            'realpart without semicolon remains literal' => ['&realpart', '&amp;realpart'],
            'realpart with semicolon' => ['&realpart;', 'ℜ'],
            'reals without semicolon remains literal' => ['&reals', '&amp;reals'],
            'reals with semicolon' => ['&reals;', 'ℝ'],
            'rect without semicolon remains literal' => ['&rect', '&amp;rect'],
            'rect with semicolon' => ['&rect;', '▭'],
            'reg without semicolon' => ['&reg', '®'],
            'reg with semicolon' => ['&reg;', '®'],
            'rfisht without semicolon remains literal' => ['&rfisht', '&amp;rfisht'],
            'rfisht with semicolon' => ['&rfisht;', '⥽'],
            'rfloor without semicolon remains literal' => ['&rfloor', '&amp;rfloor'],
            'rfloor with semicolon' => ['&rfloor;', '⌋'],
            'rfr without semicolon remains literal' => ['&rfr', '&amp;rfr'],
            'rfr with semicolon' => ['&rfr;', '𝔯'],
            'rhard without semicolon remains literal' => ['&rhard', '&amp;rhard'],
            'rhard with semicolon' => ['&rhard;', '⇁'],
            'rharu without semicolon remains literal' => ['&rharu', '&amp;rharu'],
            'rharu with semicolon' => ['&rharu;', '⇀'],
            'rharul without semicolon remains literal' => ['&rharul', '&amp;rharul'],
            'rharul with semicolon' => ['&rharul;', '⥬'],
            'rho without semicolon remains literal' => ['&rho', '&amp;rho'],
            'rho with semicolon' => ['&rho;', 'ρ'],
            'rhov without semicolon remains literal' => ['&rhov', '&amp;rhov'],
            'rhov with semicolon' => ['&rhov;', 'ϱ'],
            'rightarrow without semicolon remains literal' => ['&rightarrow', '&amp;rightarrow'],
            'rightarrow with semicolon' => ['&rightarrow;', '→'],
            'rightarrowtail without semicolon remains literal' => ['&rightarrowtail', '&amp;rightarrowtail'],
            'rightarrowtail with semicolon' => ['&rightarrowtail;', '↣'],
            'rightharpoondown without semicolon remains literal' => ['&rightharpoondown', '&amp;rightharpoondown'],
            'rightharpoondown with semicolon' => ['&rightharpoondown;', '⇁'],
            'rightharpoonup without semicolon remains literal' => ['&rightharpoonup', '&amp;rightharpoonup'],
            'rightharpoonup with semicolon' => ['&rightharpoonup;', '⇀'],
            'rightleftarrows without semicolon remains literal' => ['&rightleftarrows', '&amp;rightleftarrows'],
            'rightleftarrows with semicolon' => ['&rightleftarrows;', '⇄'],
            'rightleftharpoons without semicolon remains literal' => ['&rightleftharpoons', '&amp;rightleftharpoons'],
            'rightleftharpoons with semicolon' => ['&rightleftharpoons;', '⇌'],
            'rightrightarrows without semicolon remains literal' => ['&rightrightarrows', '&amp;rightrightarrows'],
            'rightrightarrows with semicolon' => ['&rightrightarrows;', '⇉'],
            'rightsquigarrow without semicolon remains literal' => ['&rightsquigarrow', '&amp;rightsquigarrow'],
            'rightsquigarrow with semicolon' => ['&rightsquigarrow;', '↝'],
            'rightthreetimes without semicolon remains literal' => ['&rightthreetimes', '&amp;rightthreetimes'],
            'rightthreetimes with semicolon' => ['&rightthreetimes;', '⋌'],
            'ring without semicolon remains literal' => ['&ring', '&amp;ring'],
            'ring with semicolon' => ['&ring;', '˚'],
            'risingdotseq without semicolon remains literal' => ['&risingdotseq', '&amp;risingdotseq'],
            'risingdotseq with semicolon' => ['&risingdotseq;', '≓'],
            'rlarr without semicolon remains literal' => ['&rlarr', '&amp;rlarr'],
            'rlarr with semicolon' => ['&rlarr;', '⇄'],
            'rlhar without semicolon remains literal' => ['&rlhar', '&amp;rlhar'],
            'rlhar with semicolon' => ['&rlhar;', '⇌'],
            'rlm without semicolon remains literal' => ['&rlm', '&amp;rlm'],
            'rlm with semicolon' => ['&rlm;', "\u{200F}"],
            'rmoust without semicolon remains literal' => ['&rmoust', '&amp;rmoust'],
            'rmoust with semicolon' => ['&rmoust;', '⎱'],
            'rmoustache without semicolon remains literal' => ['&rmoustache', '&amp;rmoustache'],
            'rmoustache with semicolon' => ['&rmoustache;', '⎱'],
            'rnmid without semicolon remains literal' => ['&rnmid', '&amp;rnmid'],
            'rnmid with semicolon' => ['&rnmid;', '⫮'],
            'roang without semicolon remains literal' => ['&roang', '&amp;roang'],
            'roang with semicolon' => ['&roang;', '⟭'],
            'roarr without semicolon remains literal' => ['&roarr', '&amp;roarr'],
            'roarr with semicolon' => ['&roarr;', '⇾'],
            'robrk without semicolon remains literal' => ['&robrk', '&amp;robrk'],
            'robrk with semicolon' => ['&robrk;', '⟧'],
            'ropar without semicolon remains literal' => ['&ropar', '&amp;ropar'],
            'ropar with semicolon' => ['&ropar;', '⦆'],
            'ropf without semicolon remains literal' => ['&ropf', '&amp;ropf'],
            'ropf with semicolon' => ['&ropf;', '𝕣'],
            'roplus without semicolon remains literal' => ['&roplus', '&amp;roplus'],
            'roplus with semicolon' => ['&roplus;', '⨮'],
            'rotimes without semicolon remains literal' => ['&rotimes', '&amp;rotimes'],
            'rotimes with semicolon' => ['&rotimes;', '⨵'],
            'rpar without semicolon remains literal' => ['&rpar', '&amp;rpar'],
            'rpar with semicolon' => ['&rpar;', ')'],
            'rpargt without semicolon remains literal' => ['&rpargt', '&amp;rpargt'],
            'rpargt with semicolon' => ['&rpargt;', '⦔'],
            'rppolint without semicolon remains literal' => ['&rppolint', '&amp;rppolint'],
            'rppolint with semicolon' => ['&rppolint;', '⨒'],
            'rrarr without semicolon remains literal' => ['&rrarr', '&amp;rrarr'],
            'rrarr with semicolon' => ['&rrarr;', '⇉'],
            'rsaquo without semicolon remains literal' => ['&rsaquo', '&amp;rsaquo'],
            'rsaquo with semicolon' => ['&rsaquo;', '›'],
            'rscr without semicolon remains literal' => ['&rscr', '&amp;rscr'],
            'rscr with semicolon' => ['&rscr;', '𝓇'],
            'rsh without semicolon remains literal' => ['&rsh', '&amp;rsh'],
            'rsh with semicolon' => ['&rsh;', '↱'],
            'rsqb without semicolon remains literal' => ['&rsqb', '&amp;rsqb'],
            'rsqb with semicolon' => ['&rsqb;', ']'],
            'rsquo without semicolon remains literal' => ['&rsquo', '&amp;rsquo'],
            'rsquo with semicolon' => ['&rsquo;', '’'],
            'rsquor without semicolon remains literal' => ['&rsquor', '&amp;rsquor'],
            'rsquor with semicolon' => ['&rsquor;', '’'],
            'rthree without semicolon remains literal' => ['&rthree', '&amp;rthree'],
            'rthree with semicolon' => ['&rthree;', '⋌'],
            'rtimes without semicolon remains literal' => ['&rtimes', '&amp;rtimes'],
        ] as $label => $case) {
            yield "html5lib namedEntities $label" => $case;
        }
        yield 'char_ref.ton #35 decimal reference without semicolon' => ['&#80', 'P'];
        yield 'char_ref.ton #36 decimal reference before text' => ['&#80v', 'Pv'];
        yield 'char_ref.ton #37 text before decimal reference' => ['a&#80v', 'aPv'];
        yield 'char_ref.ton #38 oversized decimal reference is replaced' => ['a&#654654654654654646546v', 'a�v'];
        foreach ([
            'overflow before EOF 11 digits' => ['&#11111111111', '�'],
            'overflow before EOF 10 digits' => ['&#1111111111', '�'],
            'overflow before EOF 12 digits' => ['&#111111111111', '�'],
            'overflow before text 11 digits' => ['&#11111111111x', '�x'],
            'overflow before text 10 digits' => ['&#1111111111x', '�x'],
            'overflow before text 12 digits' => ['&#111111111111x', '�x'],
            'overflow semicolon 11 digits' => ['&#11111111111;', '�'],
            'overflow semicolon 10 digits' => ['&#1111111111;', '�'],
            'overflow semicolon 12 digits' => ['&#111111111111;', '�'],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'control U+0000 replacement' => ['&#x0000;', '�'],
            'control U+0001' => ['&#x0001;', "\u{0001}"],
            'control U+0002' => ['&#x0002;', "\u{0002}"],
            'control U+0003' => ['&#x0003;', "\u{0003}"],
            'control U+0004' => ['&#x0004;', "\u{0004}"],
            'control U+0005' => ['&#x0005;', "\u{0005}"],
            'control U+0006' => ['&#x0006;', "\u{0006}"],
            'control U+0007' => ['&#x0007;', "\u{0007}"],
            'control U+0008' => ['&#x0008;', "\u{0008}"],
            'control U+000B' => ['&#x000b;', "\u{000B}"],
            'control U+000E' => ['&#x000e;', "\u{000E}"],
            'control U+000F' => ['&#x000f;', "\u{000F}"],
            'control U+0010' => ['&#x0010;', "\u{0010}"],
            'control U+0011' => ['&#x0011;', "\u{0011}"],
            'control U+0012' => ['&#x0012;', "\u{0012}"],
            'control U+0013' => ['&#x0013;', "\u{0013}"],
            'control U+0014' => ['&#x0014;', "\u{0014}"],
            'control U+0015' => ['&#x0015;', "\u{0015}"],
            'control U+0016' => ['&#x0016;', "\u{0016}"],
            'control U+0017' => ['&#x0017;', "\u{0017}"],
            'control U+0018' => ['&#x0018;', "\u{0018}"],
            'control U+0019' => ['&#x0019;', "\u{0019}"],
            'control U+001A' => ['&#x001a;', "\u{001A}"],
            'control U+001B' => ['&#x001b;', "\u{001B}"],
            'control U+001C' => ['&#x001c;', "\u{001C}"],
            'control U+001D' => ['&#x001d;', "\u{001D}"],
            'control U+001E' => ['&#x001e;', "\u{001E}"],
            'control U+001F' => ['&#x001f;', "\u{001F}"],
            'control U+007F' => ['&#x007f;', "\u{007F}"],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'surrogate U+D800 replacement' => ['&#xd800;', '�'],
            'surrogate U+DFFF replacement' => ['&#xdfff;', '�'],
            'noncharacter U+FDD0' => ['&#xfdd0;', "\u{FDD0}"],
            'noncharacter U+FDD1' => ['&#xfdd1;', "\u{FDD1}"],
            'noncharacter U+FDD2' => ['&#xfdd2;', "\u{FDD2}"],
            'noncharacter U+FDD3' => ['&#xfdd3;', "\u{FDD3}"],
            'noncharacter U+FDD4' => ['&#xfdd4;', "\u{FDD4}"],
            'noncharacter U+FDD5' => ['&#xfdd5;', "\u{FDD5}"],
            'noncharacter U+FDD6' => ['&#xfdd6;', "\u{FDD6}"],
            'noncharacter U+FDD7' => ['&#xfdd7;', "\u{FDD7}"],
            'noncharacter U+FDD8' => ['&#xfdd8;', "\u{FDD8}"],
            'noncharacter U+FDD9' => ['&#xfdd9;', "\u{FDD9}"],
            'noncharacter U+FDDA' => ['&#xfdda;', "\u{FDDA}"],
            'noncharacter U+FDDB' => ['&#xfddb;', "\u{FDDB}"],
            'noncharacter U+FDDC' => ['&#xfddc;', "\u{FDDC}"],
            'noncharacter U+FDDD' => ['&#xfddd;', "\u{FDDD}"],
            'noncharacter U+FDDE' => ['&#xfdde;', "\u{FDDE}"],
            'noncharacter U+FDDF' => ['&#xfddf;', "\u{FDDF}"],
            'noncharacter U+FDE0' => ['&#xfde0;', "\u{FDE0}"],
            'noncharacter U+FDE1' => ['&#xfde1;', "\u{FDE1}"],
            'noncharacter U+FDE2' => ['&#xfde2;', "\u{FDE2}"],
            'noncharacter U+FDE3' => ['&#xfde3;', "\u{FDE3}"],
            'noncharacter U+FDE4' => ['&#xfde4;', "\u{FDE4}"],
            'noncharacter U+FDE5' => ['&#xfde5;', "\u{FDE5}"],
            'noncharacter U+FDE6' => ['&#xfde6;', "\u{FDE6}"],
            'noncharacter U+FDE7' => ['&#xfde7;', "\u{FDE7}"],
            'noncharacter U+FDE8' => ['&#xfde8;', "\u{FDE8}"],
            'noncharacter U+FDE9' => ['&#xfde9;', "\u{FDE9}"],
            'noncharacter U+FDEA' => ['&#xfdea;', "\u{FDEA}"],
            'noncharacter U+FDEB' => ['&#xfdeb;', "\u{FDEB}"],
            'noncharacter U+FDEC' => ['&#xfdec;', "\u{FDEC}"],
            'noncharacter U+FDED' => ['&#xfded;', "\u{FDED}"],
            'noncharacter U+FDEE' => ['&#xfdee;', "\u{FDEE}"],
            'noncharacter U+FDEF' => ['&#xfdef;', "\u{FDEF}"],
            'noncharacter U+FFFE' => ['&#xfffe;', "\u{FFFE}"],
            'noncharacter U+FFFF' => ['&#xffff;', "\u{FFFF}"],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'supplementary noncharacter U+1FFFE' => ['&#x1fffe;', "\u{1FFFE}"],
            'supplementary noncharacter U+1FFFF' => ['&#x1ffff;', "\u{1FFFF}"],
            'supplementary noncharacter U+2FFFE' => ['&#x2fffe;', "\u{2FFFE}"],
            'supplementary noncharacter U+2FFFF' => ['&#x2ffff;', "\u{2FFFF}"],
            'supplementary noncharacter U+3FFFE' => ['&#x3fffe;', "\u{3FFFE}"],
            'supplementary noncharacter U+3FFFF' => ['&#x3ffff;', "\u{3FFFF}"],
            'supplementary noncharacter U+4FFFE' => ['&#x4fffe;', "\u{4FFFE}"],
            'supplementary noncharacter U+4FFFF' => ['&#x4ffff;', "\u{4FFFF}"],
            'supplementary noncharacter U+5FFFE' => ['&#x5fffe;', "\u{5FFFE}"],
            'supplementary noncharacter U+5FFFF' => ['&#x5ffff;', "\u{5FFFF}"],
            'supplementary noncharacter U+6FFFE' => ['&#x6fffe;', "\u{6FFFE}"],
            'supplementary noncharacter U+6FFFF' => ['&#x6ffff;', "\u{6FFFF}"],
            'supplementary noncharacter U+7FFFE' => ['&#x7fffe;', "\u{7FFFE}"],
            'supplementary noncharacter U+7FFFF' => ['&#x7ffff;', "\u{7FFFF}"],
            'supplementary noncharacter U+8FFFE' => ['&#x8fffe;', "\u{8FFFE}"],
            'supplementary noncharacter U+8FFFF' => ['&#x8ffff;', "\u{8FFFF}"],
            'supplementary noncharacter U+9FFFE' => ['&#x9fffe;', "\u{9FFFE}"],
            'supplementary noncharacter U+9FFFF' => ['&#x9ffff;', "\u{9FFFF}"],
            'supplementary noncharacter U+AFFFE' => ['&#xafffe;', "\u{AFFFE}"],
            'supplementary noncharacter U+AFFFF' => ['&#xaffff;', "\u{AFFFF}"],
            'supplementary noncharacter U+BFFFE' => ['&#xbfffe;', "\u{BFFFE}"],
            'supplementary noncharacter U+BFFFF' => ['&#xbffff;', "\u{BFFFF}"],
            'supplementary noncharacter U+CFFFE' => ['&#xcfffe;', "\u{CFFFE}"],
            'supplementary noncharacter U+CFFFF' => ['&#xcffff;', "\u{CFFFF}"],
            'supplementary noncharacter U+DFFFE' => ['&#xdfffe;', "\u{DFFFE}"],
            'supplementary noncharacter U+DFFFF' => ['&#xdffff;', "\u{DFFFF}"],
            'supplementary noncharacter U+EFFFE' => ['&#xefffe;', "\u{EFFFE}"],
            'supplementary noncharacter U+EFFFF' => ['&#xeffff;', "\u{EFFFF}"],
            'supplementary noncharacter U+FFFFE' => ['&#xffffe;', "\u{FFFFE}"],
            'supplementary noncharacter U+FFFFF' => ['&#xfffff;', "\u{FFFFF}"],
            'supplementary noncharacter U+10FFFE' => ['&#x10fffe;', "\u{10FFFE}"],
            'supplementary noncharacter U+10FFFF' => ['&#x10ffff;', "\u{10FFFF}"],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'valid ASCII tab' => ['&#x0009;', "\t"],
            'valid ASCII newline' => ['&#x000a;', "\n"],
            'valid ASCII space' => ['&#x0020;', ' '],
            'valid ASCII exclamation mark' => ['&#x0021;', '!'],
            'valid ASCII double quote' => ['&#x0022;', '"'],
            'valid ASCII number sign' => ['&#x0023;', '#'],
            'valid ASCII dollar sign' => ['&#x0024;', '$'],
            'valid ASCII percent sign' => ['&#x0025;', '%'],
            'valid ASCII ampersand' => ['&#x0026;', '&amp;'],
            'valid ASCII apostrophe' => ['&#x0027;', "'"],
            'valid ASCII left parenthesis' => ['&#x0028;', '('],
            'valid ASCII right parenthesis' => ['&#x0029;', ')'],
            'valid ASCII asterisk' => ['&#x002a;', '*'],
            'valid ASCII plus sign' => ['&#x002b;', '+'],
            'valid ASCII comma' => ['&#x002c;', ','],
            'valid ASCII hyphen-minus' => ['&#x002d;', '-'],
            'valid ASCII full stop' => ['&#x002e;', '.'],
            'valid ASCII solidus' => ['&#x002f;', '/'],
            'valid ASCII digit 0' => ['&#x0030;', '0'],
            'valid ASCII digit 1' => ['&#x0031;', '1'],
            'valid ASCII digit 2' => ['&#x0032;', '2'],
            'valid ASCII digit 3' => ['&#x0033;', '3'],
            'valid ASCII digit 4' => ['&#x0034;', '4'],
            'valid ASCII digit 5' => ['&#x0035;', '5'],
            'valid ASCII digit 6' => ['&#x0036;', '6'],
            'valid ASCII digit 7' => ['&#x0037;', '7'],
            'valid ASCII digit 8' => ['&#x0038;', '8'],
            'valid ASCII digit 9' => ['&#x0039;', '9'],
            'valid ASCII colon' => ['&#x003a;', ':'],
            'valid ASCII semicolon' => ['&#x003b;', ';'],
            'valid ASCII less-than sign' => ['&#x003c;', '&lt;'],
            'valid ASCII equals sign' => ['&#x003d;', '='],
            'valid ASCII greater-than sign' => ['&#x003e;', '&gt;'],
            'valid ASCII question mark' => ['&#x003f;', '?'],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'valid ASCII at sign' => ['&#x0040;', '@'],
            'valid ASCII uppercase A' => ['&#x0041;', 'A'],
            'valid ASCII uppercase B' => ['&#x0042;', 'B'],
            'valid ASCII uppercase C' => ['&#x0043;', 'C'],
            'valid ASCII uppercase D' => ['&#x0044;', 'D'],
            'valid ASCII uppercase E' => ['&#x0045;', 'E'],
            'valid ASCII uppercase F' => ['&#x0046;', 'F'],
            'valid ASCII uppercase G' => ['&#x0047;', 'G'],
            'valid ASCII uppercase H' => ['&#x0048;', 'H'],
            'valid ASCII uppercase I' => ['&#x0049;', 'I'],
            'valid ASCII uppercase J' => ['&#x004a;', 'J'],
            'valid ASCII uppercase K' => ['&#x004b;', 'K'],
            'valid ASCII uppercase L' => ['&#x004c;', 'L'],
            'valid ASCII uppercase M' => ['&#x004d;', 'M'],
            'valid ASCII uppercase N' => ['&#x004e;', 'N'],
            'valid ASCII uppercase O' => ['&#x004f;', 'O'],
            'valid ASCII uppercase P' => ['&#x0050;', 'P'],
            'valid ASCII uppercase Q' => ['&#x0051;', 'Q'],
            'valid ASCII uppercase R' => ['&#x0052;', 'R'],
            'valid ASCII uppercase S' => ['&#x0053;', 'S'],
            'valid ASCII uppercase T' => ['&#x0054;', 'T'],
            'valid ASCII uppercase U' => ['&#x0055;', 'U'],
            'valid ASCII uppercase V' => ['&#x0056;', 'V'],
            'valid ASCII uppercase W' => ['&#x0057;', 'W'],
            'valid ASCII uppercase X' => ['&#x0058;', 'X'],
            'valid ASCII uppercase Y' => ['&#x0059;', 'Y'],
            'valid ASCII uppercase Z' => ['&#x005a;', 'Z'],
            'valid ASCII left square bracket' => ['&#x005b;', '['],
            'valid ASCII reverse solidus' => ['&#x005c;', '\\'],
            'valid ASCII right square bracket' => ['&#x005d;', ']'],
            'valid ASCII circumflex accent' => ['&#x005e;', '^'],
            'valid ASCII low line' => ['&#x005f;', '_'],
            'valid ASCII grave accent' => ['&#x0060;', '`'],
            'valid ASCII lowercase a' => ['&#x0061;', 'a'],
            'valid ASCII lowercase b' => ['&#x0062;', 'b'],
            'valid ASCII lowercase c' => ['&#x0063;', 'c'],
            'valid ASCII lowercase d' => ['&#x0064;', 'd'],
            'valid ASCII lowercase e' => ['&#x0065;', 'e'],
            'valid ASCII lowercase f' => ['&#x0066;', 'f'],
            'valid ASCII lowercase g' => ['&#x0067;', 'g'],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'valid ASCII lowercase h' => ['&#x0068;', 'h'],
            'valid ASCII lowercase i' => ['&#x0069;', 'i'],
            'valid ASCII lowercase j' => ['&#x006a;', 'j'],
            'valid ASCII lowercase k' => ['&#x006b;', 'k'],
            'valid ASCII lowercase l' => ['&#x006c;', 'l'],
            'valid ASCII lowercase m' => ['&#x006d;', 'm'],
            'valid ASCII lowercase n' => ['&#x006e;', 'n'],
            'valid ASCII lowercase o' => ['&#x006f;', 'o'],
            'valid ASCII lowercase p' => ['&#x0070;', 'p'],
            'valid ASCII lowercase q' => ['&#x0071;', 'q'],
            'valid ASCII lowercase r' => ['&#x0072;', 'r'],
            'valid ASCII lowercase s' => ['&#x0073;', 's'],
            'valid ASCII lowercase t' => ['&#x0074;', 't'],
            'valid ASCII lowercase u' => ['&#x0075;', 'u'],
            'valid ASCII lowercase v' => ['&#x0076;', 'v'],
            'valid ASCII lowercase w' => ['&#x0077;', 'w'],
            'valid ASCII lowercase x' => ['&#x0078;', 'x'],
            'valid ASCII lowercase y' => ['&#x0079;', 'y'],
            'valid ASCII lowercase z' => ['&#x007a;', 'z'],
            'valid ASCII left curly bracket' => ['&#x007b;', '{'],
            'valid ASCII vertical line' => ['&#x007c;', '|'],
            'valid ASCII right curly bracket' => ['&#x007d;', '}'],
            'valid ASCII tilde' => ['&#x007e;', '~'],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'valid Latin-1 U+00A0 no-break space' => ['&#x00a0;', '&nbsp;'],
            'valid Latin-1 U+00A1' => ['&#x00a1;', "\u{00A1}"],
            'valid Latin-1 U+00A2' => ['&#x00a2;', "\u{00A2}"],
            'valid Latin-1 U+00A3' => ['&#x00a3;', "\u{00A3}"],
            'valid Latin-1 U+00A4' => ['&#x00a4;', "\u{00A4}"],
            'valid Latin-1 U+00A5' => ['&#x00a5;', "\u{00A5}"],
            'valid Latin-1 U+00A6' => ['&#x00a6;', "\u{00A6}"],
            'valid Latin-1 U+00A7' => ['&#x00a7;', "\u{00A7}"],
            'valid Latin-1 U+00A8' => ['&#x00a8;', "\u{00A8}"],
            'valid Latin-1 U+00A9' => ['&#x00a9;', "\u{00A9}"],
            'valid Latin-1 U+00AA' => ['&#x00aa;', "\u{00AA}"],
            'valid Latin-1 U+00AB' => ['&#x00ab;', "\u{00AB}"],
            'valid Latin-1 U+00AC' => ['&#x00ac;', "\u{00AC}"],
            'valid Latin-1 U+00AD' => ['&#x00ad;', "\u{00AD}"],
            'valid Latin-1 U+00AE' => ['&#x00ae;', "\u{00AE}"],
            'valid Latin-1 U+00AF' => ['&#x00af;', "\u{00AF}"],
            'valid Latin-1 U+00B0' => ['&#x00b0;', "\u{00B0}"],
            'valid Latin-1 U+00B1' => ['&#x00b1;', "\u{00B1}"],
            'valid Latin-1 U+00B2' => ['&#x00b2;', "\u{00B2}"],
            'valid Latin-1 U+00B3' => ['&#x00b3;', "\u{00B3}"],
            'valid Latin-1 U+00B4' => ['&#x00b4;', "\u{00B4}"],
            'valid Latin-1 U+00B5' => ['&#x00b5;', "\u{00B5}"],
            'valid Latin-1 U+00B6' => ['&#x00b6;', "\u{00B6}"],
            'valid Latin-1 U+00B7' => ['&#x00b7;', "\u{00B7}"],
            'valid Latin-1 U+00B8' => ['&#x00b8;', "\u{00B8}"],
            'valid Latin-1 U+00B9' => ['&#x00b9;', "\u{00B9}"],
            'valid Latin-1 U+00BA' => ['&#x00ba;', "\u{00BA}"],
            'valid Latin-1 U+00BB' => ['&#x00bb;', "\u{00BB}"],
            'valid Latin-1 U+00BC' => ['&#x00bc;', "\u{00BC}"],
            'valid Latin-1 U+00BD' => ['&#x00bd;', "\u{00BD}"],
            'valid Latin-1 U+00BE' => ['&#x00be;', "\u{00BE}"],
            'valid Latin-1 U+00BF' => ['&#x00bf;', "\u{00BF}"],
            'valid Latin-1 U+00C0' => ['&#x00c0;', "\u{00C0}"],
            'valid Latin-1 U+00C1' => ['&#x00c1;', "\u{00C1}"],
            'valid Latin-1 U+00C2' => ['&#x00c2;', "\u{00C2}"],
            'valid Latin-1 U+00C3' => ['&#x00c3;', "\u{00C3}"],
            'valid Latin-1 U+00C4' => ['&#x00c4;', "\u{00C4}"],
            'valid Latin-1 U+00C5' => ['&#x00c5;', "\u{00C5}"],
            'valid Latin-1 U+00C6' => ['&#x00c6;', "\u{00C6}"],
            'valid Latin-1 U+00C7' => ['&#x00c7;', "\u{00C7}"],
            'valid Latin-1 U+00C8' => ['&#x00c8;', "\u{00C8}"],
            'valid Latin-1 U+00C9' => ['&#x00c9;', "\u{00C9}"],
            'valid Latin-1 U+00CA' => ['&#x00ca;', "\u{00CA}"],
            'valid Latin-1 U+00CB' => ['&#x00cb;', "\u{00CB}"],
            'valid Latin-1 U+00CC' => ['&#x00cc;', "\u{00CC}"],
            'valid Latin-1 U+00CD' => ['&#x00cd;', "\u{00CD}"],
            'valid Latin-1 U+00CE' => ['&#x00ce;', "\u{00CE}"],
            'valid Latin-1 U+00CF' => ['&#x00cf;', "\u{00CF}"],
            'valid Latin-1 U+00D0' => ['&#x00d0;', "\u{00D0}"],
            'valid Latin-1 U+00D1' => ['&#x00d1;', "\u{00D1}"],
            'valid Latin-1 U+00D2' => ['&#x00d2;', "\u{00D2}"],
            'valid Latin-1 U+00D3' => ['&#x00d3;', "\u{00D3}"],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'valid Latin-1 U+00D4' => ['&#x00d4;', "\u{00D4}"],
            'valid Latin-1 U+00D5' => ['&#x00d5;', "\u{00D5}"],
            'valid Latin-1 U+00D6' => ['&#x00d6;', "\u{00D6}"],
            'valid Latin-1 U+00D7' => ['&#x00d7;', "\u{00D7}"],
            'valid Latin-1 U+00D8' => ['&#x00d8;', "\u{00D8}"],
            'valid Latin-1 U+00D9' => ['&#x00d9;', "\u{00D9}"],
            'valid Latin-1 U+00DA' => ['&#x00da;', "\u{00DA}"],
            'valid Latin-1 U+00DB' => ['&#x00db;', "\u{00DB}"],
            'valid Latin-1 U+00DC' => ['&#x00dc;', "\u{00DC}"],
            'valid Latin-1 U+00DD' => ['&#x00dd;', "\u{00DD}"],
            'valid Latin-1 U+00DE' => ['&#x00de;', "\u{00DE}"],
            'valid Latin-1 U+00DF' => ['&#x00df;', "\u{00DF}"],
            'valid Latin-1 U+00E0' => ['&#x00e0;', "\u{00E0}"],
            'valid Latin-1 U+00E1' => ['&#x00e1;', "\u{00E1}"],
            'valid Latin-1 U+00E2' => ['&#x00e2;', "\u{00E2}"],
            'valid Latin-1 U+00E3' => ['&#x00e3;', "\u{00E3}"],
            'valid Latin-1 U+00E4' => ['&#x00e4;', "\u{00E4}"],
            'valid Latin-1 U+00E5' => ['&#x00e5;', "\u{00E5}"],
            'valid Latin-1 U+00E6' => ['&#x00e6;', "\u{00E6}"],
            'valid Latin-1 U+00E7' => ['&#x00e7;', "\u{00E7}"],
            'valid Latin-1 U+00E8' => ['&#x00e8;', "\u{00E8}"],
            'valid Latin-1 U+00E9' => ['&#x00e9;', "\u{00E9}"],
            'valid Latin-1 U+00EA' => ['&#x00ea;', "\u{00EA}"],
            'valid Latin-1 U+00EB' => ['&#x00eb;', "\u{00EB}"],
            'valid Latin-1 U+00EC' => ['&#x00ec;', "\u{00EC}"],
            'valid Latin-1 U+00ED' => ['&#x00ed;', "\u{00ED}"],
            'valid Latin-1 U+00EE' => ['&#x00ee;', "\u{00EE}"],
            'valid Latin-1 U+00EF' => ['&#x00ef;', "\u{00EF}"],
            'valid Latin-1 U+00F0' => ['&#x00f0;', "\u{00F0}"],
            'valid Latin-1 U+00F1' => ['&#x00f1;', "\u{00F1}"],
            'valid Latin-1 U+00F2' => ['&#x00f2;', "\u{00F2}"],
            'valid Latin-1 U+00F3' => ['&#x00f3;', "\u{00F3}"],
            'valid Latin-1 U+00F4' => ['&#x00f4;', "\u{00F4}"],
            'valid Latin-1 U+00F5' => ['&#x00f5;', "\u{00F5}"],
            'valid Latin-1 U+00F6' => ['&#x00f6;', "\u{00F6}"],
            'valid Latin-1 U+00F7' => ['&#x00f7;', "\u{00F7}"],
            'valid Latin-1 U+00F8' => ['&#x00f8;', "\u{00F8}"],
            'valid Latin-1 U+00F9' => ['&#x00f9;', "\u{00F9}"],
            'valid Latin-1 U+00FA' => ['&#x00fa;', "\u{00FA}"],
            'valid Latin-1 U+00FB' => ['&#x00fb;', "\u{00FB}"],
            'valid Latin-1 U+00FC' => ['&#x00fc;', "\u{00FC}"],
            'valid Latin-1 U+00FD' => ['&#x00fd;', "\u{00FD}"],
            'valid Latin-1 U+00FE' => ['&#x00fe;', "\u{00FE}"],
            'valid Latin-1 U+00FF' => ['&#x00ff;', "\u{00FF}"],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        foreach ([
            'valid Unicode boundary U+D7FF' => ['&#xd7ff;', "\u{D7FF}"],
            'valid Unicode boundary U+E000' => ['&#xe000;', "\u{E000}"],
            'valid Unicode boundary U+FDCF' => ['&#xfdcf;', "\u{FDCF}"],
            'valid Unicode boundary U+FDF0' => ['&#xfdf0;', "\u{FDF0}"],
            'valid Unicode boundary U+FFFD' => ['&#xfffd;', "\u{FFFD}"],
            'valid Unicode boundary U+10000' => ['&#x10000;', "\u{10000}"],
            'valid Unicode boundary U+1FFFD' => ['&#x1fffd;', "\u{1FFFD}"],
            'valid Unicode boundary U+20000' => ['&#x20000;', "\u{20000}"],
            'valid Unicode boundary U+2FFFD' => ['&#x2fffd;', "\u{2FFFD}"],
            'valid Unicode boundary U+30000' => ['&#x30000;', "\u{30000}"],
            'valid Unicode boundary U+3FFFD' => ['&#x3fffd;', "\u{3FFFD}"],
            'valid Unicode boundary U+40000' => ['&#x40000;', "\u{40000}"],
            'valid Unicode boundary U+4FFFD' => ['&#x4fffd;', "\u{4FFFD}"],
            'valid Unicode boundary U+50000' => ['&#x50000;', "\u{50000}"],
            'valid Unicode boundary U+5FFFD' => ['&#x5fffd;', "\u{5FFFD}"],
            'valid Unicode boundary U+60000' => ['&#x60000;', "\u{60000}"],
            'valid Unicode boundary U+6FFFD' => ['&#x6fffd;', "\u{6FFFD}"],
            'valid Unicode boundary U+70000' => ['&#x70000;', "\u{70000}"],
            'valid Unicode boundary U+7FFFD' => ['&#x7fffd;', "\u{7FFFD}"],
            'valid Unicode boundary U+80000' => ['&#x80000;', "\u{80000}"],
            'valid Unicode boundary U+8FFFD' => ['&#x8fffd;', "\u{8FFFD}"],
            'valid Unicode boundary U+90000' => ['&#x90000;', "\u{90000}"],
            'valid Unicode boundary U+9FFFD' => ['&#x9fffd;', "\u{9FFFD}"],
            'valid Unicode boundary U+A0000' => ['&#xa0000;', "\u{A0000}"],
            'valid Unicode boundary U+AFFFD' => ['&#xafffd;', "\u{AFFFD}"],
            'valid Unicode boundary U+B0000' => ['&#xb0000;', "\u{B0000}"],
            'valid Unicode boundary U+BFFFD' => ['&#xbfffd;', "\u{BFFFD}"],
            'valid Unicode boundary U+C0000' => ['&#xc0000;', "\u{C0000}"],
            'valid Unicode boundary U+CFFFD' => ['&#xcfffd;', "\u{CFFFD}"],
            'valid Unicode boundary U+D0000' => ['&#xd0000;', "\u{D0000}"],
            'valid Unicode boundary U+DFFFD' => ['&#xdfffd;', "\u{DFFFD}"],
            'valid Unicode boundary U+E0000' => ['&#xe0000;', "\u{E0000}"],
            'valid Unicode boundary U+EFFFD' => ['&#xefffd;', "\u{EFFFD}"],
            'valid Unicode boundary U+F0000' => ['&#xf0000;', "\u{F0000}"],
            'valid Unicode boundary U+FFFFD' => ['&#xffffd;', "\u{FFFFD}"],
            'valid Unicode boundary U+100000' => ['&#x100000;', "\u{100000}"],
            'valid Unicode boundary U+10FFFD' => ['&#x10fffd;', "\u{10FFFD}"],
        ] as $label => $case) {
            yield "html5lib numericEntities $label" => $case;
        }
        yield 'char_ref.ton #39 decimal Cyrillic reference' => ['&#1024', 'Ѐ'];
        yield 'char_ref.ton #40 invalid decimal reference remains literal' => ['&#j', '&amp;#j'];
        yield 'char_ref.ton #41 invalid decimal reference before text remains literal' => ['&#jgf', '&amp;#jgf'];
        yield 'char_ref.ton #42 hexadecimal Windows-1252 replacement' => ['&#x80', '€'];
        yield 'char_ref.ton #43 adjacent hexadecimal references' => ['&#x80&#x80', '€€'];
        yield 'char_ref.ton #44 hexadecimal references around text' => ['&#x80v&#x80', '€v€'];
        yield 'char_ref.ton #45 hexadecimal references separated by space' => ['&#x80 &#x80', '€ €'];
        yield 'char_ref.ton #46 hexadecimal BMP reference plus Windows-1252 replacement' => ['&#xAAAD &#x80 hm', 'ꪭ € hm'];
        yield 'char_ref.ton #47 hexadecimal Windows-1252 0x9F replacement' => ['&#x9F', 'Ÿ'];
        yield 'char_ref.ton #48 out-of-range hexadecimal reference is replaced' => ['&#x11FFFF', '�'];
        yield 'char_ref.ton #49 NUL hexadecimal reference is replaced' => ['&#x00', '�'];
        yield 'char_ref.ton #50 adjacent NUL hexadecimal references are replaced' => ['&#x00&#x00', '��'];
        yield 'char_ref.ton #51 high surrogate hexadecimal reference is replaced' => ['&#xD800', '�'];
        yield 'char_ref.ton #52 high surrogate range hexadecimal reference is replaced' => ['&#xDAFF', '�'];
        yield 'char_ref.ton #53 low surrogate hexadecimal reference is replaced' => ['&#xDFFF', '�'];
        yield 'char_ref.ton #54 carriage-return hexadecimal reference' => ['&#xd', "\r"];
        foreach ([
            'CR decimal entity' => ['&#013;', "\r"],
            'CR hexadecimal entity' => ['&#x00D;', "\r"],
            'decimal Windows-1252 euro sign' => ['&#0128;', '€'],
            'decimal Windows-1252 replacement char 0x81' => ['&#0129;', "\u{0081}"],
            'decimal Windows-1252 single low-9 quotation mark' => ['&#0130;', '‚'],
            'decimal Windows-1252 latin small f with hook' => ['&#0131;', 'ƒ'],
            'decimal Windows-1252 double low-9 quotation mark' => ['&#0132;', '„'],
            'decimal Windows-1252 horizontal ellipsis' => ['&#0133;', '…'],
            'decimal Windows-1252 dagger' => ['&#0134;', '†'],
            'decimal Windows-1252 double dagger' => ['&#0135;', '‡'],
            'decimal Windows-1252 modifier circumflex' => ['&#0136;', 'ˆ'],
            'decimal Windows-1252 per mille sign' => ['&#0137;', '‰'],
            'decimal Windows-1252 latin capital S with caron' => ['&#0138;', 'Š'],
            'decimal Windows-1252 single left angle quote' => ['&#0139;', '‹'],
            'decimal Windows-1252 latin capital OE ligature' => ['&#0140;', 'Œ'],
            'decimal Windows-1252 replacement char 0x8D' => ['&#0141;', "\u{008D}"],
            'decimal Windows-1252 latin capital Z with caron' => ['&#0142;', 'Ž'],
            'decimal Windows-1252 replacement char 0x8F' => ['&#0143;', "\u{008F}"],
            'decimal Windows-1252 replacement char 0x90' => ['&#0144;', "\u{0090}"],
            'decimal Windows-1252 left single quotation mark' => ['&#0145;', '‘'],
            'decimal Windows-1252 right single quotation mark' => ['&#0146;', '’'],
            'decimal Windows-1252 left double quotation mark' => ['&#0147;', '“'],
            'decimal Windows-1252 right double quotation mark' => ['&#0148;', '”'],
            'decimal Windows-1252 bullet' => ['&#0149;', '•'],
            'decimal Windows-1252 en dash' => ['&#0150;', '–'],
            'decimal Windows-1252 em dash' => ['&#0151;', '—'],
            'decimal Windows-1252 small tilde' => ['&#0152;', '˜'],
            'decimal Windows-1252 trade mark sign' => ['&#0153;', '™'],
            'decimal Windows-1252 latin small s with caron' => ['&#0154;', 'š'],
            'decimal Windows-1252 single right angle quote' => ['&#0155;', '›'],
            'decimal Windows-1252 latin small oe ligature' => ['&#0156;', 'œ'],
            'decimal Windows-1252 replacement char 0x9D' => ['&#0157;', "\u{009D}"],
        ] as $label => $case) {
            yield "html5lib entities $label" => $case;
        }
        foreach ([
            'hexadecimal Windows-1252 euro sign' => ['&#x080;', '€'],
            'hexadecimal Windows-1252 replacement char 0x81' => ['&#x081;', "\u{0081}"],
            'hexadecimal Windows-1252 single low-9 quotation mark' => ['&#x082;', '‚'],
            'hexadecimal Windows-1252 latin small f with hook' => ['&#x083;', 'ƒ'],
            'hexadecimal Windows-1252 double low-9 quotation mark' => ['&#x084;', '„'],
            'hexadecimal Windows-1252 horizontal ellipsis' => ['&#x085;', '…'],
            'hexadecimal Windows-1252 dagger' => ['&#x086;', '†'],
            'hexadecimal Windows-1252 double dagger' => ['&#x087;', '‡'],
            'hexadecimal Windows-1252 modifier circumflex' => ['&#x088;', 'ˆ'],
            'hexadecimal Windows-1252 per mille sign' => ['&#x089;', '‰'],
            'hexadecimal Windows-1252 latin capital S with caron' => ['&#x08A;', 'Š'],
            'hexadecimal Windows-1252 single left angle quote' => ['&#x08B;', '‹'],
            'hexadecimal Windows-1252 latin capital OE ligature' => ['&#x08C;', 'Œ'],
            'hexadecimal Windows-1252 replacement char 0x8D' => ['&#x08D;', "\u{008D}"],
            'hexadecimal Windows-1252 latin capital Z with caron' => ['&#x08E;', 'Ž'],
            'hexadecimal Windows-1252 replacement char 0x8F' => ['&#x08F;', "\u{008F}"],
            'hexadecimal Windows-1252 replacement char 0x90' => ['&#x090;', "\u{0090}"],
            'hexadecimal Windows-1252 left single quotation mark' => ['&#x091;', '‘'],
            'hexadecimal Windows-1252 right single quotation mark' => ['&#x092;', '’'],
            'hexadecimal Windows-1252 left double quotation mark' => ['&#x093;', '“'],
            'hexadecimal Windows-1252 right double quotation mark' => ['&#x094;', '”'],
            'hexadecimal Windows-1252 bullet' => ['&#x095;', '•'],
            'hexadecimal Windows-1252 en dash' => ['&#x096;', '–'],
            'hexadecimal Windows-1252 em dash' => ['&#x097;', '—'],
            'hexadecimal Windows-1252 small tilde' => ['&#x098;', '˜'],
            'hexadecimal Windows-1252 trade mark sign' => ['&#x099;', '™'],
            'hexadecimal Windows-1252 latin small s with caron' => ['&#x09A;', 'š'],
            'hexadecimal Windows-1252 single right angle quote' => ['&#x09B;', '›'],
            'hexadecimal Windows-1252 latin small oe ligature' => ['&#x09C;', 'œ'],
            'hexadecimal Windows-1252 replacement char 0x9D' => ['&#x09D;', "\u{009D}"],
            'hexadecimal Windows-1252 latin small z with caron' => ['&#x09E;', 'ž'],
            'hexadecimal Windows-1252 latin capital Y with diaeresis' => ['&#x09F;', 'Ÿ'],
        ] as $label => $case) {
            yield "html5lib entities $label" => $case;
        }
        foreach ([
            'decimal numeric reference before lowercase a' => ['&#97a', 'aa'],
            'decimal numeric reference before uppercase A' => ['&#97A', 'aA'],
            'decimal numeric reference before lowercase f' => ['&#97f', 'af'],
            'decimal numeric reference before uppercase F' => ['&#97F', 'aF'],
        ] as $label => $case) {
            yield "html5lib entities $label" => $case;
        }
        yield 'char_ref.ton #55 invalid hexadecimal reference remains literal' => ['&#xj', '&amp;#xj'];
        yield 'char_ref.ton #56 invalid hexadecimal reference before text remains literal' => ['&#xjgf', '&amp;#xjgf'];
        yield 'char_ref.ton #19 failed short named reference acir remains literal' => ['&acir', '&amp;acir'];
        yield 'char_ref.ton #20 failed short named reference aci remains literal' => ['&aci', '&amp;aci'];
        yield 'char_ref.ton #21 failed short named reference ac remains literal' => ['&ac', '&amp;ac'];
        yield 'char_ref.ton #22 failed short named reference a remains literal' => ['&a', '&amp;a'];
        yield 'char_ref.ton #23 bare ampersand remains literal' => ['&', '&amp;'];
        yield 'html5lib entities undefined double-quoted attribute entity remains literal' => [
            '<h a="&noti;">',
            '<h a="&amp;noti;"></h>',
        ];
        yield 'html5lib entities semicolon-required double-quoted attribute entity before equals remains literal' => [
            '<h a="&lang=">',
            '<h a="&amp;lang="></h>',
        ];
        yield 'html5lib entities legacy double-quoted attribute entity before equals remains literal' => [
            '<h a="&not=">',
            '<h a="&amp;not="></h>',
        ];
        yield 'html5lib entities undefined single-quoted attribute entity remains literal' => [
            "<h a='&noti;'>",
            '<h a="&amp;noti;"></h>',
        ];
        yield 'html5lib entities semicolon-required single-quoted attribute entity before equals remains literal' => [
            "<h a='&lang='>",
            '<h a="&amp;lang="></h>',
        ];
        yield 'html5lib entities legacy single-quoted attribute entity before equals remains literal' => [
            "<h a='&not='>",
            '<h a="&amp;not="></h>',
        ];
        yield 'html5lib entities undefined unquoted attribute entity remains literal' => [
            '<h a=&noti;>',
            '<h a="&amp;noti;"></h>',
        ];
        yield 'html5lib entities semicolon-required unquoted attribute entity before equals remains literal' => [
            '<h a=&lang=>',
            '<h a="&amp;lang="></h>',
        ];
        yield 'html5lib entities legacy unquoted attribute entity before equals remains literal' => [
            '<h a=&not=>',
            '<h a="&amp;not="></h>',
        ];
        yield 'html5lib entities ambiguous ampersand remains literal' => [
            '&rrrraannddom;',
            '&amp;rrrraannddom;',
        ];
        yield 'html5lib entities semicolonless not body reference before name tail' => [
            '&noti;',
            '¬i;',
        ];
        $longUndefinedNamedEntity = '&a' . str_repeat('m', 946) . 'p;';
        yield 'html5lib entities very long undefined named entity remains literal' => [
            $longUndefinedNamedEntity,
            '&amp;' . substr($longUndefinedNamedEntity, 1),
        ];
        yield 'html5lib test3 data state equals text' => ['=', '='];
        yield 'html5lib test3 data state greater-than text' => ['>', '&gt;'];
        yield 'html5lib test3 data state question mark text' => ['?', '?'];
        yield 'html5lib test3 data state at sign text' => ['@', '@'];
        yield 'html5lib test3 data state uppercase A text' => ['A', 'A'];
        yield 'html5lib test3 data state uppercase B text' => ['B', 'B'];
        yield 'html5lib test3 data state uppercase Y text' => ['Y', 'Y'];
        yield 'html5lib test3 data state uppercase Z text' => ['Z', 'Z'];
        yield 'html5lib test3 data state backtick text' => ['`', '`'];
        yield 'html5lib test3 data state lowercase a text' => ['a', 'a'];
        yield 'html5lib test3 data state lowercase b text' => ['b', 'b'];
        yield 'html5lib test3 data state lowercase y text' => ['y', 'y'];
        yield 'html5lib test3 data state lowercase z text' => ['z', 'z'];
        yield 'html5lib test3 data state left brace text' => ['{', '{'];
        yield 'html5lib test3 data state non-BMP text' => ["\u{100000}", "\u{100000}"];
        foreach ([
            'data-state control U+0001' => ["\u{0001}", "\u{0001}"],
            'data-state control U+0002' => ["\u{0002}", "\u{0002}"],
            'data-state control U+0003' => ["\u{0003}", "\u{0003}"],
            'data-state control U+0004' => ["\u{0004}", "\u{0004}"],
            'data-state control U+0005' => ["\u{0005}", "\u{0005}"],
            'data-state control U+0006' => ["\u{0006}", "\u{0006}"],
            'data-state control U+0007' => ["\u{0007}", "\u{0007}"],
            'data-state control U+0008' => ["\u{0008}", "\u{0008}"],
            'data-state control U+000B' => ["\u{000B}", "\u{000B}"],
            'data-state control U+000E' => ["\u{000E}", "\u{000E}"],
            'data-state control U+000F' => ["\u{000F}", "\u{000F}"],
            'data-state control U+0010' => ["\u{0010}", "\u{0010}"],
            'data-state control U+0011' => ["\u{0011}", "\u{0011}"],
            'data-state control U+0012' => ["\u{0012}", "\u{0012}"],
            'data-state control U+0013' => ["\u{0013}", "\u{0013}"],
            'data-state control U+0014' => ["\u{0014}", "\u{0014}"],
            'data-state control U+0015' => ["\u{0015}", "\u{0015}"],
            'data-state control U+0016' => ["\u{0016}", "\u{0016}"],
            'data-state control U+0017' => ["\u{0017}", "\u{0017}"],
            'data-state control U+0018' => ["\u{0018}", "\u{0018}"],
            'data-state control U+0019' => ["\u{0019}", "\u{0019}"],
            'data-state control U+001A' => ["\u{001A}", "\u{001A}"],
            'data-state control U+001B' => ["\u{001B}", "\u{001B}"],
            'data-state control U+001C' => ["\u{001C}", "\u{001C}"],
            'data-state control U+001D' => ["\u{001D}", "\u{001D}"],
            'data-state control U+001E' => ["\u{001E}", "\u{001E}"],
            'data-state control U+001F' => ["\u{001F}", "\u{001F}"],
            'data-state control U+007F' => ["\u{007F}", "\u{007F}"],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state noncharacter U+FDD0' => ["\u{FDD0}", "\u{FDD0}"],
            'data-state noncharacter U+FDD1' => ["\u{FDD1}", "\u{FDD1}"],
            'data-state noncharacter U+FDD2' => ["\u{FDD2}", "\u{FDD2}"],
            'data-state noncharacter U+FDD3' => ["\u{FDD3}", "\u{FDD3}"],
            'data-state noncharacter U+FDD4' => ["\u{FDD4}", "\u{FDD4}"],
            'data-state noncharacter U+FDD5' => ["\u{FDD5}", "\u{FDD5}"],
            'data-state noncharacter U+FDD6' => ["\u{FDD6}", "\u{FDD6}"],
            'data-state noncharacter U+FDD7' => ["\u{FDD7}", "\u{FDD7}"],
            'data-state noncharacter U+FDD8' => ["\u{FDD8}", "\u{FDD8}"],
            'data-state noncharacter U+FDD9' => ["\u{FDD9}", "\u{FDD9}"],
            'data-state noncharacter U+FDDA' => ["\u{FDDA}", "\u{FDDA}"],
            'data-state noncharacter U+FDDB' => ["\u{FDDB}", "\u{FDDB}"],
            'data-state noncharacter U+FDDC' => ["\u{FDDC}", "\u{FDDC}"],
            'data-state noncharacter U+FDDD' => ["\u{FDDD}", "\u{FDDD}"],
            'data-state noncharacter U+FDDE' => ["\u{FDDE}", "\u{FDDE}"],
            'data-state noncharacter U+FDDF' => ["\u{FDDF}", "\u{FDDF}"],
            'data-state noncharacter U+FDE0' => ["\u{FDE0}", "\u{FDE0}"],
            'data-state noncharacter U+FDE1' => ["\u{FDE1}", "\u{FDE1}"],
            'data-state noncharacter U+FDE2' => ["\u{FDE2}", "\u{FDE2}"],
            'data-state noncharacter U+FDE3' => ["\u{FDE3}", "\u{FDE3}"],
            'data-state noncharacter U+FDE4' => ["\u{FDE4}", "\u{FDE4}"],
            'data-state noncharacter U+FDE5' => ["\u{FDE5}", "\u{FDE5}"],
            'data-state noncharacter U+FDE6' => ["\u{FDE6}", "\u{FDE6}"],
            'data-state noncharacter U+FDE7' => ["\u{FDE7}", "\u{FDE7}"],
            'data-state noncharacter U+FDE8' => ["\u{FDE8}", "\u{FDE8}"],
            'data-state noncharacter U+FDE9' => ["\u{FDE9}", "\u{FDE9}"],
            'data-state noncharacter U+FDEA' => ["\u{FDEA}", "\u{FDEA}"],
            'data-state noncharacter U+FDEB' => ["\u{FDEB}", "\u{FDEB}"],
            'data-state noncharacter U+FDEC' => ["\u{FDEC}", "\u{FDEC}"],
            'data-state noncharacter U+FDED' => ["\u{FDED}", "\u{FDED}"],
            'data-state noncharacter U+FDEE' => ["\u{FDEE}", "\u{FDEE}"],
            'data-state noncharacter U+FDEF' => ["\u{FDEF}", "\u{FDEF}"],
            'data-state noncharacter U+FFFE' => ["\u{FFFE}", "\u{FFFE}"],
            'data-state noncharacter U+FFFF' => ["\u{FFFF}", "\u{FFFF}"],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state supplementary-plane noncharacter U+1FFFE' => ["\u{1FFFE}", "\u{1FFFE}"],
            'data-state supplementary-plane noncharacter U+1FFFF' => ["\u{1FFFF}", "\u{1FFFF}"],
            'data-state supplementary-plane noncharacter U+2FFFE' => ["\u{2FFFE}", "\u{2FFFE}"],
            'data-state supplementary-plane noncharacter U+2FFFF' => ["\u{2FFFF}", "\u{2FFFF}"],
            'data-state supplementary-plane noncharacter U+3FFFE' => ["\u{3FFFE}", "\u{3FFFE}"],
            'data-state supplementary-plane noncharacter U+3FFFF' => ["\u{3FFFF}", "\u{3FFFF}"],
            'data-state supplementary-plane noncharacter U+4FFFE' => ["\u{4FFFE}", "\u{4FFFE}"],
            'data-state supplementary-plane noncharacter U+4FFFF' => ["\u{4FFFF}", "\u{4FFFF}"],
            'data-state supplementary-plane noncharacter U+5FFFE' => ["\u{5FFFE}", "\u{5FFFE}"],
            'data-state supplementary-plane noncharacter U+5FFFF' => ["\u{5FFFF}", "\u{5FFFF}"],
            'data-state supplementary-plane noncharacter U+6FFFE' => ["\u{6FFFE}", "\u{6FFFE}"],
            'data-state supplementary-plane noncharacter U+6FFFF' => ["\u{6FFFF}", "\u{6FFFF}"],
            'data-state supplementary-plane noncharacter U+7FFFE' => ["\u{7FFFE}", "\u{7FFFE}"],
            'data-state supplementary-plane noncharacter U+7FFFF' => ["\u{7FFFF}", "\u{7FFFF}"],
            'data-state supplementary-plane noncharacter U+8FFFE' => ["\u{8FFFE}", "\u{8FFFE}"],
            'data-state supplementary-plane noncharacter U+8FFFF' => ["\u{8FFFF}", "\u{8FFFF}"],
            'data-state supplementary-plane noncharacter U+9FFFE' => ["\u{9FFFE}", "\u{9FFFE}"],
            'data-state supplementary-plane noncharacter U+9FFFF' => ["\u{9FFFF}", "\u{9FFFF}"],
            'data-state supplementary-plane noncharacter U+AFFFE' => ["\u{AFFFE}", "\u{AFFFE}"],
            'data-state supplementary-plane noncharacter U+AFFFF' => ["\u{AFFFF}", "\u{AFFFF}"],
            'data-state supplementary-plane noncharacter U+BFFFE' => ["\u{BFFFE}", "\u{BFFFE}"],
            'data-state supplementary-plane noncharacter U+BFFFF' => ["\u{BFFFF}", "\u{BFFFF}"],
            'data-state supplementary-plane noncharacter U+CFFFE' => ["\u{CFFFE}", "\u{CFFFE}"],
            'data-state supplementary-plane noncharacter U+CFFFF' => ["\u{CFFFF}", "\u{CFFFF}"],
            'data-state supplementary-plane noncharacter U+DFFFE' => ["\u{DFFFE}", "\u{DFFFE}"],
            'data-state supplementary-plane noncharacter U+DFFFF' => ["\u{DFFFF}", "\u{DFFFF}"],
            'data-state supplementary-plane noncharacter U+EFFFE' => ["\u{EFFFE}", "\u{EFFFE}"],
            'data-state supplementary-plane noncharacter U+EFFFF' => ["\u{EFFFF}", "\u{EFFFF}"],
            'data-state supplementary-plane noncharacter U+FFFFE' => ["\u{FFFFE}", "\u{FFFFE}"],
            'data-state supplementary-plane noncharacter U+FFFFF' => ["\u{FFFFF}", "\u{FFFFF}"],
            'data-state supplementary-plane noncharacter U+10FFFE' => ["\u{10FFFE}", "\u{10FFFE}"],
            'data-state supplementary-plane noncharacter U+10FFFF' => ["\u{10FFFF}", "\u{10FFFF}"],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state valid U+0009 tab' => ["\t", "\t"],
            'data-state valid U+000A newline' => ["\n", "\n"],
            'data-state valid U+0020 space' => [' ', ' '],
            'data-state valid U+0021 exclamation mark' => ['!', '!'],
            'data-state valid U+0022 quotation mark' => ['"', '"'],
            'data-state valid U+0023 number sign' => ['#', '#'],
            'data-state valid U+0024 dollar sign' => ['$', '$'],
            'data-state valid U+0025 percent sign' => ['%', '%'],
            'data-state valid U+0026 ampersand' => ['&', '&amp;'],
            'data-state valid U+0027 apostrophe' => ["'", "'"],
            'data-state valid U+0028 left parenthesis' => ['(', '('],
            'data-state valid U+0029 right parenthesis' => [')', ')'],
            'data-state valid U+002A asterisk' => ['*', '*'],
            'data-state valid U+002B plus sign' => ['+', '+'],
            'data-state valid U+002C comma' => [',', ','],
            'data-state valid U+002D hyphen-minus' => ['-', '-'],
            'data-state valid U+002E full stop' => ['.', '.'],
            'data-state valid U+002F solidus' => ['/', '/'],
            'data-state valid U+0030 digit zero' => ['0', '0'],
            'data-state valid U+0031 digit one' => ['1', '1'],
            'data-state valid U+0032 digit two' => ['2', '2'],
            'data-state valid U+0033 digit three' => ['3', '3'],
            'data-state valid U+0034 digit four' => ['4', '4'],
            'data-state valid U+0035 digit five' => ['5', '5'],
            'data-state valid U+0036 digit six' => ['6', '6'],
            'data-state valid U+0037 digit seven' => ['7', '7'],
            'data-state valid U+0038 digit eight' => ['8', '8'],
            'data-state valid U+0039 digit nine' => ['9', '9'],
            'data-state valid U+003A colon' => [':', ':'],
            'data-state valid U+003B semicolon' => [';', ';'],
            'data-state valid U+003D equals sign' => ['=', '='],
            'data-state valid U+003E greater-than sign' => ['>', '&gt;'],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state valid U+003F question mark' => ['?', '?'],
            'data-state valid U+0040 at sign' => ['@', '@'],
            'data-state valid U+0041 uppercase A' => ['A', 'A'],
            'data-state valid U+0042 uppercase B' => ['B', 'B'],
            'data-state valid U+0043 uppercase C' => ['C', 'C'],
            'data-state valid U+0044 uppercase D' => ['D', 'D'],
            'data-state valid U+0045 uppercase E' => ['E', 'E'],
            'data-state valid U+0046 uppercase F' => ['F', 'F'],
            'data-state valid U+0047 uppercase G' => ['G', 'G'],
            'data-state valid U+0048 uppercase H' => ['H', 'H'],
            'data-state valid U+0049 uppercase I' => ['I', 'I'],
            'data-state valid U+004A uppercase J' => ['J', 'J'],
            'data-state valid U+004B uppercase K' => ['K', 'K'],
            'data-state valid U+004C uppercase L' => ['L', 'L'],
            'data-state valid U+004D uppercase M' => ['M', 'M'],
            'data-state valid U+004E uppercase N' => ['N', 'N'],
            'data-state valid U+004F uppercase O' => ['O', 'O'],
            'data-state valid U+0050 uppercase P' => ['P', 'P'],
            'data-state valid U+0051 uppercase Q' => ['Q', 'Q'],
            'data-state valid U+0052 uppercase R' => ['R', 'R'],
            'data-state valid U+0053 uppercase S' => ['S', 'S'],
            'data-state valid U+0054 uppercase T' => ['T', 'T'],
            'data-state valid U+0055 uppercase U' => ['U', 'U'],
            'data-state valid U+0056 uppercase V' => ['V', 'V'],
            'data-state valid U+0057 uppercase W' => ['W', 'W'],
            'data-state valid U+0058 uppercase X' => ['X', 'X'],
            'data-state valid U+0059 uppercase Y' => ['Y', 'Y'],
            'data-state valid U+005A uppercase Z' => ['Z', 'Z'],
            'data-state valid U+005B left square bracket' => ['[', '['],
            'data-state valid U+005C reverse solidus' => ['\\', '\\'],
            'data-state valid U+005D right square bracket' => [']', ']'],
            'data-state valid U+005E circumflex accent' => ['^', '^'],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state valid U+005F low line' => ['_', '_'],
            'data-state valid U+0060 grave accent' => ['`', '`'],
            'data-state valid U+0061 lowercase a' => ['a', 'a'],
            'data-state valid U+0062 lowercase b' => ['b', 'b'],
            'data-state valid U+0063 lowercase c' => ['c', 'c'],
            'data-state valid U+0064 lowercase d' => ['d', 'd'],
            'data-state valid U+0065 lowercase e' => ['e', 'e'],
            'data-state valid U+0066 lowercase f' => ['f', 'f'],
            'data-state valid U+0067 lowercase g' => ['g', 'g'],
            'data-state valid U+0068 lowercase h' => ['h', 'h'],
            'data-state valid U+0069 lowercase i' => ['i', 'i'],
            'data-state valid U+006A lowercase j' => ['j', 'j'],
            'data-state valid U+006B lowercase k' => ['k', 'k'],
            'data-state valid U+006C lowercase l' => ['l', 'l'],
            'data-state valid U+006D lowercase m' => ['m', 'm'],
            'data-state valid U+006E lowercase n' => ['n', 'n'],
            'data-state valid U+006F lowercase o' => ['o', 'o'],
            'data-state valid U+0070 lowercase p' => ['p', 'p'],
            'data-state valid U+0071 lowercase q' => ['q', 'q'],
            'data-state valid U+0072 lowercase r' => ['r', 'r'],
            'data-state valid U+0073 lowercase s' => ['s', 's'],
            'data-state valid U+0074 lowercase t' => ['t', 't'],
            'data-state valid U+0075 lowercase u' => ['u', 'u'],
            'data-state valid U+0076 lowercase v' => ['v', 'v'],
            'data-state valid U+0077 lowercase w' => ['w', 'w'],
            'data-state valid U+0078 lowercase x' => ['x', 'x'],
            'data-state valid U+0079 lowercase y' => ['y', 'y'],
            'data-state valid U+007A lowercase z' => ['z', 'z'],
            'data-state valid U+007B left curly bracket' => ['{', '{'],
            'data-state valid U+007C vertical line' => ['|', '|'],
            'data-state valid U+007D right curly bracket' => ['}', '}'],
            'data-state valid U+007E tilde' => ['~', '~'],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state valid U+00A0 no-break space' => ["\u{00A0}", '&nbsp;'],
            'data-state valid U+00A1 inverted exclamation mark' => ["\u{00A1}", "\u{00A1}"],
            'data-state valid U+00A2 cent sign' => ["\u{00A2}", "\u{00A2}"],
            'data-state valid U+00A3 pound sign' => ["\u{00A3}", "\u{00A3}"],
            'data-state valid U+00A4 currency sign' => ["\u{00A4}", "\u{00A4}"],
            'data-state valid U+00A5 yen sign' => ["\u{00A5}", "\u{00A5}"],
            'data-state valid U+00A6 broken bar' => ["\u{00A6}", "\u{00A6}"],
            'data-state valid U+00A7 section sign' => ["\u{00A7}", "\u{00A7}"],
            'data-state valid U+00A8 diaeresis' => ["\u{00A8}", "\u{00A8}"],
            'data-state valid U+00A9 copyright sign' => ["\u{00A9}", "\u{00A9}"],
            'data-state valid U+00AA feminine ordinal indicator' => ["\u{00AA}", "\u{00AA}"],
            'data-state valid U+00AB left-pointing double angle quotation mark' => ["\u{00AB}", "\u{00AB}"],
            'data-state valid U+00AC not sign' => ["\u{00AC}", "\u{00AC}"],
            'data-state valid U+00AD soft hyphen' => ["\u{00AD}", "\u{00AD}"],
            'data-state valid U+00AE registered sign' => ["\u{00AE}", "\u{00AE}"],
            'data-state valid U+00AF macron' => ["\u{00AF}", "\u{00AF}"],
            'data-state valid U+00B0 degree sign' => ["\u{00B0}", "\u{00B0}"],
            'data-state valid U+00B1 plus-minus sign' => ["\u{00B1}", "\u{00B1}"],
            'data-state valid U+00B2 superscript two' => ["\u{00B2}", "\u{00B2}"],
            'data-state valid U+00B3 superscript three' => ["\u{00B3}", "\u{00B3}"],
            'data-state valid U+00B4 acute accent' => ["\u{00B4}", "\u{00B4}"],
            'data-state valid U+00B5 micro sign' => ["\u{00B5}", "\u{00B5}"],
            'data-state valid U+00B6 pilcrow sign' => ["\u{00B6}", "\u{00B6}"],
            'data-state valid U+00B7 middle dot' => ["\u{00B7}", "\u{00B7}"],
            'data-state valid U+00B8 cedilla' => ["\u{00B8}", "\u{00B8}"],
            'data-state valid U+00B9 superscript one' => ["\u{00B9}", "\u{00B9}"],
            'data-state valid U+00BA masculine ordinal indicator' => ["\u{00BA}", "\u{00BA}"],
            'data-state valid U+00BB right-pointing double angle quotation mark' => ["\u{00BB}", "\u{00BB}"],
            'data-state valid U+00BC vulgar fraction one quarter' => ["\u{00BC}", "\u{00BC}"],
            'data-state valid U+00BD vulgar fraction one half' => ["\u{00BD}", "\u{00BD}"],
            'data-state valid U+00BE vulgar fraction three quarters' => ["\u{00BE}", "\u{00BE}"],
            'data-state valid U+00BF inverted question mark' => ["\u{00BF}", "\u{00BF}"],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state valid U+00C0 latin capital A with grave' => ["\u{00C0}", "\u{00C0}"],
            'data-state valid U+00C1 latin capital A with acute' => ["\u{00C1}", "\u{00C1}"],
            'data-state valid U+00C2 latin capital A with circumflex' => ["\u{00C2}", "\u{00C2}"],
            'data-state valid U+00C3 latin capital A with tilde' => ["\u{00C3}", "\u{00C3}"],
            'data-state valid U+00C4 latin capital A with diaeresis' => ["\u{00C4}", "\u{00C4}"],
            'data-state valid U+00C5 latin capital A with ring above' => ["\u{00C5}", "\u{00C5}"],
            'data-state valid U+00C6 latin capital AE' => ["\u{00C6}", "\u{00C6}"],
            'data-state valid U+00C7 latin capital C with cedilla' => ["\u{00C7}", "\u{00C7}"],
            'data-state valid U+00C8 latin capital E with grave' => ["\u{00C8}", "\u{00C8}"],
            'data-state valid U+00C9 latin capital E with acute' => ["\u{00C9}", "\u{00C9}"],
            'data-state valid U+00CA latin capital E with circumflex' => ["\u{00CA}", "\u{00CA}"],
            'data-state valid U+00CB latin capital E with diaeresis' => ["\u{00CB}", "\u{00CB}"],
            'data-state valid U+00CC latin capital I with grave' => ["\u{00CC}", "\u{00CC}"],
            'data-state valid U+00CD latin capital I with acute' => ["\u{00CD}", "\u{00CD}"],
            'data-state valid U+00CE latin capital I with circumflex' => ["\u{00CE}", "\u{00CE}"],
            'data-state valid U+00CF latin capital I with diaeresis' => ["\u{00CF}", "\u{00CF}"],
            'data-state valid U+00D0 latin capital Eth' => ["\u{00D0}", "\u{00D0}"],
            'data-state valid U+00D1 latin capital N with tilde' => ["\u{00D1}", "\u{00D1}"],
            'data-state valid U+00D2 latin capital O with grave' => ["\u{00D2}", "\u{00D2}"],
            'data-state valid U+00D3 latin capital O with acute' => ["\u{00D3}", "\u{00D3}"],
            'data-state valid U+00D4 latin capital O with circumflex' => ["\u{00D4}", "\u{00D4}"],
            'data-state valid U+00D5 latin capital O with tilde' => ["\u{00D5}", "\u{00D5}"],
            'data-state valid U+00D6 latin capital O with diaeresis' => ["\u{00D6}", "\u{00D6}"],
            'data-state valid U+00D7 multiplication sign' => ["\u{00D7}", "\u{00D7}"],
            'data-state valid U+00D8 latin capital O with stroke' => ["\u{00D8}", "\u{00D8}"],
            'data-state valid U+00D9 latin capital U with grave' => ["\u{00D9}", "\u{00D9}"],
            'data-state valid U+00DA latin capital U with acute' => ["\u{00DA}", "\u{00DA}"],
            'data-state valid U+00DB latin capital U with circumflex' => ["\u{00DB}", "\u{00DB}"],
            'data-state valid U+00DC latin capital U with diaeresis' => ["\u{00DC}", "\u{00DC}"],
            'data-state valid U+00DD latin capital Y with acute' => ["\u{00DD}", "\u{00DD}"],
            'data-state valid U+00DE latin capital Thorn' => ["\u{00DE}", "\u{00DE}"],
            'data-state valid U+00DF latin small sharp s' => ["\u{00DF}", "\u{00DF}"],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state valid U+00E0 latin small a with grave' => ["\u{00E0}", "\u{00E0}"],
            'data-state valid U+00E1 latin small a with acute' => ["\u{00E1}", "\u{00E1}"],
            'data-state valid U+00E2 latin small a with circumflex' => ["\u{00E2}", "\u{00E2}"],
            'data-state valid U+00E3 latin small a with tilde' => ["\u{00E3}", "\u{00E3}"],
            'data-state valid U+00E4 latin small a with diaeresis' => ["\u{00E4}", "\u{00E4}"],
            'data-state valid U+00E5 latin small a with ring above' => ["\u{00E5}", "\u{00E5}"],
            'data-state valid U+00E6 latin small ae' => ["\u{00E6}", "\u{00E6}"],
            'data-state valid U+00E7 latin small c with cedilla' => ["\u{00E7}", "\u{00E7}"],
            'data-state valid U+00E8 latin small e with grave' => ["\u{00E8}", "\u{00E8}"],
            'data-state valid U+00E9 latin small e with acute' => ["\u{00E9}", "\u{00E9}"],
            'data-state valid U+00EA latin small e with circumflex' => ["\u{00EA}", "\u{00EA}"],
            'data-state valid U+00EB latin small e with diaeresis' => ["\u{00EB}", "\u{00EB}"],
            'data-state valid U+00EC latin small i with grave' => ["\u{00EC}", "\u{00EC}"],
            'data-state valid U+00ED latin small i with acute' => ["\u{00ED}", "\u{00ED}"],
            'data-state valid U+00EE latin small i with circumflex' => ["\u{00EE}", "\u{00EE}"],
            'data-state valid U+00EF latin small i with diaeresis' => ["\u{00EF}", "\u{00EF}"],
            'data-state valid U+00F0 latin small eth' => ["\u{00F0}", "\u{00F0}"],
            'data-state valid U+00F1 latin small n with tilde' => ["\u{00F1}", "\u{00F1}"],
            'data-state valid U+00F2 latin small o with grave' => ["\u{00F2}", "\u{00F2}"],
            'data-state valid U+00F3 latin small o with acute' => ["\u{00F3}", "\u{00F3}"],
            'data-state valid U+00F4 latin small o with circumflex' => ["\u{00F4}", "\u{00F4}"],
            'data-state valid U+00F5 latin small o with tilde' => ["\u{00F5}", "\u{00F5}"],
            'data-state valid U+00F6 latin small o with diaeresis' => ["\u{00F6}", "\u{00F6}"],
            'data-state valid U+00F7 division sign' => ["\u{00F7}", "\u{00F7}"],
            'data-state valid U+00F8 latin small o with stroke' => ["\u{00F8}", "\u{00F8}"],
            'data-state valid U+00F9 latin small u with grave' => ["\u{00F9}", "\u{00F9}"],
            'data-state valid U+00FA latin small u with acute' => ["\u{00FA}", "\u{00FA}"],
            'data-state valid U+00FB latin small u with circumflex' => ["\u{00FB}", "\u{00FB}"],
            'data-state valid U+00FC latin small u with diaeresis' => ["\u{00FC}", "\u{00FC}"],
            'data-state valid U+00FD latin small y with acute' => ["\u{00FD}", "\u{00FD}"],
            'data-state valid U+00FE latin small thorn' => ["\u{00FE}", "\u{00FE}"],
            'data-state valid U+00FF latin small y with diaeresis' => ["\u{00FF}", "\u{00FF}"],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        foreach ([
            'data-state valid U+D7FF high BMP scalar boundary' => ["\u{D7FF}", "\u{D7FF}"],
            'data-state valid U+E000 low private-use scalar boundary' => ["\u{E000}", "\u{E000}"],
            'data-state valid U+FDCF Arabic presentation boundary' => ["\u{FDCF}", "\u{FDCF}"],
            'data-state valid U+FDF0 Arabic presentation boundary' => ["\u{FDF0}", "\u{FDF0}"],
            'data-state valid U+FFFD replacement character' => ["\u{FFFD}", "\u{FFFD}"],
            'data-state valid U+10000 plane 1 start' => ["\u{10000}", "\u{10000}"],
            'data-state valid U+1FFFD plane 1 end' => ["\u{1FFFD}", "\u{1FFFD}"],
            'data-state valid U+20000 plane 2 start' => ["\u{20000}", "\u{20000}"],
            'data-state valid U+2FFFD plane 2 end' => ["\u{2FFFD}", "\u{2FFFD}"],
            'data-state valid U+30000 plane 3 start' => ["\u{30000}", "\u{30000}"],
            'data-state valid U+3FFFD plane 3 end' => ["\u{3FFFD}", "\u{3FFFD}"],
            'data-state valid U+40000 plane 4 start' => ["\u{40000}", "\u{40000}"],
            'data-state valid U+4FFFD plane 4 end' => ["\u{4FFFD}", "\u{4FFFD}"],
            'data-state valid U+50000 plane 5 start' => ["\u{50000}", "\u{50000}"],
            'data-state valid U+5FFFD plane 5 end' => ["\u{5FFFD}", "\u{5FFFD}"],
            'data-state valid U+60000 plane 6 start' => ["\u{60000}", "\u{60000}"],
            'data-state valid U+6FFFD plane 6 end' => ["\u{6FFFD}", "\u{6FFFD}"],
            'data-state valid U+70000 plane 7 start' => ["\u{70000}", "\u{70000}"],
            'data-state valid U+7FFFD plane 7 end' => ["\u{7FFFD}", "\u{7FFFD}"],
            'data-state valid U+80000 plane 8 start' => ["\u{80000}", "\u{80000}"],
            'data-state valid U+8FFFD plane 8 end' => ["\u{8FFFD}", "\u{8FFFD}"],
            'data-state valid U+90000 plane 9 start' => ["\u{90000}", "\u{90000}"],
            'data-state valid U+9FFFD plane 9 end' => ["\u{9FFFD}", "\u{9FFFD}"],
            'data-state valid U+A0000 plane 10 start' => ["\u{A0000}", "\u{A0000}"],
            'data-state valid U+AFFFD plane 10 end' => ["\u{AFFFD}", "\u{AFFFD}"],
            'data-state valid U+B0000 plane 11 start' => ["\u{B0000}", "\u{B0000}"],
            'data-state valid U+BFFFD plane 11 end' => ["\u{BFFFD}", "\u{BFFFD}"],
            'data-state valid U+C0000 plane 12 start' => ["\u{C0000}", "\u{C0000}"],
            'data-state valid U+CFFFD plane 12 end' => ["\u{CFFFD}", "\u{CFFFD}"],
            'data-state valid U+D0000 plane 13 start' => ["\u{D0000}", "\u{D0000}"],
            'data-state valid U+DFFFD plane 13 end' => ["\u{DFFFD}", "\u{DFFFD}"],
            'data-state valid U+E0000 plane 14 start' => ["\u{E0000}", "\u{E0000}"],
            'data-state valid U+EFFFD plane 14 end' => ["\u{EFFFD}", "\u{EFFFD}"],
            'data-state valid U+F0000 plane 15 start' => ["\u{F0000}", "\u{F0000}"],
            'data-state valid U+FFFFD plane 15 end' => ["\u{FFFFD}", "\u{FFFFD}"],
            'data-state valid U+100000 plane 16 start' => ["\u{100000}", "\u{100000}"],
            'data-state valid U+10FFFD plane 16 end' => ["\u{10FFFD}", "\u{10FFFD}"],
        ] as $label => $case) {
            yield "html5lib unicodeChars $label" => $case;
        }
        yield 'char_ref.ton #28 NUL text is preserved' => ["\0", "\0"];
        yield 'char_ref.ton #29 NUL in text is preserved' => ["a\0b", "a\0b"];
        yield 'char_ref.ton #30 leading NUL in text is preserved' => ["\0b", "\0b"];
        yield 'char_ref.ton #31 trailing NUL in text is preserved' => ["a\0", "a\0"];
        yield 'char_ref.ton #32 text before named reference' => ['a&DownArrowBar;', 'a⤓'];
        yield 'char_ref.ton #33 legacy named reference before named reference' => ['&acirc&DownArrowBar;', 'â⤓'];
        yield 'char_ref.ton #57 no-break space reference' => ['&nbsp;', '&nbsp;'];
        yield 'char_ref.ton #58 no-break space reference inside text' => ['ab&nbsp;cd', 'ab&nbsp;cd'];
        yield 'char_ref.ton #59 legacy no-break space reference' => ['&nbsp', '&nbsp;'];
        yield 'char_ref.ton #60 legacy no-break space reference before text' => ['ab&nbspcd', 'ab&nbsp;cd'];
        yield 'char_ref.ton #34 mixed text and comment reference handling' => [
            "&AEli var asdasd = 131312;\n    var ggff = \"sdfsdf\";\n      &AElig;&acute&acute; &Agrave;\n    <!--<script --</script>\r\r\r\r\n&between",
            "&amp;AEli var asdasd = 131312;\n    var ggff = \"sdfsdf\";\n      Æ´´ À\n    <!--<script --</script>\n\n\n\n&between-->",
        ];
        yield 'EOF comment consumes terminal newline' => ["<!--x\n", "<!--x\n-->"];
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
        yield 'html5lib test1 truncated doctype start is bogus comment' => ['<!DOC>', '<!--' . 'DOC' . '-->'];
        yield 'html5lib test3 incorrectly opened comment NUL' => ["<!\0", "<!--\u{FFFD}-->"];
        yield 'html5lib test3 incorrectly opened comment space NUL' => ["<! \0", "<!-- \u{FFFD}-->"];
        yield 'html5lib test3 processing instruction EOF' => ['<?', '<!--?-->'];
        yield 'html5lib test3 processing instruction NUL' => ["<?\0", "<!--?\u{FFFD}-->"];
        yield 'html5lib test3 processing instruction tab' => ["<?\t", "<!--?\t-->"];
        yield 'html5lib test3 processing instruction line feed' => ["<?\n", "<!--?\n-->"];
        yield 'html5lib test3 processing instruction vertical tab' => ["<?\v", "<!--?\v-->"];
        yield 'html5lib test3 processing instruction form feed' => ["<?\f", "<!--?\f-->"];
        yield 'html5lib test3 processing instruction space' => ['<? ', '<!--? -->'];
        yield 'html5lib test3 processing instruction space NUL' => ["<? \0", "<!--? \u{FFFD}-->"];
        yield 'html5lib test3 processing instruction exclamation' => ['<?!', '<!--?!-->'];
        yield 'html5lib test3 processing instruction double quote' => ['<?"', '<!--?"-->'];
        yield 'html5lib test3 processing instruction ampersand' => ['<?&', '<!--?&-->'];
        yield 'html5lib test3 processing instruction single quote' => ["<?'", "<!--?'-->"];
        yield 'html5lib test3 processing instruction dash' => ['<?-', '<!--?--->'];
        yield 'html5lib test3 processing instruction slash' => ['<?/', '<!--?/-->'];
        yield 'html5lib test3 processing instruction zero' => ['<?0', '<!--?0-->'];
        yield 'html5lib test3 processing instruction one' => ['<?1', '<!--?1-->'];
        yield 'html5lib test3 processing instruction nine' => ['<?9', '<!--?9-->'];
        yield 'html5lib test3 processing instruction less-than' => ['<?<', '<!--?<-->'];
        yield 'html5lib test3 processing instruction equals' => ['<?=', '<!--?=-->'];
        yield 'html5lib test3 processing instruction terminator' => ['<?>', '<!--?-->'];
        yield 'html5lib test3 processing instruction question mark' => ['<??', '<!--??-->'];
        yield 'html5lib test3 processing instruction at sign' => ['<?@', '<!--?@-->'];
        yield 'html5lib test3 processing instruction uppercase A' => ['<?A', '<!--?A-->'];
        yield 'html5lib test3 processing instruction uppercase B' => ['<?B', '<!--?B-->'];
        yield 'html5lib test3 processing instruction uppercase Y' => ['<?Y', '<!--?Y-->'];
        yield 'html5lib test3 processing instruction uppercase Z' => ['<?Z', '<!--?Z-->'];
        yield 'html5lib test3 processing instruction backtick' => ['<?`', '<!--?`-->'];
        yield 'html5lib test3 processing instruction lowercase a' => ['<?a', '<!--?a-->'];
        yield 'html5lib test3 processing instruction lowercase b' => ['<?b', '<!--?b-->'];
        yield 'html5lib test3 processing instruction lowercase y' => ['<?y', '<!--?y-->'];
        yield 'html5lib test3 processing instruction lowercase z' => ['<?z', '<!--?z-->'];
        yield 'html5lib test3 processing instruction opening brace' => ['<?{', '<!--?{-->'];
        yield 'html5lib test3 processing instruction non-BMP' => ["<?\u{100000}", "<!--?\u{100000}-->"];
        foreach ([
            'uppercase Y' => ['<!Y', '<!--Y-->'],
            'uppercase Z' => ['<!Z', '<!--Z-->'],
            'backtick' => ['<!`', '<!--`-->'],
            'lowercase a' => ['<!a', '<!--a-->'],
            'lowercase b' => ['<!b', '<!--b-->'],
            'lowercase y' => ['<!y', '<!--y-->'],
            'lowercase z' => ['<!z', '<!--z-->'],
            'opening brace' => ['<!{', '<!--{-->'],
            'non-BMP' => ["<!\u{100000}", "<!--\u{100000}-->"],
        ] as $label => [$html, $expected]) {
            yield "html5lib test3 incorrectly opened comment $label" => [$html, $expected];
        }
        yield 'html5lib test1 incorrectly opened comment start' => ['<!-', '<!--' . '-' . '-->'];
        yield 'html5lib test1 short empty comment' => ['<!-->', '<!--' . '' . '-->'];
        yield 'html5lib test1 short empty comment with dash' => ['<!--->', '<!--' . '' . '-->'];
        yield 'html5lib test1 unfinished nested comment start' => ['<!-- <!--', '<!--' . ' <!' . '-->'];
        yield 'html5lib test2 comment with dash at EOF' => ['<!---x', '<!--' . '-x' . '-->'];
        yield 'html5lib test3 unfinished four-dash comment' => ['<!----', '<!--' . '' . '-->'];
        yield 'html5lib test3 incorrectly closed empty comment' => ['<!----!>', '<!--' . '' . '-->'];
        yield 'html5lib test3 incorrectly closed comment with data' => ['<!----!-->', '<!--' . '--!' . '-->'];
        yield 'html5lib pendingSpecChanges unfinished four-dash comment with space' => ['<!---- >', '<!--' . '-- >' . '-->'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function html5CdataProvider(): iterable
    {
        yield 'tests21.ton #1 SVG CDATA is text' => ['<svg><![CDATA[foo]]>', '<svg>foo</svg>'];
        yield 'tests21.ton #2 Math CDATA is text' => ['<math><![CDATA[foo]]>', '<math>foo</math>'];
        yield 'tests21.ton #3 HTML CDATA is bogus comment' => ['<div><![CDATA[foo]]>', '<div><!--[CDATA[foo]]--></div>'];
        yield 'tests21.ton #14 foreignObject HTML child CDATA is bogus comment' => [
            '<svg><foreignObject><div><![CDATA[foo]]></div></foreignObject></svg>',
            '<svg><foreignobject><div><!--[CDATA[foo]]--></div></foreignobject></svg>',
        ];
        yield 'tests21.ton #15 SVG CDATA can contain markup text' => ['<svg><![CDATA[<svg>]]>', '<svg>&lt;svg&gt;</svg>'];
        yield 'foreignObject direct child CDATA stays SVG text' => [
            '<svg><foreignObject><![CDATA[foo]]></foreignObject></svg>',
            '<svg><foreignobject>foo</foreignobject></svg>',
        ];
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
        yield 'html5lib test2 less-than in tag name' => ['<a<b>', '<a<b></a<b>'];
        yield 'html5lib test3 exclamation in tag name' => ['<a!>', '<a!></a!>'];
        yield 'html5lib test3 double quote in tag name' => ['<a">', '<a"></a">'];
        yield 'html5lib test3 ampersand in tag name' => ['<a&>', '<a&></a&>'];
        yield 'html5lib test3 single quote in tag name' => ["<a'>", "<a'></a'>"];
        yield 'html5lib test3 dash in tag name' => ['<a->', '<a-></a->'];
        yield 'html5lib test3 period in tag name' => ['<a.>', '<a.></a.>'];
        yield 'html5lib test3 self-closing slash after tag name' => ['<a/>', '<a></a>'];
        yield 'html5lib test3 zero in tag name' => ['<a0>', '<a0></a0>'];
        yield 'html5lib test3 one in tag name' => ['<a1>', '<a1></a1>'];
        yield 'html5lib test3 nine in tag name' => ['<a9>', '<a9></a9>'];
        yield 'html5lib test3 terminal less-than in tag name' => ['<a<>', '<a<></a<>'];
        yield 'html5lib test3 equals in tag name' => ['<a=>', '<a=></a=>'];
        yield 'html5lib test3 question mark in tag name' => ['<a?>', '<a?></a?>'];
        yield 'html5lib test3 at sign in tag name' => ['<a@>', '<a@></a@>'];
        yield 'html5lib test3 left bracket in tag name' => ['<a[>', '<a[></a[>'];
        yield 'html5lib test3 grave accent in tag name' => ['<a`>', '<a`></a`>'];
        yield 'html5lib test3 left brace in tag name' => ['<a{>', '<a{></a{>'];
        yield 'html5lib test3 uppercase A continuation folds' => ['<aA>', '<aa></aa>'];
        yield 'html5lib test3 uppercase B continuation folds' => ['<aB>', '<ab></ab>'];
        yield 'html5lib test3 uppercase Y continuation folds' => ['<aY>', '<ay></ay>'];
        yield 'html5lib test3 uppercase continuation folds' => ['<aZ>', '<az></az>'];
        yield 'html5lib test3 lowercase a continuation' => ['<aa>', '<aa></aa>'];
        yield 'html5lib test3 lowercase b continuation' => ['<ab>', '<ab></ab>'];
        yield 'html5lib test3 lowercase y continuation' => ['<ay>', '<ay></ay>'];
        yield 'html5lib test3 lowercase z continuation' => ['<az>', '<az></az>'];
        yield 'html5lib test3 non-BMP tag name continuation' => [
            "<a\u{100000}>",
            "<a\u{100000}></a\u{100000}>",
        ];
        yield 'html5lib test3 invalid tag-open double quote is text' => ['<"', '&lt;"'];
        yield 'html5lib test3 invalid tag-open ampersand is text' => ['<&', '&lt;&amp;'];
        yield 'html5lib test3 invalid tag-open single quote is text' => ["<'", "&lt;'"];
        yield 'html5lib test3 invalid tag-open dash is text' => ['<-', '&lt;-'];
        yield 'html5lib test3 invalid tag-open period is text' => ['<.', '&lt;.'];
        yield 'html5lib test3 invalid tag-open zero is text' => ['<0', '&lt;0'];
        yield 'html5lib test3 invalid tag-open one is text' => ['<1', '&lt;1'];
        yield 'html5lib test3 invalid tag-open nine is text' => ['<9', '&lt;9'];
        yield 'html5lib test3 invalid tag-open less-than is text' => ['<<', '&lt;&lt;'];
        yield 'html5lib test3 invalid tag-open equals is text' => ['<=', '&lt;='];
        yield 'html5lib test3 invalid tag-open terminator is text' => ['<>', '&lt;&gt;'];
        yield 'html5lib test3 invalid tag-open at sign is text' => ['<@', '&lt;@'];
        yield 'html5lib test3 standalone uppercase A start tag folds' => ['<A>', '<a></a>'];
        yield 'html5lib test3 standalone uppercase B start tag folds' => ['<B>', '<b></b>'];
        yield 'html5lib test3 standalone uppercase Y start tag folds' => ['<Y>', '<y></y>'];
        yield 'html5lib test3 standalone uppercase Z start tag folds' => ['<Z>', '<z></z>'];
        yield 'html5lib test3 invalid tag-open left bracket is text' => ['<[', '&lt;['];
        yield 'html5lib test3 invalid tag-open backtick is text' => ['<`', '&lt;`'];
        yield 'html5lib test3 standalone lowercase a start tag' => ['<a>', '<a></a>'];
        yield 'html5lib test3 standalone lowercase b start tag' => ['<b>', '<b></b>'];
        yield 'html5lib test3 standalone lowercase y start tag' => ['<y>', '<y></y>'];
        yield 'html5lib test3 standalone lowercase z start tag' => ['<z>', '<z></z>'];
        yield 'html5lib test3 invalid tag-open left brace is text' => ['<{', '&lt;{'];
        yield 'html5lib test3 invalid tag-open non-BMP is text' => [
            "<\u{100000}",
            "&lt;\u{100000}",
        ];
        yield 'html5lib test3 tag name NUL replacement' => ["<a\0>", "<a\u{FFFD}></a\u{FFFD}>"];
        yield 'html5lib test3 tag name backspace is retained' => ["<a\x08>", "<a\x08></a\x08>"];
        yield 'html5lib test3 tag name tab boundary' => ["<a\t>", '<a></a>'];
        yield 'html5lib test3 tag name line feed boundary' => ["<a\n>", '<a></a>'];
        yield 'html5lib test3 tag name vertical tab is retained' => ["<a\v>", "<a\v></a\v>"];
        yield 'html5lib test3 tag name form feed boundary' => ["<a\f>", '<a></a>'];
        yield 'html5lib test3 tag name carriage return boundary' => ["<a\r>", '<a></a>'];
        yield 'html5lib test3 tag name unit separator is retained' => ["<a\x1F>", "<a\x1F></a\x1F>"];
        yield 'html5lib test3 EOF before end tag name is text' => ['</', '&lt;/'];
        yield 'html5lib test4 EOF in tag name state' => ['<a', ''];
        yield 'html5lib test4 slash EOF in tag name state' => ['<z/', ''];
        yield 'html5lib test4 CR EOF in tag name state' => ["<z\r", ''];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function html5libTokenizerEmptyEndTagProvider(): iterable
    {
        yield 'html5lib test3 standalone empty end tag' => ['</>', ''];
        yield 'html5lib test2 empty end tag before characters' => ['a</>bc', 'abc'];
        yield 'html5lib test2 empty end tag before start tag' => ['a</><b>c', 'a<b>c</b>'];
        yield 'html5lib test2 empty end tag before comment' => ['a</><!--b-->c', 'a<!--b-->c'];
        yield 'html5lib test2 empty end tag before end tag' => ['a</></b>c', 'ac'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function html5libTokenizerInvalidEndTagProvider(): iterable
    {
        yield 'html5lib test2 illegal end tag name' => ['</1>', '<!--1-->'];
        yield 'invalid end tag with whitespace is bogus comment' => ['a</ >bc', 'a<!-- -->bc'];
        yield 'html5lib test3 invalid end tag NUL at EOF' => ["</\0", "<!--\u{FFFD}-->"];
        yield 'html5lib test3 invalid end tag tab at EOF' => ["</\t", "<!--\t-->"];
        yield 'html5lib test3 invalid end tag line feed at EOF' => ["</\n", "<!--\n-->"];
        yield 'html5lib test3 invalid end tag vertical tab at EOF' => ["</\v", "<!--\v-->"];
        yield 'html5lib test3 invalid end tag form feed at EOF' => ["</\f", "<!--\f-->"];
        yield 'html5lib test3 invalid end tag space at EOF' => ['</ ', '<!-- -->'];
        yield 'html5lib test3 invalid end tag space NUL at EOF' => ["</ \0", "<!-- \u{FFFD}-->"];
        yield 'html5lib test3 invalid end tag exclamation at EOF' => ['</!', '<!--!-->'];
        yield 'html5lib test3 invalid end tag double quote at EOF' => ['</"', '<!--"-->'];
        yield 'html5lib test3 invalid end tag ampersand at EOF' => ['</&', '<!--&-->'];
        yield 'html5lib test3 invalid end tag single quote at EOF' => ["</'", "<!--'-->"];
        yield 'html5lib test3 invalid end tag dash at EOF' => ['</-', '<!----->'];
        yield 'html5lib test3 invalid end tag slash at EOF' => ['<//', '<!--/-->'];
        yield 'html5lib test3 invalid end tag zero at EOF' => ['</0', '<!--0-->'];
        yield 'html5lib test3 invalid end tag one at EOF' => ['</1', '<!--1-->'];
        yield 'html5lib test3 invalid end tag nine at EOF' => ['</9', '<!--9-->'];
        yield 'html5lib test3 invalid end tag less-than at EOF' => ['</<', '<!--<-->'];
        yield 'html5lib test3 invalid end tag equals at EOF' => ['</=', '<!--=-->'];
        yield 'html5lib test3 invalid end tag question mark at EOF' => ['</?', '<!--?-->'];
        yield 'html5lib test3 invalid end tag at sign at EOF' => ['</@', '<!--@-->'];
        yield 'html5lib test3 invalid end tag left bracket at EOF' => ['</[', '<!--[-->'];
        yield 'html5lib test3 invalid end tag backtick at EOF' => ['</`', '<!--`-->'];
        yield 'html5lib test3 invalid end tag opening brace at EOF' => ['</{', '<!--{-->'];
        yield 'html5lib test3 invalid end tag non-BMP at EOF' => ["</\u{100000}", "<!--\u{100000}-->"];
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
        yield 'html5lib test3 before attribute name space boundary' => ['<a >', '<a></a>'];
        yield 'html5lib test3 before attribute name NUL replacement' => [
            "<a \0>",
            "<a \u{FFFD}=\"\"></a>",
        ];
        yield 'html5lib test3 before attribute name backspace retained' => [
            "<a \x08>",
            "<a \x08=\"\"></a>",
        ];
        yield 'html5lib test3 before attribute name tab boundary' => ["<a \t>", '<a></a>'];
        yield 'html5lib test3 before attribute name line feed boundary' => ["<a \n>", '<a></a>'];
        yield 'html5lib test3 before attribute name vertical tab retained' => [
            "<a \v>",
            "<a \v=\"\"></a>",
        ];
        yield 'html5lib test3 before attribute name form feed boundary' => ["<a \f>", '<a></a>'];
        yield 'html5lib test3 before attribute name carriage return boundary' => ["<a \r>", '<a></a>'];
        yield 'html5lib test3 before attribute name unit separator retained' => [
            "<a \x1F>",
            "<a \x1F=\"\"></a>",
        ];
        yield 'html5lib test3 repeated before attribute name space boundary' => ['<a  >', '<a></a>'];
        yield 'html5lib test3 before attribute name exclamation' => ['<a !>', '<a !=""></a>'];
        yield 'html5lib test3 before attribute name double quote' => ['<a ">', '<a &quot;=""></a>'];
        yield 'html5lib test3 before attribute name hash' => ['<a #>', '<a #=""></a>'];
        yield 'html5lib test3 before attribute name ampersand' => ['<a &>', '<a &amp;=""></a>'];
        yield 'html5lib test3 before attribute name single quote' => ["<a '>", '<a &#039;=""></a>'];
        yield 'html5lib test3 before attribute name opening parenthesis' => ['<a (>', '<a (=""></a>'];
        yield 'html5lib test3 before attribute name dash' => ['<a ->', '<a -=""></a>'];
        yield 'html5lib test3 before attribute name period' => ['<a .>', '<a .=""></a>'];
        yield 'html5lib test3 before attribute name self-closing slash' => ['<a />', '<a></a>'];
        yield 'html5lib test3 self-closing slash NUL replacement' => [
            "<a/\0>",
            "<a \u{FFFD}=\"\"></a>",
        ];
        yield 'html5lib test3 self-closing slash tab boundary' => ["<a/\t>", '<a></a>'];
        yield 'html5lib test3 self-closing slash line feed boundary' => ["<a/\n>", '<a></a>'];
        yield 'html5lib test3 self-closing slash vertical tab retained' => [
            "<a/\v>",
            "<a \v=\"\"></a>",
        ];
        yield 'html5lib test3 self-closing slash form feed boundary' => ["<a/\f>", '<a></a>'];
        yield 'html5lib test3 self-closing slash space boundary' => ['<a/ >', '<a></a>'];
        yield 'html5lib test3 self-closing slash exclamation' => ['<a/!>', '<a !=""></a>'];
        yield 'html5lib test3 self-closing slash double quote' => ['<a/">', '<a &quot;=""></a>'];
        yield 'html5lib test3 self-closing slash ampersand' => ['<a/&>', '<a &amp;=""></a>'];
        yield 'html5lib test3 self-closing slash single quote' => ["<a/'>", '<a &#039;=""></a>'];
        yield 'html5lib test3 self-closing slash dash' => ['<a/->', '<a -=""></a>'];
        yield 'html5lib test3 repeated self-closing slash' => ['<a//>', '<a></a>'];
        yield 'html5lib test3 self-closing slash zero' => ['<a/0>', '<a 0=""></a>'];
        yield 'html5lib test3 self-closing slash one' => ['<a/1>', '<a 1=""></a>'];
        yield 'html5lib test3 self-closing slash nine' => ['<a/9>', '<a 9=""></a>'];
        yield 'html5lib test3 self-closing slash less-than' => ['<a/<>', '<a &lt;=""></a>'];
        yield 'html5lib test3 self-closing slash equals' => ['<a/=>', '<a ==""></a>'];
        yield 'html5lib test3 self-closing slash question mark' => ['<a/?>', '<a ?=""></a>'];
        yield 'html5lib test3 self-closing slash at sign' => ['<a/@>', '<a @=""></a>'];
        yield 'html5lib test3 self-closing slash uppercase A' => ['<a/A>', '<a a=""></a>'];
        yield 'html5lib test3 self-closing slash uppercase B' => ['<a/B>', '<a b=""></a>'];
        yield 'html5lib test3 self-closing slash uppercase Y' => ['<a/Y>', '<a y=""></a>'];
        yield 'html5lib test3 self-closing slash uppercase Z' => ['<a/Z>', '<a z=""></a>'];
        yield 'html5lib test3 self-closing slash backtick' => ['<a/`>', '<a `=""></a>'];
        yield 'html5lib test3 self-closing slash lowercase a' => ['<a/a>', '<a a=""></a>'];
        yield 'html5lib test3 self-closing slash lowercase b' => ['<a/b>', '<a b=""></a>'];
        yield 'html5lib test3 self-closing slash lowercase y' => ['<a/y>', '<a y=""></a>'];
        yield 'html5lib test3 self-closing slash lowercase z' => ['<a/z>', '<a z=""></a>'];
        yield 'html5lib test3 self-closing slash left brace' => ['<a/{>', '<a {=""></a>'];
        yield 'html5lib test3 self-closing slash non-BMP' => [
            "<a/\u{100000}>",
            "<a \u{100000}=\"\"></a>",
        ];
        yield 'html5lib test3 before attribute name zero' => ['<a 0>', '<a 0=""></a>'];
        yield 'html5lib test3 before attribute name one' => ['<a 1>', '<a 1=""></a>'];
        yield 'html5lib test3 before attribute name nine' => ['<a 9>', '<a 9=""></a>'];
        yield 'html5lib test3 before attribute name less-than' => ['<a <>', '<a &lt;=""></a>'];
        yield 'html5lib test3 before attribute name equals' => ['<a =>', '<a ==""></a>'];
        yield 'html5lib test3 before attribute name question mark' => ['<a ?>', '<a ?=""></a>'];
        yield 'html5lib test3 before attribute name at sign' => ['<a @>', '<a @=""></a>'];
        yield 'html5lib test3 before attribute name uppercase A' => ['<a A>', '<a a=""></a>'];
        yield 'html5lib test3 before attribute name uppercase B' => ['<a B>', '<a b=""></a>'];
        yield 'html5lib test3 before attribute name uppercase Y' => ['<a Y>', '<a y=""></a>'];
        yield 'html5lib test3 before attribute name uppercase Z' => ['<a Z>', '<a z=""></a>'];
        yield 'html5lib test3 before attribute name left bracket' => ['<a [>', '<a [=""></a>'];
        yield 'html5lib test3 before attribute name backtick' => ['<a `>', '<a `=""></a>'];
        yield 'html5lib test3 before attribute name lowercase b' => ['<a b>', '<a b=""></a>'];
        yield 'html5lib test3 before attribute name lowercase y' => ['<a y>', '<a y=""></a>'];
        yield 'html5lib test3 before attribute name lowercase z' => ['<a z>', '<a z=""></a>'];
        yield 'html5lib test3 before attribute name left brace' => ['<a {>', '<a {=""></a>'];
        yield 'html5lib test3 before attribute name non-BMP' => [
            "<a \u{100000}>",
            "<a \u{100000}=\"\"></a>",
        ];
        yield 'html5lib test3 attribute name lowercase a' => ['<a a>', '<a a=""></a>'];
        yield 'html5lib test3 attribute name NUL replacement' => [
            "<a a\0>",
            "<a a\u{FFFD}=\"\"></a>",
        ];
        yield 'html5lib test3 attribute name backspace retained' => [
            "<a a\x08>",
            "<a a\x08=\"\"></a>",
        ];
        yield 'html5lib test3 attribute name tab boundary' => ["<a a\t>", '<a a=""></a>'];
        yield 'html5lib test3 attribute name line feed boundary' => ["<a a\n>", '<a a=""></a>'];
        yield 'html5lib test3 attribute name vertical tab retained' => [
            "<a a\v>",
            "<a a\v=\"\"></a>",
        ];
        yield 'html5lib test3 attribute name form feed boundary' => ["<a a\f>", '<a a=""></a>'];
        yield 'html5lib test3 attribute name carriage return boundary' => ["<a a\r>", '<a a=""></a>'];
        yield 'html5lib test3 attribute name unit separator retained' => [
            "<a a\x1F>",
            "<a a\x1F=\"\"></a>",
        ];
        yield 'html5lib test3 after attribute name space boundary' => ['<a a >', '<a a=""></a>'];
        yield 'html5lib test3 after attribute name NUL replacement' => [
            "<a a \0>",
            "<a a=\"\" \u{FFFD}=\"\"></a>",
        ];
        yield 'html5lib test3 after attribute name backspace retained' => [
            "<a a \x08>",
            "<a a=\"\" \x08=\"\"></a>",
        ];
        yield 'html5lib test3 after attribute name tab boundary' => ["<a a \t>", '<a a=""></a>'];
        yield 'html5lib test3 after attribute name line feed boundary' => ["<a a \n>", '<a a=""></a>'];
        yield 'html5lib test3 after attribute name vertical tab retained' => [
            "<a a \v>",
            "<a a=\"\" \v=\"\"></a>",
        ];
        yield 'html5lib test3 after attribute name form feed boundary' => ["<a a \f>", '<a a=""></a>'];
        yield 'html5lib test3 after attribute name carriage return boundary' => ["<a a \r>", '<a a=""></a>'];
        yield 'html5lib test3 after attribute name unit separator retained' => [
            "<a a \x1F>",
            "<a a=\"\" \x1F=\"\"></a>",
        ];
        yield 'html5lib test3 repeated after attribute name space boundary' => ['<a a  >', '<a a=""></a>'];
        yield 'html5lib test3 after attribute name exclamation' => ['<a a !>', '<a a="" !=""></a>'];
        yield 'html5lib test3 after attribute name double quote' => ['<a a ">', '<a a="" &quot;=""></a>'];
        yield 'html5lib test3 after attribute name hash' => ['<a a #>', '<a a="" #=""></a>'];
        yield 'html5lib test3 after attribute name ampersand' => ['<a a &>', '<a a="" &amp;=""></a>'];
        yield 'html5lib test3 after attribute name single quote' => ["<a a '>", '<a a="" &#039;=""></a>'];
        yield 'html5lib test3 after attribute name opening parenthesis' => ['<a a (>', '<a a="" (=""></a>'];
        yield 'html5lib test3 after attribute name dash' => ['<a a ->', '<a a="" -=""></a>'];
        yield 'html5lib test3 after attribute name period' => ['<a a .>', '<a a="" .=""></a>'];
        yield 'html5lib test3 after attribute name self-closing slash' => ['<a a />', '<a a=""></a>'];
        yield 'html5lib test3 after attribute name zero' => ['<a a 0>', '<a a="" 0=""></a>'];
        yield 'html5lib test3 after attribute name one' => ['<a a 1>', '<a a="" 1=""></a>'];
        yield 'html5lib test3 after attribute name nine' => ['<a a 9>', '<a a="" 9=""></a>'];
        yield 'html5lib test3 after attribute name less-than' => ['<a a <>', '<a a="" &lt;=""></a>'];
        yield 'html5lib test3 after attribute name equals' => ['<a a =>', '<a a=""></a>'];
        yield 'html5lib test3 after attribute name question mark' => ['<a a ?>', '<a a="" ?=""></a>'];
        yield 'html5lib test3 after attribute name at sign' => ['<a a @>', '<a a="" @=""></a>'];
        yield 'html5lib test3 after attribute name uppercase A duplicate' => ['<a a A>', '<a a=""></a>'];
        yield 'html5lib test3 after attribute name uppercase B' => ['<a a B>', '<a a="" b=""></a>'];
        yield 'html5lib test3 after attribute name uppercase Y' => ['<a a Y>', '<a a="" y=""></a>'];
        yield 'html5lib test3 after attribute name uppercase Z' => ['<a a Z>', '<a a="" z=""></a>'];
        yield 'html5lib test3 after attribute name left bracket' => ['<a a [>', '<a a="" [=""></a>'];
        yield 'html5lib test3 after attribute name backtick' => ['<a a `>', '<a a="" `=""></a>'];
        yield 'html5lib test3 after attribute name lowercase a duplicate' => ['<a a a>', '<a a=""></a>'];
        yield 'html5lib test3 after attribute name lowercase b' => ['<a a b>', '<a a="" b=""></a>'];
        yield 'html5lib test3 after attribute name lowercase y' => ['<a a y>', '<a a="" y=""></a>'];
        yield 'html5lib test3 after attribute name lowercase z' => ['<a a z>', '<a a="" z=""></a>'];
        yield 'html5lib test3 after attribute name left brace' => ['<a a {>', '<a a="" {=""></a>'];
        yield 'html5lib test3 after attribute name non-BMP' => [
            "<a a \u{100000}>",
            "<a a=\"\" \u{100000}=\"\"></a>",
        ];
        yield 'html5lib test3 attribute name exclamation suffix' => ['<a a!>', '<a a!=""></a>'];
        yield 'html5lib test3 attribute name double quote suffix' => ['<a a">', '<a a&quot;=""></a>'];
        yield 'html5lib test3 attribute name hash suffix' => ['<a a#>', '<a a#=""></a>'];
        yield 'html5lib test3 attribute name ampersand suffix' => ['<a a&>', '<a a&amp;=""></a>'];
        yield 'html5lib test3 attribute name single quote suffix' => ["<a a'>", '<a a&#039;=""></a>'];
        yield 'html5lib test3 attribute name opening parenthesis suffix' => ['<a a(>', '<a a(=""></a>'];
        yield 'html5lib test3 attribute name dash suffix' => ['<a a->', '<a a-=""></a>'];
        yield 'html5lib test3 attribute name period suffix' => ['<a a.>', '<a a.=""></a>'];
        yield 'html5lib test3 attribute name self-closing slash boundary' => ['<a a/>', '<a a=""></a>'];
        yield 'html5lib test3 attribute name zero suffix' => ['<a a0>', '<a a0=""></a>'];
        yield 'html5lib test3 attribute name one suffix' => ['<a a1>', '<a a1=""></a>'];
        yield 'html5lib test3 attribute name nine suffix' => ['<a a9>', '<a a9=""></a>'];
        yield 'html5lib test3 attribute name less-than suffix' => ['<a a<>', '<a a&lt;=""></a>'];
        yield 'html5lib test3 attribute name equals boundary' => ['<a a=>', '<a a=""></a>'];
        yield 'html5lib test3 attribute name question mark suffix' => ['<a a?>', '<a a?=""></a>'];
        yield 'html5lib test3 attribute name at sign suffix' => ['<a a@>', '<a a@=""></a>'];
        yield 'html5lib test3 attribute name uppercase A suffix' => ['<a aA>', '<a aa=""></a>'];
        yield 'html5lib test3 attribute name uppercase B suffix' => ['<a aB>', '<a ab=""></a>'];
        yield 'html5lib test3 attribute name uppercase Y suffix' => ['<a aY>', '<a ay=""></a>'];
        yield 'html5lib test3 attribute name uppercase Z suffix' => ['<a aZ>', '<a az=""></a>'];
        yield 'html5lib test3 attribute name left bracket suffix' => ['<a a[>', '<a a[=""></a>'];
        yield 'html5lib test3 attribute name backtick suffix' => ['<a a`>', '<a a`=""></a>'];
        yield 'html5lib test3 attribute name lowercase a suffix' => ['<a aa>', '<a aa=""></a>'];
        yield 'html5lib test3 attribute name lowercase b suffix' => ['<a ab>', '<a ab=""></a>'];
        yield 'html5lib test3 attribute name lowercase y suffix' => ['<a ay>', '<a ay=""></a>'];
        yield 'html5lib test3 attribute name lowercase z suffix' => ['<a az>', '<a az=""></a>'];
        yield 'html5lib test3 attribute name left brace suffix' => ['<a a{>', '<a a{=""></a>'];
        yield 'html5lib test3 attribute name non-BMP suffix' => [
            "<a a\u{100000}>",
            "<a a\u{100000}=\"\"></a>",
        ];
        yield 'html5lib test3 before attribute value NUL replacement' => [
            "<a a=\0>",
            "<a a=\"\u{FFFD}\"></a>",
        ];
        yield 'html5lib test3 before attribute value backspace retained' => [
            "<a a=\x08>",
            "<a a=\"\x08\"></a>",
        ];
        yield 'html5lib test3 before attribute value tab boundary' => ["<a a=\t>", '<a a=""></a>'];
        yield 'html5lib test3 before attribute value line feed boundary' => ["<a a=\n>", '<a a=""></a>'];
        yield 'html5lib test3 before attribute value vertical tab retained' => [
            "<a a=\v>",
            "<a a=\"\v\"></a>",
        ];
        yield 'html5lib test3 before attribute value form feed boundary' => ["<a a=\f>", '<a a=""></a>'];
        yield 'html5lib test3 before attribute value carriage return boundary' => ["<a a=\r>", '<a a=""></a>'];
        yield 'html5lib test3 before attribute value unit separator retained' => [
            "<a a=\x1F>",
            "<a a=\"\x1F\"></a>",
        ];
        yield 'html5lib test3 before attribute value space boundary' => ['<a a= >', '<a a=""></a>'];
        yield 'html5lib test3 before attribute value exclamation' => ['<a a=!>', '<a a="!"></a>'];
        yield 'html5lib test3 double-quoted attribute value empty' => ['<a a="">', '<a a=""></a>'];
        yield 'html5lib test3 double-quoted attribute value tab retained' => [
            "<a a=\"\t\">",
            "<a a=\"\t\"></a>",
        ];
        yield 'html5lib test3 double-quoted attribute value line feed retained' => [
            "<a a=\"\n\">",
            "<a a=\"\n\"></a>",
        ];
        yield 'html5lib test3 double-quoted attribute value vertical tab retained' => [
            "<a a=\"\v\">",
            "<a a=\"\v\"></a>",
        ];
        yield 'html5lib test3 double-quoted attribute value form feed retained' => [
            "<a a=\"\f\">",
            "<a a=\"\f\"></a>",
        ];
        yield 'html5lib test3 double-quoted attribute value space retained' => ['<a a=" ">', '<a a=" "></a>'];
        yield 'html5lib test3 double-quoted attribute value exclamation' => ['<a a="!">', '<a a="!"></a>'];
        yield 'html5lib test3 double-quoted attribute value hash' => ['<a a="#">', '<a a="#"></a>'];
        yield 'html5lib test3 double-quoted attribute value percent' => ['<a a="%">', '<a a="%"></a>'];
        yield 'html5lib test3 double-quoted attribute value ampersand' => ['<a a="&">', '<a a="&amp;"></a>'];
        yield 'html5lib test3 double-quoted attribute value single quote' => ["<a a=\"'\">", "<a a=\"'\"></a>"];
        yield 'html5lib test3 double-quoted attribute value dash' => ['<a a="-">', '<a a="-"></a>'];
        yield 'html5lib test3 double-quoted attribute value slash' => ['<a a="/">', '<a a="/"></a>'];
        yield 'html5lib test3 double-quoted attribute value zero' => ['<a a="0">', '<a a="0"></a>'];
        yield 'html5lib test3 double-quoted attribute value one' => ['<a a="1">', '<a a="1"></a>'];
        yield 'html5lib test3 double-quoted attribute value nine' => ['<a a="9">', '<a a="9"></a>'];
        yield 'html5lib test3 double-quoted attribute value less-than' => ['<a a="<">', '<a a="&lt;"></a>'];
        yield 'html5lib test3 double-quoted attribute value equals' => ['<a a="=">', '<a a="="></a>'];
        yield 'html5lib test3 double-quoted attribute value greater-than' => ['<a a=">">', '<a a="&gt;"></a>'];
        yield 'html5lib test3 double-quoted attribute value question mark' => ['<a a="?">', '<a a="?"></a>'];
        yield 'html5lib test3 double-quoted attribute value at sign' => ['<a a="@">', '<a a="@"></a>'];
        yield 'html5lib test3 double-quoted attribute value uppercase A' => ['<a a="A">', '<a a="A"></a>'];
        yield 'html5lib test3 double-quoted attribute value uppercase B' => ['<a a="B">', '<a a="B"></a>'];
        yield 'html5lib test3 double-quoted attribute value uppercase Y' => ['<a a="Y">', '<a a="Y"></a>'];
        yield 'html5lib test3 double-quoted attribute value uppercase Z' => ['<a a="Z">', '<a a="Z"></a>'];
        yield 'html5lib test3 double-quoted attribute value backtick' => ['<a a="`">', '<a a="`"></a>'];
        yield 'html5lib test3 double-quoted attribute value lowercase a' => ['<a a="a">', '<a a="a"></a>'];
        yield 'html5lib test3 double-quoted attribute value lowercase b' => ['<a a="b">', '<a a="b"></a>'];
        yield 'html5lib test3 double-quoted attribute value lowercase y' => ['<a a="y">', '<a a="y"></a>'];
        yield 'html5lib test3 double-quoted attribute value lowercase z' => ['<a a="z">', '<a a="z"></a>'];
        yield 'html5lib test3 double-quoted attribute value open brace' => ['<a a="{">', '<a a="{"></a>'];
        yield 'html5lib test3 double-quoted attribute value non-BMP' => ["<a a=\"\u{100000}\">", "<a a=\"\u{100000}\"></a>"];
        yield 'html5lib test3 NUL in unquoted attribute value is replaced' => [
            "<a a=a\0>",
            "<a a=\"a\u{FFFD}\"></a>",
        ];
        yield 'html5lib test3 NUL in double-quoted attribute value is replaced' => [
            "<a a=\"\0\">",
            "<a a=\"\u{FFFD}\"></a>",
        ];
        yield 'html5lib test3 NUL in single-quoted attribute value is replaced' => [
            "<a a='\0'>",
            "<a a=\"\u{FFFD}\"></a>",
        ];
        yield 'html5lib test3 unquoted attribute value hash' => ['<a a=#>', '<a a="#"></a>'];
        yield 'html5lib test3 unquoted attribute value percent' => ['<a a=%>', '<a a="%"></a>'];
        yield 'html5lib test3 unquoted attribute value ampersand' => ['<a a=&>', '<a a="&amp;"></a>'];
        yield 'html5lib test3 single-quoted attribute value empty' => ["<a a=''>", '<a a=""></a>'];
        yield 'html5lib test3 single-quoted attribute value tab retained' => [
            "<a a='\t'>",
            "<a a=\"\t\"></a>",
        ];
        yield 'html5lib test3 single-quoted attribute value line feed retained' => [
            "<a a='\n'>",
            "<a a=\"\n\"></a>",
        ];
        yield 'html5lib test3 single-quoted attribute value vertical tab retained' => [
            "<a a='\v'>",
            "<a a=\"\v\"></a>",
        ];
        yield 'html5lib test3 single-quoted attribute value form feed retained' => [
            "<a a='\f'>",
            "<a a=\"\f\"></a>",
        ];
        yield 'html5lib test3 single-quoted attribute value space retained' => ["<a a=' '>", '<a a=" "></a>'];
        yield 'html5lib test3 single-quoted attribute value exclamation' => ["<a a='!'>", '<a a="!"></a>'];
        yield 'html5lib test3 single-quoted attribute value double quote' => ['<a a=\'"\'>', '<a a="&quot;"></a>'];
        yield 'html5lib test3 single-quoted attribute value percent' => ["<a a='%'>", '<a a="%"></a>'];
        yield 'html5lib test3 single-quoted attribute value ampersand' => ["<a a='&'>", '<a a="&amp;"></a>'];
        yield 'html5lib test3 after single-quoted value NUL replacement' => [
            "<a a=''\0>",
            "<a a=\"\" \u{FFFD}=\"\"></a>",
        ];
        yield 'html5lib test3 after single-quoted value backspace retained' => [
            "<a a=''\x08>",
            "<a a=\"\" \x08=\"\"></a>",
        ];
        yield 'html5lib test3 after single-quoted value tab boundary' => ["<a a=''\t>", '<a a=""></a>'];
        yield 'html5lib test3 after single-quoted value line feed boundary' => ["<a a=''\n>", '<a a=""></a>'];
        yield 'html5lib test3 after single-quoted value vertical tab retained' => [
            "<a a=''\v>",
            "<a a=\"\" \v=\"\"></a>",
        ];
        yield 'html5lib test3 after single-quoted value form feed boundary' => ["<a a=''\f>", '<a a=""></a>'];
        yield 'html5lib test3 after single-quoted value carriage return boundary' => ["<a a=''\r>", '<a a=""></a>'];
        yield 'html5lib test3 after single-quoted value unit separator retained' => [
            "<a a=''\x1F>",
            "<a a=\"\" \x1F=\"\"></a>",
        ];
        yield 'html5lib test3 after single-quoted value space boundary' => ["<a a='' >", '<a a=""></a>'];
        yield 'html5lib test3 after single-quoted value exclamation' => ["<a a=''!>", '<a a="" !=""></a>'];
        yield 'html5lib test3 after single-quoted value double quote' => ["<a a=''\">", '<a a="" &quot;=""></a>'];
        yield 'html5lib test3 after single-quoted value ampersand' => ["<a a=''&>", '<a a="" &amp;=""></a>'];
        yield 'html5lib test3 after single-quoted value single quote' => ["<a a='''>", '<a a="" &#039;=""></a>'];
        yield 'html5lib test3 after single-quoted value dash' => ["<a a=''->", '<a a="" -=""></a>'];
        yield 'html5lib test3 after single-quoted value period' => ["<a a=''.>", '<a a="" .=""></a>'];
        yield 'html5lib test3 after single-quoted value self-closing slash' => ["<a a=''/>", '<a a=""></a>'];
        yield 'html5lib test3 after single-quoted value zero' => ["<a a=''0>", '<a a="" 0=""></a>'];
        yield 'html5lib test3 after single-quoted value one' => ["<a a=''1>", '<a a="" 1=""></a>'];
        yield 'html5lib test3 after single-quoted value nine' => ["<a a=''9>", '<a a="" 9=""></a>'];
        yield 'html5lib test3 after single-quoted value less-than' => ["<a a=''<>", '<a a="" &lt;=""></a>'];
        yield 'html5lib test3 after single-quoted value equals' => ["<a a=''=>", '<a a="" ==""></a>'];
        yield 'html5lib test3 after single-quoted value question mark' => ["<a a=''?>", '<a a="" ?=""></a>'];
        yield 'html5lib test3 after single-quoted value at sign' => ["<a a=''@>", '<a a="" @=""></a>'];
        yield 'html5lib test3 after single-quoted value uppercase A duplicate' => ["<a a=''A>", '<a a=""></a>'];
        yield 'html5lib test3 after single-quoted value uppercase B' => ["<a a=''B>", '<a a="" b=""></a>'];
        yield 'html5lib test3 after single-quoted value uppercase Y' => ["<a a=''Y>", '<a a="" y=""></a>'];
        yield 'html5lib test3 after single-quoted value uppercase Z' => ["<a a=''Z>", '<a a="" z=""></a>'];
        yield 'html5lib test3 after single-quoted value backtick' => ["<a a=''`>", '<a a="" `=""></a>'];
        yield 'html5lib test3 after single-quoted value lowercase a duplicate' => ["<a a=''a>", '<a a=""></a>'];
        yield 'html5lib test3 after single-quoted value lowercase b' => ["<a a=''b>", '<a a="" b=""></a>'];
        yield 'html5lib test3 after single-quoted value lowercase y' => ["<a a=''y>", '<a a="" y=""></a>'];
        yield 'html5lib test3 after single-quoted value lowercase z' => ["<a a=''z>", '<a a="" z=""></a>'];
        yield 'html5lib test3 after single-quoted value open brace' => ["<a a=''{>", '<a a="" {=""></a>'];
        yield 'html5lib test3 after single-quoted value non-BMP' => [
            "<a a=''\u{100000}>",
            "<a a=\"\" \u{100000}=\"\"></a>",
        ];
        yield 'html5lib test3 single-quoted attribute value opening parenthesis' => ["<a a='('>", '<a a="("></a>'];
        yield 'html5lib test3 single-quoted attribute value dash' => ["<a a='-'>", '<a a="-"></a>'];
        yield 'html5lib test3 single-quoted attribute value slash' => ["<a a='/'>", '<a a="/"></a>'];
        yield 'html5lib test3 single-quoted attribute value zero' => ["<a a='0'>", '<a a="0"></a>'];
        yield 'html5lib test3 single-quoted attribute value one' => ["<a a='1'>", '<a a="1"></a>'];
        yield 'html5lib test3 single-quoted attribute value nine' => ["<a a='9'>", '<a a="9"></a>'];
        yield 'html5lib test3 single-quoted attribute value less-than' => ["<a a='<'>", '<a a="&lt;"></a>'];
        yield 'html5lib test3 single-quoted attribute value equals' => ["<a a='='>", '<a a="="></a>'];
        yield 'html5lib test3 single-quoted attribute value greater-than' => ["<a a='>'>", '<a a="&gt;"></a>'];
        yield 'html5lib test3 single-quoted attribute value question mark' => ["<a a='?'>", '<a a="?"></a>'];
        yield 'html5lib test3 single-quoted attribute value at sign' => ["<a a='@'>", '<a a="@"></a>'];
        yield 'html5lib test3 single-quoted attribute value uppercase A' => ["<a a='A'>", '<a a="A"></a>'];
        yield 'html5lib test3 single-quoted attribute value uppercase B' => ["<a a='B'>", '<a a="B"></a>'];
        yield 'html5lib test3 single-quoted attribute value uppercase Y' => ["<a a='Y'>", '<a a="Y"></a>'];
        yield 'html5lib test3 single-quoted attribute value uppercase Z' => ["<a a='Z'>", '<a a="Z"></a>'];
        yield 'html5lib test3 single-quoted attribute value backtick' => ["<a a='`'>", '<a a="`"></a>'];
        yield 'html5lib test3 single-quoted attribute value lowercase a' => ["<a a='a'>", '<a a="a"></a>'];
        yield 'html5lib test3 single-quoted attribute value lowercase b' => ["<a a='b'>", '<a a="b"></a>'];
        yield 'html5lib test3 single-quoted attribute value lowercase y' => ["<a a='y'>", '<a a="y"></a>'];
        yield 'html5lib test3 single-quoted attribute value lowercase z' => ["<a a='z'>", '<a a="z"></a>'];
        yield 'html5lib test3 single-quoted attribute value open brace' => ["<a a='{'>", '<a a="{"></a>'];
        yield 'html5lib test3 single-quoted attribute value non-BMP' => [
            "<a a='\u{100000}'>",
            "<a a=\"\u{100000}\"></a>",
        ];
        yield 'trailing NUL survives self-closing slash removal' => [
            "<div disabled\0/>",
            "<div disabled\u{FFFD}=\"\"></div>",
        ];
        yield 'html5lib test3 unquoted attribute value opening parenthesis' => ['<a a=(>', '<a a="("></a>'];
        yield 'html5lib test3 unquoted attribute value dash' => ['<a a=->', '<a a="-"></a>'];
        yield 'html5lib test3 unquoted attribute value slash' => ['<a a=/>', '<a a="/"></a>'];
        yield 'html5lib test3 unquoted attribute value zero' => ['<a a=0>', '<a a="0"></a>'];
        yield 'html5lib test3 unquoted attribute value one' => ['<a a=1>', '<a a="1"></a>'];
        yield 'html5lib test3 unquoted attribute value nine' => ['<a a=9>', '<a a="9"></a>'];
        yield 'html5lib test3 unquoted attribute value less-than' => ['<a a=<>', '<a a="&lt;"></a>'];
        yield 'html5lib test3 unquoted attribute value equals' => ['<a a==>', '<a a="="></a>'];
        yield 'html5lib test3 unquoted attribute value empty' => ['<a a=>', '<a a=""></a>'];
        yield 'html5lib test3 unquoted attribute value question mark' => ['<a a=?>', '<a a="?"></a>'];
        yield 'html5lib test3 unquoted attribute value at sign' => ['<a a=@>', '<a a="@"></a>'];
        yield 'html5lib test3 unquoted attribute value uppercase A' => ['<a a=A>', '<a a="A"></a>'];
        yield 'html5lib test3 unquoted attribute value uppercase B' => ['<a a=B>', '<a a="B"></a>'];
        yield 'html5lib test3 unquoted attribute value uppercase Y' => ['<a a=Y>', '<a a="Y"></a>'];
        yield 'html5lib test3 unquoted attribute value uppercase Z' => ['<a a=Z>', '<a a="Z"></a>'];
        yield 'html5lib test3 unquoted attribute value backtick' => ['<a a=`>', '<a a="`"></a>'];
        yield 'html5lib test3 unquoted attribute value lowercase a' => ['<a a=a>', '<a a="a"></a>'];
        yield 'html5lib test3 unquoted attribute value lowercase b' => ['<a a=b>', '<a a="b"></a>'];
        yield 'html5lib test3 unquoted attribute value lowercase y' => ['<a a=y>', '<a a="y"></a>'];
        yield 'html5lib test3 unquoted attribute value lowercase z' => ['<a a=z>', '<a a="z"></a>'];
        yield 'html5lib test3 unquoted attribute value open brace' => ['<a a={>', '<a a="{"></a>'];
        yield 'html5lib test3 unquoted attribute value non-BMP' => [
            "<a a=\u{100000}>",
            "<a a=\"\u{100000}\"></a>",
        ];
        yield 'html5lib test3 unquoted attribute value backspace retained after character' => [
            "<a a=a\x08>",
            "<a a=\"a\x08\"></a>",
        ];
        yield 'html5lib test3 unquoted attribute value tab boundary after character' => ["<a a=a\t>", '<a a="a"></a>'];
        yield 'html5lib test3 unquoted attribute value line feed boundary after character' => ["<a a=a\n>", '<a a="a"></a>'];
        yield 'html5lib test3 unquoted attribute value vertical tab retained after character' => [
            "<a a=a\v>",
            "<a a=\"a\v\"></a>",
        ];
        yield 'html5lib test3 unquoted attribute value form feed boundary after character' => ["<a a=a\f>", '<a a="a"></a>'];
        yield 'html5lib test3 unquoted attribute value carriage return boundary after character' => ["<a a=a\r>", '<a a="a"></a>'];
        yield 'html5lib test3 unquoted attribute value unit separator retained after character' => [
            "<a a=a\x1F>",
            "<a a=\"a\x1F\"></a>",
        ];
        yield 'html5lib test3 unquoted attribute value space boundary after character' => ['<a a=a >', '<a a="a"></a>'];
        yield 'html5lib test3 unquoted attribute value exclamation after character' => ['<a a=a!>', '<a a="a!"></a>'];
        yield 'html5lib test3 unquoted attribute value double quote after character' => ['<a a=a">', '<a a="a&quot;"></a>'];
        yield 'html5lib test3 unquoted attribute value hash after character' => ['<a a=a#>', '<a a="a#"></a>'];
        yield 'html5lib test3 unquoted attribute value percent after character' => ['<a a=a%>', '<a a="a%"></a>'];
        yield 'html5lib test3 unquoted attribute value ampersand after character' => ['<a a=a&>', '<a a="a&amp;"></a>'];
        yield 'html5lib test3 unquoted attribute value single quote after character' => ["<a a=a'>", '<a a="a\'"></a>'];
        yield 'html5lib test3 unquoted attribute value opening parenthesis after character' => ['<a a=a(>', '<a a="a("></a>'];
        yield 'html5lib test3 unquoted attribute value dash after character' => ['<a a=a->', '<a a="a-"></a>'];
        yield 'html5lib test3 unquoted attribute value zero after character' => ['<a a=a0>', '<a a="a0"></a>'];
        yield 'html5lib test3 unquoted attribute value one after character' => ['<a a=a1>', '<a a="a1"></a>'];
        yield 'html5lib test3 unquoted attribute value nine after character' => ['<a a=a9>', '<a a="a9"></a>'];
        yield 'html5lib test3 unquoted attribute value less-than after character' => ['<a a=a<>', '<a a="a&lt;"></a>'];
        yield 'html5lib test3 unquoted attribute value equals after character' => ['<a a=a=>', '<a a="a="></a>'];
        yield 'html5lib test3 unquoted attribute value question mark after character' => ['<a a=a?>', '<a a="a?"></a>'];
        yield 'html5lib test3 unquoted attribute value at sign after character' => ['<a a=a@>', '<a a="a@"></a>'];
        yield 'html5lib test3 unquoted attribute value uppercase A after character' => ['<a a=aA>', '<a a="aA"></a>'];
        yield 'html5lib test3 unquoted attribute value uppercase B after character' => ['<a a=aB>', '<a a="aB"></a>'];
        yield 'html5lib test3 unquoted attribute value uppercase Y after character' => ['<a a=aY>', '<a a="aY"></a>'];
        yield 'html5lib test3 unquoted attribute value uppercase Z after character' => ['<a a=aZ>', '<a a="aZ"></a>'];
        yield 'html5lib test3 unquoted attribute value backtick after character' => ['<a a=a`>', '<a a="a`"></a>'];
        yield 'html5lib test3 unquoted attribute value lowercase a after character' => ['<a a=aa>', '<a a="aa"></a>'];
        yield 'html5lib test3 unquoted attribute value lowercase b after character' => ['<a a=ab>', '<a a="ab"></a>'];
        yield 'html5lib test3 unquoted attribute value lowercase y after character' => ['<a a=ay>', '<a a="ay"></a>'];
        yield 'html5lib test3 unquoted attribute value lowercase z after character' => ['<a a=az>', '<a a="az"></a>'];
        yield 'html5lib test3 unquoted attribute value open brace after character' => ['<a a=a{>', '<a a="a{"></a>'];
        yield 'html5lib test3 unquoted attribute value non-BMP after character' => [
            "<a a=a\u{100000}>",
            "<a a=\"a\u{100000}\"></a>",
        ];
        yield 'html5lib test1 less-than in unquoted attribute value' => [
            '<a a=f<>',
            '<a a="f&lt;"></a>',
        ];
        yield 'html5lib test3 slash in unquoted attribute value before tag end' => [
            '<a a=a/>',
            '<a a="a/"></a>',
        ];
        yield 'html5lib test4 EOF in before attribute name state' => ['<a ', ''];
        yield 'html5lib test4 EOF in attribute name state' => ['<a a', ''];
        yield 'html5lib test4 EOF in after attribute name state' => ['<a a ', ''];
        yield 'html5lib test4 EOF in before attribute value state' => ['<a a =', ''];
        yield 'html5lib test4 EOF in double-quoted attribute value state' => ['<a a ="a', ''];
        yield 'html5lib test4 EOF in single-quoted attribute value state' => ["<a a ='a", ''];
        yield 'html5lib test4 EOF in unquoted attribute value state' => ['<a a =a', ''];
        yield 'html5lib test4 EOF in after attribute value state' => ["<a a ='a'", ''];
        yield 'html5lib test2 double-quote after attribute name' => [
            '<h a ">',
            '<h a="" &quot;=""></h>',
        ];
        yield 'html5lib test2 single-quote after attribute name' => [
            "<h a '>",
            '<h a="" &#039;=""></h>',
        ];
        yield 'html5lib test4 attribute name starting with double quote' => [
            '<foo "=\'bar\'>',
            '<foo &quot;="bar"></foo>',
        ];
        yield 'html5lib test4 attribute name starting with single quote' => [
            "<foo '='bar'>",
            '<foo &#039;="bar"></foo>',
        ];
        yield 'html5lib test4 attribute name containing double quote' => [
            '<foo a"b=\'bar\'>',
            '<foo a&quot;b="bar"></foo>',
        ];
        yield 'html5lib test4 attribute name containing single quote' => [
            "<foo a'b='bar'>",
            '<foo a&#039;b="bar"></foo>',
        ];
        yield 'html5lib test4 unquoted attribute value containing single quote' => [
            "<foo a=b'c>",
            '<foo a="b\'c"></foo>',
        ];
        yield 'html5lib test4 unquoted attribute value containing double quote' => [
            '<foo a=b"c>',
            '<foo a="b&quot;c"></foo>',
        ];
        yield 'html5lib test4 equals attribute' => [
            '<z =>',
            '<z ==""></z>',
        ];
        yield 'html5lib test4 double equals attribute' => [
            '<z ==>',
            '<z ==""></z>',
        ];
        yield 'html5lib test4 triple equals attribute' => [
            '<z ===>',
            '<z =="="></z>',
        ];
        yield 'html5lib test4 quadruple equals attribute' => [
            '<z ====>',
            '<z =="=="></z>',
        ];
        yield 'html5lib test4 numeric and less-than attribute names' => [
            '<z/0  <>',
            '<z 0="" &lt;=""></z>',
        ];
        yield 'quote in unquoted attribute value does not consume following tag' => [
            '<foo a=b"c><bar title="x">z</bar>',
            '<foo a="b&quot;c"><bar title="x">z</bar></foo>',
        ];
        yield 'quote in attribute name does not consume following tag' => [
            '<foo a"b><bar title="x">z</bar>',
            '<foo a&quot;b=""><bar title="x">z</bar></foo>',
        ];
        yield 'bare equals attribute name does not consume following tag' => [
            '<foo ="x><bar title="y">z</bar>',
            '<foo =&quot;x=""><bar title="y">z</bar></foo>',
        ];
        yield 'numeric attribute name remains string' => [
            '<div 0=x>',
            '<div 0="x"></div>',
        ];
        yield 'slash separates alphabetic attribute names' => [
            '<z a/b>',
            '<z a="" b=""></z>',
        ];
        yield 'slash separates numeric attribute names' => [
            '<z 0/1>',
            '<z 0="" 1=""></z>',
        ];
        yield 'quote in body attribute value does not consume following tag' => [
            '<body a=b"c><bar title="">z</bar></body>',
            '<bar title="">z</bar>',
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
    public function testFullDocumentTypeSerializationPreservesEmptyIdentifiers(string $html): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($html, Serializer::serializeDeep($document, fullDoctype: true));
    }

    #[DataProvider('tokenizerDoctypeProvider')]
    public function testTokenizerDoctypeRegressions(string $html, string $expected, bool $quirksMode): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($quirksMode, $document->isQuirksMode());
        self::assertSame($expected, Serializer::serializeDeep($document, fullDoctype: true));
    }

    #[DataProvider('html5libTokenizerContentModelProvider')]
    public function testHtml5libTokenizerContentModelRegressions(string $html, string $expected): void
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

    #[DataProvider('tokenizerCommentProvider')]
    public function testTokenizerCommentRegressions(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('html5CdataProvider')]
    public function testHtml5CdataRegressions(string $html, string $expected): void
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

    #[DataProvider('html5libTokenizerEmptyEndTagProvider')]
    public function testHtml5libTokenizerEmptyEndTagRegressions(string $html, string $expected): void
    {
        $document = new Document();
        self::assertSame(Status::Ok, $document->parse($html));

        self::assertSame($expected, Serializer::serializeDeep($document->bodyElement()));
    }

    #[DataProvider('html5libTokenizerInvalidEndTagProvider')]
    public function testHtml5libTokenizerInvalidEndTagRegressions(string $html, string $expected): void
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

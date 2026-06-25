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
        yield 'char_ref.ton #35 decimal reference without semicolon' => ['&#80', 'P'];
        yield 'char_ref.ton #36 decimal reference before text' => ['&#80v', 'Pv'];
        yield 'char_ref.ton #37 text before decimal reference' => ['a&#80v', 'aPv'];
        yield 'char_ref.ton #38 oversized decimal reference is replaced' => ['a&#654654654654654646546v', 'a�v'];
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
        yield 'char_ref.ton #55 invalid hexadecimal reference remains literal' => ['&#xj', '&amp;#xj'];
        yield 'char_ref.ton #56 invalid hexadecimal reference before text remains literal' => ['&#xjgf', '&amp;#xjgf'];
        yield 'char_ref.ton #19 failed short named reference acir remains literal' => ['&acir', '&amp;acir'];
        yield 'char_ref.ton #20 failed short named reference aci remains literal' => ['&aci', '&amp;aci'];
        yield 'char_ref.ton #21 failed short named reference ac remains literal' => ['&ac', '&amp;ac'];
        yield 'char_ref.ton #22 failed short named reference a remains literal' => ['&a', '&amp;a'];
        yield 'char_ref.ton #23 bare ampersand remains literal' => ['&', '&amp;'];
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
        yield 'html5lib test3 processing instruction NUL' => ["<?\0", "<!--?\u{FFFD}-->"];
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
        yield 'html5lib test3 equals in tag name' => ['<a=>', '<a=></a=>'];
        yield 'html5lib test3 question mark in tag name' => ['<a?>', '<a?></a?>'];
        yield 'html5lib test3 at sign in tag name' => ['<a@>', '<a@></a@>'];
        yield 'html5lib test3 left bracket in tag name' => ['<a[>', '<a[></a[>'];
        yield 'html5lib test3 grave accent in tag name' => ['<a`>', '<a`></a`>'];
        yield 'html5lib test3 left brace in tag name' => ['<a{>', '<a{></a{>'];
        yield 'html5lib test3 uppercase continuation folds' => ['<aZ>', '<az></az>'];
        yield 'html5lib test4 EOF in tag name state' => ['<a', ''];
        yield 'html5lib test4 slash EOF in tag name state' => ['<z/', ''];
        yield 'html5lib test4 CR EOF in tag name state' => ["<z\r", ''];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function html5libTokenizerEmptyEndTagProvider(): iterable
    {
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
        yield 'trailing NUL survives self-closing slash removal' => [
            "<div disabled\0/>",
            "<div disabled\u{FFFD}=\"\"></div>",
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

<?php

declare(strict_types=1);

namespace Lexbor\Tests\Html;

use Lexbor\Core\Status;
use Lexbor\Html\Encoding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, int}>
     */
    public static function upstreamMetaProvider(): iterable
    {
        yield 'html/encoding.c #1 charset unquoted' => ['<meta charset=utf-8>', 'utf-8', 0];
        yield 'html/encoding.c #2 charset single quoted' => ["<meta charset='utf-8'>", 'utf-8', 0];
        yield 'html/encoding.c #3 charset preserves quoted whitespace' => ["<meta charset=' utf-8 '>", ' utf-8 ', 0];
        yield 'html/encoding.c #4 http-equiv before charset' => ['<meta http-equiv="content-type" charset=utf-8>', 'utf-8', 0];
        yield 'html/encoding.c #5 charset before http-equiv' => ['<meta charset=utf-8 http-equiv="content-type">', 'utf-8', 0];
        yield 'html/encoding.c #6 content-type charset' => ['<meta http-equiv="content-type" content="text/html; charset=utf-8">', 'utf-8', 0];
        yield 'html/encoding.c #7 content before http-equiv' => ['<meta content="text/html; charset=utf-8" http-equiv="content-type">', 'utf-8', 0];
        yield 'html/encoding.c #8 wrong http-equiv' => ['<meta content="text/html; charset=utf-8" http-equiv="content-typ">', null, 0];
        yield 'html/encoding.c #9 quoted charset inside content' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8\'">', 'utf-8', 0];
        yield 'html/encoding.c #10 quoted content charset preserves whitespace' => ['<meta http-equiv="content-type" content="text/html; charset=\' utf-8 \'">', ' utf-8 ', 0];
        yield 'html/encoding.c #11 rejects unclosed quoted content charset' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8">', null, 0];
        yield 'html/encoding.c #12 rejects unclosed quoted content charset with trailing spaces' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8   ">', null, 0];
        yield 'html/encoding.c #13 second meta entry' => [
            '<meta http-equiv="content-type" content="text/html; charset=windows-1251">'
            . '<meta http-equiv="content-type" content="text/html; charset=utf-8">',
            'utf-8',
            1,
        ];
        yield 'html/encoding.c #14 content without http-equiv' => ['<meta content="text/html; charset=utf-8">', null, 0];
        yield 'html/encoding.c #15 content-type without charset' => ['<meta http-equiv="content-type" content="text/html">', null, 0];
        yield 'html/encoding.c #16 meta after html tag' => ["<html>\n <meta http-equiv=\"content-type\" charset=utf-8>", 'utf-8', 0];
        yield 'html/encoding.c #17 ignores meta text inside quoted end-tag attribute' => [
            "</html lala='><meta charset=cp1251>'>\n <meta http-equiv=\"content-type\" charset=utf-8>",
            'utf-8',
            0,
        ];
        yield 'html/encoding.c #18 charset before viewport metadata' => ['<meta charset="windows-1251" name="viewport" content="width">', 'windows-1251', 0];
        yield 'html/encoding.c #19 charset among bogus attributes' => ['<meta bu charset="windows-1251" be name="viewport" bu content="width" be>', 'windows-1251', 0];
        yield 'html/encoding.c #20 whitespace before equals' => ['<meta charset =utf-8>', 'utf-8', 0];
        yield 'html/encoding.c #21 whitespace around equals' => ['<meta charset = utf-8>', 'utf-8', 0];
        yield 'html/encoding.c #22 repeated whitespace around equals' => ['<meta charset   =   utf-8   >', 'utf-8', 0];
        yield 'html/encoding.c #23 quoted value after whitespace around equals' => ["<meta charset = 'utf-8'>", 'utf-8', 0];
        yield 'regression skips meta-looking text inside comments' => ['<!-- > <meta charset=utf-8> -->', null, 0];
        yield 'regression resumes scanning after comment close' => [
            '<!-- > <meta charset=utf-8> --><meta charset=windows-1251>',
            'windows-1251',
            0,
        ];
        yield 'regression valueless charset does not suppress content charset' => [
            '<meta charset content="text/html; charset=utf-8" http-equiv=content-type>',
            'utf-8',
            0,
        ];
        yield 'regression unquoted content charset rejects apostrophe' => [
            '<meta http-equiv=content-type content="text/html; charset=utf\'8">',
            null,
            0,
        ];
        yield 'regression unquoted content charset rejects quote' => [
            '<meta http-equiv=content-type content=\'text/html; charset=utf"8\'>',
            null,
            0,
        ];
        yield 'regression unquoted charset preserves slash' => ['<meta charset=utf/8>', 'utf/8', 0];
        yield 'regression content charset scan does not require token boundary' => [
            '<meta http-equiv=content-type content="text/html; foocharset=utf-8">',
            'utf-8',
            0,
        ];
        yield 'regression mixed content charset remains first entry' => [
            '<meta http-equiv=content-type content="text/html; charset=koi8-r" charset=utf-8>',
            'koi8-r',
            0,
        ];
        yield 'regression mixed direct charset remains second entry' => [
            '<meta http-equiv=content-type content="text/html; charset=koi8-r" charset=utf-8>',
            'utf-8',
            1,
        ];
        yield 'regression charset prefix attribute name matches upstream' => ['<meta charsetx=utf-8>', 'utf-8', 0];
        yield 'regression content prefix attribute name matches upstream' => [
            '<meta http-equiv=content-type contentx="text/html; charset=utf-8">',
            'utf-8',
            0,
        ];
        yield 'regression processing instruction raw skip exposes following meta' => [
            '<?x="><meta charset=cp1251>"><meta charset=utf-8>',
            'cp1251',
            0,
        ];
        yield 'regression non-alpha end tag raw skip exposes following meta' => [
            '</1 x="><meta charset=cp1251>"><meta charset=utf-8>',
            'cp1251',
            0,
        ];
        yield 'regression short comment close exposes following meta' => ['<!--><meta charset=utf-8>', 'utf-8', 0];
        yield 'regression slash in non-meta name raw-skips to inner meta' => [
            '<div/lala=\'><meta charset=cp1251>\'><meta charset=utf-8>',
            'cp1251',
            0,
        ];
        yield 'regression slash in alpha end-tag name raw-skips to inner meta' => [
            '</html/lala=\'><meta charset=cp1251>\'><meta charset=utf-8>',
            'cp1251',
            0,
        ];
        yield 'regression length-10 charset prefix is ignored like http-equiv branch' => [
            '<meta charsetxxx=utf-8>',
            null,
            0,
        ];
        yield 'regression length-10 content prefix is ignored like http-equiv branch' => [
            '<meta http-equiv=content-type contentxxx="text/html; charset=utf-8">',
            null,
            0,
        ];
        yield 'regression slash after charset name leaves it valueless' => ['<meta charset/=utf-8>', null, 0];
        yield 'regression slash after content name leaves it valueless' => [
            '<meta http-equiv=content-type content/="text/html; charset=utf-8">',
            null,
            0,
        ];
        yield 'regression slash after http-equiv name leaves pragma unset' => [
            '<meta http-equiv/=content-type content=charset=utf-8>',
            null,
            0,
        ];
        yield 'regression bare equals consumes following charset as bogus value' => [
            '<meta = charset=utf-8>',
            null,
            0,
        ];
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function upstreamPrescanProvider(): iterable
    {
        yield 'encoding_prescan.c utf16le_xml_declaration' => ["\x3C\x00\x3F\x00\x78\x00", 'UTF-16LE'];
        yield 'encoding_prescan.c utf16be_xml_declaration' => ["\x00\x3C\x00\x3F\x00\x78", 'UTF-16BE'];
        yield 'encoding_prescan.c utf16le_xml_declaration_extra' => ["\x3C\x00\x3F\x00\x78\x00\x6D\x00\x6C\x00", 'UTF-16LE'];
        yield 'encoding_prescan.c utf16be_xml_declaration_extra' => ["\x00\x3C\x00\x3F\x00\x78\x00\x6D\x00\x6C", 'UTF-16BE'];
        yield 'encoding_prescan.c short data abc' => ['abc', null];
        yield 'encoding_prescan.c short data lt' => ['<', null];
        yield 'encoding_prescan.c short data empty' => ['', null];
        yield 'encoding_prescan.c short_data_almost_utf16le' => ["\x3C\x00\x3F\x00\x78", null];
        yield 'encoding_prescan.c short_data_almost_utf16be' => ["\x00\x3C\x00\x3F\x00", null];
        yield 'encoding_prescan.c six_bytes_no_utf16' => ["\x3C\x3F\x78\x6D\x6C\x20", null];
        yield 'encoding_prescan.c no_meta_tag document' => ['<html><head><title>Test</title></head></html>', null];
        yield 'encoding_prescan.c no_meta_tag div' => ['<div>content</div>', null];
        yield 'encoding_prescan.c meta_no_charset viewport' => ['<meta name="viewport" content="width">', null];
        yield 'encoding_prescan.c meta_no_charset http-equiv only' => ['<meta http-equiv="content-type">', null];
        yield 'encoding_prescan.c meta_content_no_http_equiv' => ['<meta content="text/html; charset=utf-8">', null];
        yield 'encoding_prescan.c meta_http_equiv_no_charset_in_content' => ['<meta http-equiv="content-type" content="text/html">', null];
        yield 'encoding_prescan.c meta_charset_simple unquoted' => ['<meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_charset_simple single quoted' => ["<meta charset='utf-8'>", 'utf-8'];
        yield 'encoding_prescan.c meta_charset_simple double quoted' => ['<meta charset="utf-8">', 'utf-8'];
        yield 'encoding_prescan.c meta_charset_spaces before equals' => ['<meta charset =utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_charset_spaces around equals' => ['<meta charset = utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_charset_spaces repeated' => ['<meta charset   =   utf-8   >', 'utf-8'];
        yield 'encoding_prescan.c meta_charset_spaces quoted' => ["<meta charset = 'utf-8'>", 'utf-8'];
        yield 'encoding_prescan.c meta_charset_windows_1251' => ['<meta charset=windows-1251>', 'windows-1251'];
        yield 'encoding_prescan.c meta_http_equiv_content_type' => ['<meta http-equiv="content-type" content="text/html; charset=utf-8">', 'utf-8'];
        yield 'encoding_prescan.c meta_http_equiv_content_type reversed' => ['<meta content="text/html; charset=utf-8" http-equiv="content-type">', 'utf-8'];
        yield 'encoding_prescan.c meta_content_charset_quoted' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8\'">', 'utf-8'];
        yield 'encoding_prescan.c alias_utf_16le lowercase' => ['<meta charset=utf-16le>', 'UTF-8'];
        yield 'encoding_prescan.c alias_utf_16le uppercase' => ['<meta charset=UTF-16LE>', 'UTF-8'];
        yield 'encoding_prescan.c alias_utf_16le quoted' => ['<meta charset="utf-16le">', 'UTF-8'];
        yield 'encoding_prescan.c alias_utf_16be lowercase' => ['<meta charset=utf-16be>', 'UTF-8'];
        yield 'encoding_prescan.c alias_utf_16be uppercase' => ['<meta charset=UTF-16BE>', 'UTF-8'];
        yield 'encoding_prescan.c alias_utf_16' => ['<meta charset=utf-16>', 'UTF-8'];
        yield 'encoding_prescan.c alias_unicode' => ['<meta charset=unicode>', 'UTF-8'];
        yield 'encoding_prescan.c alias_unicodefeff' => ['<meta charset=unicodefeff>', 'UTF-8'];
        yield 'encoding_prescan.c alias_unicodefffe' => ['<meta charset=unicodefffe>', 'UTF-8'];
        yield 'encoding_prescan.c alias_csunicode' => ['<meta charset=csunicode>', 'UTF-8'];
        yield 'encoding_prescan.c alias_iso_10646_ucs_2' => ['<meta charset=iso-10646-ucs-2>', 'UTF-8'];
        yield 'encoding_prescan.c alias_ucs_2' => ['<meta charset=ucs-2>', 'UTF-8'];
        yield 'encoding_prescan.c alias_x_user_defined lowercase' => ['<meta charset=x-user-defined>', 'windows-1252'];
        yield 'encoding_prescan.c alias_x_user_defined uppercase' => ['<meta charset=X-USER-DEFINED>', 'windows-1252'];
        yield 'encoding_prescan.c no_alias_match iso-8859-1' => ['<meta charset=iso-8859-1>', 'iso-8859-1'];
        yield 'encoding_prescan.c no_alias_match koi8-r' => ['<meta charset=koi8-r>', 'koi8-r'];
        yield 'encoding_prescan.c no_alias_match shift_jis' => ['<meta charset=shift_jis>', 'shift_jis'];
        yield 'encoding_prescan.c no_alias_match euc-jp' => ['<meta charset=euc-jp>', 'euc-jp'];
        yield 'encoding_prescan.c charset_and_http_equiv http first' => ['<meta http-equiv="content-type" charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c charset_and_http_equiv charset first' => ['<meta charset=utf-8 http-equiv="content-type">', 'utf-8'];
        yield 'encoding_prescan.c meta_content_unclosed_quote' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8">', null];
        yield 'encoding_prescan.c meta_content_unclosed_quote trailing spaces' => ['<meta http-equiv="content-type" content="text/html; charset=\'utf-8   ">', null];
        yield 'encoding_prescan.c meta_http_equiv_wrong_value typo' => ['<meta http-equiv="content-typ" content="text/html; charset=utf-8">', null];
        yield 'encoding_prescan.c meta_http_equiv_wrong_value refresh' => ['<meta http-equiv="refresh" content="text/html; charset=utf-8">', null];
        yield 'encoding_prescan.c html_before_meta tag' => ["<html>\n <meta http-equiv=\"content-type\" charset=utf-8>", 'utf-8'];
        yield 'encoding_prescan.c html_before_meta comment' => ['<!-- comment --><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c html_before_meta processing instruction' => ['<?xml version="1.0"?><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c end_tag_skipped attributes' => ["</html lala='><meta charset=cp1251>'>\n <meta http-equiv=\"content-type\" charset=utf-8>", 'utf-8'];
        yield 'encoding_prescan.c end_tag_skipped div' => ['</div><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c non_meta_tags_skipped div' => ['<div id="test" class="foo"><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c non_meta_tags_skipped link' => ['<link rel="stylesheet" href="style.css"><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_extra_attributes viewport' => ['<meta charset="windows-1251" name="viewport" content="width">', 'windows-1251'];
        yield 'encoding_prescan.c meta_extra_attributes bogus' => ['<meta bu charset="windows-1251" be name="viewport" bu content="width" be>', 'windows-1251'];
        yield 'encoding_prescan.c comment_before_meta empty' => ['<!-- --><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c comment_before_meta multiline' => ["<!-- multi\nline\ncomment --><meta charset=utf-8>", 'utf-8'];
        yield 'encoding_prescan.c comment_before_meta internal gt' => ['<!-- foo > bar --><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c bogus_comment_before_meta doctype' => ['<!DOCTYPE html><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c bogus_comment_before_meta foo' => ['<!foo bar><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c processing_instruction_before_meta' => ['<?xml version="1.0"?><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c non_alpha_after_lt digit' => ['<1><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c non_alpha_after_lt space' => ['< ><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c end_tag_non_alpha' => ['</1><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c end_tag_short' => ['</a', null];
        yield 'encoding_prescan.c truncated_data charset' => ['<meta charset=utf-', null];
        yield 'encoding_prescan.c truncated_data char' => ['<meta char', null];
        yield 'encoding_prescan.c truncated_data meta' => ['<meta', null];
        yield 'encoding_prescan.c truncated_data lt' => ['<', null];
        yield 'encoding_prescan.c lone_lt_at_end' => ['hello<', null];
        yield 'encoding_prescan.c not_a_comment_excl' => ['<!-x><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c excl_short_data four bytes' => ['<!abc', null];
        yield 'encoding_prescan.c excl_short_data three bytes' => ['<!ab', null];
        yield 'encoding_prescan.c non_meta_alpha_tag div' => ['<div class="x"><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c non_meta_alpha_tag span' => ['<span><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_like_tag_name' => ['<metadata charset=utf-8><meta charset=koi8-r>', 'koi8-r'];
        yield 'encoding_prescan.c meta_separators tab' => ["<meta\tcharset=utf-8>", 'utf-8'];
        yield 'encoding_prescan.c meta_separators lf' => ["<meta\ncharset=utf-8>", 'utf-8'];
        yield 'encoding_prescan.c meta_separators ff' => ["<meta\x0C" . 'charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_separators cr' => ["<meta\rcharset=utf-8>", 'utf-8'];
        yield 'encoding_prescan.c meta_separators slash' => ['<meta/charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c alpha_tag_short_data abcde' => ['<abcde', null];
        yield 'encoding_prescan.c alpha_tag_short_data abcd' => ['<abcd', null];
        yield 'encoding_prescan.c tag_ends_at_gt' => ['<div><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c empty_data' => ['', null];
        yield 'encoding_prescan.c multiple_meta_first' => ['<meta charset=windows-1251><meta charset=utf-8>', 'windows-1251'];
        yield 'encoding_prescan.c alias_via_content utf-16le' => ['<meta http-equiv="content-type" content="text/html; charset=utf-16le">', 'UTF-8'];
        yield 'encoding_prescan.c alias_via_content x-user-defined' => ['<meta http-equiv="content-type" content="text/html; charset=x-user-defined">', 'windows-1252'];
        yield 'encoding_prescan.c duplicate_attribute_ignored' => ['<meta charset=windows-1251 charset=utf-8>', 'windows-1251'];
        yield 'encoding_prescan.c short_attribute_name_skipped foo' => ['<meta foo=bar charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c short_attribute_name_skipped id' => ['<meta id=x charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c attribute_no_value' => ['<meta http-equiv charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_case_insensitive upper' => ['<META charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_case_insensitive title' => ['<Meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c meta_case_insensitive mixed' => ['<mEtA charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c attribute_case_insensitive charset' => ['<meta CHARSET=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c attribute_case_insensitive http content' => ['<meta HTTP-EQUIV="content-type" CONTENT="text/html; charset=utf-8">', 'utf-8'];
        yield 'encoding_prescan.c meta_no_closing_gt' => ['<meta charset=utf-8', null];
        yield 'encoding_prescan.c content_charset_no_equals' => ['<meta http-equiv="content-type" content="charset utf-8">', null];
        yield 'encoding_prescan.c content_charset_eq_at_end' => ['<meta http-equiv="content-type" content="charset=">', null];
        yield 'encoding_prescan.c content_charset_semicolon' => ['<meta http-equiv="content-type" content="text/html; charset=utf-8; boundary=something">', 'utf-8'];
        yield 'encoding_prescan.c content_charset_space_terminated' => ['<meta http-equiv="content-type" content="text/html; charset=utf-8 extra">', 'utf-8'];
        yield 'encoding_prescan.c nul_bytes_in_data before meta' => ["<\x00meta charset=utf-8>", null];
        yield 'encoding_prescan.c nul_bytes_in_data value' => ["<meta charset=\"utf\x00" . '8">', "utf\x00" . '8'];
        yield 'encoding_prescan.c nul_bytes_in_data name' => ["<meta char\x00" . 'set=utf-8>', null];
        yield 'encoding_prescan.c utf16_wins_over_meta' => ["\x3C\x00\x3F\x00\x78\x00<meta charset=utf-8>", 'UTF-16LE'];
        yield 'encoding_prescan.c utf16le_partial_mismatch' => ["\x3C\x00\x3F\x00\x78\x01", null];
        yield 'encoding_prescan.c utf16be_partial_mismatch' => ["\x00\x3C\x00\x3F\x00\x79", null];
        yield 'encoding_prescan.c many_comments' => ['<!-- a --><!-- b --><!-- c --><!-- d --><!-- e --><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c comment_never_closes plain' => ['<!-- this comment never ends', null];
        yield 'encoding_prescan.c comment_never_closes gt no close' => ['<!-- has > but no dash dash end', null];
        yield 'encoding_prescan.c comment_end_boundary minimal' => ['<!----><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c comment_end_boundary three dashes' => ['<!---><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c comment_end_boundary extra dashes' => ['<!-----><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c pi_never_closes' => ['<?xml version', null];
        yield 'encoding_prescan.c multiple_bare_lt meta' => ['<<<<<<<<<meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c multiple_bare_lt only lt' => ['<<<<<', null];
        yield 'encoding_prescan.c meta_ends_after_separator space' => ['<meta ', null];
        yield 'encoding_prescan.c meta_ends_after_separator tab' => ["<meta\t", null];
        yield 'encoding_prescan.c attr_value_is_gt' => ['<meta charset=>', null];
        yield 'encoding_prescan.c attr_empty_quoted_value double' => ['<meta charset="">', ''];
        yield 'encoding_prescan.c attr_empty_quoted_value single' => ["<meta charset=''>", ''];
        yield 'encoding_prescan.c attr_quote_then_eof double' => ['<meta charset="', null];
        yield 'encoding_prescan.c attr_quote_then_eof single' => ["<meta charset='", null];
        yield 'encoding_prescan.c attr_eq_spaces_eof' => ['<meta charset=   ', null];
        yield 'encoding_prescan.c attr_name_slash' => ['<meta charset/>', null];
        yield 'encoding_prescan.c attr_name_to_eof' => ['<meta charsetxxxxxxxxx', null];
        yield 'encoding_prescan.c content_charset_too_short six' => ['<meta http-equiv="content-type" content="charse">', null];
        yield 'encoding_prescan.c content_charset_too_short exact' => ['<meta http-equiv="content-type" content="charset">', null];
        yield 'encoding_prescan.c content_charset_repeat' => ['<meta http-equiv="content-type" content="charset nope; charset=utf-8">', 'utf-8'];
        yield 'encoding_prescan.c content_charset_quote_in_value single' => ['<meta http-equiv="content-type" content="text/html; charset=utf-8\'">', null];
        yield 'encoding_prescan.c content_charset_quote_in_value double' => ['<meta http-equiv="content-type" content=\'text/html; charset=utf-8"\'>', null];
        yield 'encoding_prescan.c content_charset_eq_spaces_end' => ['<meta http-equiv="content-type" content="charset=   ">', null];
        yield 'encoding_prescan.c content_charset_value_to_end' => ['<meta http-equiv="content-type" content="charset=utf-8">', 'utf-8'];
        yield 'encoding_prescan.c content_charset_double_quoted single inner' => ['<meta http-equiv="content-type" content="text/html; charset=\'koi8-r\'">', 'koi8-r'];
        yield 'encoding_prescan.c content_charset_double_quoted double inner' => ['<meta http-equiv=\'content-type\' content=\'text/html; charset="koi8-r"\'>', 'koi8-r'];
        yield 'encoding_prescan.c long_non_meta_tag' => ['<metaAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c end_tag_meta' => ['</meta charset=utf-8><meta charset=koi8-r>', 'koi8-r'];
        yield 'encoding_prescan.c end_tag_uppercase' => ['</DIV><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c bare_equals_attr' => ['<meta = charset=utf-8>', null];
        yield 'encoding_prescan.c attr_whitespace_chaos' => ["<meta \t\n\r\x0C charset \t\n\r\x0C = \t\n\r\x0C utf-8 \t\n\r\x0C >", 'utf-8'];
        yield 'encoding_prescan.c comment_dashes_no_gt empty' => ['<!--', null];
        yield 'encoding_prescan.c comment_dashes_no_gt dash' => ['<!---', null];
        yield 'encoding_prescan.c comment_dashes_no_gt text' => ['<!-- no close', null];
        yield 'encoding_prescan.c end_tag_slash_eof slash' => ['</', null];
        yield 'encoding_prescan.c end_tag_slash_eof alpha' => ['</x', null];
        yield 'encoding_prescan.c end_tag_nonalpha_no_gt' => ['</123', null];
        yield 'encoding_prescan.c attr_parse_tight' => ['<meta http-equiv=content-type content=charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c content_many_charset_substrings' => ['<meta http-equiv="content-type" content="charsetX charsetY charset=utf-8">', 'utf-8'];
        yield 'encoding_prescan.c attr_unquoted_value_gt' => ['<meta charset=utf-8>rest', 'utf-8'];
        yield 'encoding_prescan.c high_bytes tag' => ["<\xFF><meta charset=utf-8>", 'utf-8'];
        yield 'encoding_prescan.c high_bytes value' => ["<meta charset=\xE4\xB8\xAD\xE6\x96\x87>", "\xE4\xB8\xAD\xE6\x96\x87"];
        yield 'encoding_prescan.c only_spaces_between' => ["   \t\n\r   <meta charset=utf-8>", 'utf-8'];
        yield 'encoding_prescan.c meta_fifth_char_boundary closed' => ['<meta><meta charset=utf-8>', null];
        yield 'encoding_prescan.c meta_fifth_char_boundary slash' => ['<meta/><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c duplicate_content_ignored' => ['<meta http-equiv="content-type" content="text/html; charset=utf-8" content="text/html; charset=koi8-r">', 'utf-8'];
        yield 'encoding_prescan.c content_then_charset' => ['<meta http-equiv="content-type" content="text/html; charset=koi8-r" charset=utf-8>', 'koi8-r'];
        yield 'encoding_prescan.c all_whitespace' => ["     \t\n\r\x0C     ", null];
        yield 'encoding_prescan.c gt_without_lt' => ['>>>><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c skip_name_reaches_end' => ['<divclassname', null];
        yield 'encoding_prescan.c content_charset_eq_immediate_semicolon' => ['<meta http-equiv="content-type" content="charset=;">', ''];
        yield 'encoding_prescan.c content_charset_eq_immediate_space' => ['<meta http-equiv="content-type" content="charset= ">', null];
        yield 'encoding_prescan.c bogus_excl_immediate_gt' => ['<!><meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c attr_eq_at_eof tight' => ['<meta charset=', null];
        yield 'encoding_prescan.c attr_eq_at_eof spaces' => ['<meta charset =', null];
        yield 'encoding_prescan.c non_meta_unclosed_attr_quote' => ['<div class="unclosed><meta charset=utf-8>', null];
        yield 'encoding_prescan.c minimal_comment_5bytes' => ['<!-->rest<meta charset=utf-8>', 'utf-8'];
        yield 'encoding_prescan.c comment_immediate_gt_6plus' => ['<!--><meta charset=utf-8>', 'utf-8'];
    }

    #[DataProvider('upstreamMetaProvider')]
    public function testUpstreamEncodingByMeta(string $html, ?string $expected, int $index): void
    {
        self::assertMetaEntry($html, $expected, $index);
        self::assertIncrementalMetaEntry($html, $expected, $index);
    }

    #[DataProvider('upstreamPrescanProvider')]
    public function testUpstreamEncodingPrescan(string $html, ?string $expected): void
    {
        $encoding = new Encoding();

        self::assertSame($expected, $encoding->prescan($html));
        self::assertSame($expected, Encoding::prescanName($html));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function html5libEncodingProvider(): iterable
    {
        $directory = dirname(__DIR__, 2) . '/upstream/lexbor/test/files/lexbor/html/html5lib_encoding';
        $files = glob($directory . '/*.dat');

        if ($files === false || $files === []) {
            throw new \RuntimeException('Unable to load upstream html5lib encoding fixtures.');
        }

        sort($files);

        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                throw new \RuntimeException("Unable to read upstream html5lib encoding fixture: {$file}");
            }

            preg_match_all('/^#data\n(.*?)\n#encoding\n([^\n]*)/ms', $contents, $matches, PREG_SET_ORDER);
            if ($matches === []) {
                throw new \RuntimeException("No upstream html5lib encoding fixtures found in: {$file}");
            }

            foreach ($matches as $index => $match) {
                yield basename($file) . ' #' . ($index + 1) => [$match[1], $match[2]];
            }
        }
    }

    #[DataProvider('html5libEncodingProvider')]
    public function testHtml5libEncodingFixtures(string $html, string $expected): void
    {
        self::assertSame(0, strcasecmp($expected, Encoding::determineName($html)));
    }

    private static function assertMetaEntry(string $html, ?string $expected, int $index): void
    {
        $encoding = new Encoding();

        self::assertSame(Status::Ok, $encoding->determine($html));
        self::assertSame($expected, $encoding->metaEntry($index));
        self::assertSame($expected, $encoding->metaEntries()[$index] ?? null);
    }

    private static function assertIncrementalMetaEntry(string $html, ?string $expected, int $index): void
    {
        $encoding = new Encoding();
        $length = strlen($html);

        for ($offset = 0; $offset <= $length; $offset++) {
            $encoding->clean();
            self::assertSame(Status::Ok, $encoding->determine(substr($html, 0, $offset)));

            $entry = $encoding->metaEntry($index);
            if ($entry !== null) {
                self::assertSame($expected, $entry);
                return;
            }
        }

        self::assertNull($expected);
    }
}

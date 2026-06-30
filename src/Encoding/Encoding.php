<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

final class Encoding
{
    public const DEFAULT = 'DEFAULT';
    public const UTF_8 = 'UTF-8';
    public const UTF_16BE = 'UTF-16BE';
    public const UTF_16LE = 'UTF-16LE';
    public const WINDOWS_1252 = 'windows-1252';
    public const X_USER_DEFINED = 'x-user-defined';

    /**
     * Encoding labels generated from upstream/lexbor/source/lexbor/encoding/res.c.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'koi8' => 'KOI8-R',
        'iso8859-2' => 'ISO-8859-2',
        'iso-8859-2' => 'ISO-8859-2',
        'iso88592' => 'ISO-8859-2',
        'gb2312' => 'GBK',
        'gb_2312' => 'GBK',
        'euc-jp' => 'EUC-JP',
        'gb_2312-80' => 'GBK',
        'ecma-114' => 'ISO-8859-6',
        'ibm819' => 'windows-1252',
        'x-sjis' => 'Shift_JIS',
        'iso88599' => 'windows-1254',
        'cp1254' => 'windows-1254',
        'iso-ir-149' => 'EUC-KR',
        'iso_8859-2' => 'ISO-8859-2',
        'windows-31j' => 'Shift_JIS',
        'cp1252' => 'windows-1252',
        'csisolatin4' => 'ISO-8859-4',
        'iso_8859-9:1989' => 'windows-1254',
        'cp866' => 'IBM866',
        'cp1256' => 'windows-1256',
        'sjis' => 'Shift_JIS',
        'l6' => 'ISO-8859-10',
        'csmacintosh' => 'macintosh',
        'x-cp1258' => 'windows-1258',
        'csisolatin6' => 'ISO-8859-10',
        'latin6' => 'ISO-8859-10',
        'csiso58gb231280' => 'GBK',
        'l2' => 'ISO-8859-2',
        'euc-kr' => 'EUC-KR',
        'csgb2312' => 'GBK',
        'windows-1251' => 'windows-1251',
        'latin2' => 'ISO-8859-2',
        'iso885914' => 'ISO-8859-14',
        'iso8859-14' => 'ISO-8859-14',
        'iso-8859-14' => 'ISO-8859-14',
        'ecma-118' => 'ISO-8859-7',
        'elot_928' => 'ISO-8859-7',
        'csisolatin2' => 'ISO-8859-2',
        'windows-1250' => 'windows-1250',
        'x-euc-jp' => 'EUC-JP',
        'unicode-1-1-utf-8' => 'UTF-8',
        'iso8859-9' => 'windows-1254',
        'iso-ir-109' => 'ISO-8859-3',
        'iso-8859-9' => 'windows-1254',
        'iso_8859-9' => 'windows-1254',
        'koi' => 'KOI8-R',
        'logical' => 'ISO-8859-8-I',
        'iso-2022-kr' => 'replacement',
        'replacement' => 'replacement',
        'csibm866' => 'IBM866',
        'x-cp1251' => 'windows-1251',
        'x-x-big5' => 'Big5',
        'iso-2022-cn-ext' => 'replacement',
        'ksc5601' => 'EUC-KR',
        'ksc_5601' => 'EUC-KR',
        'hz-gb-2312' => 'replacement',
        'shift-jis' => 'Shift_JIS',
        'shift_jis' => 'Shift_JIS',
        'cseuckr' => 'EUC-KR',
        'greek8' => 'ISO-8859-7',
        'cp1258' => 'windows-1258',
        'ibm866' => 'IBM866',
        'csiso2022kr' => 'replacement',
        'iso88596' => 'ISO-8859-6',
        'iso8859-6' => 'ISO-8859-6',
        'iso-8859-6' => 'ISO-8859-6',
        'iso-8859-16' => 'ISO-8859-16',
        'l9' => 'ISO-8859-15',
        'iso88594' => 'ISO-8859-4',
        'koi8-r' => 'KOI8-R',
        866 => 'IBM866',
        'iso8859-4' => 'ISO-8859-4',
        'windows-1253' => 'windows-1253',
        'l5' => 'windows-1254',
        'arabic' => 'ISO-8859-6',
        'iso-8859-4' => 'ISO-8859-4',
        'koi8-u' => 'KOI8-U',
        'latin5' => 'windows-1254',
        'iso_8859-4' => 'ISO-8859-4',
        'l1' => 'windows-1252',
        'iso-ir-144' => 'ISO-8859-5',
        'x-cp1255' => 'windows-1255',
        'windows-1252' => 'windows-1252',
        'latin1' => 'windows-1252',
        'iso88591' => 'windows-1252',
        'iso8859-1' => 'windows-1252',
        'iso-ir-101' => 'ISO-8859-2',
        'iso-8859-11' => 'windows-874',
        'csiso2022jp' => 'ISO-2022-JP',
        'cskoi8r' => 'KOI8-R',
        'dos-874' => 'windows-874',
        'iso_8859-6' => 'ISO-8859-6',
        'windows-874' => 'windows-874',
        'utf-16' => 'UTF-16LE',
        'iso-ir-126' => 'ISO-8859-7',
        'asmo-708' => 'ISO-8859-6',
        'iso-ir-58' => 'GBK',
        'iso-8859-8' => 'ISO-8859-8',
        'koi8_r' => 'KOI8-R',
        'x-mac-cyrillic' => 'x-mac-cyrillic',
        'cp1251' => 'windows-1251',
        'ansi_x3.4-1968' => 'windows-1252',
        'iso_8859-3:1988' => 'ISO-8859-3',
        'ks_c_5601-1987' => 'EUC-KR',
        'sun_eu_greek' => 'ISO-8859-7',
        'csisolatin1' => 'windows-1252',
        'koi8-ru' => 'KOI8-U',
        'chinese' => 'GBK',
        'cp1253' => 'windows-1253',
        'visual' => 'ISO-8859-8',
        'csisolatincyrillic' => 'ISO-8859-5',
        'csiso88596e' => 'ISO-8859-6',
        'iso-8859-6-e' => 'ISO-8859-6',
        'csisolatin3' => 'ISO-8859-3',
        'windows-1255' => 'windows-1255',
        'x-cp1252' => 'windows-1252',
        'csbig5' => 'Big5',
        'cn-big5' => 'Big5',
        'iso8859-13' => 'ISO-8859-13',
        'iso-8859-13' => 'ISO-8859-13',
        'iso885911' => 'windows-874',
        'csisolatin5' => 'windows-1254',
        'us-ascii' => 'windows-1252',
        'iso-8859-1' => 'windows-1252',
        'cp1257' => 'windows-1257',
        'l4' => 'ISO-8859-4',
        'iso_8859-1' => 'windows-1252',
        'gbk' => 'GBK',
        'x-mac-roman' => 'macintosh',
        'greek' => 'ISO-8859-7',
        'iso8859-11' => 'windows-874',
        'cp819' => 'windows-1252',
        'x-mac-ukrainian' => 'x-mac-cyrillic',
        'windows-1254' => 'windows-1254',
        'iso88598' => 'ISO-8859-8',
        'big5-hkscs' => 'Big5',
        'iso8859-8' => 'ISO-8859-8',
        'x-cp1253' => 'windows-1253',
        'iso-ir-138' => 'ISO-8859-8',
        'csisolatingreek' => 'ISO-8859-7',
        'iso_8859-8' => 'ISO-8859-8',
        'iso-ir-148' => 'windows-1254',
        'tis-620' => 'windows-874',
        'cyrillic' => 'ISO-8859-5',
        'iso_8859-4:1988' => 'ISO-8859-4',
        'iso_8859-5:1988' => 'ISO-8859-5',
        'ks_c_5601-1989' => 'EUC-KR',
        'iso_8859-8:1988' => 'ISO-8859-8',
        'iso88595' => 'ISO-8859-5',
        'iso885915' => 'ISO-8859-15',
        'x-gbk' => 'GBK',
        'iso-8859-15' => 'ISO-8859-15',
        'utf-16be' => 'UTF-16BE',
        'utf-16le' => 'UTF-16LE',
        'iso-2022-cn' => 'replacement',
        'csisolatinarabic' => 'ISO-8859-6',
        'windows-1257' => 'windows-1257',
        'x-user-defined' => 'x-user-defined',
        'x-cp1256' => 'windows-1256',
        'csiso88598e' => 'ISO-8859-8',
        'iso-8859-8-e' => 'ISO-8859-8',
        'cp1255' => 'windows-1255',
        'ms_kanji' => 'Shift_JIS',
        'iso88593' => 'ISO-8859-3',
        'iso885913' => 'ISO-8859-13',
        'x-cp1250' => 'windows-1250',
        'csshiftjis' => 'Shift_JIS',
        'hebrew' => 'ISO-8859-8',
        'iso8859-3' => 'ISO-8859-3',
        'ascii' => 'windows-1252',
        'iso885910' => 'ISO-8859-10',
        'iso8859-10' => 'ISO-8859-10',
        'iso-8859-10' => 'ISO-8859-10',
        'iso-8859-3' => 'ISO-8859-3',
        'ms932' => 'Shift_JIS',
        'iso_8859-3' => 'ISO-8859-3',
        'iso-8859-6-i' => 'ISO-8859-6',
        'l3' => 'ISO-8859-3',
        'cseucpkdfmtjapanese' => 'EUC-JP',
        'korean' => 'EUC-KR',
        'iso88597' => 'ISO-8859-7',
        'latin3' => 'ISO-8859-3',
        'iso-ir-157' => 'ISO-8859-10',
        'csiso88596i' => 'ISO-8859-6',
        'csiso88598i' => 'ISO-8859-8-I',
        'latin4' => 'ISO-8859-4',
        'iso-2022-jp' => 'ISO-2022-JP',
        'iso_8859-2:1987' => 'ISO-8859-2',
        'csisolatinhebrew' => 'ISO-8859-8',
        'csksc56011987' => 'EUC-KR',
        'windows-1256' => 'windows-1256',
        'csisolatin9' => 'ISO-8859-15',
        'iso8859-5' => 'ISO-8859-5',
        'iso8859-15' => 'ISO-8859-15',
        'iso-8859-5' => 'ISO-8859-5',
        'x-cp1254' => 'windows-1254',
        'iso_8859-5' => 'ISO-8859-5',
        'cp1250' => 'windows-1250',
        'gb18030' => 'gb18030',
        'utf8' => 'UTF-8',
        'utf-8' => 'UTF-8',
        'iso_8859-15' => 'ISO-8859-15',
        'x-cp1257' => 'windows-1257',
        'iso-ir-110' => 'ISO-8859-4',
        'iso-ir-100' => 'windows-1252',
        'iso-8859-8-i' => 'ISO-8859-8-I',
        'mac' => 'macintosh',
        'big5' => 'Big5',
        'windows-1258' => 'windows-1258',
        'iso8859-7' => 'ISO-8859-7',
        'iso-ir-127' => 'ISO-8859-6',
        'iso-8859-7' => 'ISO-8859-7',
        'iso_8859-7' => 'ISO-8859-7',
        'iso_8859-6:1987' => 'ISO-8859-6',
        'iso_8859-7:1987' => 'ISO-8859-7',
        'iso_8859-1:1987' => 'windows-1252',
        'windows-949' => 'EUC-KR',
        'macintosh' => 'macintosh',
    ];

    public static function bomSniff(string $data): string
    {
        return match (true) {
            str_starts_with($data, "\xEF\xBB\xBF") => self::UTF_8,
            str_starts_with($data, "\xFE\xFF") => self::UTF_16BE,
            str_starts_with($data, "\xFF\xFE") => self::UTF_16LE,
            default => self::DEFAULT,
        };
    }

    public static function dataByPreName(string $name): ?string
    {
        $normalized = self::trimAsciiWhitespace($name);

        if ($normalized === '') {
            return null;
        }

        return self::LABELS[strtolower($normalized)] ?? null;
    }

    public static function dataPrescanValidate(string $name): ?string
    {
        $encoding = self::dataByPreName($name);

        return match ($encoding) {
            self::UTF_16BE, self::UTF_16LE => self::UTF_8,
            self::X_USER_DEFINED => self::WINDOWS_1252,
            default => $encoding,
        };
    }

    public static function prescanValidate(string $name): string
    {
        return self::dataPrescanValidate($name) ?? self::DEFAULT;
    }

    private static function trimAsciiWhitespace(string $value): string
    {
        return trim($value, " \t\n\r\f");
    }
}

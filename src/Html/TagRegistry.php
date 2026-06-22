<?php

declare(strict_types=1);

namespace Lexbor\Html;

final class TagRegistry
{
    /**
     * @var array<int, string>
     */
    private const KNOWN_NAMES = [
        0 => '#undef',
        1 => '#end-of-file',
        2 => '#text',
        3 => '#document',
        4 => '!--',
        5 => '!doctype',
        6 => 'a',
        7 => 'abbr',
        8 => 'acronym',
        9 => 'address',
        10 => 'altglyph',
        11 => 'altglyphdef',
        12 => 'altglyphitem',
        13 => 'animatecolor',
        14 => 'animatemotion',
        15 => 'animatetransform',
        16 => 'annotation-xml',
        17 => 'applet',
        18 => 'area',
        19 => 'article',
        20 => 'aside',
        21 => 'audio',
        22 => 'b',
        23 => 'base',
        24 => 'basefont',
        25 => 'bdi',
        26 => 'bdo',
        27 => 'bgsound',
        28 => 'big',
        29 => 'blink',
        30 => 'blockquote',
        31 => 'body',
        32 => 'br',
        33 => 'button',
        34 => 'canvas',
        35 => 'caption',
        36 => 'center',
        37 => 'cite',
        38 => 'clippath',
        39 => 'code',
        40 => 'col',
        41 => 'colgroup',
        42 => 'data',
        43 => 'datalist',
        44 => 'dd',
        45 => 'del',
        46 => 'desc',
        47 => 'details',
        48 => 'dfn',
        49 => 'dialog',
        50 => 'dir',
        51 => 'div',
        52 => 'dl',
        53 => 'dt',
        54 => 'em',
        55 => 'embed',
        56 => 'feblend',
        57 => 'fecolormatrix',
        58 => 'fecomponenttransfer',
        59 => 'fecomposite',
        60 => 'feconvolvematrix',
        61 => 'fediffuselighting',
        62 => 'fedisplacementmap',
        63 => 'fedistantlight',
        64 => 'fedropshadow',
        65 => 'feflood',
        66 => 'fefunca',
        67 => 'fefuncb',
        68 => 'fefuncg',
        69 => 'fefuncr',
        70 => 'fegaussianblur',
        71 => 'feimage',
        72 => 'femerge',
        73 => 'femergenode',
        74 => 'femorphology',
        75 => 'feoffset',
        76 => 'fepointlight',
        77 => 'fespecularlighting',
        78 => 'fespotlight',
        79 => 'fetile',
        80 => 'feturbulence',
        81 => 'fieldset',
        82 => 'figcaption',
        83 => 'figure',
        84 => 'font',
        85 => 'footer',
        86 => 'foreignobject',
        87 => 'form',
        88 => 'frame',
        89 => 'frameset',
        90 => 'glyphref',
        91 => 'h1',
        92 => 'h2',
        93 => 'h3',
        94 => 'h4',
        95 => 'h5',
        96 => 'h6',
        97 => 'head',
        98 => 'header',
        99 => 'hgroup',
        100 => 'hr',
        101 => 'html',
        102 => 'i',
        103 => 'iframe',
        104 => 'image',
        105 => 'img',
        106 => 'input',
        107 => 'ins',
        108 => 'isindex',
        109 => 'kbd',
        110 => 'keygen',
        111 => 'label',
        112 => 'legend',
        113 => 'li',
        114 => 'lineargradient',
        115 => 'link',
        116 => 'listing',
        117 => 'main',
        118 => 'malignmark',
        119 => 'map',
        120 => 'mark',
        121 => 'marquee',
        122 => 'math',
        123 => 'menu',
        124 => 'meta',
        125 => 'meter',
        126 => 'mfenced',
        127 => 'mglyph',
        128 => 'mi',
        129 => 'mn',
        130 => 'mo',
        131 => 'ms',
        132 => 'mtext',
        133 => 'multicol',
        134 => 'nav',
        135 => 'nextid',
        136 => 'nobr',
        137 => 'noembed',
        138 => 'noframes',
        139 => 'noscript',
        140 => 'object',
        141 => 'ol',
        142 => 'optgroup',
        143 => 'option',
        144 => 'output',
        145 => 'p',
        146 => 'param',
        147 => 'path',
        148 => 'picture',
        149 => 'plaintext',
        150 => 'pre',
        151 => 'progress',
        152 => 'q',
        153 => 'radialgradient',
        154 => 'rb',
        155 => 'rp',
        156 => 'rt',
        157 => 'rtc',
        158 => 'ruby',
        159 => 's',
        160 => 'samp',
        161 => 'script',
        162 => 'search',
        163 => 'section',
        164 => 'select',
        165 => 'selectedcontent',
        166 => 'slot',
        167 => 'small',
        168 => 'source',
        169 => 'spacer',
        170 => 'span',
        171 => 'strike',
        172 => 'strong',
        173 => 'style',
        174 => 'sub',
        175 => 'summary',
        176 => 'sup',
        177 => 'svg',
        178 => 'table',
        179 => 'tbody',
        180 => 'td',
        181 => 'template',
        182 => 'textarea',
        183 => 'textpath',
        184 => 'tfoot',
        185 => 'th',
        186 => 'thead',
        187 => 'time',
        188 => 'title',
        189 => 'tr',
        190 => 'track',
        191 => 'tt',
        192 => 'u',
        193 => 'ul',
        194 => 'var',
        195 => 'video',
        196 => 'wbr',
        197 => 'xmp',
    ];

    /**
     * @var array<string, int>|null
     */
    private static ?array $knownIds = null;

    /**
     * @var array<int, string>
     */
    private static array $dynamicNames = [];

    private static int $nextDynamicId = Tag::LAST_ENTRY + 1;

    /**
     * @var array<string, int>
     */
    private array $dynamicIds = [];

    public function idForName(string $name): int
    {
        $normalized = strtolower($name);
        $knownId = self::knownIds()[$normalized] ?? null;

        if ($knownId !== null) {
            return $knownId;
        }

        if (isset($this->dynamicIds[$normalized])) {
            return $this->dynamicIds[$normalized];
        }

        $id = self::$nextDynamicId++;
        $this->dynamicIds[$normalized] = $id;
        self::$dynamicNames[$id] = $normalized;

        return $id;
    }

    public static function nameById(int $tagId): ?string
    {
        return self::KNOWN_NAMES[$tagId] ?? self::$dynamicNames[$tagId] ?? null;
    }

    /**
     * @return array<string, int>
     */
    private static function knownIds(): array
    {
        if (self::$knownIds === null) {
            self::$knownIds = array_flip(self::KNOWN_NAMES);
        }

        return self::$knownIds;
    }
}
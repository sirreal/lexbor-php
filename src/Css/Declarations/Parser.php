<?php

declare(strict_types=1);

namespace Lexbor\Css\Declarations;

use Lexbor\Css\Syntax\Token;
use Lexbor\Css\Syntax\Tokenizer;

final class Parser
{
    private const array KNOWN_PROPERTIES = [
        'align-content' => true,
        'align-items' => true,
        'align-self' => true,
        'alignment-baseline' => true,
        'background-color' => true,
        'baseline-shift' => true,
        'baseline-source' => true,
        'border' => true,
        'border-bottom' => true,
        'border-bottom-color' => true,
        'border-left' => true,
        'border-left-color' => true,
        'border-right' => true,
        'border-right-color' => true,
        'border-top' => true,
        'border-top-color' => true,
        'box-sizing' => true,
        'bottom' => true,
        'clear' => true,
        'color' => true,
        'direction' => true,
        'display' => true,
        'dominant-baseline' => true,
        'flex' => true,
        'flex-basis' => true,
        'flex-direction' => true,
        'flex-flow' => true,
        'flex-grow' => true,
        'flex-shrink' => true,
        'flex-wrap' => true,
        'float' => true,
        'float-defer' => true,
        'float-offset' => true,
        'float-reference' => true,
        'font-family' => true,
        'font-size' => true,
        'font-stretch' => true,
        'font-style' => true,
        'font-weight' => true,
        'hanging-punctuation' => true,
        'height' => true,
        'hyphens' => true,
        'inset-block-end' => true,
        'inset-block-start' => true,
        'inset-inline-end' => true,
        'inset-inline-start' => true,
        'justify-content' => true,
        'left' => true,
        'letter-spacing' => true,
        'line-break' => true,
        'line-height' => true,
        'margin' => true,
        'margin-bottom' => true,
        'margin-left' => true,
        'margin-right' => true,
        'margin-top' => true,
        'max-height' => true,
        'max-width' => true,
        'min-height' => true,
        'min-width' => true,
        'opacity' => true,
        'order' => true,
        'overflow-block' => true,
        'overflow-inline' => true,
        'overflow-wrap' => true,
        'overflow-x' => true,
        'overflow-y' => true,
        'padding' => true,
        'padding-bottom' => true,
        'padding-left' => true,
        'padding-right' => true,
        'padding-top' => true,
        'position' => true,
        'right' => true,
        'tab-size' => true,
        'text-align' => true,
        'text-align-all' => true,
        'text-align-last' => true,
        'text-combine-upright' => true,
        'text-decoration' => true,
        'text-decoration-color' => true,
        'text-decoration-line' => true,
        'text-decoration-style' => true,
        'text-indent' => true,
        'text-justify' => true,
        'text-orientation' => true,
        'text-overflow' => true,
        'text-transform' => true,
        'top' => true,
        'unicode-bidi' => true,
        'vertical-align' => true,
        'visibility' => true,
        'white-space' => true,
        'width' => true,
        'word-break' => true,
        'word-wrap' => true,
        'word-spacing' => true,
        'writing-mode' => true,
        'z-index' => true,
    ];

    private const array CSS_WIDE_KEYWORDS = [
        'inherit' => true,
        'initial' => true,
        'revert' => true,
        'unset' => true,
    ];

    private const array COLOR_PROPERTIES = [
        'background-color' => true,
        'border-bottom-color' => true,
        'border-left-color' => true,
        'border-right-color' => true,
        'border-top-color' => true,
        'color' => true,
        'text-decoration-color' => true,
    ];

    private const array BORDER_PROPERTIES = [
        'border' => true,
        'border-bottom' => true,
        'border-left' => true,
        'border-right' => true,
        'border-top' => true,
    ];

    private const array BORDER_WIDTH_KEYWORDS = [
        'medium' => true,
        'thick' => true,
        'thin' => true,
    ];

    private const array BORDER_STYLE_KEYWORDS = [
        'dashed' => true,
        'dotted' => true,
        'double' => true,
        'groove' => true,
        'hidden' => true,
        'inset' => true,
        'none' => true,
        'outset' => true,
        'ridge' => true,
        'solid' => true,
    ];

    private const array COLOR_KEYWORDS = [
        'accentcolor' => true,
        'accentcolortext' => true,
        'activetext' => true,
        'aliceblue' => true,
        'antiquewhite' => true,
        'aqua' => true,
        'aquamarine' => true,
        'azure' => true,
        'beige' => true,
        'bisque' => true,
        'black' => true,
        'blanchedalmond' => true,
        'blue' => true,
        'blueviolet' => true,
        'brown' => true,
        'burlywood' => true,
        'buttonborder' => true,
        'buttonface' => true,
        'buttontext' => true,
        'cadetblue' => true,
        'canvas' => true,
        'canvastext' => true,
        'chartreuse' => true,
        'chocolate' => true,
        'coral' => true,
        'cornflowerblue' => true,
        'cornsilk' => true,
        'crimson' => true,
        'currentcolor' => true,
        'cyan' => true,
        'darkblue' => true,
        'darkcyan' => true,
        'darkgoldenrod' => true,
        'darkgray' => true,
        'darkgreen' => true,
        'darkgrey' => true,
        'darkkhaki' => true,
        'darkmagenta' => true,
        'darkolivegreen' => true,
        'darkorange' => true,
        'darkorchid' => true,
        'darkred' => true,
        'darksalmon' => true,
        'darkseagreen' => true,
        'darkslateblue' => true,
        'darkslategray' => true,
        'darkslategrey' => true,
        'darkturquoise' => true,
        'darkviolet' => true,
        'deeppink' => true,
        'deepskyblue' => true,
        'dimgray' => true,
        'dimgrey' => true,
        'dodgerblue' => true,
        'field' => true,
        'fieldtext' => true,
        'firebrick' => true,
        'floralwhite' => true,
        'forestgreen' => true,
        'fuchsia' => true,
        'gainsboro' => true,
        'ghostwhite' => true,
        'gold' => true,
        'goldenrod' => true,
        'gray' => true,
        'graytext' => true,
        'green' => true,
        'greenyellow' => true,
        'grey' => true,
        'highlight' => true,
        'highlighttext' => true,
        'honeydew' => true,
        'hotpink' => true,
        'indianred' => true,
        'indigo' => true,
        'ivory' => true,
        'khaki' => true,
        'lavender' => true,
        'lavenderblush' => true,
        'lawngreen' => true,
        'lemonchiffon' => true,
        'lightblue' => true,
        'lightcoral' => true,
        'lightcyan' => true,
        'lightgoldenrodyellow' => true,
        'lightgray' => true,
        'lightgreen' => true,
        'lightgrey' => true,
        'lightpink' => true,
        'lightsalmon' => true,
        'lightseagreen' => true,
        'lightskyblue' => true,
        'lightslategray' => true,
        'lightslategrey' => true,
        'lightsteelblue' => true,
        'lightyellow' => true,
        'lime' => true,
        'limegreen' => true,
        'linen' => true,
        'linktext' => true,
        'magenta' => true,
        'mark' => true,
        'marktext' => true,
        'maroon' => true,
        'mediumaquamarine' => true,
        'mediumblue' => true,
        'mediumorchid' => true,
        'mediumpurple' => true,
        'mediumseagreen' => true,
        'mediumslateblue' => true,
        'mediumspringgreen' => true,
        'mediumturquoise' => true,
        'mediumvioletred' => true,
        'midnightblue' => true,
        'mintcream' => true,
        'mistyrose' => true,
        'moccasin' => true,
        'navajowhite' => true,
        'navy' => true,
        'oldlace' => true,
        'olive' => true,
        'olivedrab' => true,
        'orange' => true,
        'orangered' => true,
        'orchid' => true,
        'palegoldenrod' => true,
        'palegreen' => true,
        'paleturquoise' => true,
        'palevioletred' => true,
        'papayawhip' => true,
        'peachpuff' => true,
        'peru' => true,
        'pink' => true,
        'plum' => true,
        'powderblue' => true,
        'purple' => true,
        'rebeccapurple' => true,
        'red' => true,
        'rosybrown' => true,
        'royalblue' => true,
        'saddlebrown' => true,
        'salmon' => true,
        'sandybrown' => true,
        'seagreen' => true,
        'seashell' => true,
        'selecteditem' => true,
        'selecteditemtext' => true,
        'sienna' => true,
        'silver' => true,
        'skyblue' => true,
        'slateblue' => true,
        'slategray' => true,
        'slategrey' => true,
        'snow' => true,
        'springgreen' => true,
        'steelblue' => true,
        'tan' => true,
        'teal' => true,
        'thistle' => true,
        'tomato' => true,
        'transparent' => true,
        'turquoise' => true,
        'violet' => true,
        'visitedtext' => true,
        'wheat' => true,
        'white' => true,
        'whitesmoke' => true,
        'yellow' => true,
        'yellowgreen' => true,
    ];

    private const array LEXBOR_VALUE_KEYWORDS = [
        'initial' => 'initial',
        'inherit' => 'inherit',
        'unset' => 'unset',
        'revert' => 'revert',
        'flex-start' => 'flex-start',
        'flex-end' => 'flex-end',
        'center' => 'center',
        'space-between' => 'space-between',
        'space-around' => 'space-around',
        'stretch' => 'stretch',
        'baseline' => 'baseline',
        'auto' => 'auto',
        'text-bottom' => 'text-bottom',
        'alphabetic' => 'alphabetic',
        'ideographic' => 'ideographic',
        'middle' => 'middle',
        'central' => 'central',
        'mathematical' => 'mathematical',
        'text-top' => 'text-top',
        '_length' => '_length',
        '_percentage' => '_percentage',
        'sub' => 'sub',
        'super' => 'super',
        'top' => 'top',
        'bottom' => 'bottom',
        'first' => 'first',
        'last' => 'last',
        'thin' => 'thin',
        'medium' => 'medium',
        'thick' => 'thick',
        'none' => 'none',
        'hidden' => 'hidden',
        'dotted' => 'dotted',
        'dashed' => 'dashed',
        'solid' => 'solid',
        'double' => 'double',
        'groove' => 'groove',
        'ridge' => 'ridge',
        'inset' => 'inset',
        'outset' => 'outset',
        'content-box' => 'content-box',
        'border-box' => 'border-box',
        'inline-start' => 'inline-start',
        'inline-end' => 'inline-end',
        'block-start' => 'block-start',
        'block-end' => 'block-end',
        'left' => 'left',
        'right' => 'right',
        'currentcolor' => 'currentcolor',
        'transparent' => 'transparent',
        'hex' => 'hex',
        'aliceblue' => 'aliceblue',
        'antiquewhite' => 'antiquewhite',
        'aqua' => 'aqua',
        'aquamarine' => 'aquamarine',
        'azure' => 'azure',
        'beige' => 'beige',
        'bisque' => 'bisque',
        'black' => 'black',
        'blanchedalmond' => 'blanchedalmond',
        'blue' => 'blue',
        'blueviolet' => 'blueviolet',
        'brown' => 'brown',
        'burlywood' => 'burlywood',
        'cadetblue' => 'cadetblue',
        'chartreuse' => 'chartreuse',
        'chocolate' => 'chocolate',
        'coral' => 'coral',
        'cornflowerblue' => 'cornflowerblue',
        'cornsilk' => 'cornsilk',
        'crimson' => 'crimson',
        'cyan' => 'cyan',
        'darkblue' => 'darkblue',
        'darkcyan' => 'darkcyan',
        'darkgoldenrod' => 'darkgoldenrod',
        'darkgray' => 'darkgray',
        'darkgreen' => 'darkgreen',
        'darkgrey' => 'darkgrey',
        'darkkhaki' => 'darkkhaki',
        'darkmagenta' => 'darkmagenta',
        'darkolivegreen' => 'darkolivegreen',
        'darkorange' => 'darkorange',
        'darkorchid' => 'darkorchid',
        'darkred' => 'darkred',
        'darksalmon' => 'darksalmon',
        'darkseagreen' => 'darkseagreen',
        'darkslateblue' => 'darkslateblue',
        'darkslategray' => 'darkslategray',
        'darkslategrey' => 'darkslategrey',
        'darkturquoise' => 'darkturquoise',
        'darkviolet' => 'darkviolet',
        'deeppink' => 'deeppink',
        'deepskyblue' => 'deepskyblue',
        'dimgray' => 'dimgray',
        'dimgrey' => 'dimgrey',
        'dodgerblue' => 'dodgerblue',
        'firebrick' => 'firebrick',
        'floralwhite' => 'floralwhite',
        'forestgreen' => 'forestgreen',
        'fuchsia' => 'fuchsia',
        'gainsboro' => 'gainsboro',
        'ghostwhite' => 'ghostwhite',
        'gold' => 'gold',
        'goldenrod' => 'goldenrod',
        'gray' => 'gray',
        'green' => 'green',
        'greenyellow' => 'greenyellow',
        'grey' => 'grey',
        'honeydew' => 'honeydew',
        'hotpink' => 'hotpink',
        'indianred' => 'indianred',
        'indigo' => 'indigo',
        'ivory' => 'ivory',
        'khaki' => 'khaki',
        'lavender' => 'lavender',
        'lavenderblush' => 'lavenderblush',
        'lawngreen' => 'lawngreen',
        'lemonchiffon' => 'lemonchiffon',
        'lightblue' => 'lightblue',
        'lightcoral' => 'lightcoral',
        'lightcyan' => 'lightcyan',
        'lightgoldenrodyellow' => 'lightgoldenrodyellow',
        'lightgray' => 'lightgray',
        'lightgreen' => 'lightgreen',
        'lightgrey' => 'lightgrey',
        'lightpink' => 'lightpink',
        'lightsalmon' => 'lightsalmon',
        'lightseagreen' => 'lightseagreen',
        'lightskyblue' => 'lightskyblue',
        'lightslategray' => 'lightslategray',
        'lightslategrey' => 'lightslategrey',
        'lightsteelblue' => 'lightsteelblue',
        'lightyellow' => 'lightyellow',
        'lime' => 'lime',
        'limegreen' => 'limegreen',
        'linen' => 'linen',
        'magenta' => 'magenta',
        'maroon' => 'maroon',
        'mediumaquamarine' => 'mediumaquamarine',
        'mediumblue' => 'mediumblue',
        'mediumorchid' => 'mediumorchid',
        'mediumpurple' => 'mediumpurple',
        'mediumseagreen' => 'mediumseagreen',
        'mediumslateblue' => 'mediumslateblue',
        'mediumspringgreen' => 'mediumspringgreen',
        'mediumturquoise' => 'mediumturquoise',
        'mediumvioletred' => 'mediumvioletred',
        'midnightblue' => 'midnightblue',
        'mintcream' => 'mintcream',
        'mistyrose' => 'mistyrose',
        'moccasin' => 'moccasin',
        'navajowhite' => 'navajowhite',
        'navy' => 'navy',
        'oldlace' => 'oldlace',
        'olive' => 'olive',
        'olivedrab' => 'olivedrab',
        'orange' => 'orange',
        'orangered' => 'orangered',
        'orchid' => 'orchid',
        'palegoldenrod' => 'palegoldenrod',
        'palegreen' => 'palegreen',
        'paleturquoise' => 'paleturquoise',
        'palevioletred' => 'palevioletred',
        'papayawhip' => 'papayawhip',
        'peachpuff' => 'peachpuff',
        'peru' => 'peru',
        'pink' => 'pink',
        'plum' => 'plum',
        'powderblue' => 'powderblue',
        'purple' => 'purple',
        'rebeccapurple' => 'rebeccapurple',
        'red' => 'red',
        'rosybrown' => 'rosybrown',
        'royalblue' => 'royalblue',
        'saddlebrown' => 'saddlebrown',
        'salmon' => 'salmon',
        'sandybrown' => 'sandybrown',
        'seagreen' => 'seagreen',
        'seashell' => 'seashell',
        'sienna' => 'sienna',
        'silver' => 'silver',
        'skyblue' => 'skyblue',
        'slateblue' => 'slateblue',
        'slategray' => 'slategray',
        'slategrey' => 'slategrey',
        'snow' => 'snow',
        'springgreen' => 'springgreen',
        'steelblue' => 'steelblue',
        'tan' => 'tan',
        'teal' => 'teal',
        'thistle' => 'thistle',
        'tomato' => 'tomato',
        'turquoise' => 'turquoise',
        'violet' => 'violet',
        'wheat' => 'wheat',
        'white' => 'white',
        'whitesmoke' => 'whitesmoke',
        'yellow' => 'yellow',
        'yellowgreen' => 'yellowgreen',
        'canvas' => 'Canvas',
        'canvastext' => 'CanvasText',
        'linktext' => 'LinkText',
        'visitedtext' => 'VisitedText',
        'activetext' => 'ActiveText',
        'buttonface' => 'ButtonFace',
        'buttontext' => 'ButtonText',
        'buttonborder' => 'ButtonBorder',
        'field' => 'Field',
        'fieldtext' => 'FieldText',
        'highlight' => 'Highlight',
        'highlighttext' => 'HighlightText',
        'selecteditem' => 'SelectedItem',
        'selecteditemtext' => 'SelectedItemText',
        'mark' => 'Mark',
        'marktext' => 'MarkText',
        'graytext' => 'GrayText',
        'accentcolor' => 'AccentColor',
        'accentcolortext' => 'AccentColorText',
        'rgb' => 'rgb',
        'rgba' => 'rgba',
        'hsl' => 'hsl',
        'hsla' => 'hsla',
        'hwb' => 'hwb',
        'lab' => 'lab',
        'lch' => 'lch',
        'oklab' => 'oklab',
        'oklch' => 'oklch',
        'color' => 'color',
        'ltr' => 'ltr',
        'rtl' => 'rtl',
        'block' => 'block',
        'inline' => 'inline',
        'run-in' => 'run-in',
        'flow' => 'flow',
        'flow-root' => 'flow-root',
        'table' => 'table',
        'flex' => 'flex',
        'grid' => 'grid',
        'ruby' => 'ruby',
        'list-item' => 'list-item',
        'table-row-group' => 'table-row-group',
        'table-header-group' => 'table-header-group',
        'table-footer-group' => 'table-footer-group',
        'table-row' => 'table-row',
        'table-cell' => 'table-cell',
        'table-column-group' => 'table-column-group',
        'table-column' => 'table-column',
        'table-caption' => 'table-caption',
        'ruby-base' => 'ruby-base',
        'ruby-text' => 'ruby-text',
        'ruby-base-container' => 'ruby-base-container',
        'ruby-text-container' => 'ruby-text-container',
        'contents' => 'contents',
        'inline-block' => 'inline-block',
        'inline-table' => 'inline-table',
        'inline-flex' => 'inline-flex',
        'inline-grid' => 'inline-grid',
        'hanging' => 'hanging',
        'content' => 'content',
        'row' => 'row',
        'row-reverse' => 'row-reverse',
        'column' => 'column',
        'column-reverse' => 'column-reverse',
        '_number' => '_number',
        'nowrap' => 'nowrap',
        'wrap' => 'wrap',
        'wrap-reverse' => 'wrap-reverse',
        'snap-block' => 'snap-block',
        'start' => 'start',
        'end' => 'end',
        'near' => 'near',
        'snap-inline' => 'snap-inline',
        '_integer' => '_integer',
        'region' => 'region',
        'page' => 'page',
        'serif' => 'serif',
        'sans-serif' => 'sans-serif',
        'cursive' => 'cursive',
        'fantasy' => 'fantasy',
        'monospace' => 'monospace',
        'system-ui' => 'system-ui',
        'emoji' => 'emoji',
        'math' => 'math',
        'fangsong' => 'fangsong',
        'ui-serif' => 'ui-serif',
        'ui-sans-serif' => 'ui-sans-serif',
        'ui-monospace' => 'ui-monospace',
        'ui-rounded' => 'ui-rounded',
        'xx-small' => 'xx-small',
        'x-small' => 'x-small',
        'small' => 'small',
        'large' => 'large',
        'x-large' => 'x-large',
        'xx-large' => 'xx-large',
        'xxx-large' => 'xxx-large',
        'larger' => 'larger',
        'smaller' => 'smaller',
        'normal' => 'normal',
        'ultra-condensed' => 'ultra-condensed',
        'extra-condensed' => 'extra-condensed',
        'condensed' => 'condensed',
        'semi-condensed' => 'semi-condensed',
        'semi-expanded' => 'semi-expanded',
        'expanded' => 'expanded',
        'extra-expanded' => 'extra-expanded',
        'ultra-expanded' => 'ultra-expanded',
        'italic' => 'italic',
        'oblique' => 'oblique',
        'bold' => 'bold',
        'bolder' => 'bolder',
        'lighter' => 'lighter',
        'force-end' => 'force-end',
        'allow-end' => 'allow-end',
        'min-content' => 'min-content',
        'max-content' => 'max-content',
        '_angle' => '_angle',
        'manual' => 'manual',
        'loose' => 'loose',
        'strict' => 'strict',
        'anywhere' => 'anywhere',
        'visible' => 'visible',
        'clip' => 'clip',
        'scroll' => 'scroll',
        'break-word' => 'break-word',
        'static' => 'static',
        'relative' => 'relative',
        'absolute' => 'absolute',
        'sticky' => 'sticky',
        'fixed' => 'fixed',
        'justify' => 'justify',
        'match-parent' => 'match-parent',
        'justify-all' => 'justify-all',
        'all' => 'all',
        'digits' => 'digits',
        'underline' => 'underline',
        'overline' => 'overline',
        'line-through' => 'line-through',
        'blink' => 'blink',
        'wavy' => 'wavy',
        'each-line' => 'each-line',
        'inter-word' => 'inter-word',
        'inter-character' => 'inter-character',
        'mixed' => 'mixed',
        'upright' => 'upright',
        'sideways' => 'sideways',
        'ellipsis' => 'ellipsis',
        'capitalize' => 'capitalize',
        'uppercase' => 'uppercase',
        'lowercase' => 'lowercase',
        'full-width' => 'full-width',
        'full-size-kana' => 'full-size-kana',
        'embed' => 'embed',
        'isolate' => 'isolate',
        'bidi-override' => 'bidi-override',
        'isolate-override' => 'isolate-override',
        'plaintext' => 'plaintext',
        'collapse' => 'collapse',
        'pre' => 'pre',
        'pre-wrap' => 'pre-wrap',
        'break-spaces' => 'break-spaces',
        'pre-line' => 'pre-line',
        'keep-all' => 'keep-all',
        'break-all' => 'break-all',
        'both' => 'both',
        'minimum' => 'minimum',
        'maximum' => 'maximum',
        'clear' => 'clear',
        'horizontal-tb' => 'horizontal-tb',
        'vertical-rl' => 'vertical-rl',
        'vertical-lr' => 'vertical-lr',
        'sideways-rl' => 'sideways-rl',
        'sideways-lr' => 'sideways-lr',
    ];

    private const array DISPLAY_VALUES = [
        'block' => true,
        'block flex' => true,
        'block flow' => true,
        'block flow list-item' => true,
        'block flow-root' => true,
        'block flow-root list-item' => true,
        'block grid' => true,
        'block list-item' => true,
        'block list-item flow' => true,
        'block list-item flow-root' => true,
        'block ruby' => true,
        'block table' => true,
        'contents' => true,
        'flex' => true,
        'flex block' => true,
        'flex inline' => true,
        'flex run-in' => true,
        'flow' => true,
        'flow block' => true,
        'flow block list-item' => true,
        'flow inline' => true,
        'flow inline list-item' => true,
        'flow list-item' => true,
        'flow list-item block' => true,
        'flow list-item inline' => true,
        'flow list-item run-in' => true,
        'flow-root' => true,
        'flow-root block' => true,
        'flow-root block list-item' => true,
        'flow-root inline' => true,
        'flow-root inline list-item' => true,
        'flow-root list-item' => true,
        'flow-root list-item block' => true,
        'flow-root list-item inline' => true,
        'flow-root list-item run-in' => true,
        'flow-root run-in' => true,
        'flow-root run-in list-item' => true,
        'flow run-in' => true,
        'flow run-in list-item' => true,
        'grid' => true,
        'grid block' => true,
        'grid inline' => true,
        'grid run-in' => true,
        'inherit' => true,
        'initial' => true,
        'inline' => true,
        'inline-block' => true,
        'inline flex' => true,
        'inline flow' => true,
        'inline flow list-item' => true,
        'inline flow-root' => true,
        'inline flow-root list-item' => true,
        'inline-grid' => true,
        'inline grid' => true,
        'inline list-item' => true,
        'inline list-item flow' => true,
        'inline list-item flow-root' => true,
        'inline ruby' => true,
        'inline-table' => true,
        'inline table' => true,
        'inline-flex' => true,
        'list-item' => true,
        'list-item block' => true,
        'list-item block flow' => true,
        'list-item block flow-root' => true,
        'list-item flow' => true,
        'list-item flow block' => true,
        'list-item flow inline' => true,
        'list-item flow run-in' => true,
        'list-item flow-root' => true,
        'list-item flow-root block' => true,
        'list-item flow-root inline' => true,
        'list-item flow-root run-in' => true,
        'list-item inline' => true,
        'list-item inline flow' => true,
        'list-item inline flow-root' => true,
        'list-item run-in' => true,
        'list-item run-in flow' => true,
        'list-item run-in flow-root' => true,
        'none' => true,
        'revert' => true,
        'ruby' => true,
        'ruby-base' => true,
        'ruby-base-container' => true,
        'ruby block' => true,
        'ruby inline' => true,
        'ruby run-in' => true,
        'ruby-text' => true,
        'ruby-text-container' => true,
        'run-in' => true,
        'run-in flex' => true,
        'run-in flow' => true,
        'run-in flow list-item' => true,
        'run-in flow-root' => true,
        'run-in flow-root list-item' => true,
        'run-in grid' => true,
        'run-in list-item' => true,
        'run-in list-item flow' => true,
        'run-in list-item flow-root' => true,
        'run-in ruby' => true,
        'run-in table' => true,
        'table' => true,
        'table block' => true,
        'table-caption' => true,
        'table-cell' => true,
        'table-column' => true,
        'table-column-group' => true,
        'table-footer-group' => true,
        'table-header-group' => true,
        'table inline' => true,
        'table-row' => true,
        'table-row-group' => true,
        'table run-in' => true,
        'unset' => true,
    ];

    private const array LENGTH_UNITS = [
        'cap' => true,
        'ch' => true,
        'cm' => true,
        'em' => true,
        'ex' => true,
        'ic' => true,
        'in' => true,
        'lh' => true,
        'mm' => true,
        'pc' => true,
        'pt' => true,
        'px' => true,
        'q' => true,
        'rem' => true,
        'rlh' => true,
        'vb' => true,
        'vh' => true,
        'vi' => true,
        'vmax' => true,
        'vmin' => true,
        'vw' => true,
    ];

    private const array ANGLE_UNITS = [
        'deg' => true,
        'grad' => true,
        'rad' => true,
        'turn' => true,
    ];

    private const string LONG_MAX_DECIMAL = '9223372036854775807';
    private const string LONG_MIN_ABS_DECIMAL = '9223372036854775808';

    private const array SIZE_KEYWORDS = [
        'auto' => true,
        'inherit' => true,
        'initial' => true,
        'max-content' => true,
        'min-content' => true,
        'revert' => true,
        'unset' => true,
    ];

    private const array MAX_SIZE_KEYWORDS = [
        'inherit' => true,
        'initial' => true,
        'max-content' => true,
        'min-content' => true,
        'none' => true,
        'revert' => true,
        'unset' => true,
    ];

    private const array FLEX_BASIS_KEYWORDS = [
        'auto' => true,
        'content' => true,
        'max-content' => true,
        'min-content' => true,
    ];

    private const array FLOAT_REFERENCE_KEYWORDS = [
        'column' => true,
        'inline' => true,
        'page' => true,
        'region' => true,
    ];

    private const array FLOAT_DEFER_KEYWORDS = [
        'last' => true,
        'none' => true,
    ];

    private const array FLOAT_KEYWORDS = [
        'block-end' => true,
        'block-start' => true,
        'bottom' => true,
        'inline-end' => true,
        'inline-start' => true,
        'left' => true,
        'none' => true,
        'right' => true,
        'snap-block' => true,
        'snap-inline' => true,
        'top' => true,
    ];

    private const array FLOAT_SNAP_BLOCK_KEYWORDS = [
        'end' => true,
        'near' => true,
        'start' => true,
    ];

    private const array FLOAT_SNAP_INLINE_KEYWORDS = [
        'left' => true,
        'near' => true,
        'right' => true,
    ];

    private const array TEXT_TRANSFORM_CASE_KEYWORDS = [
        'capitalize' => true,
        'lowercase' => true,
        'uppercase' => true,
    ];

    private const array TEXT_DECORATION_LINE_KEYWORDS = [
        'blink' => true,
        'line-through' => true,
        'overline' => true,
        'underline' => true,
    ];

    private const array TEXT_DECORATION_STYLE_KEYWORDS = [
        'dashed' => true,
        'dotted' => true,
        'double' => true,
        'solid' => true,
        'wavy' => true,
    ];

    private const array VERTICAL_ALIGN_TYPE_KEYWORDS = [
        'first' => true,
        'last' => true,
    ];

    private const array ALIGNMENT_BASELINE_KEYWORDS = [
        'alphabetic' => true,
        'baseline' => true,
        'central' => true,
        'ideographic' => true,
        'mathematical' => true,
        'middle' => true,
        'text-bottom' => true,
        'text-top' => true,
    ];

    private const array DOMINANT_BASELINE_KEYWORDS = [
        'alphabetic' => true,
        'auto' => true,
        'central' => true,
        'hanging' => true,
        'ideographic' => true,
        'mathematical' => true,
        'middle' => true,
        'text-bottom' => true,
        'text-top' => true,
    ];

    private const array BASELINE_SHIFT_KEYWORDS = [
        'bottom' => true,
        'center' => true,
        'sub' => true,
        'super' => true,
        'top' => true,
    ];

    private const array BASELINE_SOURCE_KEYWORDS = [
        'auto' => true,
        'first' => true,
        'last' => true,
    ];

    private const array FONT_STRETCH_KEYWORDS = [
        'condensed' => true,
        'expanded' => true,
        'extra-condensed' => true,
        'extra-expanded' => true,
        'normal' => true,
        'semi-condensed' => true,
        'semi-expanded' => true,
        'ultra-condensed' => true,
        'ultra-expanded' => true,
    ];

    private const array FONT_SIZE_KEYWORDS = [
        'large' => true,
        'larger' => true,
        'math' => true,
        'medium' => true,
        'small' => true,
        'smaller' => true,
        'x-large' => true,
        'x-small' => true,
        'xx-large' => true,
        'xx-small' => true,
        'xxx-large' => true,
    ];

    private const array KEYWORD_PROPERTIES = [
        'align-content' => [
            'center' => true,
            'flex-end' => true,
            'flex-start' => true,
            'space-around' => true,
            'space-between' => true,
            'stretch' => true,
        ],
        'align-items' => [
            'baseline' => true,
            'center' => true,
            'flex-end' => true,
            'flex-start' => true,
            'stretch' => true,
        ],
        'align-self' => [
            'auto' => true,
            'baseline' => true,
            'center' => true,
            'flex-end' => true,
            'flex-start' => true,
            'stretch' => true,
        ],
        'box-sizing' => [
            'border-box' => true,
            'content-box' => true,
        ],
        'clear' => [
            'block-end' => true,
            'block-start' => true,
            'bottom' => true,
            'inline-end' => true,
            'inline-start' => true,
            'left' => true,
            'none' => true,
            'right' => true,
            'top' => true,
        ],
        'direction' => [
            'ltr' => true,
            'rtl' => true,
        ],
        'flex-direction' => [
            'column' => true,
            'column-reverse' => true,
            'row' => true,
            'row-reverse' => true,
        ],
        'flex-wrap' => [
            'nowrap' => true,
            'wrap' => true,
            'wrap-reverse' => true,
        ],
        'hyphens' => [
            'auto' => true,
            'manual' => true,
            'none' => true,
        ],
        'justify-content' => [
            'center' => true,
            'flex-end' => true,
            'flex-start' => true,
            'space-around' => true,
            'space-between' => true,
        ],
        'line-break' => [
            'anywhere' => true,
            'auto' => true,
            'loose' => true,
            'normal' => true,
            'strict' => true,
        ],
        'overflow-block' => [
            'auto' => true,
            'clip' => true,
            'hidden' => true,
            'scroll' => true,
            'visible' => true,
        ],
        'overflow-inline' => [
            'auto' => true,
            'clip' => true,
            'hidden' => true,
            'scroll' => true,
            'visible' => true,
        ],
        'overflow-wrap' => [
            'anywhere' => true,
            'break-word' => true,
            'normal' => true,
        ],
        'overflow-x' => [
            'auto' => true,
            'clip' => true,
            'hidden' => true,
            'scroll' => true,
            'visible' => true,
        ],
        'overflow-y' => [
            'auto' => true,
            'clip' => true,
            'hidden' => true,
            'scroll' => true,
            'visible' => true,
        ],
        'position' => [
            'absolute' => true,
            'fixed' => true,
            'relative' => true,
            'static' => true,
            'sticky' => true,
        ],
        'text-align' => [
            'center' => true,
            'end' => true,
            'justify' => true,
            'justify-all' => true,
            'left' => true,
            'match-parent' => true,
            'right' => true,
            'start' => true,
        ],
        'text-align-all' => [
            'center' => true,
            'end' => true,
            'justify' => true,
            'left' => true,
            'match-parent' => true,
            'right' => true,
            'start' => true,
        ],
        'text-align-last' => [
            'auto' => true,
            'center' => true,
            'end' => true,
            'justify' => true,
            'left' => true,
            'match-parent' => true,
            'right' => true,
            'start' => true,
        ],
        'text-justify' => [
            'auto' => true,
            'inter-character' => true,
            'inter-word' => true,
            'none' => true,
        ],
        'text-orientation' => [
            'mixed' => true,
            'sideways' => true,
            'upright' => true,
        ],
        'text-overflow' => [
            'clip' => true,
            'ellipsis' => true,
        ],
        'unicode-bidi' => [
            'bidi-override' => true,
            'embed' => true,
            'isolate' => true,
            'isolate-override' => true,
            'normal' => true,
            'plaintext' => true,
        ],
        'visibility' => [
            'collapse' => true,
            'hidden' => true,
            'visible' => true,
        ],
        'white-space' => [
            'break-spaces' => true,
            'normal' => true,
            'nowrap' => true,
            'pre' => true,
            'pre-line' => true,
            'pre-wrap' => true,
        ],
        'word-break' => [
            'break-all' => true,
            'break-word' => true,
            'keep-all' => true,
            'normal' => true,
        ],
        'word-wrap' => [
            'anywhere' => true,
            'break-word' => true,
            'normal' => true,
        ],
        'writing-mode' => [
            'horizontal-tb' => true,
            'sideways-lr' => true,
            'sideways-rl' => true,
            'vertical-lr' => true,
            'vertical-rl' => true,
        ],
    ];

    public function __construct(
        private readonly Tokenizer $tokenizer = new Tokenizer(),
    ) {
    }

    /**
     * @return list<array{type: string, name: string, value: string, important: bool}>
     */
    public function parseList(string $css): array
    {
        $tokens = $this->tokenizer->tokenize($css);
        $offset = 0;
        $declarations = [];

        while ($offset < count($tokens)) {
            $this->skipDeclarationSeparators($tokens, $offset);

            if ($offset >= count($tokens)) {
                break;
            }

            if ($tokens[$offset]->type !== 'ident' || ! $this->hasDeclarationColon($tokens, $offset)) {
                $declarations[] = $this->consumeInvalidDeclaration($tokens, $offset);
                continue;
            }

            $declarations[] = $this->consumeDeclaration($tokens, $offset);
        }

        return $declarations;
    }

    /**
     * @param list<Token> $tokens
     */
    private function skipDeclarationSeparators(array $tokens, int &$offset): void
    {
        while ($offset < count($tokens) && ($tokens[$offset]->type === 'semicolon' || self::isIgnorableToken($tokens[$offset]))) {
            $offset++;
        }
    }

    /**
     * @param list<Token> $tokens
     */
    private function hasDeclarationColon(array $tokens, int $offset): bool
    {
        $offset++;

        while ($offset < count($tokens) && self::isIgnorableToken($tokens[$offset])) {
            $offset++;
        }

        return ($tokens[$offset] ?? null)?->type === 'colon';
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, name: string, value: string, important: bool}
     */
    private function consumeDeclaration(array $tokens, int &$offset): array
    {
        $name = $tokens[$offset]->value;
        $offset++;

        while ($offset < count($tokens) && self::isIgnorableToken($tokens[$offset])) {
            $offset++;
        }

        if (($tokens[$offset] ?? null)?->type === 'colon') {
            $offset++;
        }

        $leadingValueTokens = [];
        while ($offset < count($tokens) && self::isIgnorableToken($tokens[$offset])) {
            $leadingValueTokens[] = $tokens[$offset];
            $offset++;
        }

        $valueTokens = $this->consumeValueTokens($tokens, $offset);
        [$valueTokens, $important] = self::extractImportant($valueTokens);
        $value = self::serializeComponentValue($valueTokens);
        $property = strtolower($name);
        $classificationTokens = in_array($property, [
            'alignment-baseline',
            'background-color',
            'baseline-shift',
            'baseline-source',
            'border',
            'border-bottom',
            'border-bottom-color',
            'border-left',
            'border-left-color',
            'border-right',
            'border-right-color',
            'border-top',
            'border-top-color',
            'color',
            'dominant-baseline',
            'font-family',
            'text-decoration-color',
            'text-decoration',
            'text-decoration-line',
            'text-decoration-style',
            'vertical-align',
        ], true)
            ? [...$leadingValueTokens, ...$valueTokens]
            : $valueTokens;
        $type = $this->classifyDeclaration($name, $value, $classificationTokens);

        return [
            'type' => $type,
            'name' => $name,
            'value' => $type === 'property' ? self::serializeKnownPropertyValue($property, $valueTokens, $value) : $value,
            'important' => $important,
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, name: string, value: string, important: bool}
     */
    private function consumeInvalidDeclaration(array $tokens, int &$offset): array
    {
        $valueTokens = $this->consumeValueTokens($tokens, $offset);

        return [
            'type' => 'undef',
            'name' => '',
            'value' => self::serializeComponentValue($valueTokens),
            'important' => false,
        ];
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private function consumeValueTokens(array $tokens, int &$offset): array
    {
        $valueTokens = [];
        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($blockEndStack === [] && $token->type === 'semicolon') {
                $offset++;
                break;
            }

            $valueTokens[] = $token;
            $this->updateBlockEndStack($blockEndStack, $token);
            $offset++;
        }

        return $valueTokens;
    }

    /**
     * @param list<string> $blockEndStack
     */
    private function updateBlockEndStack(array &$blockEndStack, Token $token): void
    {
        $endToken = match ($token->type) {
            'function', 'left-parenthesis' => 'right-parenthesis',
            'left-square-bracket' => 'right-square-bracket',
            'left-curly-bracket' => 'right-curly-bracket',
            default => null,
        };

        if ($endToken !== null) {
            $blockEndStack[] = $endToken;
            return;
        }

        if ($blockEndStack !== [] && $token->type === end($blockEndStack)) {
            array_pop($blockEndStack);
        }
    }

    /**
     * @param list<Token> $valueTokens
     */
    private function classifyDeclaration(string $name, string $value, array $valueTokens): string
    {
        $property = strtolower($name);

        if (! isset(self::KNOWN_PROPERTIES[$property])) {
            return 'custom';
        }

        return match ($property) {
            'alignment-baseline' => self::singleLexborKeywordValue($valueTokens, self::ALIGNMENT_BASELINE_KEYWORDS) !== null ? 'property' : 'undef',
            'baseline-shift' => self::baselineShiftDeclarationValue($valueTokens) !== null ? 'property' : 'undef',
            'baseline-source' => self::singleLexborKeywordValue($valueTokens, self::BASELINE_SOURCE_KEYWORDS) !== null ? 'property' : 'undef',
            'border', 'border-bottom', 'border-left', 'border-right', 'border-top' => self::borderDeclarationValue($valueTokens) !== null ? 'property' : 'undef',
            'background-color', 'border-bottom-color', 'border-left-color', 'border-right-color', 'border-top-color', 'color', 'text-decoration-color' => self::colorDeclarationValue($valueTokens) !== null ? 'property' : 'undef',
            'dominant-baseline' => self::singleLexborKeywordValue($valueTokens, self::DOMINANT_BASELINE_KEYWORDS) !== null ? 'property' : 'undef',
            'display' => self::isValidDisplay($valueTokens) ? 'property' : 'undef',
            'flex' => self::parseFlex($valueTokens) !== null ? 'property' : 'undef',
            'flex-basis' => self::isValidFlexBasis($valueTokens) ? 'property' : 'undef',
            'flex-flow' => self::isValidFlexFlow($valueTokens) ? 'property' : 'undef',
            'float' => self::floatValue($valueTokens) !== null ? 'property' : 'undef',
            'float-defer' => self::floatDeferValue($valueTokens) !== null ? 'property' : 'undef',
            'float-offset' => self::floatOffsetValue($valueTokens) !== null ? 'property' : 'undef',
            'float-reference' => self::singleKeywordValue($valueTokens, self::FLOAT_REFERENCE_KEYWORDS) !== null ? 'property' : 'undef',
            'font-family' => self::fontFamilyValue($valueTokens) !== null ? 'property' : 'undef',
            'font-size' => self::fontSizeValue($valueTokens) !== null ? 'property' : 'undef',
            'font-stretch' => self::fontStretchValue($valueTokens) !== null ? 'property' : 'undef',
            'font-style' => self::fontStyleValue($valueTokens) !== null ? 'property' : 'undef',
            'font-weight' => self::fontWeightValue($valueTokens) !== null ? 'property' : 'undef',
            'hanging-punctuation' => self::hangingPunctuationValue($valueTokens) !== null ? 'property' : 'undef',
            'height', 'min-height', 'min-width', 'width' => self::isValidLengthSize($value, $valueTokens, self::SIZE_KEYWORDS) ? 'property' : 'undef',
            'bottom', 'inset-block-end', 'inset-block-start', 'inset-inline-end', 'inset-inline-start', 'left', 'right', 'top' => self::isValidBoxSpacing($property, $valueTokens, true) ? 'property' : 'undef',
            'flex-grow', 'flex-shrink' => self::isValidNonNegativeNumber($valueTokens) ? 'property' : 'undef',
            'letter-spacing', 'word-spacing' => self::isValidLengthKeyword($valueTokens, ['normal' => true]) ? 'property' : 'undef',
            'line-height' => self::isValidNumberLengthPercentage($valueTokens, ['normal' => true]) ? 'property' : 'undef',
            'margin', 'margin-bottom', 'margin-left', 'margin-right', 'margin-top' => self::isValidBoxSpacing($property, $valueTokens, true) ? 'property' : 'undef',
            'max-height', 'max-width' => self::isValidLengthSize($value, $valueTokens, self::MAX_SIZE_KEYWORDS) ? 'property' : 'undef',
            'opacity' => self::isValidNumberPercentage($valueTokens) ? 'property' : 'undef',
            'order' => self::isValidInteger($valueTokens) ? 'property' : 'undef',
            'padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top' => self::isValidBoxSpacing($property, $valueTokens, false) ? 'property' : 'undef',
            'tab-size' => self::numberLengthValue($valueTokens) !== null ? 'property' : 'undef',
            'text-combine-upright' => self::textCombineUprightValue($valueTokens) !== null ? 'property' : 'undef',
            'text-decoration' => self::textDecorationValue($valueTokens) !== null ? 'property' : 'undef',
            'text-decoration-line' => self::textDecorationLineValue($valueTokens) !== null ? 'property' : 'undef',
            'text-decoration-style' => self::textDecorationStyleValue($valueTokens) !== null ? 'property' : 'undef',
            'text-indent' => self::textIndentValue($valueTokens) !== null ? 'property' : 'undef',
            'text-transform' => self::textTransformValue($valueTokens) !== null ? 'property' : 'undef',
            'vertical-align' => self::verticalAlignValue($valueTokens) !== null ? 'property' : 'undef',
            'z-index' => self::isValidIntegerKeyword($valueTokens, ['auto' => true]) ? 'property' : 'undef',
            default => isset(self::KEYWORD_PROPERTIES[$property]) && self::isValidKeywordProperty($property, $valueTokens) ? 'property' : 'undef',
        };
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, true> $keywords
     */
    private static function isValidLengthSize(string $value, array $tokens, array $keywords): bool
    {
        $tokens = self::stripWhitespaceTokens($tokens);
        $lowerValue = strtolower($value);

        if (isset($keywords[$lowerValue])) {
            return count($tokens) === 1 && $tokens[0]->type === 'ident';
        }

        if (count($tokens) !== 1) {
            return false;
        }

        $token = $tokens[0];

        if ($token->type === 'number') {
            return $value === '0';
        }

        if ($token->type === 'percentage') {
            return self::isNonNegativePercentage($value);
        }

        if ($token->type !== 'dimension') {
            return false;
        }

        if (! preg_match('/^\+?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?([a-zA-Z]+)$/i', $value, $matches)) {
            return false;
        }

        return isset(self::LENGTH_UNITS[strtolower($matches[1])]);
    }

    private static function isNonNegativePercentage(string $value): bool
    {
        return preg_match('/^\+?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?%$/i', $value) === 1;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidDisplay(array $tokens): bool
    {
        $value = self::serializeIdentSequence($tokens);

        return $value !== null && isset(self::DISPLAY_VALUES[strtolower($value)]);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidBoxSpacing(string $property, array $tokens, bool $allowAuto): bool
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);
        $maxComponents = in_array($property, ['margin', 'padding'], true) ? 4 : 1;

        if ($components === [] || count($components) > $maxComponents) {
            return false;
        }

        foreach ($components as $component) {
            if (! self::isValidBoxSpacingComponent($component, $allowAuto)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Token> $tokens
     * @return list<list<Token>>
     */
    private static function splitWhitespaceSeparatedComponents(array $tokens): array
    {
        $tokens = self::stripWhitespaceTokens($tokens);
        $components = [];
        $component = [];

        foreach ($tokens as $token) {
            if (self::isIgnorableToken($token)) {
                if ($component !== []) {
                    $components[] = $component;
                    $component = [];
                }

                continue;
            }

            $component[] = $token;
        }

        if ($component !== []) {
            $components[] = $component;
        }

        return $components;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidBoxSpacingComponent(array $tokens, bool $allowAuto): bool
    {
        if (count($tokens) !== 1) {
            return false;
        }

        $token = $tokens[0];

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return in_array($value, ['inherit', 'initial', 'revert', 'unset'], true)
                || ($allowAuto && $value === 'auto');
        }

        if ($token->type === 'number') {
            return $token->value === '0';
        }

        if ($token->type === 'percentage') {
            return self::isSignedPercentage($token->value);
        }

        if ($token->type !== 'dimension') {
            return false;
        }

        if (! preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?([a-zA-Z]+)$/i', $token->value, $matches)) {
            return false;
        }

        return isset(self::LENGTH_UNITS[strtolower($matches[1])]);
    }

    private static function isSignedPercentage(string $value): bool
    {
        return preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?%$/i', $value) === 1;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidNonNegativeNumber(array $tokens): bool
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return false;
        }

        if ($token->type === 'ident') {
            return isset(self::CSS_WIDE_KEYWORDS[strtolower($token->value)]);
        }

        return $token->type === 'number' && (float) $token->value >= 0.0;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidFlexBasis(array $tokens): bool
    {
        return self::flexBasisValue($tokens) !== null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidFlexFlow(array $tokens): bool
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);

        if ($components === [] || count($components) > 2) {
            return false;
        }

        $values = [];

        foreach ($components as $component) {
            if (count($component) !== 1 || $component[0]->type !== 'ident') {
                return false;
            }

            $values[] = strtolower($component[0]->value);
        }

        if (count($values) === 1) {
            return isset(self::CSS_WIDE_KEYWORDS[$values[0]])
                || self::isFlexDirectionKeyword($values[0])
                || self::isFlexWrapKeyword($values[0]);
        }

        [$first, $second] = $values;

        if (isset(self::CSS_WIDE_KEYWORDS[$first]) || self::isFlexDirectionKeyword($first)) {
            return self::isFlexWrapKeyword($second);
        }

        return self::isFlexWrapKeyword($first) && self::isFlexDirectionKeyword($second);
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: ?string, grow: ?string, shrink: ?string, basis: ?string}|null
     */
    private static function parseFlex(array $tokens): ?array
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);
        $count = count($components);

        if ($count === 0 || $count > 3) {
            return null;
        }

        $flex = [
            'type' => null,
            'grow' => null,
            'shrink' => null,
            'basis' => null,
        ];

        $firstNumber = self::flexNumberValue($components[0]);

        if ($firstNumber !== null) {
            $flex['grow'] = $firstNumber;
            $offset = 1;

            $nextNumber = isset($components[$offset]) ? self::flexNumberValue($components[$offset]) : null;
            if ($nextNumber !== null) {
                $flex['shrink'] = $nextNumber;
                $offset++;
            }

            $basis = isset($components[$offset]) ? self::flexBasisComponentValue($components[$offset]) : null;
            if ($basis !== null) {
                $flex['basis'] = $basis;
                $offset++;
            }
            elseif (isset($components[$offset])) {
                $flex['basis'] = $flex['grow'];
                $flex['grow'] = null;

                if ($flex['shrink'] !== null) {
                    $flex['grow'] = $flex['shrink'];
                    $flex['shrink'] = null;

                    $shrink = self::flexNumberValue($components[$offset]);
                    if ($shrink === null) {
                        return null;
                    }

                    $flex['shrink'] = $shrink;
                    $offset++;
                }
                else {
                    $grow = self::flexNumberValue($components[$offset]);
                    if ($grow === null) {
                        return null;
                    }

                    $flex['grow'] = $grow;
                    $offset++;

                    $shrink = isset($components[$offset]) ? self::flexNumberValue($components[$offset]) : null;
                    if ($shrink !== null) {
                        $flex['shrink'] = $shrink;
                        $offset++;
                    }
                }
            }

            return $offset === $count ? $flex : null;
        }

        $basis = self::flexBasisComponentValue($components[0]);
        if ($basis !== null) {
            $flex['basis'] = $basis;
            $offset = 1;

            if (isset($components[$offset])) {
                $grow = self::flexNumberValue($components[$offset]);
                if ($grow === null) {
                    return null;
                }

                $flex['grow'] = $grow;
                $offset++;

                $shrink = isset($components[$offset]) ? self::flexNumberValue($components[$offset]) : null;
                if ($shrink !== null) {
                    $flex['shrink'] = $shrink;
                    $offset++;
                }
            }

            return $offset === $count ? $flex : null;
        }

        if ($count !== 1 || count($components[0]) !== 1 || $components[0][0]->type !== 'ident') {
            return null;
        }

        $value = strtolower($components[0][0]->value);
        if (isset(self::CSS_WIDE_KEYWORDS[$value]) || $value === 'none') {
            $flex['type'] = $value;

            return $flex;
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, true> $keywords
     */
    private static function singleKeywordValue(array $tokens, array $keywords): ?string
    {
        $token = self::singleValueToken($tokens);

        if ($token === null || $token->type !== 'ident') {
            return null;
        }

        $value = strtolower($token->value);

        return isset(self::CSS_WIDE_KEYWORDS[$value]) || isset($keywords[$value]) ? $value : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function floatDeferValue(array $tokens): ?string
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value]) || isset(self::FLOAT_DEFER_KEYWORDS[$value]) ? $value : null;
        }

        return $token->type === 'number' && self::isLongInteger($token->value) ? $token->value : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function floatOffsetValue(array $tokens): ?string
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);

        if (count($components) !== 1) {
            return null;
        }

        return self::lengthPercentageComponentValue($components[0], true);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function floatValue(array $tokens): ?string
    {
        return self::singleKeywordValue($tokens, self::FLOAT_KEYWORDS)
            ?? self::floatSnapValue($tokens);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function floatSnapValue(array $tokens): ?string
    {
        $tokens = self::nonIgnorableTokens($tokens);

        if (count($tokens) !== 3 && count($tokens) !== 5) {
            return null;
        }

        $function = $tokens[0];
        if ($function->type !== 'function') {
            return null;
        }

        if (! str_ends_with($function->value, '(')) {
            return null;
        }

        $name = strtolower(substr($function->value, 0, -1));
        if ($name !== 'snap-block' && $name !== 'snap-inline') {
            return null;
        }

        $length = self::floatSnapLengthValue($tokens[1]);
        if ($length === null) {
            return null;
        }

        if ($tokens[2]->type === 'right-parenthesis' && count($tokens) === 3) {
            return $name . '(' . $length . ')';
        }

        if (
            $tokens[2]->type !== 'comma'
            || count($tokens) !== 5
            || $tokens[3]->type !== 'ident'
            || $tokens[4]->type !== 'right-parenthesis'
        ) {
            return null;
        }

        $snapType = strtolower($tokens[3]->value);
        $snapKeywords = $name === 'snap-block'
            ? self::FLOAT_SNAP_BLOCK_KEYWORDS
            : self::FLOAT_SNAP_INLINE_KEYWORDS;

        return isset($snapKeywords[$snapType])
            ? $name . '(' . $length . ', ' . $snapType . ')'
            : null;
    }

    private static function floatSnapLengthValue(Token $token): ?string
    {
        if ($token->type === 'number') {
            return $token->value === '0' ? $token->value : null;
        }

        if ($token->type === 'dimension' && self::isValidLengthDimension($token->value)) {
            return self::canonicalLengthDimensionValue($token->value);
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function borderDeclarationValue(array $tokens): ?string
    {
        $offset = 0;
        self::skipLexborOptionalWhitespace($tokens, $offset);

        if (($tokens[$offset] ?? null)?->type === 'ident') {
            $value = strtolower($tokens[$offset]->value);

            if (isset(self::CSS_WIDE_KEYWORDS[$value])) {
                $offset++;
                self::skipLexborOptionalWhitespace($tokens, $offset);

                return $offset >= count($tokens) || self::remainingTokensAreIgnorable($tokens, $offset) ? $value : null;
            }
        }

        $border = [
            'width' => null,
            'style' => null,
            'color' => null,
        ];

        for ($componentCount = 0; $componentCount < 3; $componentCount++) {
            if (! isset($tokens[$offset])) {
                break;
            }

            if (! self::consumeBorderComponent($tokens, $offset, $border)) {
                return null;
            }

            self::skipLexborOptionalWhitespace($tokens, $offset);
        }

        if ($border['width'] === null && $border['style'] === null && $border['color'] === null) {
            return null;
        }

        if ($offset < count($tokens) && ! self::remainingTokensAreIgnorable($tokens, $offset)) {
            return null;
        }

        $parts = [];

        if ($border['width'] !== null) {
            $parts[] = $border['width'];
        }

        if ($border['style'] !== null) {
            $parts[] = $border['style'];
        }

        if ($border['color'] !== null) {
            $parts[] = $border['color'];
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<Token> $tokens
     * @param array{width: ?string, style: ?string, color: ?string} $border
     */
    private static function consumeBorderComponent(array $tokens, int &$offset, array &$border): bool
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return false;
        }

        if ($token->type === 'dimension') {
            if ($border['width'] !== null || ! self::isValidLengthDimension($token->value)) {
                return false;
            }

            $border['width'] = self::canonicalLengthDimensionValue($token->value);
            $offset++;
            return true;
        }

        if ($token->type === 'number') {
            if ($border['width'] !== null) {
                return false;
            }

            $border['width'] = $token->value;
            $offset++;
            return true;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            if (isset(self::BORDER_WIDTH_KEYWORDS[$value])) {
                if ($border['width'] !== null) {
                    return false;
                }

                $border['width'] = $value;
                $offset++;
                return true;
            }

            if (isset(self::BORDER_STYLE_KEYWORDS[$value])) {
                if ($border['style'] !== null) {
                    return false;
                }

                $border['style'] = $value;
                $offset++;
                return true;
            }
        }

        if ($border['color'] !== null) {
            return false;
        }

        $colorOffset = $offset;
        $color = self::colorValue($tokens, $colorOffset, false);
        if ($color === null) {
            return false;
        }

        $border['color'] = $color;
        $offset = $colorOffset;

        return true;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function colorDeclarationValue(array $tokens): ?string
    {
        $offset = 0;
        self::skipLexborOptionalWhitespace($tokens, $offset);

        $value = self::colorValue($tokens, $offset, true);
        if ($value === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        return $offset >= count($tokens) || self::remainingTokensAreIgnorable($tokens, $offset) ? $value : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function colorValue(array $tokens, int &$offset, bool $allowCssWide): ?string
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            if ($allowCssWide && isset(self::CSS_WIDE_KEYWORDS[$value])) {
                $offset++;
                return $value;
            }

            if (! isset(self::COLOR_KEYWORDS[$value])) {
                return null;
            }

            $offset++;
            return self::LEXBOR_VALUE_KEYWORDS[$value] ?? $value;
        }

        if ($token->type === 'hash') {
            $hex = substr($token->value, 1);
            $length = strlen($hex);

            if (! in_array($length, [3, 4, 6, 8], true) || ! ctype_xdigit($hex)) {
                return null;
            }

            $offset++;
            return '#' . strtolower($hex);
        }

        if ($token->type === 'function') {
            return self::colorFunctionValue($tokens, $offset);
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function colorFunctionValue(array $tokens, int &$offset): ?string
    {
        $function = $tokens[$offset] ?? null;
        if ($function === null || $function->type !== 'function' || ! str_ends_with($function->value, '(')) {
            return null;
        }

        $name = strtolower(substr($function->value, 0, -1));
        $offset++;

        return match ($name) {
            'rgb', 'rgba' => self::rgbColorFunctionValue($tokens, $offset, $name),
            'hsl', 'hsla', 'hwb' => self::hslColorFunctionValue($tokens, $offset, $name),
            'lab', 'oklab' => self::labColorFunctionValue($tokens, $offset, $name),
            'lch', 'oklch' => self::lchColorFunctionValue($tokens, $offset, $name),
            default => null,
        };
    }

    /**
     * @param list<Token> $tokens
     */
    private static function rgbColorFunctionValue(array $tokens, int &$offset, string $name): ?string
    {
        self::skipLexborOptionalWhitespace($tokens, $offset);

        $red = self::numberPercentageNoneComponent($tokens, $offset);
        if ($red === null) {
            return null;
        }

        $type = $red['type'];
        self::skipLexborOptionalWhitespace($tokens, $offset);

        if (($tokens[$offset] ?? null)?->type === 'comma') {
            if ($type === 'none') {
                return null;
            }

            $offset++;
            self::skipLexborOptionalWhitespace($tokens, $offset);

            $green = self::numberPercentageComponent($tokens, $offset);
            if ($green === null || $green['type'] !== $type) {
                return null;
            }

            self::skipLexborOptionalWhitespace($tokens, $offset);
            if (($tokens[$offset] ?? null)?->type !== 'comma') {
                return null;
            }

            $offset++;
            self::skipLexborOptionalWhitespace($tokens, $offset);

            $blue = self::numberPercentageComponent($tokens, $offset);
            if ($blue === null || $blue['type'] !== $type) {
                return null;
            }

            self::skipLexborOptionalWhitespace($tokens, $offset);
            if (self::consumeRightParenthesis($tokens, $offset)) {
                return $name . '(' . $red['value'] . ', ' . $green['value'] . ', ' . $blue['value'] . ')';
            }

            if (($tokens[$offset] ?? null)?->type !== 'comma') {
                return null;
            }

            $offset++;
            self::skipLexborOptionalWhitespace($tokens, $offset);

            $alpha = self::numberPercentageComponent($tokens, $offset);
            if ($alpha === null) {
                return null;
            }

            self::skipLexborOptionalWhitespace($tokens, $offset);

            return self::consumeRightParenthesis($tokens, $offset)
                ? $name . '(' . $red['value'] . ', ' . $green['value'] . ', ' . $blue['value'] . ', ' . $alpha['value'] . ')'
                : null;
        }

        $green = self::numberPercentageNoneComponent($tokens, $offset);
        if ($green === null) {
            return null;
        }

        if ($type !== $green['type']) {
            if ($type === 'none') {
                $type = $green['type'];
            }
            elseif ($green['type'] !== 'none') {
                return null;
            }
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $blue = self::numberPercentageNoneComponent($tokens, $offset);
        if ($blue === null) {
            return null;
        }

        if ($type !== $blue['type'] && $type !== 'none' && $blue['type'] !== 'none') {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);
        if (self::consumeRightParenthesis($tokens, $offset)) {
            return $name . '(' . $red['value'] . ' ' . $green['value'] . ' ' . $blue['value'] . ')';
        }

        if (! self::consumeSlashDelimiter($tokens, $offset)) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $alpha = self::numberPercentageNoneComponent($tokens, $offset);
        if ($alpha === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        return self::consumeRightParenthesis($tokens, $offset)
            ? $name . '(' . $red['value'] . ' ' . $green['value'] . ' ' . $blue['value'] . ' / ' . $alpha['value'] . ')'
            : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function hslColorFunctionValue(array $tokens, int &$offset, string $name): ?string
    {
        self::skipLexborOptionalWhitespace($tokens, $offset);

        $hue = self::hueNoneComponent($tokens, $offset);
        if ($hue === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        if (($tokens[$offset] ?? null)?->type === 'comma') {
            if ($hue['type'] === 'none') {
                return null;
            }

            $offset++;
            self::skipLexborOptionalWhitespace($tokens, $offset);

            $saturation = self::percentageComponent($tokens, $offset);
            if ($saturation === null) {
                return null;
            }

            self::skipLexborOptionalWhitespace($tokens, $offset);
            if (($tokens[$offset] ?? null)?->type !== 'comma') {
                return null;
            }

            $offset++;
            self::skipLexborOptionalWhitespace($tokens, $offset);

            $lightness = self::percentageComponent($tokens, $offset);
            if ($lightness === null) {
                return null;
            }

            self::skipLexborOptionalWhitespace($tokens, $offset);
            if (self::consumeRightParenthesis($tokens, $offset)) {
                return $name . '(' . $hue['value'] . ', ' . $saturation['value'] . ', ' . $lightness['value'] . ')';
            }

            if (($tokens[$offset] ?? null)?->type !== 'comma') {
                return null;
            }

            $offset++;
            self::skipLexborOptionalWhitespace($tokens, $offset);

            $alpha = self::numberPercentageComponent($tokens, $offset);
            if ($alpha === null) {
                return null;
            }

            self::skipLexborOptionalWhitespace($tokens, $offset);

            return self::consumeRightParenthesis($tokens, $offset)
                ? $name . '(' . $hue['value'] . ', ' . $saturation['value'] . ', ' . $lightness['value'] . ', ' . $alpha['value'] . ')'
                : null;
        }

        $saturation = self::percentageNoneComponent($tokens, $offset);
        if ($saturation === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $lightness = self::percentageNoneComponent($tokens, $offset);
        if ($lightness === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);
        if (self::consumeRightParenthesis($tokens, $offset)) {
            return $name . '(' . $hue['value'] . ' ' . $saturation['value'] . ' ' . $lightness['value'] . ')';
        }

        if (self::consumeSlashDelimiter($tokens, $offset)) {
            self::skipLexborOptionalWhitespace($tokens, $offset);
        }

        $alpha = self::numberPercentageNoneComponent($tokens, $offset);
        if ($alpha === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        return self::consumeRightParenthesis($tokens, $offset)
            ? $name . '(' . $hue['value'] . ' ' . $saturation['value'] . ' ' . $lightness['value'] . ' / ' . $alpha['value'] . ')'
            : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function labColorFunctionValue(array $tokens, int &$offset, string $name): ?string
    {
        self::skipLexborOptionalWhitespace($tokens, $offset);

        $lightness = self::numberPercentageNoneComponent($tokens, $offset);
        if ($lightness === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $a = self::numberPercentageNoneComponent($tokens, $offset);
        if ($a === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $b = self::numberPercentageNoneComponent($tokens, $offset);
        if ($b === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);
        if (self::consumeRightParenthesis($tokens, $offset)) {
            return $name . '(' . $lightness['value'] . ' ' . $a['value'] . ' ' . $b['value'] . ')';
        }

        if (! self::consumeSlashDelimiter($tokens, $offset)) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $alpha = self::numberPercentageNoneComponent($tokens, $offset);
        if ($alpha === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        return self::consumeRightParenthesis($tokens, $offset)
            ? $name . '(' . $lightness['value'] . ' ' . $a['value'] . ' ' . $b['value'] . ' / ' . $alpha['value'] . ')'
            : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function lchColorFunctionValue(array $tokens, int &$offset, string $name): ?string
    {
        self::skipLexborOptionalWhitespace($tokens, $offset);

        $lightness = self::numberPercentageNoneComponent($tokens, $offset);
        if ($lightness === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $chroma = self::numberPercentageNoneComponent($tokens, $offset);
        if ($chroma === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $hue = self::hueNoneComponent($tokens, $offset);
        if ($hue === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);
        if (self::consumeRightParenthesis($tokens, $offset)) {
            return $name . '(' . $lightness['value'] . ' ' . $chroma['value'] . ' ' . $hue['value'] . ')';
        }

        if (! self::consumeSlashDelimiter($tokens, $offset)) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        $alpha = self::numberPercentageNoneComponent($tokens, $offset);
        if ($alpha === null) {
            return null;
        }

        self::skipLexborOptionalWhitespace($tokens, $offset);

        return self::consumeRightParenthesis($tokens, $offset)
            ? $name . '(' . $lightness['value'] . ' ' . $chroma['value'] . ' ' . $hue['value'] . ' / ' . $alpha['value'] . ')'
            : null;
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, value: string}|null
     */
    private static function numberPercentageNoneComponent(array $tokens, int &$offset): ?array
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token->type === 'number') {
            $offset++;
            return ['type' => 'number', 'value' => $token->value];
        }

        if ($token->type === 'percentage') {
            $offset++;
            return ['type' => 'percentage', 'value' => $token->value];
        }

        if ($token->type === 'ident' && strtolower($token->value) === 'none') {
            $offset++;
            return ['type' => 'none', 'value' => 'none'];
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, value: string}|null
     */
    private static function numberPercentageComponent(array $tokens, int &$offset): ?array
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token->type === 'number') {
            $offset++;
            return ['type' => 'number', 'value' => $token->value];
        }

        if ($token->type === 'percentage') {
            $offset++;
            return ['type' => 'percentage', 'value' => $token->value];
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, value: string}|null
     */
    private static function percentageComponent(array $tokens, int &$offset): ?array
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null || $token->type !== 'percentage') {
            return null;
        }

        $offset++;
        return ['type' => 'percentage', 'value' => $token->value];
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, value: string}|null
     */
    private static function percentageNoneComponent(array $tokens, int &$offset): ?array
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token->type === 'percentage') {
            $offset++;
            return ['type' => 'percentage', 'value' => $token->value];
        }

        if ($token->type === 'ident' && strtolower($token->value) === 'none') {
            $offset++;
            return ['type' => 'none', 'value' => 'none'];
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     * @return array{type: string, value: string}|null
     */
    private static function hueNoneComponent(array $tokens, int &$offset): ?array
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident' && strtolower($token->value) === 'none') {
            $offset++;
            return ['type' => 'none', 'value' => 'none'];
        }

        if ($token->type === 'number') {
            $offset++;
            return ['type' => 'number', 'value' => $token->value];
        }

        $angle = self::angleComponentValue([$token]);
        if ($angle === null) {
            return null;
        }

        $offset++;
        return ['type' => 'angle', 'value' => $angle['value']];
    }

    /**
     * @param list<Token> $tokens
     */
    private static function consumeRightParenthesis(array $tokens, int &$offset): bool
    {
        if (($tokens[$offset] ?? null)?->type !== 'right-parenthesis') {
            return false;
        }

        $offset++;
        return true;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function consumeSlashDelimiter(array $tokens, int &$offset): bool
    {
        if (($tokens[$offset] ?? null)?->type !== 'delim' || $tokens[$offset]->value !== '/') {
            return false;
        }

        $offset++;
        return true;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function verticalAlignValue(array $tokens): ?string
    {
        $offset = 0;
        $count = count($tokens);
        self::skipLexborOptionalWhitespace($tokens, $offset);

        if ($offset >= $count) {
            return null;
        }

        $type = null;
        $alignment = null;
        $shift = null;
        $seenType = false;
        $seenAlignment = false;
        $seenShift = false;

        while ($offset < $count) {
            if (self::remainingTokensAreIgnorable($tokens, $offset)) {
                break;
            }

            $token = $tokens[$offset];
            $matched = false;

            if ($token->type === 'ident') {
                $value = strtolower($token->value);

                if (isset(self::ALIGNMENT_BASELINE_KEYWORDS[$value])) {
                    if ($seenAlignment) {
                        return null;
                    }

                    $alignment = $value;
                    $seenAlignment = true;
                    $matched = true;
                }
                elseif (isset(self::BASELINE_SHIFT_KEYWORDS[$value])) {
                    if ($seenShift) {
                        return null;
                    }

                    $shift = $value;
                    $seenShift = true;
                    $matched = true;
                }
                elseif (isset(self::CSS_WIDE_KEYWORDS[$value]) || isset(self::VERTICAL_ALIGN_TYPE_KEYWORDS[$value])) {
                    if ($seenType) {
                        return null;
                    }

                    $type = $value;
                    $seenType = true;
                    $seenAlignment = false;
                    $seenShift = false;
                    $matched = true;
                }
            }
            else {
                $value = self::baselineShiftValue($token);
                if ($value !== null) {
                    if ($seenShift) {
                        return null;
                    }

                    $shift = $value;
                    $seenShift = true;
                    $matched = true;
                }
            }

            if (! $matched) {
                return null;
            }

            $offset++;
            self::skipLexborOptionalWhitespace($tokens, $offset);
        }

        $parts = [];
        if ($type !== null) {
            $parts[] = $type;
        }

        if ($alignment !== null) {
            $parts[] = $alignment;
        }

        if ($shift !== null) {
            $parts[] = $shift;
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    private static function baselineShiftValue(Token $token): ?string
    {
        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::BASELINE_SHIFT_KEYWORDS[$value]) ? $value : null;
        }

        if ($token->type === 'number') {
            return $token->value === '0' ? $token->value : null;
        }

        if ($token->type === 'percentage') {
            return self::isSignedPercentage($token->value) ? $token->value : null;
        }

        if ($token->type === 'dimension' && self::isValidLengthDimension($token->value)) {
            return self::canonicalLengthDimensionValue($token->value);
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function baselineShiftDeclarationValue(array $tokens): ?string
    {
        $token = self::singleLexborValueToken($tokens);

        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value]) ? $value : self::baselineShiftValue($token);
        }

        return self::baselineShiftValue($token);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function textDecorationValue(array $tokens): ?string
    {
        $offset = 0;
        self::skipLexborOptionalWhitespace($tokens, $offset);

        if (($tokens[$offset] ?? null)?->type === 'ident') {
            $value = strtolower($tokens[$offset]->value);

            if (isset(self::CSS_WIDE_KEYWORDS[$value])) {
                $offset++;
                self::skipLexborOptionalWhitespace($tokens, $offset);

                return $offset >= count($tokens) || self::remainingTokensAreIgnorable($tokens, $offset) ? $value : null;
            }
        }

        $decoration = [
            'line' => null,
            'style' => null,
            'color' => null,
        ];

        for ($componentCount = 0; $componentCount < 3; $componentCount++) {
            if (! isset($tokens[$offset])) {
                break;
            }

            if (! self::consumeTextDecorationComponent($tokens, $offset, $decoration)) {
                return null;
            }

            self::skipLexborOptionalWhitespace($tokens, $offset);

            if ($offset >= count($tokens) || self::remainingTokensAreIgnorable($tokens, $offset)) {
                break;
            }
        }

        if ($decoration['line'] === null && $decoration['style'] === null && $decoration['color'] === null) {
            return null;
        }

        if ($offset < count($tokens) && ! self::remainingTokensAreIgnorable($tokens, $offset)) {
            return null;
        }

        $parts = [];

        if ($decoration['line'] !== null) {
            $parts[] = $decoration['line'];
        }

        if ($decoration['style'] !== null) {
            $parts[] = $decoration['style'];
        }

        if ($decoration['color'] !== null) {
            $parts[] = $decoration['color'];
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<Token> $tokens
     * @param array{line: ?string, style: ?string, color: ?string} $decoration
     */
    private static function consumeTextDecorationComponent(array $tokens, int &$offset, array &$decoration): bool
    {
        if ($decoration['line'] === null) {
            $lineOffset = $offset;
            $line = self::textDecorationLineComponentValue($tokens, $lineOffset);
            if ($line === false) {
                return false;
            }

            if (is_string($line)) {
                $decoration['line'] = $line;
                $offset = $lineOffset;
                return true;
            }
        }

        if ($decoration['style'] === null) {
            $styleOffset = $offset;
            $style = self::textDecorationStyleComponentValue($tokens, $styleOffset);
            if ($style !== null) {
                $decoration['style'] = $style;
                $offset = $styleOffset;
                return true;
            }
        }

        if ($decoration['color'] !== null) {
            return false;
        }

        $colorOffset = $offset;
        $color = self::colorValue($tokens, $colorOffset, false);
        if ($color === null) {
            return false;
        }

        $decoration['color'] = $color;
        $offset = $colorOffset;

        return true;
    }

    /**
     * @param list<Token> $tokens
     * @return string|false|null false means a matched line token was invalid, null means no line match.
     */
    private static function textDecorationLineComponentValue(array $tokens, int &$offset): string|false|null
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null || $token->type !== 'ident') {
            return null;
        }

        $value = strtolower($token->value);

        if ($value === 'none') {
            $offset++;
            return 'none';
        }

        if (! isset(self::TEXT_DECORATION_LINE_KEYWORDS[$value])) {
            return null;
        }

        $seen = [];

        while (($tokens[$offset] ?? null)?->type === 'ident') {
            $value = strtolower($tokens[$offset]->value);

            if (! isset(self::TEXT_DECORATION_LINE_KEYWORDS[$value])) {
                break;
            }

            if (isset($seen[$value])) {
                return false;
            }

            $seen[$value] = true;
            $offset++;

            $lookahead = $offset;
            self::skipLexborOptionalWhitespace($tokens, $lookahead);

            if (($tokens[$lookahead] ?? null)?->type !== 'ident') {
                $offset = $lookahead;
                break;
            }

            $nextValue = strtolower($tokens[$lookahead]->value);
            if (! isset(self::TEXT_DECORATION_LINE_KEYWORDS[$nextValue])) {
                $offset = $lookahead;
                break;
            }

            $offset = $lookahead;
        }

        $parts = [];
        foreach (['underline', 'overline', 'line-through', 'blink'] as $value) {
            if (isset($seen[$value])) {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function textDecorationStyleComponentValue(array $tokens, int &$offset): ?string
    {
        $token = $tokens[$offset] ?? null;
        if ($token === null || $token->type !== 'ident') {
            return null;
        }

        $value = strtolower($token->value);
        if (! isset(self::TEXT_DECORATION_STYLE_KEYWORDS[$value])) {
            return null;
        }

        $offset++;

        return $value;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function textDecorationLineValue(array $tokens): ?string
    {
        $offset = 0;
        $count = count($tokens);
        self::skipLexborOptionalWhitespace($tokens, $offset);

        if ($offset >= $count) {
            return null;
        }

        $seen = [];

        while ($offset < $count) {
            $token = $tokens[$offset];
            if ($token->type !== 'ident') {
                return null;
            }

            $value = strtolower($token->value);

            if (isset(self::CSS_WIDE_KEYWORDS[$value]) || $value === 'none') {
                if ($seen !== []) {
                    return null;
                }

                $offset++;
                self::skipLexborOptionalWhitespace($tokens, $offset);

                return $offset >= $count || self::remainingTokensAreIgnorable($tokens, $offset) ? $value : null;
            }

            if (! isset(self::TEXT_DECORATION_LINE_KEYWORDS[$value]) || isset($seen[$value])) {
                return null;
            }

            $seen[$value] = true;
            $offset++;
            self::skipLexborOptionalWhitespace($tokens, $offset);

            if ($offset >= $count || self::remainingTokensAreIgnorable($tokens, $offset)) {
                break;
            }
        }

        $parts = [];
        foreach (['underline', 'overline', 'line-through', 'blink'] as $value) {
            if (isset($seen[$value])) {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function textDecorationStyleValue(array $tokens): ?string
    {
        $offset = 0;
        $count = count($tokens);
        self::skipLexborOptionalWhitespace($tokens, $offset);

        if ($offset >= $count || $tokens[$offset]->type !== 'ident') {
            return null;
        }

        $value = strtolower($tokens[$offset]->value);
        if (! isset(self::CSS_WIDE_KEYWORDS[$value]) && ! isset(self::TEXT_DECORATION_STYLE_KEYWORDS[$value])) {
            return null;
        }

        $offset++;
        self::skipLexborOptionalWhitespace($tokens, $offset);

        return $offset >= $count || self::remainingTokensAreIgnorable($tokens, $offset) ? $value : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function textTransformValue(array $tokens): ?string
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);

        if ($components === []) {
            return null;
        }

        $case = null;
        $fullWidth = false;
        $fullSizeKana = false;

        foreach ($components as $component) {
            if (count($component) !== 1 || $component[0]->type !== 'ident') {
                return null;
            }

            $value = strtolower($component[0]->value);

            if (isset(self::CSS_WIDE_KEYWORDS[$value]) || $value === 'none') {
                return count($components) === 1 ? $value : null;
            }

            if (isset(self::TEXT_TRANSFORM_CASE_KEYWORDS[$value])) {
                if ($case !== null) {
                    return null;
                }

                $case = $value;
                continue;
            }

            if ($value === 'full-width') {
                if ($fullWidth) {
                    return null;
                }

                $fullWidth = true;
                continue;
            }

            if ($value === 'full-size-kana') {
                if ($fullSizeKana) {
                    return null;
                }

                $fullSizeKana = true;
                continue;
            }

            return null;
        }

        $parts = [];
        if ($case !== null) {
            $parts[] = $case;
        }

        if ($fullWidth) {
            $parts[] = 'full-width';
        }

        if ($fullSizeKana) {
            $parts[] = 'full-size-kana';
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function textCombineUprightValue(array $tokens): ?string
    {
        $tokens = self::nonIgnorableTokens($tokens);

        if (count($tokens) !== 1 && count($tokens) !== 2) {
            return null;
        }

        if ($tokens[0]->type !== 'ident') {
            return null;
        }

        $type = strtolower($tokens[0]->value);

        if ($type !== 'digits') {
            if (count($tokens) === 1 && (isset(self::CSS_WIDE_KEYWORDS[$type]) || $type === 'none' || $type === 'all')) {
                return $type;
            }

            return null;
        }

        if (count($tokens) === 1) {
            return 'digits';
        }

        if ($tokens[1]->type !== 'number') {
            return null;
        }

        $digits = $tokens[1]->value;

        return $digits === '2' || $digits === '4' ? 'digits ' . $digits : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function textIndentValue(array $tokens): ?string
    {
        $tokens = self::nonIgnorableTokens($tokens);

        if ($tokens === [] || count($tokens) > 3) {
            return null;
        }

        $length = null;
        $hanging = false;
        $eachLine = false;

        foreach ($tokens as $token) {
            $lengthValue = self::lengthPercentageComponentValue([$token], false);
            if ($lengthValue !== null) {
                if ($length !== null) {
                    return null;
                }

                $length = $lengthValue;
                continue;
            }

            if ($token->type !== 'ident') {
                return null;
            }

            $value = strtolower($token->value);

            if (isset(self::CSS_WIDE_KEYWORDS[$value])) {
                return count($tokens) === 1 ? $value : null;
            }

            if ($value === 'hanging') {
                if ($hanging) {
                    return null;
                }

                $hanging = true;
                continue;
            }

            if ($value === 'each-line') {
                if ($eachLine) {
                    return null;
                }

                $eachLine = true;
                continue;
            }

            return null;
        }

        if ($length === null) {
            return null;
        }

        $parts = [$length];
        if ($hanging) {
            $parts[] = 'hanging';
        }

        if ($eachLine) {
            $parts[] = 'each-line';
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function hangingPunctuationValue(array $tokens): ?string
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);

        if ($components === []) {
            return null;
        }

        $typeFirst = null;
        $forceAllow = null;
        $last = null;

        foreach ($components as $component) {
            if (count($component) !== 1 || $component[0]->type !== 'ident') {
                return null;
            }

            $value = strtolower($component[0]->value);

            if (isset(self::CSS_WIDE_KEYWORDS[$value]) || $value === 'none') {
                return count($components) === 1 ? $value : null;
            }

            if ($value === 'first') {
                if ($typeFirst !== null) {
                    return null;
                }

                $typeFirst = $value;
                continue;
            }

            if ($value === 'force-end' || $value === 'allow-end') {
                if ($forceAllow !== null) {
                    return null;
                }

                $forceAllow = $value;
                continue;
            }

            if ($value === 'last') {
                if ($last !== null) {
                    return null;
                }

                $last = $value;
                continue;
            }

            return null;
        }

        $parts = [];

        if ($typeFirst !== null) {
            $parts[] = $typeFirst;
        }

        if ($forceAllow !== null) {
            $parts[] = $forceAllow;
        }

        if ($last !== null) {
            $parts[] = $last;
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidNumberPercentage(array $tokens): bool
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return false;
        }

        if ($token->type === 'ident') {
            return isset(self::CSS_WIDE_KEYWORDS[strtolower($token->value)]);
        }

        return in_array($token->type, ['number', 'percentage'], true);
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, true> $keywords
     */
    private static function isValidNumberLengthPercentage(array $tokens, array $keywords): bool
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return false;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value]) || isset($keywords[$value]);
        }

        if (in_array($token->type, ['number', 'percentage'], true)) {
            return true;
        }

        return $token->type === 'dimension' && self::isValidLengthDimension($token->value);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function numberLengthValue(array $tokens): ?string
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value]) ? $value : null;
        }

        if ($token->type === 'number') {
            return $token->value;
        }

        if ($token->type === 'dimension' && self::isValidLengthDimension($token->value)) {
            return self::canonicalLengthDimensionValue($token->value);
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function fontWeightValue(array $tokens): ?string
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value])
                || in_array($value, ['normal', 'bold', 'bolder', 'lighter'], true)
                ? $value
                : null;
        }

        if ($token->type !== 'number') {
            return null;
        }

        $number = (float) $token->value;

        return $number >= 1.0 && $number <= 1000.0 ? $token->value : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function fontStretchValue(array $tokens): ?string
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value])
                || isset(self::FONT_STRETCH_KEYWORDS[$value])
                ? $value
                : null;
        }

        if ($token->type !== 'percentage') {
            return null;
        }

        return (float) $token->value >= 0.0 ? $token->value : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function fontStyleValue(array $tokens): ?string
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);

        if ($components === [] || count($components[0]) !== 1 || $components[0][0]->type !== 'ident') {
            return null;
        }

        $value = strtolower($components[0][0]->value);

        if (isset(self::CSS_WIDE_KEYWORDS[$value]) || $value === 'normal' || $value === 'italic') {
            return count($components) === 1 ? $value : null;
        }

        if ($value !== 'oblique') {
            return null;
        }

        if (count($components) === 1) {
            return 'oblique';
        }

        if (count($components) !== 2) {
            return null;
        }

        $angle = self::angleComponentValue($components[1]);

        if ($angle === null || $angle['number'] < -90.0 || $angle['number'] > 90.0) {
            return null;
        }

        return 'oblique ' . $angle['value'];
    }

    /**
     * @param list<Token> $component
     * @return array{value: string, number: float}|null
     */
    private static function angleComponentValue(array $component): ?array
    {
        if (count($component) !== 1 || $component[0]->type !== 'dimension') {
            return null;
        }

        if (! preg_match('/^([+-]?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?)([a-zA-Z]+)$/i', $component[0]->value, $matches)) {
            return null;
        }

        $unit = strtolower($matches[2]);

        if (! isset(self::ANGLE_UNITS[$unit])) {
            return null;
        }

        return [
            'value' => $matches[1] . $unit,
            'number' => (float) $matches[1],
        ];
    }

    /**
     * @param list<Token> $tokens
     */
    private static function fontSizeValue(array $tokens): ?string
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return null;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value])
                || isset(self::FONT_SIZE_KEYWORDS[$value])
                ? $value
                : null;
        }

        if ($token->type === 'number') {
            return $token->value === '0' ? $token->value : null;
        }

        if ($token->type === 'percentage') {
            return self::isSignedPercentage($token->value) && (float) $token->value >= 0.0 ? $token->value : null;
        }

        if ($token->type === 'dimension' && self::isValidLengthDimension($token->value)) {
            return (float) $token->value >= 0.0 ? self::canonicalLengthDimensionValue($token->value) : null;
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function fontFamilyValue(array $tokens): ?string
    {
        if ($tokens === []) {
            return null;
        }

        $offset = 0;
        $families = [];
        $count = count($tokens);

        while ($offset < $count) {
            self::skipFontFamilyOptionalWhitespace($tokens, $offset);

            if ($offset >= $count) {
                return null;
            }

            $family = self::fontFamilyNameValue($tokens[$offset]);

            if ($family === null) {
                return null;
            }

            $families[] = $family;
            $offset++;

            self::skipFontFamilyOptionalWhitespace($tokens, $offset);

            if ($offset >= $count) {
                return implode(', ', $families);
            }

            if ($tokens[$offset]->type !== 'comma') {
                return null;
            }

            $offset++;
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function skipFontFamilyOptionalWhitespace(array $tokens, int &$offset): void
    {
        $skippedWhitespace = false;

        while ($offset < count($tokens)) {
            if ($tokens[$offset]->type === 'comment') {
                $offset++;
                continue;
            }

            if ($tokens[$offset]->type === 'whitespace' && ! $skippedWhitespace) {
                $offset++;
                $skippedWhitespace = true;
                continue;
            }

            break;
        }
    }

    private static function fontFamilyNameValue(Token $token): ?string
    {
        if ($token->type === 'ident') {
            return self::LEXBOR_VALUE_KEYWORDS[strtolower($token->value)]
                ?? self::serializeIdentOrString($token->value);
        }

        if ($token->type === 'string') {
            return self::serializeIdentOrString(self::unescapeSerializedStringToken($token->value));
        }

        return null;
    }

    private static function serializeIdentOrString(string $value): string
    {
        return self::isLexborNameByteString($value)
            ? $value
            : self::serializeCssString($value);
    }

    private static function isLexborNameByteString(string $value): bool
    {
        $length = strlen($value);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($value[$offset]);

            if (
                $byte === 0x2d
                || ($byte >= 0x30 && $byte <= 0x39)
                || ($byte >= 0x41 && $byte <= 0x5a)
                || $byte === 0x5f
                || ($byte >= 0x61 && $byte <= 0x7a)
                || $byte === 0xb7
                || ($byte >= 0xc0 && $byte <= 0xd6)
                || ($byte >= 0xd8 && $byte <= 0xf6)
                || ($byte >= 0xf8 && $byte <= 0xfe)
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    private static function serializeCssString(string $value): string
    {
        $result = '"';
        $length = strlen($value);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $value[$offset];

            $result .= match ($char) {
                '\\' => '\\\\',
                '"' => '\\"',
                "\n" => self::serializeHexEscape('0a', $value, $offset),
                "\t" => self::serializeHexEscape('09', $value, $offset),
                "\r" => self::serializeHexEscape('0d', $value, $offset),
                default => $char,
            };
        }

        return $result . '"';
    }

    private static function serializeHexEscape(string $hex, string $value, int $offset): string
    {
        return '\\' . $hex . (
            isset($value[$offset + 1]) && ctype_xdigit($value[$offset + 1])
                ? ' '
                : ''
        );
    }

    private static function unescapeSerializedStringToken(string $value): string
    {
        $inner = strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"'
            ? substr($value, 1, -1)
            : $value;

        $result = '';
        $length = strlen($inner);

        for ($offset = 0; $offset < $length; $offset++) {
            if ($inner[$offset] === '\\' && $offset + 1 < $length) {
                $offset++;
            }

            $result .= $inner[$offset];
        }

        return $result;
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, true> $keywords
     */
    private static function isValidLengthKeyword(array $tokens, array $keywords): bool
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return false;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value]) || isset($keywords[$value]);
        }

        if ($token->type === 'number') {
            return $token->value === '0';
        }

        return $token->type === 'dimension' && self::isValidLengthDimension($token->value);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidInteger(array $tokens): bool
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return false;
        }

        if ($token->type === 'ident') {
            return isset(self::CSS_WIDE_KEYWORDS[strtolower($token->value)]);
        }

        return $token->type === 'number' && self::isLongInteger($token->value);
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, true> $keywords
     */
    private static function isValidIntegerKeyword(array $tokens, array $keywords): bool
    {
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return false;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value]) || isset($keywords[$value]);
        }

        return $token->type === 'number' && self::isLongInteger($token->value);
    }

    private static function isValidLengthDimension(string $value): bool
    {
        if (! preg_match('/^[+-]?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?([a-zA-Z]+)$/i', $value, $matches)) {
            return false;
        }

        return isset(self::LENGTH_UNITS[strtolower($matches[1])]);
    }

    private static function isLongInteger(string $value): bool
    {
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return false;
        }

        $negative = str_starts_with($value, '-');
        $digits = $negative ? substr($value, 1) : $value;
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return true;
        }

        $limit = $negative ? self::LONG_MIN_ABS_DECIMAL : self::LONG_MAX_DECIMAL;

        return strlen($digits) < strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function isValidKeywordProperty(string $property, array $tokens): bool
    {
        $tokens = self::nonIgnorableTokens($tokens);

        if (count($tokens) !== 1 || $tokens[0]->type !== 'ident') {
            return false;
        }

        $value = strtolower($tokens[0]->value);

        return isset(self::CSS_WIDE_KEYWORDS[$value])
            || isset(self::KEYWORD_PROPERTIES[$property][$value]);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function serializeKnownPropertyValue(string $property, array $tokens, string $fallback): string
    {
        if ($property === 'display') {
            return self::serializeIdentSequence($tokens) ?? $fallback;
        }

        if ($property === 'text-overflow') {
            return self::singleKeywordValue($tokens, self::KEYWORD_PROPERTIES['text-overflow']) ?? $fallback;
        }

        if ($property === 'white-space') {
            return self::singleKeywordValue($tokens, self::KEYWORD_PROPERTIES['white-space']) ?? $fallback;
        }

        if ($property === 'word-break') {
            return self::singleKeywordValue($tokens, self::KEYWORD_PROPERTIES['word-break']) ?? $fallback;
        }

        if (isset(self::KEYWORD_PROPERTIES[$property])) {
            return self::serializeIdentSequence($tokens) ?? $fallback;
        }

        if ($property === 'tab-size') {
            return self::numberLengthValue($tokens) ?? $fallback;
        }

        if ($property === 'hanging-punctuation') {
            return self::hangingPunctuationValue($tokens) ?? $fallback;
        }

        if (isset(self::BORDER_PROPERTIES[$property])) {
            return self::borderDeclarationValue($tokens) ?? $fallback;
        }

        if (isset(self::COLOR_PROPERTIES[$property])) {
            return self::colorDeclarationValue($tokens) ?? $fallback;
        }

        if ($property === 'alignment-baseline') {
            return self::singleLexborKeywordValue($tokens, self::ALIGNMENT_BASELINE_KEYWORDS) ?? $fallback;
        }

        if ($property === 'baseline-shift') {
            return self::baselineShiftDeclarationValue($tokens) ?? $fallback;
        }

        if ($property === 'baseline-source') {
            return self::singleLexborKeywordValue($tokens, self::BASELINE_SOURCE_KEYWORDS) ?? $fallback;
        }

        if ($property === 'dominant-baseline') {
            return self::singleLexborKeywordValue($tokens, self::DOMINANT_BASELINE_KEYWORDS) ?? $fallback;
        }

        if ($property === 'text-decoration') {
            return self::textDecorationValue($tokens) ?? $fallback;
        }

        if ($property === 'text-decoration-line') {
            return self::textDecorationLineValue($tokens) ?? $fallback;
        }

        if ($property === 'text-decoration-style') {
            return self::textDecorationStyleValue($tokens) ?? $fallback;
        }

        if ($property === 'vertical-align') {
            return self::verticalAlignValue($tokens) ?? $fallback;
        }

        if ($property === 'font-weight') {
            return self::fontWeightValue($tokens) ?? $fallback;
        }

        if ($property === 'font-family') {
            return self::fontFamilyValue($tokens) ?? $fallback;
        }

        if ($property === 'font-size') {
            return self::fontSizeValue($tokens) ?? $fallback;
        }

        if ($property === 'font-stretch') {
            return self::fontStretchValue($tokens) ?? $fallback;
        }

        if ($property === 'font-style') {
            return self::fontStyleValue($tokens) ?? $fallback;
        }

        if (in_array($property, ['flex-grow', 'flex-shrink'], true)) {
            return self::singleValueToken($tokens)?->value ?? $fallback;
        }

        if ($property === 'float') {
            return self::floatValue($tokens) ?? $fallback;
        }

        if ($property === 'float-reference') {
            return self::singleKeywordValue($tokens, self::FLOAT_REFERENCE_KEYWORDS) ?? $fallback;
        }

        if ($property === 'float-defer') {
            return self::floatDeferValue($tokens) ?? $fallback;
        }

        if ($property === 'float-offset') {
            return self::floatOffsetValue($tokens) ?? $fallback;
        }

        if ($property === 'flex') {
            return self::serializeFlex($tokens) ?? $fallback;
        }

        if ($property === 'flex-basis') {
            return self::flexBasisValue($tokens) ?? $fallback;
        }

        if ($property === 'flex-flow') {
            return self::serializeFlexFlow($tokens) ?? $fallback;
        }

        if ($property === 'text-transform') {
            return self::textTransformValue($tokens) ?? $fallback;
        }

        if ($property === 'text-combine-upright') {
            return self::textCombineUprightValue($tokens) ?? $fallback;
        }

        if ($property === 'text-indent') {
            return self::textIndentValue($tokens) ?? $fallback;
        }

        if (
            in_array($property, ['margin', 'margin-bottom', 'margin-left', 'margin-right', 'margin-top'], true)
            || in_array($property, ['padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top'], true)
            || in_array($property, ['bottom', 'inset-block-end', 'inset-block-start', 'inset-inline-end', 'inset-inline-start', 'left', 'right', 'top'], true)
        ) {
            return implode(' ', array_map(
                static fn (array $component): string => self::serializeComponentValue($component),
                self::splitWhitespaceSeparatedComponents($tokens),
            ));
        }

        return $fallback;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function serializeFlex(array $tokens): ?string
    {
        $flex = self::parseFlex($tokens);

        if ($flex === null) {
            return null;
        }

        if ($flex['type'] !== null) {
            return $flex['type'];
        }

        $parts = [];

        if ($flex['grow'] !== null) {
            $parts[] = $flex['grow'];

            if ($flex['shrink'] !== null) {
                $parts[] = $flex['shrink'];
            }
        }

        if ($flex['basis'] !== null) {
            $parts[] = $flex['basis'];
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function serializeFlexFlow(array $tokens): ?string
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);

        if ($components === []) {
            return null;
        }

        if (count($components) === 1) {
            return strtolower(self::serializeComponentValue($components[0]));
        }

        $firstValue = strtolower(self::serializeComponentValue($components[0]));
        $secondValue = strtolower(self::serializeComponentValue($components[1]));

        if (self::isFlexWrapKeyword($firstValue) && self::isFlexDirectionKeyword($secondValue)) {
            return $secondValue . ' ' . $firstValue;
        }

        return $firstValue . ' ' . $secondValue;
    }

    private static function isFlexDirectionKeyword(string $value): bool
    {
        return isset(self::KEYWORD_PROPERTIES['flex-direction'][$value]);
    }

    private static function isFlexWrapKeyword(string $value): bool
    {
        return isset(self::KEYWORD_PROPERTIES['flex-wrap'][$value]);
    }

    /**
     * @param list<Token> $tokens
     */
    private static function flexBasisValue(array $tokens): ?string
    {
        $components = self::splitWhitespaceSeparatedComponents($tokens);

        if (count($components) !== 1 || count($components[0]) !== 1) {
            return null;
        }

        $token = $components[0][0];

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value]) || isset(self::FLEX_BASIS_KEYWORDS[$value]) ? $value : null;
        }

        if ($token->type === 'number') {
            return $token->value === '0' ? $token->value : null;
        }

        if ($token->type === 'percentage') {
            return self::isSignedPercentage($token->value) ? $token->value : null;
        }

        if ($token->type === 'dimension' && self::isValidLengthDimension($token->value)) {
            return self::canonicalLengthDimensionValue($token->value);
        }

        return null;
    }

    /**
     * @param list<Token> $component
     */
    private static function flexNumberValue(array $component): ?string
    {
        if (count($component) !== 1 || $component[0]->type !== 'number') {
            return null;
        }

        return $component[0]->value;
    }

    /**
     * @param list<Token> $component
     */
    private static function flexBasisComponentValue(array $component): ?string
    {
        if (count($component) !== 1) {
            return null;
        }

        $token = $component[0];

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::FLEX_BASIS_KEYWORDS[$value]) ? $value : null;
        }

        if ($token->type === 'number') {
            return $token->value === '0' ? $token->value : null;
        }

        if ($token->type === 'percentage') {
            return self::isSignedPercentage($token->value) ? $token->value : null;
        }

        if ($token->type === 'dimension' && self::isValidLengthDimension($token->value)) {
            return self::canonicalLengthDimensionValue($token->value);
        }

        return null;
    }

    /**
     * @param list<Token> $component
     */
    private static function lengthPercentageComponentValue(array $component, bool $allowCssWide): ?string
    {
        if (count($component) !== 1) {
            return null;
        }

        $token = $component[0];

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return $allowCssWide && isset(self::CSS_WIDE_KEYWORDS[$value]) ? $value : null;
        }

        if ($token->type === 'number') {
            return $token->value === '0' ? $token->value : null;
        }

        if ($token->type === 'percentage') {
            return self::isSignedPercentage($token->value) ? $token->value : null;
        }

        if ($token->type === 'dimension' && self::isValidLengthDimension($token->value)) {
            return self::canonicalLengthDimensionValue($token->value);
        }

        return null;
    }

    private static function canonicalLengthDimensionValue(string $value): string
    {
        if (! preg_match('/^([+-]?(?:\d+|\d*\.\d+)(?:e[+-]?\d+)?)([a-zA-Z]+)$/i', $value, $matches)) {
            return $value;
        }

        $unit = strtolower($matches[2]);
        $unit = $unit === 'q' ? 'Q' : $unit;

        return $matches[1] . $unit;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function serializeIdentSequence(array $tokens): ?string
    {
        $tokens = self::nonIgnorableTokens($tokens);

        if ($tokens === []) {
            return null;
        }

        $values = [];

        foreach ($tokens as $token) {
            if ($token->type !== 'ident') {
                return null;
            }

            $values[] = $token->value;
        }

        return implode(' ', $values);
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function nonIgnorableTokens(array $tokens): array
    {
        $tokens = self::stripWhitespaceTokens($tokens);

        return array_values(array_filter(
            $tokens,
            static fn (Token $token): bool => ! self::isIgnorableToken($token),
        ));
    }

    /**
     * @param list<Token> $tokens
     */
    private static function singleValueToken(array $tokens): ?Token
    {
        $tokens = self::nonIgnorableTokens($tokens);

        return count($tokens) === 1 ? $tokens[0] : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function singleLexborValueToken(array $tokens): ?Token
    {
        $offset = 0;
        self::skipLexborOptionalWhitespace($tokens, $offset);

        if (! isset($tokens[$offset])) {
            return null;
        }

        $token = $tokens[$offset];
        $offset++;
        self::skipLexborOptionalWhitespace($tokens, $offset);

        return $offset >= count($tokens) || self::remainingTokensAreIgnorable($tokens, $offset) ? $token : null;
    }

    /**
     * @param list<Token> $tokens
     * @param array<string, true> $keywords
     */
    private static function singleLexborKeywordValue(array $tokens, array $keywords): ?string
    {
        $token = self::singleLexborValueToken($tokens);

        if ($token === null || $token->type !== 'ident') {
            return null;
        }

        $value = strtolower($token->value);

        return isset(self::CSS_WIDE_KEYWORDS[$value]) || isset($keywords[$value]) ? $value : null;
    }

    private static function isIgnorableToken(Token $token): bool
    {
        return $token->type === 'whitespace' || $token->type === 'comment';
    }

    /**
     * @param list<Token> $tokens
     */
    private static function skipLexborOptionalWhitespace(array $tokens, int &$offset): void
    {
        self::skipLexborComments($tokens, $offset);

        if (($tokens[$offset] ?? null)?->type === 'whitespace') {
            $offset++;
            self::skipLexborComments($tokens, $offset);
        }
    }

    /**
     * @param list<Token> $tokens
     */
    private static function skipLexborComments(array $tokens, int &$offset): void
    {
        while (($tokens[$offset] ?? null)?->type === 'comment') {
            $offset++;
        }
    }

    /**
     * @param list<Token> $tokens
     */
    private static function remainingTokensAreIgnorable(array $tokens, int $offset): bool
    {
        while ($offset < count($tokens)) {
            if (! self::isIgnorableToken($tokens[$offset])) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function trimTrailingIgnorableTokens(array &$tokens): void
    {
        while ($tokens !== [] && self::isIgnorableToken($tokens[array_key_last($tokens)])) {
            array_pop($tokens);
        }
    }

    /**
     * @param list<Token> $tokens
     */
    private static function trimTrailingWhitespaceTokens(array &$tokens): void
    {
        while ($tokens !== [] && $tokens[array_key_last($tokens)]->type === 'whitespace') {
            array_pop($tokens);
        }
    }

    /**
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function stripWhitespaceTokens(array $tokens): array
    {
        while ($tokens !== [] && self::isIgnorableToken($tokens[0])) {
            array_shift($tokens);
        }

        while ($tokens !== [] && self::isIgnorableToken($tokens[array_key_last($tokens)])) {
            array_pop($tokens);
        }

        return $tokens;
    }

    /**
     * @param list<Token> $tokens
     */
    private static function serializeComponentValue(array $tokens): string
    {
        while ($tokens !== [] && $tokens[0]->type === 'whitespace') {
            array_shift($tokens);
        }

        while ($tokens !== [] && $tokens[array_key_last($tokens)]->type === 'whitespace') {
            array_pop($tokens);
        }

        $value = '';

        foreach ($tokens as $token) {
            if ($token->type === 'comment') {
                continue;
            }

            $value .= $token->value;
        }

        return $value;
    }

    /**
     * @param list<Token> $tokens
     * @return array{list<Token>, bool}
     */
    private static function extractImportant(array $tokens): array
    {
        $remaining = $tokens;
        self::trimTrailingWhitespaceTokens($remaining);

        $importantToken = array_pop($remaining);
        $bangToken = array_pop($remaining);

        if (
            $importantToken !== null
            && $bangToken !== null
            && $importantToken->type === 'ident'
            && strcasecmp($importantToken->value, 'important') === 0
            && $bangToken->type === 'delim'
            && $bangToken->value === '!'
        ) {
            self::trimTrailingIgnorableTokens($remaining);

            return [$remaining, true];
        }

        return [$tokens, false];
    }
}

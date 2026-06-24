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
        'box-sizing' => true,
        'bottom' => true,
        'clear' => true,
        'direction' => true,
        'display' => true,
        'flex' => true,
        'flex-basis' => true,
        'flex-direction' => true,
        'flex-flow' => true,
        'flex-grow' => true,
        'flex-shrink' => true,
        'flex-wrap' => true,
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
        'text-align' => true,
        'text-align-all' => true,
        'text-align-last' => true,
        'text-decoration' => true,
        'text-justify' => true,
        'text-orientation' => true,
        'top' => true,
        'unicode-bidi' => true,
        'visibility' => true,
        'width' => true,
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

        while ($offset < count($tokens) && self::isIgnorableToken($tokens[$offset])) {
            $offset++;
        }

        $valueTokens = $this->consumeValueTokens($tokens, $offset);
        [$valueTokens, $important] = self::extractImportant($valueTokens);
        $value = self::serializeComponentValue($valueTokens);
        $type = $this->classifyDeclaration($name, $value, $valueTokens);

        return [
            'type' => $type,
            'name' => $name,
            'value' => $type === 'property' ? self::serializeKnownPropertyValue(strtolower($name), $valueTokens, $value) : $value,
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
            'display' => self::isValidDisplay($valueTokens) ? 'property' : 'undef',
            'flex' => self::parseFlex($valueTokens) !== null ? 'property' : 'undef',
            'flex-basis' => self::isValidFlexBasis($valueTokens) ? 'property' : 'undef',
            'flex-flow' => self::isValidFlexFlow($valueTokens) ? 'property' : 'undef',
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
        $token = self::singleValueToken($tokens);

        if ($token === null) {
            return false;
        }

        if ($token->type === 'ident') {
            $value = strtolower($token->value);

            return isset(self::CSS_WIDE_KEYWORDS[$value])
                || isset(self::FLEX_BASIS_KEYWORDS[$value]);
        }

        if ($token->type === 'number') {
            return $token->value === '0';
        }

        if ($token->type === 'percentage') {
            return self::isSignedPercentage($token->value);
        }

        return $token->type === 'dimension' && self::isValidLengthDimension($token->value);
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

        if (isset(self::KEYWORD_PROPERTIES[$property])) {
            return self::serializeIdentSequence($tokens) ?? $fallback;
        }

        if (in_array($property, ['flex-grow', 'flex-shrink'], true)) {
            return self::singleValueToken($tokens)?->value ?? $fallback;
        }

        if ($property === 'flex') {
            return self::serializeFlex($tokens) ?? $fallback;
        }

        if ($property === 'flex-flow') {
            return self::serializeFlexFlow($tokens) ?? $fallback;
        }

        if (
            in_array($property, ['margin', 'margin-bottom', 'margin-left', 'margin-right', 'margin-top'], true)
            || in_array($property, ['padding', 'padding-bottom', 'padding-left', 'padding-right', 'padding-top'], true)
            || in_array($property, ['bottom', 'inset-block-end', 'inset-block-start', 'inset-inline-end', 'inset-inline-start', 'left', 'right', 'top'], true)
            || $property === 'flex-basis'
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
            return strtolower($token->value);
        }

        return null;
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

    private static function isIgnorableToken(Token $token): bool
    {
        return $token->type === 'whitespace' || $token->type === 'comment';
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
        self::trimTrailingIgnorableTokens($remaining);

        $importantToken = array_pop($remaining);
        self::trimTrailingIgnorableTokens($remaining);
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

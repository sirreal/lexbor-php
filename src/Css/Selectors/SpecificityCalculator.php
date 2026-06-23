<?php

declare(strict_types=1);

namespace Lexbor\Css\Selectors;

final class SpecificityCalculator
{
    /**
     * @return array{a: int, b: int, c: int}
     */
    public function calculate(string $selector): array
    {
        return self::parseSelector($selector);
    }

    /**
     * @return array{a: int, b: int, c: int}
     */
    private static function parseSelector(
        string $selector,
        bool $stopAfterFirstCompound = false,
        bool $ignoreNestedFunctionalPseudo = false,
    ): array {
        $specificity = ['a' => 0, 'b' => 0, 'c' => 0];
        $offset = 0;
        $length = strlen($selector);
        $startedCompound = false;

        while ($offset < $length) {
            $char = $selector[$offset];

            if (ctype_space($char)) {
                self::skipWhitespace($selector, $offset);
                if ($stopAfterFirstCompound && $startedCompound) {
                    break;
                }

                continue;
            }

            if ($char === ',' || $char === ')') {
                break;
            }

            if (in_array($char, ['>', '+', '~'], true) || ($char === '|' && ($selector[$offset + 1] ?? '') === '|')) {
                $offset += $char === '|' ? 2 : 1;
                if ($stopAfterFirstCompound && $startedCompound) {
                    break;
                }

                continue;
            }

            if ($char === '#') {
                $specificity['a']++;
                $offset++;
                self::consumeName($selector, $offset);
                $startedCompound = true;
                continue;
            }

            if ($char === '.') {
                $specificity['b']++;
                $offset++;
                self::consumeName($selector, $offset);
                $startedCompound = true;
                continue;
            }

            if ($char === '[') {
                $specificity['b']++;
                self::consumeAttribute($selector, $offset);
                $startedCompound = true;
                continue;
            }

            if ($char === ':') {
                self::consumePseudo($selector, $offset, $specificity, $ignoreNestedFunctionalPseudo);
                $startedCompound = true;
                continue;
            }

            if ($char === '*') {
                $offset++;
                $startedCompound = true;
                continue;
            }

            if (self::isNameStart($char)) {
                self::consumeName($selector, $offset);
                $specificity['c']++;
                $startedCompound = true;
                continue;
            }

            $offset++;
        }

        return $specificity;
    }

    /**
     * @param array{a: int, b: int, c: int} $specificity
     */
    private static function consumePseudo(
        string $selector,
        int &$offset,
        array &$specificity,
        bool $ignoreNestedFunctionalPseudo,
    ): void {
        $offset++;

        if (($selector[$offset] ?? '') === ':') {
            $offset++;
            self::consumeName($selector, $offset);
            $specificity['c']++;
            return;
        }

        $nameStart = $offset;
        self::consumeName($selector, $offset);
        $name = strtolower(substr($selector, $nameStart, $offset - $nameStart));

        if (($selector[$offset] ?? '') !== '(') {
            $specificity['b']++;
            return;
        }

        $content = self::consumeFunctionContent($selector, $offset);

        if ($ignoreNestedFunctionalPseudo && in_array($name, ['has', 'is', 'not', 'where'], true)) {
            return;
        }

        if (in_array($name, ['has', 'is', 'not'], true)) {
            self::add($specificity, self::maxSelectorListSpecificity($content, false, true));
            return;
        }

        if ($name === 'where') {
            return;
        }

        if (in_array($name, ['nth-child', 'nth-last-child'], true)) {
            $specificity['b']++;
            $ofSelector = self::nthOfSelector($content);

            if ($ofSelector !== null) {
                self::add($specificity, self::maxSelectorListSpecificity($ofSelector, true, true));
            }

            return;
        }

        $specificity['b']++;
    }

    private static function consumeName(string $selector, int &$offset): void
    {
        $length = strlen($selector);

        while ($offset < $length && self::isName($selector[$offset])) {
            $offset++;
        }
    }

    private static function consumeAttribute(string $selector, int &$offset): void
    {
        $offset++;
        $length = strlen($selector);
        $quote = null;

        while ($offset < $length) {
            $char = $selector[$offset];

            if ($quote !== null) {
                if ($char === '\\') {
                    $offset += 2;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                $offset++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $offset++;
                continue;
            }

            $offset++;

            if ($char === ']') {
                return;
            }
        }
    }

    private static function consumeFunctionContent(string $selector, int &$offset): string
    {
        $offset++;
        $start = $offset;
        $length = strlen($selector);
        $depth = 1;

        while ($offset < $length) {
            $char = $selector[$offset];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    $content = substr($selector, $start, $offset - $start);
                    $offset++;

                    return $content;
                }
            }

            $offset++;
        }

        return substr($selector, $start);
    }

    /**
     * @return array{a: int, b: int, c: int}
     */
    private static function maxSelectorListSpecificity(
        string $selectorList,
        bool $stopAfterFirstCompound,
        bool $ignoreNestedFunctionalPseudo,
    ): array {
        $max = ['a' => 0, 'b' => 0, 'c' => 0];

        foreach (self::splitSelectorList($selectorList) as $selector) {
            $specificity = self::parseSelector($selector, $stopAfterFirstCompound, $ignoreNestedFunctionalPseudo);

            if (self::compare($specificity, $max) > 0) {
                $max = $specificity;
            }
        }

        return $max;
    }

    /**
     * @return list<string>
     */
    private static function splitSelectorList(string $selectorList): array
    {
        $selectors = [];
        $start = 0;
        $depth = 0;
        $length = strlen($selectorList);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $selectorList[$offset];

            if ($char === '(' || $char === '[') {
                $depth++;
                continue;
            }

            if (($char === ')' || $char === ']') && $depth > 0) {
                $depth--;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $selectors[] = substr($selectorList, $start, $offset - $start);
                $start = $offset + 1;
            }
        }

        $selectors[] = substr($selectorList, $start);

        return $selectors;
    }

    private static function nthOfSelector(string $content): ?string
    {
        $length = strlen($content);

        for ($offset = 0; $offset < $length; $offset++) {
            if (strtolower(substr($content, $offset, 2)) !== 'of') {
                continue;
            }

            $before = $content[$offset - 1] ?? ' ';
            $after = $content[$offset + 2] ?? ' ';

            if (! self::isName($before) && ! self::isName($after)) {
                return substr($content, $offset + 2);
            }
        }

        return null;
    }

    /**
     * @param array{a: int, b: int, c: int} $target
     * @param array{a: int, b: int, c: int} $source
     */
    private static function add(array &$target, array $source): void
    {
        $target['a'] += $source['a'];
        $target['b'] += $source['b'];
        $target['c'] += $source['c'];
    }

    /**
     * @param array{a: int, b: int, c: int} $left
     * @param array{a: int, b: int, c: int} $right
     */
    private static function compare(array $left, array $right): int
    {
        return ($left['a'] <=> $right['a'])
            ?: ($left['b'] <=> $right['b'])
            ?: ($left['c'] <=> $right['c']);
    }

    private static function skipWhitespace(string $selector, int &$offset): void
    {
        while (($selector[$offset] ?? null) !== null && ctype_space($selector[$offset])) {
            $offset++;
        }
    }

    private static function isNameStart(string $char): bool
    {
        return ctype_alpha($char) || $char === '_' || ord($char) >= 0x80;
    }

    private static function isName(string $char): bool
    {
        return self::isNameStart($char) || ctype_digit($char) || $char === '-';
    }
}

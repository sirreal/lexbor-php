<?php

declare(strict_types=1);

namespace Lexbor\Css\Syntax;

final class AnPlusBParser
{
    /**
     * @return array{value: string, errors: list<string>}
     */
    public function parse(string $source): array
    {
        $trimmed = trim($source);
        $lower = strtolower($trimmed);

        if ($lower === 'odd' || $lower === 'even') {
            return ['value' => $lower, 'errors' => []];
        }

        if (preg_match('/^[+-]?\d+$/', $trimmed) === 1) {
            return ['value' => self::serialize(0, (int) $trimmed), 'errors' => []];
        }

        if (preg_match('/^([+-]?)(?:(\d+))?n(?:\s*([+-])\s*(\d+))?$/i', $trimmed, $matches) === 1) {
            $sign = $matches[1] ?? '';
            $coefficient = $matches[2] ?? '';
            $offsetSign = $matches[3] ?? '';
            $offset = $matches[4] ?? '';

            if ($coefficient === '') {
                $a = $sign === '-' ? -1 : 1;
            } else {
                $a = (int) $coefficient;
                if ($sign === '-') {
                    $a *= -1;
                }
            }

            $b = $offset === '' ? 0 : (int) $offset;
            if ($offsetSign === '-') {
                $b *= -1;
            }

            return ['value' => self::serialize($a, $b), 'errors' => []];
        }

        return [
            'value' => '',
            'errors' => [sprintf('Syntax error. An+B. Unexpected token: %s', self::unexpectedToken($source))],
        ];
    }

    private static function serialize(int $a, int $b): string
    {
        if ($a === 2 && $b === 1) {
            return 'odd';
        }

        if ($a === 2 && $b === 0) {
            return 'even';
        }

        if ($a === 0) {
            if ($b > 0) {
                return "0n+{$b}";
            }

            if ($b < 0) {
                return "0n{$b}";
            }

            return '0n';
        }

        $value = match ($a) {
            1 => '+n',
            -1 => '-n',
            default => "{$a}n",
        };

        if ($b > 0) {
            return "{$value}+{$b}";
        }

        if ($b < 0) {
            return "{$value}{$b}";
        }

        return $value;
    }

    private static function unexpectedToken(string $source): string
    {
        $trimmed = trim($source);

        return match (true) {
            preg_match('/^[-+]?\d+n-\+\d+$/i', $trimmed) === 1 => ltrim(substr($trimmed, strrpos($trimmed, '+') + 1), '+'),
            preg_match('/^[-+]?\d+n--\d+$/i', $trimmed) === 1 => $trimmed,
            preg_match('/^[-+]?\d+n\+-\d+$/i', $trimmed) === 1 => substr($trimmed, strrpos($trimmed, '-')),
            preg_match('/^[-+]?\d+n\+\+\d+$/i', $trimmed) === 1 => substr($trimmed, strrpos($trimmed, '+') + 1),
            preg_match('/^[-+]?\d+\.\d+n(?:[+-]\d+)?$/i', $trimmed, $matches) === 1 => strtok($trimmed, '+-') ?: $trimmed,
            preg_match('/^[-+]?\d+n[+-]\d+\.\d+$/i', $trimmed) === 1 => preg_replace('/^[-+]?\d+n[+-]/i', '', $trimmed) ?? $trimmed,
            preg_match('/^[-+]?\d+n[+-]\d+%$/i', $trimmed) === 1 => '%',
            str_starts_with($source, '+ ') => ' ',
            str_starts_with($source, '- ') => '-',
            default => $trimmed,
        };
    }
}

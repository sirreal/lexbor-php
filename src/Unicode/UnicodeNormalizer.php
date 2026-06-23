<?php

declare(strict_types=1);

namespace Lexbor\Unicode;

use InvalidArgumentException;
use Lexbor\Encoding\Utf8;

final class UnicodeNormalizer
{
    private const int DEFAULT_FLUSH_COUNT = 1024;
    private const int HANGUL_L_BASE = 0x1100;
    private const int HANGUL_V_BASE = 0x1161;
    private const int HANGUL_T_BASE = 0x11A7;
    private const int HANGUL_S_BASE = 0xAC00;
    private const int HANGUL_L_COUNT = 19;
    private const int HANGUL_V_COUNT = 21;
    private const int HANGUL_T_COUNT = 28;
    private const int HANGUL_N_COUNT = self::HANGUL_V_COUNT * self::HANGUL_T_COUNT;
    private const int HANGUL_S_COUNT = self::HANGUL_L_COUNT * self::HANGUL_N_COUNT;
    private const int ERROR_CODE_POINT = 0x1FFFFF;

    private readonly bool $compatibility;
    private readonly bool $compose;
    private int $flushCount = self::DEFAULT_FLUSH_COUNT;

    /** @var list<array{int, int}> */
    private array $buffer = [];
    private ?int $starterIndex = null;
    private string $pendingBytes = '';

    public function __construct(string $form)
    {
        [$this->compatibility, $this->compose] = match ($form) {
            Unicode::FORM_NFC => [false, true],
            Unicode::FORM_NFD => [false, false],
            Unicode::FORM_NFKC => [true, true],
            Unicode::FORM_NFKD => [true, false],
            default => throw new InvalidArgumentException("Unknown Unicode normalization form {$form}."),
        };
    }

    public function setFlushCount(int $count): void
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Unicode normalizer flush count cannot be negative.');
        }

        $this->flushCount = $count;
    }

    public function normalize(string $data, bool $isLast = true): string
    {
        return $this->encode($this->normalizeCodePoints($this->decode($data, $isLast), $isLast));
    }

    public function finish(): string
    {
        return $this->normalize('', true);
    }

    /**
     * @param list<int> $codePoints
     * @return list<int>
     */
    public function normalizeCodePoints(array $codePoints, bool $isLast = true): array
    {
        $output = [];

        foreach ($codePoints as $codePoint) {
            foreach (self::decomposeCodePoint($codePoint, $this->compatibility) as $decomposed) {
                $this->append($decomposed, $output);
            }
        }

        if ($isLast) {
            $this->finishCodePointsInto($output);
        }

        return $output;
    }

    /**
     * @return list<int>
     */
    public function finishCodePoints(): array
    {
        return $this->normalizeCodePoints([], true);
    }

    /**
     * @param list<int> $output
     */
    private function append(int $codePoint, array &$output): void
    {
        $this->buffer[] = [$codePoint, Unicode::combiningClass($codePoint)];
        $index = count($this->buffer) - 1;

        if ($this->buffer[$index][1] === 0) {
            $this->processStarter($index, $output);
        }
    }

    /**
     * @param list<int> $output
     */
    private function processStarter(int $index, array &$output): void
    {
        $previous = $index - 1;

        if ($this->starterIndex === null) {
            if ($previous >= 0) {
                $this->reorder(0, $previous);
            }

            $this->starterIndex = $index;

            return;
        }

        $this->composeSegment($this->starterIndex, $previous, $index + 1);

        if ($this->buffer[$index][0] === self::ERROR_CODE_POINT) {
            return;
        }

        $this->starterIndex = $index;

        if ($index >= $this->flushCount) {
            $this->flushBefore($index, $output);
        }
    }

    private function composeSegment(int $starter, int $last, int $endExclusive): void
    {
        if ($last >= $starter) {
            $this->reorder($starter, $last);
        }

        if (! $this->compose) {
            return;
        }

        $index = $starter + 1;

        while ($index < $endExclusive) {
            if ($this->buffer[$index][0] === self::ERROR_CODE_POINT) {
                $index++;
                continue;
            }

            if ($this->buffer[$index - 1][1] !== 0 && $this->buffer[$index - 1][1] >= $this->buffer[$index][1]) {
                $index++;
                continue;
            }

            $composed = self::composeForNormalization($this->buffer[$starter][0], $this->buffer[$index][0]);

            if ($composed !== null) {
                $this->buffer[$starter] = [$composed, Unicode::combiningClass($composed)];
                $this->buffer[$index] = [self::ERROR_CODE_POINT, 0];
            }

            $index++;
        }
    }

    private function reorder(int $start, int $end): void
    {
        for ($index = $start + 1; $index <= $end; $index++) {
            $current = $this->buffer[$index];
            $insert = $index;

            while ($insert > $start && $this->buffer[$insert - 1][1] > $current[1]) {
                $this->buffer[$insert] = $this->buffer[$insert - 1];
                $insert--;
            }

            $this->buffer[$insert] = $current;
        }
    }

    /**
     * @param list<int> $output
     */
    private function flushBefore(int $index, array &$output): void
    {
        for ($i = 0; $i < $index; $i++) {
            if ($this->buffer[$i][0] !== self::ERROR_CODE_POINT) {
                $output[] = $this->buffer[$i][0];
            }
        }

        $this->buffer = array_values(array_slice($this->buffer, $index));
        $this->starterIndex = 0;
    }

    /**
     * @param list<int> $output
     */
    private function finishCodePointsInto(array &$output): void
    {
        $length = count($this->buffer);

        if ($length === 0) {
            return;
        }

        if ($this->starterIndex === null) {
            $this->reorder(0, $length - 1);
        } elseif ($this->starterIndex !== $length - 1) {
            $this->composeSegment($this->starterIndex, $length - 1, $length);
        }

        foreach ($this->buffer as [$codePoint]) {
            if ($codePoint !== self::ERROR_CODE_POINT) {
                $output[] = $codePoint;
            }
        }

        $this->buffer = [];
        $this->starterIndex = null;
    }

    /**
     * @return list<int>
     */
    private function decode(string $data, bool $isLast): array
    {
        if ($this->pendingBytes !== '') {
            $data = $this->pendingBytes . $data;
            $this->pendingBytes = '';
        }

        $length = strlen($data);
        $codePoints = [];

        for ($offset = 0; $offset < $length;) {
            $first = ord($data[$offset]);
            $needed = self::utf8Length($first);

            if ($needed === 0) {
                if (! $isLast && $offset + 1 >= $length) {
                    $this->pendingBytes = substr($data, $offset);
                    break;
                }

                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                $offset++;
                continue;
            }

            if ($length - $offset < $needed) {
                if (! $isLast) {
                    $this->pendingBytes = substr($data, $offset);
                    break;
                }

                $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                break;
            }

            $codePoint = self::decodeValidUtf8Single($data, $offset, $needed);
            $offset += $needed;

            if ($codePoint === self::ERROR_CODE_POINT) {
                if (! $isLast && $offset >= $length) {
                    $this->pendingBytes = substr($data, $offset - $needed);
                    break;
                }

                $codePoint = Utf8::REPLACEMENT_CODE_POINT;
            }

            $codePoints[] = $codePoint;
        }

        return $codePoints;
    }

    /**
     * @param list<int> $codePoints
     */
    private function encode(array $codePoints): string
    {
        $data = '';

        foreach ($codePoints as $codePoint) {
            if ($codePoint >= 0 && $codePoint < 0x110000) {
                $data .= Utf8::encodeCodePoint($codePoint);
            }
        }

        return $data;
    }

    /**
     * @return list<int>
     */
    private static function decomposeCodePoint(int $codePoint, bool $compatibility): array
    {
        $decompositionMap = $compatibility
            ? UnicodeData::COMPATIBILITY_DECOMPOSITION_MAP
            : UnicodeData::CANONICAL_DECOMPOSITION_MAP;

        if (isset($decompositionMap[$codePoint])) {
            return $decompositionMap[$codePoint];
        }

        if ($codePoint >= self::HANGUL_S_BASE && $codePoint < self::HANGUL_S_BASE + self::HANGUL_S_COUNT) {
            $syllableIndex = $codePoint - self::HANGUL_S_BASE;
            $trailingIndex = $syllableIndex % self::HANGUL_T_COUNT;
            $leadVowelIndex = intdiv($syllableIndex - $trailingIndex, self::HANGUL_T_COUNT);

            $decomposition = [
                self::HANGUL_L_BASE + intdiv($leadVowelIndex, self::HANGUL_V_COUNT),
                self::HANGUL_V_BASE + ($leadVowelIndex % self::HANGUL_V_COUNT),
            ];

            if ($trailingIndex !== 0) {
                $decomposition[] = self::HANGUL_T_BASE + $trailingIndex;
            }

            return $decomposition;
        }

        return [$codePoint];
    }

    private static function composeForNormalization(int $first, int $second): ?int
    {
        $composed = UnicodeData::NORMALIZATION_COMPOSITION_MAP[$first][$second] ?? null;

        if ($composed !== null) {
            return $composed;
        }

        if (
            $first >= self::HANGUL_L_BASE
            && $first < self::HANGUL_L_BASE + self::HANGUL_L_COUNT
            && $second >= self::HANGUL_V_BASE
            && $second < self::HANGUL_V_BASE + self::HANGUL_V_COUNT
        ) {
            return self::HANGUL_S_BASE
                + (((($first - self::HANGUL_L_BASE) * self::HANGUL_V_COUNT)
                    + $second - self::HANGUL_V_BASE) * self::HANGUL_T_COUNT);
        }

        if (
            $first >= self::HANGUL_S_BASE
            && $first < self::HANGUL_S_BASE + self::HANGUL_S_COUNT
            && ($first - self::HANGUL_S_BASE) % self::HANGUL_T_COUNT === 0
            && $second > self::HANGUL_T_BASE
            && $second < self::HANGUL_T_BASE + self::HANGUL_T_COUNT
        ) {
            return $first + $second - self::HANGUL_T_BASE;
        }

        return null;
    }

    private static function utf8Length(int $byte): int
    {
        if ($byte < 0x80) {
            return 1;
        }

        if (($byte & 0xE0) === 0xC0) {
            return 2;
        }

        if (($byte & 0xF0) === 0xE0) {
            return 3;
        }

        if (($byte & 0xF8) === 0xF0) {
            return 4;
        }

        return 0;
    }

    private static function decodeValidUtf8Single(string $data, int $offset, int $length): int
    {
        $first = ord($data[$offset]);

        if ($length === 1) {
            return $first;
        }

        $second = ord($data[$offset + 1]);

        if ($length === 2) {
            return (($first ^ (0xC0 & $first)) << 6)
                | ($second ^ (0x80 & $second));
        }

        $third = ord($data[$offset + 2]);

        if ($length === 3) {
            return (($first ^ (0xE0 & $first)) << 12)
                | (($second ^ (0x80 & $second)) << 6)
                | ($third ^ (0x80 & $third));
        }

        $fourth = ord($data[$offset + 3]);

        return (($first ^ (0xF0 & $first)) << 18)
            | (($second ^ (0x80 & $second)) << 12)
            | (($third ^ (0x80 & $third)) << 6)
            | ($fourth ^ (0x80 & $fourth));
    }
}

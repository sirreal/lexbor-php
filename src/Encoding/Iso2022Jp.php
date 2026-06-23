<?php

declare(strict_types=1);

namespace Lexbor\Encoding;

use Lexbor\Core\LexborException;
use Lexbor\Core\Status;

final class Iso2022Jp
{
    private const string LABEL = 'ISO-2022-JP';

    public const int STATE_ASCII = 0;
    public const int STATE_ROMAN = 1;
    public const int STATE_KATAKANA = 2;
    public const int STATE_LEAD = 3;
    public const int STATE_TRAIL = 4;
    public const int STATE_ESCAPE_START = 5;
    public const int STATE_ESCAPE = 6;
    private const int STATE_UNSET = 7;

    private const int ENCODE_ASCII = 0;
    private const int ENCODE_ROMAN = 1;
    private const int ENCODE_JIS0208 = 2;

    private function __construct()
    {
    }

    /**
     * @return list<int>
     */
    public static function decodeWithReplacement(string $data): array
    {
        $result = self::decodeToBuffer($data, strlen($data) + 8);
        $codePoints = $result->codePoints;

        if (
            $result->status === Status::Continue
            && $result->pendingIso2022JpState !== self::STATE_ASCII
        ) {
            $codePoints[] = Utf8::DECODE_CONTINUE;
        }

        return $codePoints;
    }

    public static function decodeToBuffer(
        string $data,
        int $capacity,
        int $offset = 0,
        int $pendingIso2022JpState = self::STATE_ASCII,
        int $pendingIso2022JpOutState = self::STATE_ASCII,
        bool $pendingIso2022JpOutFlag = false,
        ?int $pendingIso2022JpLead = null,
        ?int $pendingIso2022JpPrepend = null,
    ): DecodeResult {
        if ($capacity < 0) {
            self::unexpected(self::LABEL . ' decode buffer capacity cannot be negative.');
        }

        $length = strlen($data);
        $codePoints = [];
        $state = $pendingIso2022JpState;
        $outState = $pendingIso2022JpOutState;
        $outFlag = $pendingIso2022JpOutFlag;
        $lead = $pendingIso2022JpLead;
        $prepend = $pendingIso2022JpPrepend;

        while (true) {
            if ($prepend !== null) {
                if ($offset >= $length) {
                    return self::decodeResult(
                        Status::Continue,
                        $codePoints,
                        $offset,
                        $state,
                        $outState,
                        $outFlag,
                        $lead,
                        $prepend,
                    );
                }

                if (count($codePoints) >= $capacity) {
                    return self::decodeResult(
                        Status::SmallBuffer,
                        $codePoints,
                        $offset,
                        $state,
                        $outState,
                        $outFlag,
                        $lead,
                        $prepend,
                    );
                }

                $byte = $prepend;
                $prepend = null;
                goto prepended;
            }

            if ($offset >= $length) {
                return self::decodeResult(
                    Status::Ok,
                    $codePoints,
                    $offset,
                    $state,
                    $outState,
                    $outFlag,
                    $lead,
                    null,
                );
            }

            if (count($codePoints) >= $capacity) {
                return self::decodeResult(
                    Status::SmallBuffer,
                    $codePoints,
                    $offset,
                    $state,
                    $outState,
                    $outFlag,
                    $lead,
                    $prepend,
                );
            }

            $byte = ord($data[$offset]);
            $offset++;

            prepended:
            switch ($state) {
                case self::STATE_ASCII:
                    if ($byte === 0x1B) {
                        $state = self::STATE_ESCAPE_START;

                        if ($offset >= $length) {
                            return self::decodeResult(
                                Status::Continue,
                                $codePoints,
                                $offset,
                                $state,
                                $outState,
                                $outFlag,
                                $lead,
                                $prepend,
                            );
                        }

                        continue 2;
                    }

                    if ($byte <= 0x7F && $byte !== 0x0E && $byte !== 0x0F) {
                        $outFlag = false;
                        $codePoints[] = $byte;
                        continue 2;
                    }

                    $outFlag = false;
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    continue 2;

                case self::STATE_ROMAN:
                    if ($byte === 0x1B) {
                        $state = self::STATE_ESCAPE_START;

                        if ($offset >= $length) {
                            return self::decodeResult(
                                Status::Continue,
                                $codePoints,
                                $offset,
                                $state,
                                $outState,
                                $outFlag,
                                $lead,
                                $prepend,
                            );
                        }

                        continue 2;
                    }

                    if ($byte === 0x5C) {
                        $outFlag = false;
                        $codePoints[] = 0x00A5;
                        continue 2;
                    }

                    if ($byte === 0x7E) {
                        $outFlag = false;
                        $codePoints[] = 0x203E;
                        continue 2;
                    }

                    if ($byte <= 0x7F && $byte !== 0x0E && $byte !== 0x0F) {
                        $outFlag = false;
                        $codePoints[] = $byte;
                        continue 2;
                    }

                    $outFlag = false;
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    continue 2;

                case self::STATE_KATAKANA:
                    if ($byte === 0x1B) {
                        $state = self::STATE_ESCAPE_START;

                        if ($offset >= $length) {
                            return self::decodeResult(
                                Status::Continue,
                                $codePoints,
                                $offset,
                                $state,
                                $outState,
                                $outFlag,
                                $lead,
                                $prepend,
                            );
                        }

                        continue 2;
                    }

                    if ($byte >= 0x21 && $byte <= 0x5F) {
                        $outFlag = false;
                        $codePoints[] = 0xFF61 - 0x21 + $byte;
                        continue 2;
                    }

                    $outFlag = false;
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    continue 2;

                case self::STATE_LEAD:
                    if ($byte === 0x1B) {
                        $state = self::STATE_ESCAPE_START;

                        if ($offset >= $length) {
                            return self::decodeResult(
                                Status::Continue,
                                $codePoints,
                                $offset,
                                $state,
                                $outState,
                                $outFlag,
                                $lead,
                                $prepend,
                            );
                        }

                        continue 2;
                    }

                    if ($byte >= 0x21 && $byte <= 0x7E) {
                        $outFlag = false;
                        $lead = $byte;
                        $state = self::STATE_TRAIL;

                        if ($offset >= $length) {
                            return self::decodeResult(
                                Status::Continue,
                                $codePoints,
                                $offset,
                                $state,
                                $outState,
                                $outFlag,
                                $lead,
                                $prepend,
                            );
                        }

                        continue 2;
                    }

                    $outFlag = false;
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    continue 2;

                case self::STATE_TRAIL:
                    if ($byte === 0x1B) {
                        $state = self::STATE_ESCAPE_START;
                        $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                        continue 2;
                    }

                    $state = self::STATE_LEAD;

                    if ($byte >= 0x21 && $byte <= 0x7E && $lead !== null) {
                        $pointer = ($lead - 0x21) * 94 + $byte - 0x21;
                        $codePoint = Iso2022JpData::JIS0208_DECODE_INDEX[$pointer] ?? null;

                        if ($codePoint !== null) {
                            $codePoints[] = $codePoint;
                            continue 2;
                        }
                    }

                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    continue 2;

                case self::STATE_ESCAPE_START:
                    if ($byte === 0x24 || $byte === 0x28) {
                        $state = self::STATE_ESCAPE;
                        $lead = $byte;

                        if ($offset >= $length) {
                            return self::decodeResult(
                                Status::Continue,
                                $codePoints,
                                $offset,
                                $state,
                                $outState,
                                $outFlag,
                                $lead,
                                $prepend,
                            );
                        }

                        continue 2;
                    }

                    $offset--;
                    $outFlag = false;
                    $state = $outState;
                    $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                    continue 2;

                case self::STATE_ESCAPE:
                    $nextState = self::STATE_UNSET;

                    if ($lead === 0x28) {
                        $nextState = match ($byte) {
                            0x42 => self::STATE_ASCII,
                            0x4A => self::STATE_ROMAN,
                            0x49 => self::STATE_KATAKANA,
                            default => self::STATE_UNSET,
                        };
                    } elseif ($lead === 0x24 && ($byte === 0x40 || $byte === 0x42)) {
                        $nextState = self::STATE_LEAD;
                    }

                    if ($nextState === self::STATE_UNSET) {
                        $offset--;
                        $outFlag = false;
                        $state = $outState;
                        $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;

                        if (count($codePoints) >= $capacity) {
                            return self::decodeResult(
                                Status::SmallBuffer,
                                $codePoints,
                                $offset,
                                $state,
                                $outState,
                                $outFlag,
                                null,
                                $lead,
                            );
                        }

                        $byte = self::requirePendingByte($lead);
                        $lead = null;
                        goto prepended;
                    }

                    $lead = null;
                    $state = $nextState;
                    $outState = $state;

                    if ($outFlag) {
                        $codePoints[] = Utf8::REPLACEMENT_CODE_POINT;
                        continue 2;
                    }

                    $outFlag = true;

                    if ($offset >= $length) {
                        return self::decodeResult(
                            Status::Continue,
                            $codePoints,
                            $offset,
                            $state,
                            $outState,
                            $outFlag,
                            $lead,
                            $prepend,
                        );
                    }

                    continue 2;

                default:
                    self::unexpected(self::LABEL . ' decoder state is invalid.');
            }
        }
    }

    public static function decodeFinish(DecodeResult $result): DecodeResult
    {
        if (
            $result->status === Status::Continue
            && $result->pendingIso2022JpState === self::STATE_ASCII
        ) {
            return new DecodeResult(
                Status::Ok,
                $result->codePoints,
                $result->offset,
                pendingIso2022JpState: $result->pendingIso2022JpState,
                pendingIso2022JpOutState: $result->pendingIso2022JpOutState,
                pendingIso2022JpOutFlag: $result->pendingIso2022JpOutFlag,
                pendingIso2022JpLead: $result->pendingIso2022JpLead,
                pendingIso2022JpPrepend: $result->pendingIso2022JpPrepend,
            );
        }

        if (
            $result->status === Status::Continue
            && $result->pendingIso2022JpState !== self::STATE_ASCII
        ) {
            return new DecodeResult(
                Status::Ok,
                [...$result->codePoints, Utf8::REPLACEMENT_CODE_POINT],
                $result->offset,
                pendingIso2022JpState: $result->pendingIso2022JpState,
                pendingIso2022JpOutState: $result->pendingIso2022JpOutState,
                pendingIso2022JpOutFlag: $result->pendingIso2022JpOutFlag,
                pendingIso2022JpLead: $result->pendingIso2022JpLead,
                pendingIso2022JpPrepend: $result->pendingIso2022JpPrepend,
            );
        }

        return $result;
    }

    public static function encodeCodePoint(int $codePoint): string
    {
        $result = self::encodeCodePointsToBuffer([$codePoint], PHP_INT_MAX);

        if ($result->status !== Status::Ok) {
            self::unexpected('Code point cannot be encoded as ' . self::LABEL . '.');
        }

        return $result->bytes;
    }

    public static function encodeCodePointWithCapacity(int $codePoint, int $capacity): EncodeResult
    {
        if ($capacity < 1) {
            self::unexpected(self::LABEL . ' encode buffer capacity must be positive.');
        }

        $result = self::encodeCodePointsToBuffer([$codePoint], $capacity, finish: false);

        return match ($result->status) {
            Status::Ok => new EncodeResult(strlen($result->bytes), $result->bytes),
            Status::SmallBuffer => new EncodeResult(Utf8::ENCODE_SMALL_BUFFER, ''),
            default => new EncodeResult(Utf8::ENCODE_ERROR, ''),
        };
    }

    /**
     * @param list<int> $codePoints
     */
    public static function encodeCodePointsToBuffer(array $codePoints, int $capacity, bool $finish = true): BufferEncodeResult
    {
        if ($capacity < 0) {
            self::unexpected(self::LABEL . ' encode buffer capacity cannot be negative.');
        }

        $out = '';
        $state = self::ENCODE_ASCII;

        foreach ($codePoints as $codePoint) {
            if ($codePoint < 0 || $codePoint > 0x10FFFF) {
                return new BufferEncodeResult(Status::Error, $out);
            }

            $encoded = self::encodeInState($codePoint, $state);
            if ($encoded === null) {
                return new BufferEncodeResult(Status::Error, $out);
            }

            if (strlen($out) + strlen($encoded['bytes']) > $capacity) {
                return new BufferEncodeResult(Status::SmallBuffer, $out);
            }

            $out .= $encoded['bytes'];
            $state = $encoded['state'];
        }

        if ($finish && $state !== self::ENCODE_ASCII) {
            if (strlen($out) + 3 > $capacity) {
                return new BufferEncodeResult(Status::SmallBuffer, $out);
            }

            $out .= "\x1B\x28\x42";
        }

        return new BufferEncodeResult(Status::Ok, $out);
    }

    /**
     * @return array{bytes: string, state: int}|null
     */
    private static function encodeInState(int $codePoint, int $state): ?array
    {
        switch ($state) {
            case self::ENCODE_ASCII:
                if ($codePoint === 0x000E || $codePoint === 0x000F || $codePoint === 0x001B) {
                    return null;
                }

                if ($codePoint < 0x80) {
                    return ['bytes' => chr($codePoint), 'state' => self::ENCODE_ASCII];
                }

                if ($codePoint === 0x00A5 || $codePoint === 0x203E) {
                    return [
                        'bytes' => "\x1B\x28\x4A" . chr($codePoint === 0x00A5 ? 0x5C : 0x7E),
                        'state' => self::ENCODE_ROMAN,
                    ];
                }

                break;

            case self::ENCODE_ROMAN:
                if ($codePoint === 0x000E || $codePoint === 0x000F || $codePoint === 0x001B) {
                    return null;
                }

                if ($codePoint < 0x80) {
                    if ($codePoint !== 0x5C && $codePoint !== 0x7E) {
                        return ['bytes' => chr($codePoint), 'state' => self::ENCODE_ROMAN];
                    }

                    return [
                        'bytes' => "\x1B\x28\x42" . chr($codePoint),
                        'state' => self::ENCODE_ASCII,
                    ];
                }

                if ($codePoint === 0x00A5) {
                    return ['bytes' => "\x5C", 'state' => self::ENCODE_ROMAN];
                }

                if ($codePoint === 0x203E) {
                    return ['bytes' => "\x7E", 'state' => self::ENCODE_ROMAN];
                }

                break;

            case self::ENCODE_JIS0208:
                if ($codePoint < 0x80) {
                    return [
                        'bytes' => "\x1B\x28\x42" . chr($codePoint),
                        'state' => self::ENCODE_ASCII,
                    ];
                }

                if ($codePoint === 0x00A5 || $codePoint === 0x203E) {
                    return [
                        'bytes' => "\x1B\x28\x4A" . chr($codePoint === 0x00A5 ? 0x5C : 0x7E),
                        'state' => self::ENCODE_ROMAN,
                    ];
                }

                break;
        }

        $pointer = self::encodePointer($codePoint);
        if ($pointer === null) {
            return null;
        }

        $bytes = chr(intdiv($pointer, 94) + 0x21) . chr($pointer % 94 + 0x21);
        if ($state !== self::ENCODE_JIS0208) {
            $bytes = "\x1B\x24\x42" . $bytes;
        }

        return ['bytes' => $bytes, 'state' => self::ENCODE_JIS0208];
    }

    public static function encodePointer(int $codePoint): ?int
    {
        if ($codePoint === 0x2212) {
            $codePoint = 0xFF0D;
        }

        $codePoint = Iso2022JpData::KATAKANA_REVERSE_MAP[$codePoint] ?? $codePoint;

        return EucJpData::ENCODE_INDEX[$codePoint] ?? null;
    }

    private static function requirePendingByte(?int $byte): int
    {
        if ($byte === null) {
            self::unexpected(self::LABEL . ' decoder state is incomplete.');
        }

        return $byte;
    }

    /**
     * @param list<int> $codePoints
     */
    private static function decodeResult(
        Status $status,
        array $codePoints,
        int $offset,
        int $state,
        int $outState,
        bool $outFlag,
        ?int $lead,
        ?int $prepend,
    ): DecodeResult {
        return new DecodeResult(
            $status,
            $codePoints,
            $offset,
            pendingIso2022JpState: $state,
            pendingIso2022JpOutState: $outState,
            pendingIso2022JpOutFlag: $outFlag,
            pendingIso2022JpLead: $lead,
            pendingIso2022JpPrepend: $prepend,
        );
    }

    private static function unexpected(string $message): never
    {
        throw new LexborException(Status::ErrorUnexpectedData, $message);
    }
}

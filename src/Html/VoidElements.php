<?php

declare(strict_types=1);

namespace Lexbor\Html;

final class VoidElements
{
    private const NAMES = [
        'area' => true,
        'base' => true,
        'basefont' => true,
        'bgsound' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'frame' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'keygen' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

    private function __construct()
    {
    }

    public static function is(string $tagName): bool
    {
        return isset(self::NAMES[strtolower($tagName)]);
    }
}

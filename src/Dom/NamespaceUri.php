<?php

declare(strict_types=1);

namespace Lexbor\Dom;

final class NamespaceUri
{
    public const UNDEF = 0x00;
    public const ANY = 0x01;
    public const HTML = 0x02;
    public const MATH = 0x03;
    public const SVG = 0x04;
    public const XLINK = 0x05;
    public const XML = 0x06;
    public const XMLNS = 0x07;
    public const LAST_ENTRY = 0x08;

    private function __construct()
    {
    }
}

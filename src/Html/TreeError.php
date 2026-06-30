<?php

declare(strict_types=1);

namespace Lexbor\Html;

final class TreeError
{
    public const int LAST_ENTRY = 42;

    /** @var list<string> */
    private const DESCRIPTIONS = [
        'unexpected token',
        'unexpected closed token',
        'null character',
        'unexpected character token',
        'unexpected token in initial mode',
        'bad doctype token in initial mode',
        'doctype token in before html mode',
        'unexpected closed token in before html mode',
        'doctype token in before head mode',
        'unexpected closed token in before head mode',
        'doctype token in head mode',
        'non void html element start tag with trailing solidus',
        'head token in head mode',
        'unexpected closed token in head mode',
        'template closed token without opening in head mode',
        'template element is not current in head mode',
        'doctype token in head noscript mode',
        'doctype token after head mode',
        'head token after head mode',
        'doctype token in body mode',
        'bad ending open elements is wrong',
        'open elements is wrong',
        'unexpected element in open elements stack',
        'missing element in open elements stack',
        'no body element in scope',
        'missing element in scope',
        'unexpected element in scope',
        'unexpected element in active formatting stack',
        'unexpected end of file',
        'characters in table text',
        'doctype token in table mode',
        'doctype token in select mode',
        'doctype token after body mode',
        'doctype token in frameset mode',
        'doctype token after frameset mode',
        'doctype token foreign content mode',
        'select in scope',
        'fragment parsing select in context parse input',
        'fragment parsing select in context parse select',
        'hr parsing select option optgroup in scope',
        'option parsing option in scope',
        'optgroup parsing option optgroup in scope',
    ];

    public static function description(int $id): string
    {
        return self::DESCRIPTIONS[$id] ?? 'unknown error';
    }
}

<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Dom\Element;
use Lexbor\Dom\Node;

final class Serializer
{
    public static function serializeDeep(Node $node): string
    {
        if ($node instanceof Element && $node->tagName !== 'body') {
            return self::serializeElement($node);
        }

        $out = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            $out .= ($child instanceof Element) ? self::serializeElement($child) : self::serializeDeep($child);
        }

        return $out;
    }

    private static function serializeElement(Element $element): string
    {
        $attributes = '';

        foreach ($element->attributes as $name => $value) {
            $attributes .= sprintf(
                ' %s="%s"',
                htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        return sprintf('<%s%s>%s</%s>', $element->tagName, $attributes, self::serializeDeepChildren($element), $element->tagName);
    }

    private static function serializeDeepChildren(Node $node): string
    {
        $out = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            $out .= ($child instanceof Element) ? self::serializeElement($child) : self::serializeDeep($child);
        }

        return $out;
    }
}


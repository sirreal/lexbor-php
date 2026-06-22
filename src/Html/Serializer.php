<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Dom\Comment;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Dom\Text;

final class Serializer
{
    public static function serialize(Node $node): string
    {
        if ($node instanceof Text) {
            return self::serializeText($node);
        }

        if ($node instanceof Comment) {
            return self::serializeComment($node);
        }

        if ($node instanceof Element) {
            return self::serializeElement($node);
        }

        return self::serializeDeep($node);
    }

    public static function serializeDeep(Node $node): string
    {
        if ($node instanceof Text) {
            return self::serializeText($node);
        }

        if ($node instanceof Comment) {
            return self::serializeComment($node);
        }

        if ($node instanceof Element && $node->tagName !== 'body') {
            return self::serializeElement($node);
        }

        $out = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            $out .= ($child instanceof Element) ? self::serializeElement($child) : self::serializeDeep($child);
        }

        return $out;
    }

    public static function serializePretty(Node $node, int $indent = 0): string
    {
        if ($node instanceof Text) {
            $data = self::hasRawTextParent($node) ? $node->data : self::escapeText($node->data);

            return self::indent($indent) . '"' . $data . '"' . "\n";
        }

        if ($node instanceof Comment) {
            return self::indent($indent) . self::serializeComment($node) . "\n";
        }

        if ($node instanceof Element) {
            return self::indent($indent) . self::serializeElement($node) . "\n";
        }

        $out = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            $out .= self::serializePretty($child, $indent);
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
                self::escapeAttributeValue($value),
            );
        }

        if (VoidElements::is($element->tagName)) {
            return sprintf('<%s%s>', $element->tagName, $attributes);
        }

        return sprintf('<%s%s>%s</%s>', $element->tagName, $attributes, self::serializeDeepChildren($element), $element->tagName);
    }

    private static function serializeDeepChildren(Node $node): string
    {
        $out = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            $out .= self::serialize($child);
        }

        return $out;
    }

    private static function serializeComment(Comment $comment): string
    {
        return sprintf('<!--%s-->', $comment->data);
    }

    private static function serializeText(Text $text): string
    {
        if (self::hasRawTextParent($text)) {
            return $text->data;
        }

        return self::escapeText($text->data);
    }

    private static function escapeText(string $data): string
    {
        return str_replace("\u{00A0}", '&nbsp;', htmlspecialchars($data, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    private static function escapeAttributeValue(string $data): string
    {
        return str_replace("\u{00A0}", '&nbsp;', htmlspecialchars($data, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8'));
    }

    private static function hasRawTextParent(Text $text): bool
    {
        if (!$text->parent instanceof Element) {
            return false;
        }

        if ($text->parent->tagName === 'noscript') {
            $document = $text->ownerDocument;

            return $document instanceof Document && $document->isScriptingEnabled();
        }

        return in_array($text->parent->tagName, [
            'style',
            'script',
            'xmp',
            'iframe',
            'noembed',
            'noframes',
            'plaintext',
        ], true);
    }

    private static function indent(int $indent): string
    {
        return str_repeat('  ', max(0, $indent));
    }
}

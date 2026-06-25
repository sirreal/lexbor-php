<?php

declare(strict_types=1);

namespace Lexbor\Html;

use Lexbor\Dom\Comment;
use Lexbor\Dom\DocumentType;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Dom\Text;

final class Serializer
{
    public static function serialize(Node $node, bool $fullDoctype = false): string
    {
        if ($node instanceof Text) {
            return self::serializeText($node);
        }

        if ($node instanceof Comment) {
            return self::serializeComment($node);
        }

        if ($node instanceof DocumentType) {
            return self::serializeDoctype($node, $fullDoctype);
        }

        if ($node instanceof Element) {
            return self::serializeElement($node, $fullDoctype);
        }

        return self::serializeDeep($node, $fullDoctype);
    }

    public static function serializeDeep(Node $node, bool $fullDoctype = false): string
    {
        if ($node instanceof Text) {
            return self::serializeText($node);
        }

        if ($node instanceof Comment) {
            return self::serializeComment($node);
        }

        if ($node instanceof DocumentType) {
            return self::serializeDoctype($node, $fullDoctype);
        }

        if ($node instanceof Document) {
            return self::serializeDocument($node, $fullDoctype);
        }

        if ($node instanceof Element && $node->tagName !== 'body') {
            return self::serializeElement($node, $fullDoctype);
        }

        $out = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            $out .= ($child instanceof Element) ? self::serializeElement($child, $fullDoctype) : self::serializeDeep($child, $fullDoctype);
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

        if ($node instanceof DocumentType) {
            return self::indent($indent) . self::serializeDoctype($node, false) . "\n";
        }

        if ($node instanceof Element) {
            return self::indent($indent) . self::serializeElement($node, false) . "\n";
        }

        $out = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            $out .= self::serializePretty($child, $indent);
        }

        return $out;
    }

    private static function serializeDocument(Document $document, bool $fullDoctype): string
    {
        $doctype = $document->documentType();
        $prefix = $doctype === null ? '' : self::serializeDoctype($doctype, $fullDoctype);

        return $prefix . '<html><head></head>' . self::serializeElement($document->bodyElement(), $fullDoctype) . '</html>';
    }

    private static function serializeElement(Element $element, bool $fullDoctype): string
    {
        $attributes = '';

        foreach ($element->attributes as $name => $value) {
            $attributes .= sprintf(
                ' %s="%s"',
                htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                self::escapeAttributeValue($value),
            );
        }

        if (VoidElements::is($element->tagName)) {
            return sprintf('<%s%s>', $element->tagName, $attributes);
        }

        return sprintf('<%s%s>%s</%s>', $element->tagName, $attributes, self::serializeDeepChildren($element, $fullDoctype), $element->tagName);
    }

    private static function serializeDeepChildren(Node $node, bool $fullDoctype = false): string
    {
        $out = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            $out .= self::serialize($child, $fullDoctype);
        }

        return $out;
    }

    private static function serializeDoctype(DocumentType $doctype, bool $fullDoctype): string
    {
        $out = '<!DOCTYPE ' . $doctype->name();

        if ($fullDoctype) {
            if ($doctype->publicId() !== null) {
                $out .= ' PUBLIC "' . $doctype->publicId() . '"';

                if ($doctype->systemId() !== null) {
                    $out .= ' "' . $doctype->systemId() . '"';
                }
            } elseif ($doctype->systemId() !== null) {
                $out .= ' SYSTEM "' . $doctype->systemId() . '"';
            }
        }

        return $out . '>';
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

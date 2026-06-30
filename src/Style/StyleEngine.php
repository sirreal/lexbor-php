<?php

declare(strict_types=1);

namespace Lexbor\Style;

use Lexbor\Css\Declarations\Parser as DeclarationsParser;
use Lexbor\Css\Selectors\Matcher;
use Lexbor\Css\Selectors\Parser as SelectorParser;
use Lexbor\Css\Selectors\SpecificityCalculator;
use Lexbor\Css\Syntax\Token;
use Lexbor\Css\Syntax\Tokenizer;
use Lexbor\Dom\Element;
use Lexbor\Dom\Node;
use Lexbor\Dom\Text;
use Lexbor\Html\Document;

final class StyleEngine
{
    public function __construct(
        private readonly Tokenizer $tokenizer = new Tokenizer(),
        private readonly DeclarationsParser $declarationsParser = new DeclarationsParser(),
        private readonly Matcher $matcher = new Matcher(),
        private readonly SelectorParser $selectorParser = new SelectorParser(),
        private readonly SpecificityCalculator $specificityCalculator = new SpecificityCalculator(),
    ) {
    }

    public function parseStylesheet(string $css): Stylesheet
    {
        return new Stylesheet($this->parseQualifiedStyleRules($css));
    }

    public function attachStylesheet(Document $document, string|Stylesheet $stylesheet): Stylesheet
    {
        $stylesheet = is_string($stylesheet) ? $this->parseStylesheet($stylesheet) : $stylesheet;
        $document->attachStylesheet($stylesheet);

        return $stylesheet;
    }

    public function removeStylesheet(Document $document, Stylesheet $stylesheet): void
    {
        $document->removeStylesheet($stylesheet);
    }

    public function clearStylesheets(Document $document): void
    {
        $document->clearStylesheets();
    }

    public function serializeElementStyle(Element $element): string
    {
        $declarations = [];
        $document = $element->ownerDocument;

        if (
            $document instanceof Document
            && $element->styleEventConnected
            && $this->isProcessedConnectedToOwnerDocument($element, $document)
        ) {
            $this->applyDocumentStyleSources($declarations, $element, $document);
        }

        $inlineStyle = $element->processedInlineStyle;
        if ($inlineStyle !== null) {
            $this->applyDeclarations($declarations, $this->declarationsParser->parseList($inlineStyle), true);
        }

        if ($declarations === []) {
            return '';
        }

        ksort($declarations, SORT_STRING);

        $serialized = [];
        foreach ($declarations as $name => $declaration) {
            $serialized[] = $name . ': ' . $declaration['value'] . ($declaration['important'] ? ' !important' : '');
        }

        return implode('; ', $serialized);
    }

    /**
     * @param array<string, array{value: string, important: bool, rank: int, specificity: array{a: int, b: int, c: int}}> $declarations
     */
    private function applyStylesheet(array &$declarations, Element $element, Stylesheet $stylesheet): void
    {
        foreach ($stylesheet->rules() as $rule) {
            $selector = $rule['selector'];
            $specificity = $this->matchingSpecificity($element, $selector);
            if ($selector === '' || $specificity === null) {
                continue;
            }

            $this->applyDeclarations($declarations, $rule['declarations'], false, $specificity);
        }
    }

    /**
     * @param array<string, array{value: string, important: bool, rank: int, specificity: array{a: int, b: int, c: int}}> $declarations
     */
    private function applyDocumentStyleSources(
        array &$declarations,
        Element $element,
        Document $document,
    ): void {
        if (! $document->withoutEvents()) {
            $this->syncProcessedTextDataForStyleEvents($document, $element);
        }

        $snapshots = $this->useProcessedSnapshots($document, $element);

        try {
            foreach ($this->documentStyleSources($document) as $source) {
                $this->applyStylesheet($declarations, $element, $source['stylesheet']);
            }
        } finally {
            foreach ($snapshots as $snapshot) {
                if ($snapshot['element'] !== null && $snapshot['attributes'] !== null) {
                    $snapshot['element']->attributes = $snapshot['attributes'];
                }

                if ($snapshot['text'] !== null && $snapshot['data'] !== null) {
                    $snapshot['text']->setDataForStyleSnapshot($snapshot['data']);
                }

                $snapshot['node']->parent = $snapshot['parent'];
                $snapshot['node']->next = $snapshot['next'];
                $snapshot['node']->prev = $snapshot['prev'];
                $snapshot['node']->firstChild = $snapshot['firstChild'];
                $snapshot['node']->lastChild = $snapshot['lastChild'];
            }
        }
    }

    /**
     * @return list<array{node: Node, element: Element|null, attributes: array<string, string>|null, text: Text|null, data: string|null, parent: Node|null, next: Node|null, prev: Node|null, firstChild: Node|null, lastChild: Node|null}>
     */
    private function useProcessedSnapshots(Document $document, Element $target): array
    {
        $nodes = [];
        $seen = [];
        $this->collectNodesForProcessedSnapshots($document->headElement(), $nodes, $seen);
        $this->collectNodesForProcessedSnapshots($document, $nodes, $seen);
        $this->collectNodesForProcessedSnapshots($target, $nodes, $seen);

        $snapshots = [];
        foreach ($nodes as $node) {
            $snapshots[] = [
                'node' => $node,
                'element' => $node instanceof Element ? $node : null,
                'attributes' => $node instanceof Element ? $node->attributes : null,
                'text' => $node instanceof Text ? $node : null,
                'data' => $node instanceof Text ? $node->data : null,
                'parent' => $node->parent,
                'next' => $node->next,
                'prev' => $node->prev,
                'firstChild' => $node->firstChild,
                'lastChild' => $node->lastChild,
            ];

            $node->parent = $node->processedParent;
            $node->next = $node->processedNext;
            $node->prev = $node->processedPrev;
            $node->firstChild = $node->processedFirstChild;
            $node->lastChild = $node->processedLastChild;

            if ($node instanceof Element) {
                $node->attributes = $node->processedAttributes;
            }

            if ($node instanceof Text) {
                $node->setDataForStyleSnapshot($node->processedData);
            }
        }

        return $snapshots;
    }

    private function syncProcessedTextDataForStyleEvents(Document $document, Element $target): void
    {
        $nodes = [];
        $seen = [];
        $this->collectNodesForProcessedSnapshots($document->headElement(), $nodes, $seen);
        $this->collectNodesForProcessedSnapshots($document, $nodes, $seen);
        $this->collectNodesForProcessedSnapshots($target, $nodes, $seen);

        foreach ($nodes as $node) {
            if ($node instanceof Text) {
                $node->syncProcessedDataForStyleEvents();
            }
        }
    }

    /**
     * @param list<Node> $nodes
     * @param array<int, true> $seen
     */
    private function collectNodesForProcessedSnapshots(Node $node, array &$nodes, array &$seen): void
    {
        $id = spl_object_id($node);
        if (isset($seen[$id])) {
            return;
        }

        $seen[$id] = true;
        $nodes[] = $node;

        for ($child = $node->processedFirstChild; $child !== null; $child = $child->processedNext) {
            $this->collectNodesForProcessedSnapshots($child, $nodes, $seen);
        }
    }

    /**
     * @return list<array{stylesheet: Stylesheet, order: int}>
     */
    private function documentStyleSources(Document $document): array
    {
        $sources = $document->stylesheetEntries();
        $clearedStyleSourceOrder = $document->clearedStyleSourceOrder();

        foreach ($this->connectedHtmlStyleElements($document) as $styleElement) {
            $order = $styleElement->styleSourceOrder;
            if ($order === 0 || $order <= $clearedStyleSourceOrder) {
                continue;
            }

            $sources[] = [
                'stylesheet' => $this->parseStylesheet($this->textContent($styleElement)),
                'order' => $order,
            ];
        }

        usort(
            $sources,
            static fn (array $left, array $right): int => $left['order'] <=> $right['order'],
        );

        return $sources;
    }

    /**
     * @return list<Element>
     */
    private function connectedHtmlStyleElements(Document $document): array
    {
        $styleElements = [];

        $this->collectConnectedHtmlStyleElements($document->headElement(), $styleElements);
        $this->collectConnectedHtmlStyleElements($document, $styleElements);

        return $styleElements;
    }

    /**
     * @param list<Element> $styleElements
     */
    private function collectConnectedHtmlStyleElements(Node $node, array &$styleElements): void
    {
        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            if (
                $child instanceof Element
                && $child->namespace === Element::NAMESPACE_HTML
                && $child->tagName === 'style'
            ) {
                $styleElements[] = $child;
            }

            $this->collectConnectedHtmlStyleElements($child, $styleElements);
        }
    }

    private function isProcessedConnectedToOwnerDocument(Element $element, Document $document): bool
    {
        return $this->processedTreeContains($document, $element);
    }

    private function processedTreeContains(Node $root, Node $target): bool
    {
        for ($node = $root->processedFirstChild; $node !== null; $node = $node->processedNext) {
            if ($node === $target || $this->processedTreeContains($node, $target)) {
                return true;
            }
        }

        return false;
    }

    private function textContent(Node $node): string
    {
        $content = '';

        for ($child = $node->firstChild; $child !== null; $child = $child->next) {
            if ($child instanceof Text) {
                $content .= $child->processedData;
                continue;
            }

            $content .= $this->textContent($child);
        }

        return $content;
    }

    /**
     * @param array<string, array{value: string, important: bool, rank: int, specificity: array{a: int, b: int, c: int}}> $current
     * @param list<array<string, mixed>> $declarations
     * @param array{a: int, b: int, c: int} $specificity
     */
    private function applyDeclarations(
        array &$current,
        array $declarations,
        bool $inline,
        array $specificity = ['a' => 0, 'b' => 0, 'c' => 0],
    ): void {
        foreach ($declarations as $declaration) {
            $type = $declaration['type'] ?? 'property';
            if (! in_array($type, ['property', 'custom'], true)) {
                continue;
            }

            $name = (string) ($declaration['name'] ?? '');
            if ($type === 'property') {
                $name = strtolower($name);
            }

            $value = (string) ($declaration['value'] ?? '');
            if ($name === '' || $value === '') {
                continue;
            }

            $important = (bool) ($declaration['important'] ?? false);
            $rank = ($important ? 2 : 0) + ($inline ? 1 : 0);

            if (
                ! isset($current[$name])
                || $rank > $current[$name]['rank']
                || (
                    $rank === $current[$name]['rank']
                    && $this->compareSpecificity($specificity, $current[$name]['specificity']) >= 0
                )
            ) {
                $current[$name] = [
                    'value' => $value,
                    'important' => $important,
                    'rank' => $rank,
                    'specificity' => $specificity,
                ];
            }
        }
    }

    /**
     * @return array{a: int, b: int, c: int}|null
     */
    private function matchingSpecificity(Element $element, string $selector): ?array
    {
        if ($selector === '' || ! $this->matcher->matches($element, $selector)) {
            return null;
        }

        $specificity = null;

        foreach ($this->selectorListBranches($selector) as $branch) {
            $parsed = $this->selectorParser->parseForMatching($branch);
            if ($parsed['value'] === '' || ! $this->matcher->matches($element, $parsed['value'])) {
                continue;
            }

            $branchSpecificity = $this->specificity($parsed['value']);
            if (
                $specificity === null
                || $this->compareSpecificity($branchSpecificity, $specificity) > 0
            ) {
                $specificity = $branchSpecificity;
            }
        }

        return $specificity;
    }

    /**
     * @return array{a: int, b: int, c: int}
     */
    private function specificity(string $selector): array
    {
        return $this->specificityCalculator->calculate($selector);
    }

    /**
     * @param array{a: int, b: int, c: int} $left
     * @param array{a: int, b: int, c: int} $right
     */
    private function compareSpecificity(array $left, array $right): int
    {
        return [$left['a'], $left['b'], $left['c']] <=> [$right['a'], $right['b'], $right['c']];
    }

    /**
     * @return list<string>
     */
    private function selectorListBranches(string $selector): array
    {
        $branches = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($selector);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $selector[$offset];

            if ($quote !== null) {
                if ($char === '\\') {
                    $offset++;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '(' || $char === '[') {
                $depth++;
                continue;
            }

            if (($char === ')' || $char === ']') && $depth > 0) {
                $depth--;
                continue;
            }

            if ($char !== ',' || $depth !== 0) {
                continue;
            }

            $branch = trim(substr($selector, $start, $offset - $start));
            if ($branch !== '') {
                $branches[] = $branch;
            }

            $start = $offset + 1;
        }

        $branch = trim(substr($selector, $start));
        if ($branch !== '') {
            $branches[] = $branch;
        }

        return $branches;
    }

    /**
     * @return list<array{selector: string, declarations: list<array{type: string, name: string, value: string, important: bool}>}>
     */
    private function parseQualifiedStyleRules(string $css): array
    {
        $tokens = $this->tokenizer->tokenize($css);
        $rules = [];
        $offset = 0;

        while ($offset < count($tokens)) {
            $this->skipTopLevelIgnored($tokens, $offset);

            if ($offset >= count($tokens)) {
                break;
            }

            if ($tokens[$offset]->type === 'at-keyword') {
                $this->skipRule($tokens, $offset);
                continue;
            }

            $rule = $this->consumeQualifiedStyleRule($tokens, $offset);
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param list<Token> $tokens
     */
    private function skipTopLevelIgnored(array $tokens, int &$offset): void
    {
        while (
            $offset < count($tokens)
            && in_array($tokens[$offset]->type, ['whitespace', 'CDO', 'CDC', 'comment'], true)
        ) {
            $offset++;
        }
    }

    /**
     * @param list<Token> $tokens
     * @return array{selector: string, declarations: list<array{type: string, name: string, value: string, important: bool}>}|null
     */
    private function consumeQualifiedStyleRule(array $tokens, int &$offset): ?array
    {
        $prelude = [];
        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($token->type === 'comment') {
                $offset++;
                continue;
            }

            if ($token->type === 'left-curly-bracket' && $blockEndStack === []) {
                $offset++;
                $declarationBody = $this->consumeRawBlock($tokens, $offset);
                $selector = trim($this->serializeTokens($prelude));

                if ($selector === '') {
                    return null;
                }

                return [
                    'selector' => $selector,
                    'declarations' => $this->declarationsParser->parseList($declarationBody),
                ];
            }

            $prelude[] = $token;
            $this->updateBlockEndStack($blockEndStack, $token);
            $offset++;
        }

        return null;
    }

    /**
     * @param list<Token> $tokens
     */
    private function skipRule(array $tokens, int &$offset): void
    {
        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($blockEndStack === []) {
                if ($token->type === 'semicolon') {
                    $offset++;
                    return;
                }

                if ($token->type === 'left-curly-bracket') {
                    $offset++;
                    $this->consumeRawBlock($tokens, $offset);
                    return;
                }
            }

            $this->updateBlockEndStack($blockEndStack, $token);
            $offset++;
        }
    }

    /**
     * @param list<Token> $tokens
     */
    private function consumeRawBlock(array $tokens, int &$offset): string
    {
        $body = [];
        $blockEndStack = [];

        while ($offset < count($tokens)) {
            $token = $tokens[$offset];

            if ($blockEndStack === [] && $token->type === 'right-curly-bracket') {
                $offset++;
                break;
            }

            $body[] = $token;
            $this->updateBlockEndStack($blockEndStack, $token);
            $offset++;
        }

        return $this->serializeTokens($body);
    }

    /**
     * @param list<string> $blockEndStack
     */
    private function updateBlockEndStack(array &$blockEndStack, Token $token): void
    {
        $endToken = match ($token->type) {
            'function', 'left-parenthesis' => 'right-parenthesis',
            'left-square-bracket' => 'right-square-bracket',
            'left-curly-bracket' => 'right-curly-bracket',
            default => null,
        };

        if ($endToken !== null) {
            $blockEndStack[] = $endToken;
            return;
        }

        if ($blockEndStack !== [] && $token->type === end($blockEndStack)) {
            array_pop($blockEndStack);
        }
    }

    /**
     * @param list<Token> $tokens
     */
    private function serializeTokens(array $tokens): string
    {
        $serialized = '';

        foreach ($tokens as $token) {
            $serialized .= $token->value;
        }

        return $serialized;
    }

}

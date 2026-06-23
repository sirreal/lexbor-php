<?php

declare(strict_types=1);

namespace Lexbor\Css\Stylesheet;

use Lexbor\Css\Syntax\Parser as SyntaxParser;

final class Parser
{
    public function __construct(
        private readonly SyntaxParser $syntaxParser = new SyntaxParser(),
    ) {
    }

    /**
     * @return array{type: string, rules: list<array<string, mixed>>}
     */
    public function parse(string $css): array
    {
        return [
            'type' => 'stylesheet',
            'rules' => $this->syntaxParser->parseListRules($css),
        ];
    }
}

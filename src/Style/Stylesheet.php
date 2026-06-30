<?php

declare(strict_types=1);

namespace Lexbor\Style;

final class Stylesheet
{
    /**
     * @param list<array{selector: string, declarations: list<array{type: string, name: string, value: string, important: bool}>}> $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    /**
     * @return list<array{selector: string, declarations: list<array{type: string, name: string, value: string, important: bool}>}>
     */
    public function rules(): array
    {
        return $this->rules;
    }
}

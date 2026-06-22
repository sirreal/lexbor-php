<?php

declare(strict_types=1);

namespace Lexbor\Tests\Css\Syntax;

use Lexbor\Css\Syntax\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{type: string, prelude: list<array{type: string, value: string}>, block: list<array<string, mixed>>}>}>
     */
    public static function upstreamQualifiedProvider(): iterable
    {
        yield 'qualified.ton #1 selector prelude' => ['#id .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #2 leading whitespace skipped' => ['    #id .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #3 trailing whitespace retained' => ['#id .class    ', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                    ['type' => 'whitespace', 'value' => '    '],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #4 mismatched square block keeps curly tokens in prelude' => ['#id [{]{ .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'left-square-bracket', 'value' => '['],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'right-square-bracket', 'value' => ']'],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #5 mismatched parenthesis block keeps curly tokens in prelude' => ['#id ({){ .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'left-parenthesis', 'value' => '('],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'right-parenthesis', 'value' => ')'],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #6 mismatched function block keeps curly token in prelude' => ['#id last({) .class', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'function', 'value' => 'last('],
                    ['type' => 'left-curly-bracket', 'value' => '{'],
                    ['type' => 'right-parenthesis', 'value' => ')'],
                    ['type' => 'whitespace', 'value' => ' '],
                    ['type' => 'delim', 'value' => '.'],
                    ['type' => 'ident', 'value' => 'class'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #7 empty block after spaced prelude' => ['#id {}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #8 empty block after compact prelude' => ['#id{}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                ],
                'block' => [],
            ],
        ]];
        yield 'qualified.ton #9 simple declaration' => ['#id {width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #10 leading block whitespace before declaration' => ['#id {   width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #11 declaration whitespace before colon' => ['#id {width    : 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #12 declaration without whitespace after colon' => ['#id {width:10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #13 declaration whitespace after colon' => ['#id {width:    10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #14 trailing declaration whitespace before block end' => ['#id {width: 10px    }', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #15 leading semicolon before declaration' => ['#id {;width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #16 bracketed semicolon in declaration value' => ['#id {width: [;] 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '[;] 10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #17 trailing semicolons after declaration' => ['#id {width: 10px;;;;;}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #18 declaration at EOF in block' => ['#id {width: 10px', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #19 important declaration before block end' => ['#id {width: 10px !important}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => true],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #20 important declaration with trailing whitespace' => ['#id {width: 10px !important   }', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => true],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #21 important declaration followed by second declaration' => ['#id {width: 10px !important   ; height: 20px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => true],
                            ['name' => 'height', 'value' => '20px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #22 compact important declaration followed by second declaration' => ['#id {width: 10px!important; height: 20px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => true],
                            ['name' => 'height', 'value' => '20px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #23 important with trailing ident stays value text' => ['#id {width: 10px !important x; height: 20px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px !important x', 'important' => false],
                            ['name' => 'height', 'value' => '20px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #24 spaced important marker stays value text' => ['#id {width: 10px ! important; height: 20px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px ! important', 'important' => false],
                            ['name' => 'height', 'value' => '20px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
    }

    /**
     * @param list<array{type: string, prelude: list<array{type: string, value: string}>, block: list<array<string, mixed>>}> $expected
     */
    #[DataProvider('upstreamQualifiedProvider')]
    public function testUpstreamQualifiedFixtures(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseListRules($css));
    }
}

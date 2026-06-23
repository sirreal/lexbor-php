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
        yield 'qualified.ton #25 at-rule with nested qualified block' => ['#id {@Naruto Orochimaru {Sasuke Uchiha}}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'at-rule',
                        'name' => '@Naruto',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Orochimaru'],
                            ['type' => 'whitespace', 'value' => ' '],
                        ],
                        'block' => [
                            [
                                'type' => 'qualified-rule',
                                'prelude' => [
                                    ['type' => 'ident', 'value' => 'Sasuke'],
                                    ['type' => 'whitespace', 'value' => ' '],
                                    ['type' => 'ident', 'value' => 'Uchiha'],
                                ],
                                'block' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #26 at-rule terminated by semicolon in block' => ['#id {@Naruto Orochimaru;}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'at-rule',
                        'name' => '@Naruto',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Orochimaru'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #27 at-rule terminated by block end' => ['#id {@Naruto Orochimaru}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'at-rule',
                        'name' => '@Naruto',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Orochimaru'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #28 at-rule semicolon then declaration' => ['#id {@Naruto Orochimaru; width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'at-rule',
                        'name' => '@Naruto',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Orochimaru'],
                        ],
                    ],
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #29 at-rule adjacent declaration' => ['#id {@Naruto Orochimaru;width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'at-rule',
                        'name' => '@Naruto',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Orochimaru'],
                        ],
                    ],
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #30 at-rule block then spaced declaration' => ['#id {@Naruto Orochimaru {Sasuke Uchiha} width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'at-rule',
                        'name' => '@Naruto',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Orochimaru'],
                            ['type' => 'whitespace', 'value' => ' '],
                        ],
                        'block' => [
                            [
                                'type' => 'qualified-rule',
                                'prelude' => [
                                    ['type' => 'ident', 'value' => 'Sasuke'],
                                    ['type' => 'whitespace', 'value' => ' '],
                                    ['type' => 'ident', 'value' => 'Uchiha'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #31 at-rule block adjacent declaration' => ['#id {@Naruto Orochimaru {Sasuke Uchiha}width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'at-rule',
                        'name' => '@Naruto',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Orochimaru'],
                            ['type' => 'whitespace', 'value' => ' '],
                        ],
                        'block' => [
                            [
                                'type' => 'qualified-rule',
                                'prelude' => [
                                    ['type' => 'ident', 'value' => 'Sasuke'],
                                    ['type' => 'whitespace', 'value' => ' '],
                                    ['type' => 'ident', 'value' => 'Uchiha'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #32 broken declaration as qualified rule' => ['#id {broken declaration}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'broken'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'declaration'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #33 broken declaration with colon as qualified rule' => ['#id {broken de:claration}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'broken'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'de'],
                            ['type' => 'colon', 'value' => ':'],
                            ['type' => 'ident', 'value' => 'claration'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #34 broken qualified rule then spaced declaration' => ['#id {broken declaration; width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'broken'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'declaration'],
                        ],
                    ],
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'qualified.ton #35 broken qualified rule adjacent declaration' => ['#id {broken declaration;width: 10px}', [
            [
                'type' => 'qualified-rule',
                'prelude' => [
                    ['type' => 'hash', 'value' => '#id'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'broken'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'declaration'],
                        ],
                    ],
                    [
                        'type' => 'declarations',
                        'declarations' => [
                            ['name' => 'width', 'value' => '10px', 'important' => false],
                        ],
                    ],
                ],
            ],
        ]];
    }

    /**
     * @return iterable<string, array{string, list<array<string, mixed>>}>
     */
    public static function upstreamAtProvider(): iterable
    {
        yield 'at.ton #1 at-rule with qualified block' => ['@Naruto Orochimaru {Sasuke Uchiha}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Orochimaru'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Sasuke'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'Uchiha'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #2 at-rule terminated by semicolon' => ['@Naruto Orochimaru;', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Orochimaru'],
                ],
                'block' => [],
            ],
        ]];
        yield 'at.ton #3 at-rule terminated by EOF' => ['@Naruto Orochimaru', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Orochimaru'],
                ],
                'block' => [],
            ],
        ]];
        yield 'at.ton #4 bare at-rule terminated by EOF' => ['@Naruto', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [],
            ],
        ]];
        yield 'at.ton #5 at-rule with empty block' => ['@Naruto Orochimaru {}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Orochimaru'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [],
            ],
        ]];
        yield 'at.ton #6 bare at-rule terminated by semicolon' => ['@Naruto;', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [],
            ],
        ]];
        yield 'at.ton #7 bracketed semicolon remains in prelude' => ['@Naruto [;]', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'left-square-bracket', 'value' => '['],
                    ['type' => 'semicolon', 'value' => ';'],
                    ['type' => 'right-square-bracket', 'value' => ']'],
                ],
                'block' => [],
            ],
        ]];
        yield 'at.ton #8 function semicolon remains in prelude' => ['@Naruto Orochimaru(;)', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'function', 'value' => 'Orochimaru('],
                    ['type' => 'semicolon', 'value' => ';'],
                    ['type' => 'right-parenthesis', 'value' => ')'],
                ],
                'block' => [],
            ],
        ]];
        yield 'at.ton #9 parenthesized semicolon remains in prelude' => ['@Naruto (;)', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'left-parenthesis', 'value' => '('],
                    ['type' => 'semicolon', 'value' => ';'],
                    ['type' => 'right-parenthesis', 'value' => ')'],
                ],
                'block' => [],
            ],
        ]];
        yield 'at.ton #10 bracketed semicolon before malformed block' => ['@Naruto [;] {[}]}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'left-square-bracket', 'value' => '['],
                    ['type' => 'semicolon', 'value' => ';'],
                    ['type' => 'right-square-bracket', 'value' => ']'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'left-square-bracket', 'value' => '['],
                            ['type' => 'right-curly-bracket', 'value' => '}'],
                            ['type' => 'right-square-bracket', 'value' => ']'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #14 at-rule with qualified block and spaced name' => ['@Naruto {Sasuke Uchiha}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Sasuke'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'Uchiha'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #15 at-rule with adjacent qualified block' => ['@Naruto{Sasuke Uchiha}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Sasuke'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'Uchiha'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #16 at-rule with adjacent empty block' => ['@Naruto{}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [],
            ],
        ]];
        yield 'at.ton #17 duplicate bare at-rule fixture' => ['@Naruto', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [],
            ],
        ]];
        yield 'at.ton #18 leading block whitespace before qualified rule' => ['@Naruto {    Sasuke Uchiha}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Sasuke'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'Uchiha'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #19 trailing block whitespace retained in qualified prelude' => ['@Naruto {Sasuke Uchiha    }', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Sasuke'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'Uchiha'],
                            ['type' => 'whitespace', 'value' => '    '],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #20 whitespace-only block' => ['@Naruto {    }', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [],
                'block' => [],
            ],
        ]];
        yield 'at.ton #21 spaced consecutive at-rules with blocks' => ['@Naruto Orochimaru {Sasuke Uchiha} @Red Blue {Yellow}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Orochimaru'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Sasuke'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'Uchiha'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'at-rule',
                'name' => '@Red',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Blue'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Yellow'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #22 adjacent consecutive at-rules with blocks' => ['@Naruto Orochimaru {Sasuke Uchiha}@Red Blue {Yellow}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Orochimaru'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Sasuke'],
                            ['type' => 'whitespace', 'value' => ' '],
                            ['type' => 'ident', 'value' => 'Uchiha'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'at-rule',
                'name' => '@Red',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Blue'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Yellow'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #23 semicolon at-rule then spaced block at-rule' => ['@Naruto Orochimaru; @Red Blue {Yellow}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Orochimaru'],
                ],
                'block' => [],
            ],
            [
                'type' => 'at-rule',
                'name' => '@Red',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Blue'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Yellow'],
                        ],
                    ],
                ],
            ],
        ]];
        yield 'at.ton #24 semicolon at-rule then adjacent block at-rule' => ['@Naruto Orochimaru;@Red Blue {Yellow}', [
            [
                'type' => 'at-rule',
                'name' => '@Naruto',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Orochimaru'],
                ],
                'block' => [],
            ],
            [
                'type' => 'at-rule',
                'name' => '@Red',
                'prelude' => [
                    ['type' => 'ident', 'value' => 'Blue'],
                    ['type' => 'whitespace', 'value' => ' '],
                ],
                'block' => [
                    [
                        'type' => 'qualified-rule',
                        'prelude' => [
                            ['type' => 'ident', 'value' => 'Yellow'],
                        ],
                    ],
                ],
            ],
        ]];
    }

    /**
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('upstreamQualifiedProvider')]
    public function testUpstreamQualifiedFixtures(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseListRules($css));
    }

    /**
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('upstreamAtProvider')]
    public function testUpstreamAtFixtures(string $css, array $expected): void
    {
        self::assertSame($expected, (new Parser())->parseListRules($css));
    }
}

<?php

declare(strict_types=1);

namespace Lexbor\Core;

enum Status: string
{
    case Ok = 'ok';
    case ErrorUnexpectedData = 'error_unexpected_data';
    case ErrorOverflow = 'error_overflow';
    case ErrorMemoryAllocation = 'error_memory_allocation';
}


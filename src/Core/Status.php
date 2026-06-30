<?php

declare(strict_types=1);

namespace Lexbor\Core;

enum Status: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Continue = 'continue';
    case SmallBuffer = 'small_buffer';
    case ErrorObjectIsNull = 'error_object_is_null';
    case ErrorTooSmallSize = 'error_too_small_size';
    case ErrorWrongArgs = 'error_wrong_args';
    case ErrorUnexpectedData = 'error_unexpected_data';
    case ErrorUnexpectedResult = 'error_unexpected_result';
    case ErrorOverflow = 'error_overflow';
    case ErrorMemoryAllocation = 'error_memory_allocation';
}

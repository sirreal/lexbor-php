<?php

declare(strict_types=1);

namespace Lexbor\Url;

enum ValidationError: string
{
    case InvalidUrlUnit = 'invalid_url_unit';
}

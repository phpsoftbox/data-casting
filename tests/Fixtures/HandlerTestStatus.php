<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests\Fixtures;

enum HandlerTestStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}

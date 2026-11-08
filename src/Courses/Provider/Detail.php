<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * Details a provider asks of the person who books (decision 0351).
 */
enum Detail: string
{
    case Address = 'address';
    case Phone = 'phone';
    case DateOfBirth = 'dateOfBirth';
}

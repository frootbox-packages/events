<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * How the person who books wants to be addressed.
 */
enum Salutation: string
{
    case Mr = 'mr';
    case Ms = 'ms';
    case Diverse = 'diverse';
}

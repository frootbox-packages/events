<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * Whether a date can be booked right now.
 */
enum BookingState: string
{
    case Open = 'open';
    case SoldOut = 'soldOut';
    case NotYetOpen = 'notYetOpen';
    case Closed = 'closed';
    case Offline = 'offline';
}

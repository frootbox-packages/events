<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * The seats of a date; without a maximum there is no limit.
 */
final readonly class Seats
{
    public function __construct(
        public ?int $max = null,
        public int $booked = 0,
        public ?int $free = null,
    ) {}

    public function fit(int $attendees): bool
    {
        return null === $this->free || $this->free >= $attendees;
    }
}

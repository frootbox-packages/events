<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

use DateTimeImmutable;

/**
 * Someone booked for a date: names and when, nothing else (decision 0359).
 */
final readonly class BookedAttendee
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?DateTimeImmutable $bookedAt = null,
    ) {}

    public function name(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}

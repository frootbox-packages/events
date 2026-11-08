<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A booking the provider took; repeated if the key had been sent before.
 */
final readonly class BookingConfirmation
{
    public function __construct(
        public string $id,
        public string $number,
        public int $attendees,
        public int $gross,
        public string $currency,
        public bool $repeated = false,
    ) {}
}

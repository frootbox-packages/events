<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A booking as the module sends it to the provider (decision 0351).
 */
final readonly class BookingRequest
{
    /**
     * @param list<Attendee>                                $attendees
     * @param array<string, bool|string>                    $fields      answers by key of the field
     * @param list<array{key: string, version: string}>     $consents    the legal texts agreed to
     * @param int                                           $expectedGross the total in cents the person saw
     */
    public function __construct(
        public string $date,
        public Booker $booker,
        public array $attendees,
        public int $expectedGross,
        public array $fields = [],
        public ?string $message = null,
        public array $consents = [],
    ) {}
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * Where a date takes place.
 */
final readonly class Venue
{
    public function __construct(
        public string $title,
        public string $street = '',
        public string $postalCode = '',
        public string $city = '',
        public string $country = '',
    ) {}

    /**
     * The address in one line, e.g. "Theaterstraße 1, 52062 Aachen".
     */
    public function address(): string
    {
        return implode(', ', array_filter([$this->street, trim($this->postalCode . ' ' . $this->city)], static fn(string $part): bool => '' !== $part));
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A course of the provider, e.g. "First aid", with what its dates have
 * by default.
 */
final readonly class Course
{
    /**
     * @param int|null $priceGross gross per attendee in cents, null without a price
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $currency,
        public string $subtitle = '',
        public ?string $parent = null,
        public ?int $priceGross = null,
        public ?int $attendeesMin = null,
        public ?int $attendeesMax = null,
    ) {}
}

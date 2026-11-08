<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

use DateTimeImmutable;

/**
 * Which dates to list: of a course or all, from a moment on (else now),
 * only those that can be booked, at most this many, earliest first.
 */
final readonly class DateQuery
{
    public function __construct(
        public ?string $course = null,
        public ?DateTimeImmutable $from = null,
        public bool $available = false,
        public int $limit = 20,
    ) {}
}

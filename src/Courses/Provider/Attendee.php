<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

use DateTimeImmutable;

/**
 * Someone who takes part.
 */
final readonly class Attendee
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?DateTimeImmutable $dateOfBirth = null,
    ) {}
}

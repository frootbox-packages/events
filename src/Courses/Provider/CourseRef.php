<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * The course a date belongs to.
 */
final readonly class CourseRef
{
    public function __construct(
        public string $id,
        public string $title,
    ) {}
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A provider as an extension registers it (decision 0351).
 */
final readonly class CourseProviderEntry
{
    /**
     * @param class-string<CourseProvider> $class
     */
    public function __construct(
        public string $class,
        public string $title,
    ) {}
}

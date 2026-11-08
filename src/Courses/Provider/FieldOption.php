<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * An option of a field of the kind choice.
 */
final readonly class FieldOption
{
    public function __construct(
        public string $value,
        public string $title,
    ) {}
}

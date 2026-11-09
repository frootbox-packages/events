<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A further channel of a provider (decision 0360), e.g. a closed area for
 * a company. A restricted one shows only on pages with a password.
 */
final readonly class ChannelChoice
{
    public function __construct(
        public string $key,
        public string $title,
        public bool $restricted = true,
    ) {}
}

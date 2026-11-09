<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A provider with channels besides its own (decision 0360), beside
 * CourseProvider: each channel is a provider of its own, e.g. another
 * client of the same system.
 */
interface ChannelSource
{
    /**
     * @return list<ChannelChoice> without the provider's own
     */
    public function channels(): array;

    /**
     * The provider for one of channels(), null for an unknown key.
     */
    public function in(string $key): ?CourseProvider;
}

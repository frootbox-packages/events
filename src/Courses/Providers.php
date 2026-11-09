<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Extension\Registry;
use Frootbox\Events\Courses\Provider\ChannelChoice;
use Frootbox\Events\Courses\Provider\ChannelSource;
use Frootbox\Events\Courses\Provider\CourseProvider;
use Frootbox\Events\Courses\Provider\CourseProviderEntry;
use Frootbox\Events\EventsSettings;
use Psr\Container\ContainerInterface;

/**
 * The providers of courses extensions registered and the active one
 * (decision 0351): the one of the setting "provider", or the only one.
 * Its further channels come from ChannelSource (decision 0360).
 */
final readonly class Providers
{
    public function __construct(
        private Registry $registry,
        private EventsSettings $settings,
        private ContainerInterface $container,
    ) {}

    /**
     * @return list<CourseProviderEntry>
     */
    public function entries(): array
    {
        return $this->registry->items(CourseProviderEntry::class);
    }

    /**
     * The active provider, or one of its channels; null without a
     * provider or for an unknown channel.
     */
    public function active(string $channel = ''): ?CourseProvider
    {
        $provider = $this->provider();

        if ('' === $channel || null === $provider) {
            return $provider;
        }

        return $provider instanceof ChannelSource ? $provider->in($channel) : null;
    }

    /**
     * The further channels of the active provider.
     *
     * @return list<ChannelChoice>
     */
    public function channels(): array
    {
        $provider = $this->provider();

        return $provider instanceof ChannelSource ? $provider->channels() : [];
    }

    private function provider(): ?CourseProvider
    {
        $entries = $this->entries();
        $entry = '' === $this->settings->provider
            ? (1 === \count($entries) ? $entries[0] : null)
            : array_find($entries, fn(CourseProviderEntry $entry): bool => $entry->class === $this->settings->provider);

        if (null === $entry) {
            return null;
        }

        $provider = $this->container->get($entry->class);

        return $provider instanceof CourseProvider ? $provider : null;
    }
}

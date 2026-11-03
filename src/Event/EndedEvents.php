<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use Frootbox\Core\Cache\PageCache;
use Frootbox\Core\ProjectPaths;
use Frootbox\Core\Schedule\ScheduleRan;

/**
 * Listens to the scheduler: when an event is over, lists of upcoming
 * events in the page cache are out of date, so the cache is cleared
 * (decision 0227). The moment of the last look lives in var/, like the
 * cache; without it there is nothing to clear.
 */
final readonly class EndedEvents
{
    public const string FILE = 'cache/frootbox-events-checked';

    public function __construct(
        private PublishedEvents $events,
        private PageCache $cache,
        private ProjectPaths $paths,
    ) {}

    public function __invoke(ScheduleRan $event): void
    {
        $file = $this->paths->var(self::FILE);
        $last = is_file($file) ? @file_get_contents($file) : false;
        $after = false === $last ? null : DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, trim($last));

        if (false !== $after && null !== $after && $this->events->endedBetween($after, $event->now)) {
            $this->cache->clear();
        }

        $directory = \dirname($file);

        if (is_dir($directory) || @mkdir($directory, 0o775, true)) {
            @file_put_contents($file, $event->now->format(DateTimeImmutable::ATOM));
        }
    }
}

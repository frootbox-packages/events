<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

/**
 * A page of the list of events.
 */
final readonly class EventList
{
    /**
     * @param list<EventSummary> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pages,
    ) {}
}

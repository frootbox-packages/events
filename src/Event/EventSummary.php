<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;

/**
 * An event in the list of the backend.
 */
final readonly class EventSummary
{
    /**
     * @param list<string> $languages The languages it has, in the draft if there is one
     */
    public function __construct(
        public string $id,
        public EventStatus $status,
        public string $title,
        public DateTimeImmutable $start,
        public bool $allDay,
        public array $languages,
        public ?DateTimeImmutable $publishAt = null,
        public ?DateTimeImmutable $unpublishAt = null,
    ) {}
}

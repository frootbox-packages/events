<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use Frootbox\Database\Entity;

/**
 * A published event in the table, for lists by time and category.
 */
final readonly class Event extends Entity
{
    /**
     * @param DateTimeImmutable  $lastsUntil The moment the event is over, for lists of upcoming and past events
     * @param array<mixed>|null  $image The value of an image field: {"media": "01J…", "alt": "…"}
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public bool $allDay,
        public DateTimeImmutable $lastsUntil,
        public string $address,
        public string $coordinates,
        public ?array $image,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        /** The space of the events (decision 0232) */
        public string $space = 'default',
    ) {}
}

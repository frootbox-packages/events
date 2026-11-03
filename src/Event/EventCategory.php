<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Database\Entity;

/**
 * A category of a published event.
 */
final readonly class EventCategory extends Entity
{
    public function __construct(
        public int $id,
        public string $eventId,
        public string $categoryId,
    ) {}
}

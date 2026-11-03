<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Database\Repository;

/**
 * @extends Repository<EventCategory>
 */
final class EventCategories extends Repository
{
    public const string TABLE = 'frootbox_events_event_categories';

    /**
     * @return list<string> The ids of the categories of an event
     */
    public function of(string $eventId): array
    {
        return array_map(static fn(EventCategory $category): string => $category->categoryId, $this->fetch(['where' => ['eventId' => $eventId], 'order' => 'id'])->all());
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return EventCategory::class;
    }
}

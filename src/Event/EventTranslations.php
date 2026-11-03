<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Database\Repository;

/**
 * @extends Repository<EventTranslation>
 */
final class EventTranslations extends Repository
{
    public const string TABLE = 'frootbox_events_event_translations';

    /**
     * @return array<string, EventTranslation> by language
     */
    public function of(string $eventId): array
    {
        $translations = [];

        foreach ($this->fetch(['where' => ['eventId' => $eventId]]) as $translation) {
            $translations[$translation->language] = $translation;
        }

        return $translations;
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return EventTranslation::class;
    }
}

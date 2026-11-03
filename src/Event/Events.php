<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Database\Repository;

/**
 * @extends Repository<Event>
 */
final class Events extends Repository
{
    public const string TABLE = 'frootbox_events_events';

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Event::class;
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Schedule\ScheduledType;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\PublishEvents;
use Frootbox\Events\Event\UnpublishEvent;

/**
 * The scheduler publishes events and takes them offline at their time.
 */
final readonly class EventSchedule implements ScheduledType
{
    public function type(): string
    {
        return EventStore::TYPE;
    }

    public function publish(array $ids): Action
    {
        return new PublishEvents($ids);
    }

    public function unpublish(string $id): Action
    {
        return new UnpublishEvent($id);
    }
}

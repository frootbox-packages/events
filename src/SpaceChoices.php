<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Plugin\PluginSpaces;
use Frootbox\Events\Event\EventStore;

/**
 * @implements Query<list<array{value: string, label: string}>>
 */
#[AsQuery('frootbox/events/space.choices', 'The spaces of the events as choices for a block, "All" first; empty with only one space', permission: 'frootbox/events/event.list')]
final readonly class SpaceChoices implements Query
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function handle(PluginSpaces $spaces): array
    {
        return $spaces->choices(EventStore::TYPE);
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\ListOf;

/**
 * @implements Action<list<EventDetails>>
 */
#[AsAction('frootbox/events/event.publish', 'Publishes the drafts of one or more events')]
final readonly class PublishEvents implements Action
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        #[ListOf('string')]
        public array $ids,
    ) {}

    /**
     * @return list<EventDetails>
     */
    public function handle(EventEditor $editor): array
    {
        return $editor->publish('frootbox/events/event.publish', $this->ids);
    }
}

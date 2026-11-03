<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Database\Ulid;

/**
 * @implements Query<EventDetails>
 */
#[AsQuery('frootbox/events/event.show', 'Returns an event with its draft, its published version and where visitors find it')]
final readonly class ShowEvent implements Query
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
    ) {}

    public function handle(EventStore $store): EventDetails
    {
        return $store->details($this->id, true);
    }
}

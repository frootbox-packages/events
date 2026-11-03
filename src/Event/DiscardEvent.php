<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Database\Ulid;

/**
 * @implements Action<EventDetails|null>
 */
#[AsAction('frootbox/events/event.discard', 'Throws away the draft of an event; one that was never published is gone then')]
final readonly class DiscardEvent implements Action
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
    ) {}

    public function handle(EventEditor $editor): ?EventDetails
    {
        return $editor->discard('frootbox/events/event.discard', $this->id);
    }
}

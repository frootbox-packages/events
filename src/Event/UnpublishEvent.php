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
 * @implements Action<EventDetails>
 */
#[AsAction('frootbox/events/event.unpublish', 'Takes an event offline; it stays as a draft and can be published again')]
final readonly class UnpublishEvent implements Action
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
    ) {}

    public function handle(EventEditor $editor): EventDetails
    {
        return $editor->unpublish('frootbox/events/event.unpublish', $this->id);
    }
}

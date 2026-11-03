<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Core\Auth\Scope;
use Frootbox\Core\Auth\Scoped;
use Frootbox\Core\Plugin\SpaceScopes;
use Frootbox\Database\Ulid;

/**
 * @implements Action<EventDetails|null>
 */
#[AsAction('frootbox/events/event.discard', 'Throws away the draft of an event; one that was never published is gone then')]
final readonly class DiscardEvent implements Action, Scoped
{
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
    ) {}

    /**
     * The object within its space (decision 0245).
     *
     * @return list<Scope>
     */
    public function scopes(): array
    {
        return [SpaceScopes::object(EventStore::TYPE, $this->id)];
    }

    public function handle(EventEditor $editor): ?EventDetails
    {
        return $editor->discard('frootbox/events/event.discard', $this->id);
    }
}

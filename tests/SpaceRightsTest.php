<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests;

use DateTimeImmutable;
use Frootbox\Core\Action\AccessDeniedException;
use Frootbox\Core\Action\Channel;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Auth\Actor;
use Frootbox\Core\Plugin\CreateSpace;
use Frootbox\Core\Plugin\Space;
use Frootbox\Core\Plugin\SpaceScopes;
use Frootbox\Core\User\AssignRole;
use Frootbox\Core\User\CreateUser;
use Frootbox\Events\Event\CreateEvent;
use Frootbox\Events\Event\DeleteEvent;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventSummary;
use Frootbox\Events\Event\ListEvents;
use Frootbox\Events\Event\PublishEvents;
use Frootbox\Events\Event\UpdateEvent;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * An editor with a role only within one space (decision 0245).
 */
#[CoversClass(ListEvents::class)]
#[CoversClass(UpdateEvent::class)]
final class SpaceRightsTest extends EventsTestCase
{
    public function testAnEditorWorksOnlyWithinTheSpace(): void
    {
        $space = $this->runner()->run(new CreateSpace(EventStore::TYPE, 'Kurse'))->id;
        $outside = $this->event(['de' => ['title' => 'Sommerfest']]);
        $inside = $this->event(['de' => ['title' => 'Latte-Art-Kurs']], space: $space);

        $user = $this->runner()->run(new CreateUser('jane@example.org', 'Jane', 'kaffeebohne42'))->user;
        $this->runner()->run(new AssignRole($user->id, 'editor', 'space:' . SpaceScopes::id(EventStore::TYPE, $space)));
        $editor = new Context(Actor::user($user->id, $user->name), Channel::Api);

        self::assertSame([$inside], array_map(static fn(EventSummary $item): string => $item->id, $this->runner()->ask(new ListEvents(), $editor)->items));
        $created = $this->runner()->run(new CreateEvent(['de' => ['title' => 'Röstkurs']], new DateTimeImmutable('+1 week'), space: $space), $editor)->id;
        $this->runner()->run(new UpdateEvent($inside, ['de' => ['title' => 'Latte Art für alle']]), $editor);
        self::assertCount(2, $this->runner()->run(new PublishEvents([$inside, $created]), $editor));

        foreach ([new CreateEvent(['de' => ['title' => 'Draußen']], new DateTimeImmutable('+1 week')), new UpdateEvent($outside, ['de' => ['title' => 'Draußen']]), new UpdateEvent($inside, space: Space::DEFAULT), new PublishEvents([$inside, $outside]), new DeleteEvent($outside)] as $action) {
            try {
                $this->runner()->run($action, $editor);
                self::fail(\sprintf('%s was allowed.', $action::class));
            } catch (AccessDeniedException) {
                self::assertSame('denied', $this->lastEntry()->status->value);
            }
        }
    }
}

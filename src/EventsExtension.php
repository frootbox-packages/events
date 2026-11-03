<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events;

use Frootbox\Backend\Screen;
use Frootbox\Backend\SearchCommand;
use Frootbox\Backend\SearchLink;
use Frootbox\Core\Extension\Extension;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Events\Backend\CategoriesHandler;
use Frootbox\Events\Backend\EventHandler;
use Frootbox\Events\Backend\EventsHandler;
use Frootbox\Events\Calendar\Calendar;
use Frootbox\Events\Calendar\CalendarHandler;
use Frootbox\Events\Category\CreateCategory;
use Frootbox\Events\Category\DeleteCategory;
use Frootbox\Events\Category\ListCategories;
use Frootbox\Events\Category\UpdateCategory;
use Frootbox\Events\Event\CreateEvent;
use Frootbox\Events\Event\DeleteEvent;
use Frootbox\Events\Event\DiscardEvent;
use Frootbox\Events\Event\EndedEvents;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\ListEvents;
use Frootbox\Events\Event\PublishEvents;
use Frootbox\Events\Event\ScheduleEvent;
use Frootbox\Events\Event\ShowEvent;
use Frootbox\Events\Event\UnpublishEvent;
use Frootbox\Events\Event\UpdateEvent;

/**
 * Events as a plugin (decisions 0219, 0227): events with drafts, their
 * addresses below the page with the list, the scheduler, the search, a
 * calendar file for each event and a fresh page cache once one is over.
 * The screens come only with the backend.
 */
final class EventsExtension implements Extension
{
    public function register(Registry $registry): void
    {
        $registry->action(CreateEvent::class);
        $registry->action(UpdateEvent::class);
        $registry->action(PublishEvents::class);
        $registry->action(UnpublishEvent::class);
        $registry->action(ScheduleEvent::class);
        $registry->action(DiscardEvent::class);
        $registry->action(DeleteEvent::class);
        $registry->query(ListEvents::class);
        $registry->query(ShowEvent::class);
        $registry->action(CreateCategory::class);
        $registry->action(UpdateCategory::class);
        $registry->action(DeleteCategory::class);
        $registry->query(ListCategories::class);

        $registry->subpages(EventPages::class);
        $registry->scheduled(EventSchedule::class);
        $registry->searchSource(EventSource::class);

        $registry->get(Calendar::ROUTE, CalendarHandler::class, 'frootbox.events.calendar');
        $registry->listen(ScheduleRan::class, EndedEvents::class);

        if (class_exists(Screen::class)) {
            $this->backend($registry);
        }
    }

    /**
     * The screens in the backend (decision 0220), only if it is installed.
     */
    private function backend(Registry $registry): void
    {
        $registry->mount('backend.path', '/cms')
            ->get('/events', EventsHandler::class, 'frootbox.events.events')
            ->get('/events/new', EventHandler::class, 'frootbox.events.events.new')
            ->get('/events/categories', CategoriesHandler::class, 'frootbox.events.events.categories')
            ->get('/events/{id:[0-9A-Za-z]{26}}', EventHandler::class, 'frootbox.events.events.show');

        $registry->add(Screen::class, new Screen('Events', 'frootbox.events.events', Screen::CONTENT, 'calendar', EventsHandler::PERMISSION, order: 17));
        $registry->add(SearchLink::class, new SearchLink(EventStore::TYPE, 'frootbox.events.events.show', 'id', 'calendar'));
        $registry->add(SearchCommand::class, new SearchCommand('New event', 'frootbox.events.events.new', 'frootbox/events/event.create'));
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests;

use DateTimeImmutable;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\Schedule\Scheduler;
use Frootbox\Core\Search\Search;
use Frootbox\Core\Url\Urls;
use Frootbox\Core\User\Roles;
use Frootbox\Events\Category\Categories;
use Frootbox\Events\Category\CategoryEditor;
use Frootbox\Events\Category\CreateCategory;
use Frootbox\Events\Category\DeleteCategory;
use Frootbox\Events\Event\CreateEvent;
use Frootbox\Events\Event\DeleteEvent;
use Frootbox\Events\Event\DiscardEvent;
use Frootbox\Events\Event\EventDetails;
use Frootbox\Events\Event\EventEditor;
use Frootbox\Events\Event\Events;
use Frootbox\Events\Event\EventStatus;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventTranslations;
use Frootbox\Events\Event\ListEvents;
use Frootbox\Events\Event\PublishEvents;
use Frootbox\Events\Event\ScheduleEvent;
use Frootbox\Events\Event\ShowEvent;
use Frootbox\Events\Event\UnpublishEvent;
use Frootbox\Events\Event\UpdateEvent;
use Frootbox\Events\EventSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventSource::class)]
#[CoversClass(EventEditor::class)]
#[CoversClass(EventStore::class)]
#[CoversClass(CategoryEditor::class)]
final class EventsTest extends EventsTestCase
{
    public function testCreatesADraftAndPublishesIt(): void
    {
        $details = $this->create(['de' => ['title' => 'Neue Sorten', 'teaser' => 'Drei Bohnen aus Peru.', 'text' => self::text('Frisch geröstet.')]]);

        self::assertSame(EventStatus::Draft, $details->status);
        self::assertSame('neue-sorten', $details->draft?->translations['de']->slug, 'The slug comes from the title.');
        self::assertNull($this->get(Events::class)->fetchById($details->id), 'A draft is not in the table.');

        $published = $this->runner()->run(new PublishEvents([$details->id]));
        self::assertSame(EventStatus::Published, $published[0]->status);
        self::assertSame(1, $published[0]->version);
        self::assertSame('Drei Bohnen aus Peru.', $this->get(EventTranslations::class)->of($details->id)['de']->teaser);

        $this->runner()->run(new UpdateEvent($details->id, ['de' => ['title' => 'Neue Sorten aus Peru']]));
        $shown = $this->runner()->ask(new ShowEvent($details->id));
        self::assertSame(EventStatus::Changed, $shown->status);
        self::assertSame('neue-sorten', $shown->draft?->translations['de']->slug, 'The slug stays when only the title changes.');
        self::assertSame('Drei Bohnen aus Peru.', $shown->draft->translations['de']->teaser, 'What is left out stays.');
        self::assertSame('Neue Sorten', $this->get(EventTranslations::class)->of($details->id)['de']->title, 'Visitors see the published version.');
    }

    public function testKeepsTimePlaceAndWhenTheEventIsOver(): void
    {
        $details = $this->runner()->run(new CreateEvent(
            ['de' => ['title' => 'Röstkurs', 'venue' => ' Rösterei ']],
            new DateTimeImmutable('2030-03-04T09:00:00Z'),
            new DateTimeImmutable('2030-03-04T13:00:00Z'),
            address: ' Hafenstraße 12, 20457 Hamburg ',
            coordinates: '53.5444, 9.9663',
        ));
        $draft = $details->draft;
        self::assertNotNull($draft);
        self::assertSame('Rösterei', $draft->translations['de']->venue);
        self::assertSame('Hafenstraße 12, 20457 Hamburg', $draft->address);
        self::assertSame(date_default_timezone_get(), $draft->start->getTimezone()->getName(), 'Times are kept in the time zone of the website, where its days begin.');
        self::assertEquals(new DateTimeImmutable('2030-03-04T13:00:00Z'), $draft->until());

        $this->runner()->run(new PublishEvents([$details->id]));
        $row = $this->get(Events::class)->fetchById($details->id);
        self::assertNotNull($row);
        self::assertEquals(new DateTimeImmutable('2030-03-04T13:00:00Z'), $row->lastsUntil);
        self::assertSame('Rösterei', $this->get(EventTranslations::class)->of($details->id)['de']->venue);

        $whole = $this->runner()->run(new UpdateEvent($details->id, start: new DateTimeImmutable('2030-03-04 00:00'), allDay: true));
        self::assertNull($whole->draft?->end, 'Start, end and allDay change together: no end now.');
        self::assertSame('2030-03-04 23:59:59', $whole->draft?->until()->format('Y-m-d H:i:s'), 'All day long lasts until the end of the day.');

        $kept = $this->runner()->run(new UpdateEvent($details->id, ['de' => ['teaser' => 'Vier Stunden']]));
        self::assertTrue($kept->draft?->allDay, 'Without a start the time stays.');
    }

    public function testRefusesAnEndBeforeTheStartAndWrongCoordinates(): void
    {
        try {
            $this->runner()->run(new CreateEvent(['de' => ['title' => 'Kurs']], new DateTimeImmutable('2030-03-04 10:00'), new DateTimeImmutable('2030-03-04 09:00'), coordinates: '91, 10'));
            self::fail('The end is before the start.');
        } catch (InvalidInputException $error) {
            self::assertSame(['/end', '/coordinates'], array_keys($error->errors));
        }

        $days = $this->runner()->run(new CreateEvent(['de' => ['title' => 'Fest']], new DateTimeImmutable('2030-03-04 15:00'), new DateTimeImmutable('2030-03-04 00:00'), true));
        self::assertNotNull($days->draft, 'All day long only the days count.');
    }

    public function testDraftsMayBeUnfinishedButPublishingChecksThem(): void
    {
        $details = $this->create(['de' => ['title' => '']]);

        try {
            $this->runner()->run(new PublishEvents([$details->id]));
            self::fail('An event without a title is not published.');
        } catch (InvalidInputException $error) {
            self::assertSame(['/ids/0/translations/de/title', '/ids/0/translations/de/slug'], array_keys($error->errors));
        }

        $this->expectException(InvalidInputException::class);
        $this->create(['fr' => ['title' => 'Nouvelles']]);
    }

    public function testSlugsAreUniqueInALanguage(): void
    {
        $this->event(['de' => ['title' => 'Neue Sorten']]);
        $this->create(['en' => ['title' => 'Neue Sorten']]);

        try {
            $this->create(['de' => ['title' => 'Neue Sorten']]);
            self::fail('Two events cannot share a slug.');
        } catch (InvalidInputException $error) {
            self::assertArrayHasKey('/translations/de/slug', $error->errors);
        }
    }

    public function testTakesOfflineDiscardsAndDeletes(): void
    {
        $id = $this->event(['de' => ['title' => 'Neue Sorten']]);

        $details = $this->runner()->run(new UnpublishEvent($id));
        self::assertSame(EventStatus::Draft, $details->status, 'Offline it stays as a draft.');
        self::assertNull($this->get(Events::class)->fetchById($id));

        $this->runner()->run(new PublishEvents([$id]));
        $this->runner()->run(new UpdateEvent($id, ['de' => ['title' => 'Anders']]));
        $kept = $this->runner()->run(new DiscardEvent($id));
        self::assertInstanceOf(EventDetails::class, $kept);
        self::assertSame(EventStatus::Published, $kept->status);

        $this->runner()->run(new DeleteEvent($id));
        self::assertNull($this->get(Events::class)->fetchById($id));
        self::assertSame([], $this->get(EventStore::class)->ids());

        $draft = $this->create(['de' => ['title' => 'Nur Entwurf']]);
        self::assertNull($this->runner()->run(new DiscardEvent($draft->id)), 'Never published: gone.');
    }

    public function testListsEventsWithTheLatestStartFirst(): void
    {
        $this->event(['de' => ['title' => 'Älter']], '2025-06-01 10:00');
        $this->event(['de' => ['title' => 'Neuer'], 'en' => ['title' => 'Newer']], '2026-10-01 10:00');
        $this->create(['de' => ['title' => 'Entwurf']], '2026-11-01 10:00');

        $list = $this->runner()->ask(new ListEvents('de'));
        self::assertSame(['Entwurf', 'Neuer', 'Älter'], array_map(static fn($item): string => $item->title, $list->items));
        self::assertSame(['de', 'en'], $list->items[1]->languages);
        self::assertSame(EventStatus::Draft, $list->items[0]->status);

        $found = $this->runner()->ask(new ListEvents('de', 'neu'));
        self::assertSame(1, $found->total);
    }

    public function testCategoriesAreCheckedAndDeletedCategoriesDropOut(): void
    {
        $coffee = $this->runner()->run(new CreateCategory(['de' => ['title' => 'Kaffee']]));
        self::assertSame('kaffee', $coffee->slug('de'));

        try {
            $this->runner()->run(new CreateCategory(['de' => ['title' => 'Kaffee']]));
            self::fail('Two categories cannot share a slug.');
        } catch (InvalidInputException $error) {
            self::assertArrayHasKey('/translations/de/slug', $error->errors);
        }

        try {
            $this->create(['de' => ['title' => 'Neue Sorten']], categories: ['01JNOCATEGORY0000000000000']);
            self::fail('Unknown categories are refused.');
        } catch (InvalidInputException $error) {
            self::assertArrayHasKey('/categories/0', $error->errors);
        }

        $id = $this->event(['de' => ['title' => 'Neue Sorten']], categories: [$coffee->id]);
        $this->runner()->run(new DeleteCategory($coffee->id));

        self::assertSame([], $this->get(Categories::class)->all());
        $this->runner()->run(new UpdateEvent($id, ['de' => ['teaser' => 'Neu']]));
        $published = $this->runner()->run(new PublishEvents([$id]));
        self::assertCount(1, $published, 'A deleted category does not keep the event from being published.');
    }

    public function testTheSchedulerPublishesEvents(): void
    {
        $details = $this->create(['de' => ['title' => 'Später']]);
        $this->runner()->run(new ScheduleEvent($details->id, new DateTimeImmutable('+1 hour')));

        $result = $this->get(Scheduler::class)->run(new DateTimeImmutable('+2 hours'));

        self::assertSame([$details->id], $result->published);
        self::assertNotNull($this->get(Events::class)->fetchById($details->id));
    }

    public function testEventsAreFoundAndEditorsMayChangeThem(): void
    {
        $page = $this->page(null, 'Start', [['block' => 'frootbox/events/EventList']]);
        $this->runner()->run(new PublishPages([$page]));
        $id = $this->event(['de' => ['title' => 'Neue Sorten', 'text' => self::text('Geröstet in Hamburg')]]);

        $hits = $this->runner()->ask(new Search('hamburg'));
        self::assertSame(['frootbox/events/event'], array_values(array_unique(array_map(static fn($hit): string => $hit->source, $hits))));
        self::assertSame($id, $hits[0]->item);
        self::assertSame('/neue-sorten', $this->get(Urls::class)->path(EventStore::target($id), 'de'));

        $published = iterator_to_array($this->get(EventSource::class)->published([$id]), false);
        self::assertCount(1, $published, 'The published version, for the search on the website.');
        self::assertSame(['Neue Sorten', 'de', EventStore::target($id)], [$published[0]->title, $published[0]->language, (string) $published[0]->target]);
        self::assertStringContainsString('Geröstet in Hamburg', $published[0]->text);

        $editor = $this->get(Roles::class)->fetchOne(['where' => ['name' => 'editor']]);
        self::assertNotNull($editor);
        self::assertContains('frootbox/events/*', $editor->permissions);
    }

    /**
     * @param array<string, mixed> $translations
     * @param list<string>         $categories
     */
    private function create(array $translations, string $start = '+3 days 18:00', array $categories = []): EventDetails
    {
        return $this->runner()->run(new CreateEvent($translations, new DateTimeImmutable($start), categories: $categories));
    }
}

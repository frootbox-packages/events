<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests;

use DateTimeImmutable;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Undo\UndoAction;
use Frootbox\Core\Cache\PageCache;
use Frootbox\Core\Category\CreateCategory;
use Frootbox\Core\Http\Exception\HttpException;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\Page\UpdatePage;
use Frootbox\Core\Plugin\AssignPlugin;
use Frootbox\Core\Plugin\CreateSpace;
use Frootbox\Core\ProjectPaths;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Events\Blocks\EventList;
use Frootbox\Events\Blocks\EventTeaser;
use Frootbox\Events\Calendar\Calendar;
use Frootbox\Events\Calendar\CalendarHandler;
use Frootbox\Events\Event\EndedEvents;
use Frootbox\Events\Event\EventRendering;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\PublishEvents;
use Frootbox\Events\Event\UnpublishEvent;
use Frootbox\Events\Event\UpdateEvent;
use Frootbox\Events\EventPlugin;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventList::class)]
#[CoversClass(EventTeaser::class)]
#[CoversClass(EventPlugin::class)]
#[CoversClass(EventRendering::class)]
#[CoversClass(Calendar::class)]
#[CoversClass(EndedEvents::class)]
final class WebsiteTest extends EventsTestCase
{
    private string $start;

    private string $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->start = $this->page(null, 'Start', [['block' => 'frootbox/events/EventTeaser', 'options' => ['count' => 2]]]);
        $this->events = $this->eventsPage($this->start, 'Termine', ['perPage' => 2]);
        $this->runner()->run(new PublishPages([$this->start, $this->events]));
    }

    public function testListsTheUpcomingEventsFromTheNextOn(): void
    {
        $this->event(['de' => ['title' => 'Drittes']], '+3 days 10:00');
        $this->event(['de' => ['title' => 'Erstes', 'teaser' => 'Der Anfang.', 'venue' => 'Rösterei']], '+1 day 10:00');
        $this->event(['de' => ['title' => 'Zweites']], '-1 hour', '+1 hour');
        $this->event(['de' => ['title' => 'Vorbei']], '-2 days 10:00');

        $html = $this->html('/termine');
        self::assertMatchesRegularExpression('~<h\d class="events-item__title"><a href="/termine/zweites">Zweites</a></h\d>.*<a href="/termine/erstes">Erstes</a>~s', $html, 'Running events first, then the next; the title links to the event.');
        self::assertStringContainsString('Rösterei', $html);
        self::assertStringNotContainsString('Drittes', $html, 'Two per page.');
        self::assertStringNotContainsString('Vorbei', $html, 'Past events are not upcoming.');
        self::assertStringContainsString('href="/termine?page=2" rel="next"', $html);
        self::assertStringContainsString('<a href="/termine?past=1">Vergangene Termine</a>', $html);

        self::assertStringContainsString('Drittes', $this->html('/termine?page=2'));

        $past = $this->html('/termine?past=1');
        self::assertStringContainsString('Vorbei', $past);
        self::assertStringNotContainsString('Erstes', $past);
        self::assertStringContainsString('<a href="/termine">Kommende Termine</a>', $past);
    }

    public function testShowsTimesInTheWordsOfTheLanguage(): void
    {
        $this->event(['de' => ['title' => 'Kurs']], '2030-03-04 18:00', '2030-03-04 20:00');
        $this->event(['de' => ['title' => 'Fest']], '2030-03-09', '2030-03-10', true);

        $html = $this->html('/termine');

        self::assertStringContainsString('<time datetime="2030-03-04T18:00:00', $html);
        self::assertStringContainsString('4. März 2030 um 18:00 – 20:00', $html);
        self::assertStringContainsString('<time datetime="2030-03-09">9. März 2030 – 10. März 2030</time>', $html, 'All day long without times.');
        self::assertMatchesRegularExpression('~<span class="events-item__number">4</span> <span class="events-item__month">März?\.?</span>~u', $html);
    }

    public function testFiltersByCategory(): void
    {
        $courses = $this->runner()->run(new CreateCategory(EventStore::CATEGORIES, ['de' => ['title' => 'Kurse']]));
        $barista = $this->runner()->run(new CreateCategory(EventStore::CATEGORIES, ['de' => ['title' => 'Barista']], $courses->id));
        $this->runner()->run(new CreateCategory(EventStore::CATEGORIES, ['de' => ['title' => 'Feste']]));
        $this->event(['de' => ['title' => 'Röstkurs']], categories: [$courses->id]);
        $this->event(['de' => ['title' => 'Latte Art']], '+30 days 18:00', categories: [$barista->id]);
        $this->event(['de' => ['title' => 'Hoffest']]);

        $html = $this->html('/termine?category=kurse');
        self::assertStringContainsString('Latte Art', $html, 'A category counts with all below it.');
        self::assertStringContainsString('<li data-depth="1"><a class="events-filter__link" href="/termine?category=barista">Barista</a></li>', $html);

        self::assertStringContainsString('Röstkurs', $html);
        self::assertStringNotContainsString('Hoffest', $html);
        self::assertStringContainsString('<a class="events-filter__link" href="/termine?category=kurse" aria-current="page">Kurse</a>', $html);
        self::assertStringContainsString('<a class="events-filter__link" href="/termine">Alle</a>', $html);
        self::assertStringContainsString('<a href="/termine?category=kurse&amp;past=1">Vergangene Termine</a>', $html, 'The past ones keep the category.');
        self::assertStringContainsString('Hoffest', $this->html('/termine?category=unbekannt'), 'An unknown category shows all.');
    }

    public function testCategoriesHavePagesOfTheirOwnWithTheSwitch(): void
    {
        $courses = $this->runner()->run(new CreateCategory(EventStore::CATEGORIES, ['de' => ['title' => 'Kurse', 'description' => 'Lernen an der Maschine.']]));
        $barista = $this->runner()->run(new CreateCategory(EventStore::CATEGORIES, ['de' => ['title' => 'Barista']], $courses->id));
        $this->event(['de' => ['title' => 'Röstkurs']], categories: [$courses->id]);
        $this->event(['de' => ['title' => 'Latte Art']], '+30 days 18:00', categories: [$barista->id]);
        $this->event(['de' => ['title' => 'Hoffest']]);
        $this->runner()->run(new AssignPlugin($this->events, EventStore::TYPE, categories: true));
        $this->runner()->run(new PublishPages([$this->events]));

        $html = $this->html('/termine/kurse');
        self::assertMatchesRegularExpression('~<h1 class="events-list__title">Kurse</h1>~', $html, 'The category owns the h1 (decision 0243).');
        self::assertStringContainsString('<p class="events-list__intro">Lernen an der Maschine.</p>', $html);
        self::assertStringContainsString('Latte Art', $html, 'With the ones below it.');
        self::assertStringNotContainsString('Hoffest', $html);
        self::assertStringContainsString('<a class="events-filter__link" href="/termine/kurse/barista">Barista</a>', $html);
        self::assertStringContainsString('<a class="events-filter__link" href="/termine">Alle</a>', $html);
        self::assertStringContainsString('<a href="/termine/kurse?past=1">Vergangene Termine</a>', $html, 'The past ones stay on the page of the category.');
        self::assertStringContainsString('href="/termine/kurse/barista">Barista</a>', $this->html('/termine/latte-art'), 'The categories of an event too.');
    }

    public function testFiltersByTagsOfAClosedList(): void
    {
        // The events have their own presets, a closed list (decision 0239).
        file_put_contents($this->get(ProjectPaths::class)->config('tags.yaml'), "tags: [Nachhaltigkeit]\ntypes:\n  frootbox/events/event: { tags: [Kurs, Fest], closed: true }\n");
        $this->event(['de' => ['title' => 'Röstkurs']], tags: ['kurs']);
        $this->event(['de' => ['title' => 'Hoffest']], '+30 days 18:00', tags: ['Fest', 'Nachhaltigkeit']);

        try {
            $this->event(['de' => ['title' => 'Zufall']], tags: ['Zufall']);
            self::fail('Only the presets count.');
        } catch (InvalidInputException $exception) {
            self::assertSame(['/tags/0'], array_keys($exception->errors));
        }

        $html = $this->html('/termine?tag=fest');
        self::assertStringContainsString('Hoffest', $html);
        self::assertStringNotContainsString('Röstkurs', $html);
        self::assertStringContainsString('Termine mit dem Schlagwort „Fest“ · <a href="/termine">Alle Termine</a>', $html);
        self::assertStringContainsString('<a href="/termine?tag=fest&amp;past=1">Vergangene Termine</a>', $html, 'The past ones keep the tag.');
        self::assertStringContainsString('<li><a class="events-tags__link" href="/termine?tag=fest">Fest</a></li><li><a class="events-tags__link" href="/termine?tag=nachhaltigkeit">Nachhaltigkeit</a></li>', $this->html('/termine/hoffest'));

        $chosen = $this->page($this->start, 'Kurse', [['block' => 'frootbox/events/EventTeaser', 'options' => ['tag' => 'kurs']]]);
        $this->runner()->run(new PublishPages([$chosen]));
        $teaser = $this->html('/kurse');
        self::assertStringContainsString('Röstkurs', $teaser);
        self::assertStringNotContainsString('Hoffest', $teaser);
    }

    public function testShowsAnEventAtItsAddress(): void
    {
        $id = $this->event(['de' => ['title' => 'Röstkurs', 'teaser' => 'Vier Stunden.', 'venue' => 'Rösterei', 'text' => self::text('Mit eigener Charge.')]], '2030-03-04 10:00', '2030-03-04 14:00', address: 'Hafenstraße 12, 20457 Hamburg', coordinates: '53.5444, 9.9663');

        $html = $this->html('/termine/roestkurs');

        self::assertStringContainsString('<title>Röstkurs – Testsite</title>', $html);
        self::assertStringContainsString('<meta name="description" content="Vier Stunden.">', $html, 'The teaser describes the event.');
        self::assertMatchesRegularExpression('~<h\d class="events-event__title">Röstkurs</h\d>~', $html);
        self::assertStringContainsString('4. März 2030 um 10:00 – 14:00', $html);
        self::assertStringContainsString('<p class="events-event__place">Rösterei<br>Hafenstraße 12, 20457 Hamburg</p>', $html);
        self::assertStringContainsString('<p>Mit eigener Charge.</p>', $html);
        self::assertStringContainsString('<fb-external class="external" service="openstreetmap"', $html, 'The map waits for consent.');
        self::assertStringContainsString('<a href="/_events/calendar/' . $id . '/de.ics" download>In den Kalender eintragen</a>', $html);
        self::assertStringContainsString('<a href="/termine">Alle Termine</a>', $html);
        self::assertSame(1, preg_match('~<script type="application/ld\+json">(\{"@context":"https://schema.org","@type":"Event".*?)</script>~', $html, $match));
        $data = json_decode($match[1], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('Röstkurs', $data['name']);
        self::assertSame('https://example.org/termine/roestkurs', $data['url']);
        self::assertIsString($data['startDate'] ?? null);
        self::assertStringStartsWith('2030-03-04T10:00:00', $data['startDate']);
        self::assertSame(['@type' => 'Place', 'name' => 'Rösterei', 'address' => 'Hafenstraße 12, 20457 Hamburg', 'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 53.5444, 'longitude' => 9.9663]], $data['location']);
        self::assertStringNotContainsString('events-list', $html, 'Not the list.');
        self::assertSame(404, $this->statusOf('/termine/anderer-kurs'));

        $this->runner()->run(new UpdateEvent($id, ['de' => ['slug' => 'kurs']]));
        $this->runner()->run(new PublishEvents([$id]));
        self::assertSame(['/termine/kurs', 301], $this->redirect('/termine/roestkurs'));

        $this->runner()->run(new UpdatePage($this->events, ['de' => ['slug' => 'kalender']]));
        $this->runner()->run(new PublishPages([$this->events]));
        self::assertSame(['/kalender/kurs', 301], $this->redirect('/termine/kurs'));

        $this->runner()->run(new UnpublishEvent($id));
        self::assertSame(410, $this->statusOf('/kalender/kurs'));
    }

    public function testPublishingCanBeUndone(): void
    {
        $id = $this->event(['de' => ['title' => 'Röstkurs']], publish: false);
        $this->runner()->run(new PublishEvents([$id]));
        self::assertSame(200, $this->statusOf('/termine/roestkurs'));

        $this->runner()->run(new UndoAction($this->lastEntry()->id));

        self::assertSame(404, $this->statusOf('/termine/roestkurs'), 'The address is gone again.');
        self::assertStringNotContainsString('Röstkurs', $this->html('/termine'));
    }

    public function testTheTeaserShowsTheNextEvents(): void
    {
        $this->event(['de' => ['title' => 'Später']], '+9 days 10:00');
        $this->event(['de' => ['title' => 'Bald']], '+2 days 10:00');
        $this->event(['de' => ['title' => 'Morgen']], '+1 day 10:00');
        $this->event(['de' => ['title' => 'Gestern']], '-1 day 10:00');

        $html = $this->html('/');

        self::assertMatchesRegularExpression('~Morgen.*Bald~s', $html);
        self::assertStringNotContainsString('Später', $html);
        self::assertStringNotContainsString('Gestern', $html);
        self::assertStringContainsString('<a href="/termine">Alle Termine</a>', $html);
    }

    public function testOffersACalendarFile(): void
    {
        $id = $this->event(['de' => ['title' => 'Röstkurs; mit Kaffee, Kuchen', 'teaser' => 'Vier Stunden.', 'venue' => 'Rösterei']], '2030-03-04T10:00:00+01:00', '2030-03-04T14:00:00+01:00', address: 'Hafenstraße 12, 20457 Hamburg', coordinates: '53.5444, 9.9663');
        $days = $this->event(['de' => ['title' => 'Fest']], '2030-03-09', '2030-03-10', true);

        $response = $this->calendar($id, 'de');
        $ics = (string) $response->getBody();

        self::assertSame('text/calendar; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
        self::assertStringContainsString("\r\nUID:" . $id . "@example.org\r\n", $ics);
        self::assertStringContainsString("\r\nDTSTART:20300304T090000Z\r\nDTEND:20300304T130000Z\r\n", $ics, 'In UTC.');
        self::assertStringContainsString("\r\nSUMMARY:Röstkurs\; mit Kaffee\\, Kuchen\r\n", $ics, 'Escaped as RFC 5545 wants.');
        self::assertStringContainsString("\r\nLOCATION:Rösterei\\, Hafenstraße 12\\, 20457 Hamburg\r\n", $ics);
        self::assertStringContainsString("\r\nGEO:53.5444;9.9663\r\n", $ics);
        self::assertStringContainsString("\r\nURL:https://example.org/termine/roestkurs-mit-kaffee-kuchen\r\n", $ics);
        self::assertStringEndsWith("END:VEVENT\r\nEND:VCALENDAR\r\n", $ics);

        self::assertStringContainsString("\r\nDTSTART;VALUE=DATE:20300309\r\nDTEND;VALUE=DATE:20300311\r\n", (string) $this->calendar($days, 'de')->getBody(), 'The end of a day is the start of the next.');

        $this->expectException(HttpException::class);
        $this->calendar($id, 'fr');
    }

    public function testLongLinesOfTheCalendarAreFolded(): void
    {
        $id = $this->event(['de' => ['title' => 'Kurs', 'teaser' => str_repeat('Röstkurs mit Kaffee ', 10)]], '2030-03-04 10:00');

        foreach (explode("\r\n", (string) $this->calendar($id, 'de')->getBody()) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line), $line);
            self::assertTrue(mb_check_encoding($line, 'UTF-8'), 'No character is cut in two.');
        }
    }

    public function testClearsThePageCacheWhenAnEventIsOver(): void
    {
        $this->event(['de' => ['title' => 'Kurs']], '2030-03-04 10:00', '2030-03-04 12:00');
        $cache = $this->get(PageCache::class);
        $request = new ServerRequest('GET', 'https://example.org/termine');
        $listener = $this->get(EndedEvents::class);
        @unlink($this->get(ProjectPaths::class)->var(EndedEvents::FILE));

        $cache->put($request, 'list');
        $listener(new ScheduleRan(new DateTimeImmutable('2030-03-04 11:00')));
        self::assertSame('list', $cache->get($request), 'The first look only remembers the time.');

        $listener(new ScheduleRan(new DateTimeImmutable('2030-03-04 11:59')));
        self::assertSame('list', $cache->get($request), 'Still running.');

        $listener(new ScheduleRan(new DateTimeImmutable('2030-03-04 12:01')));
        self::assertNull($cache->get($request), 'Over: the lists are out of date.');
    }

    private function calendar(string $id, string $language): \Psr\Http\Message\ResponseInterface
    {
        return $this->get(CalendarHandler::class)->handle(new ServerRequest('GET', 'https://example.org/_events/calendar/' . $id . '/' . $language . '.ics')->withAttribute('id', $id)->withAttribute('language', $language));
    }

    public function testASpaceHasItsEventsBelowItsOwnPage(): void
    {
        $courses = $this->runner()->run(new CreateSpace(EventStore::TYPE, 'Kurse'))->id;
        $page = $this->eventsPage($this->start, 'Kurse', space: $courses);
        $this->runner()->run(new PublishPages([$page]));
        $this->event(['de' => ['title' => 'Röstkurs']], space: $courses);
        $this->event(['de' => ['title' => 'Hoffest']]);

        self::assertSame(200, $this->statusOf('/kurse/roestkurs'));
        self::assertSame(404, $this->statusOf('/termine/roestkurs'), 'Not below the page of another space.');
        self::assertStringNotContainsString('Hoffest', $this->html('/kurse'), 'Only the events of its space.');
        self::assertStringContainsString('href="/kurse/roestkurs"', $this->html('/'), 'A teaser over all spaces links each event below its page.');
    }
}

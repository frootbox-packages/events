<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests;

use Frootbox\Backend\Tests\Support\BackendTestCase;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\User\CreateRole;
use Frootbox\Events\Backend\EventHandler;
use Frootbox\Events\Backend\EventsHandler;
use Frootbox\Events\Event\Events;
use Frootbox\Events\Event\EventTranslations;
use Frootbox\Events\EventsExtension;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventsHandler::class)]
#[CoversClass(EventHandler::class)]
#[CoversClass(EventsExtension::class)]
final class BackendTest extends BackendTestCase
{
    protected function tables(): array
    {
        return [EventTranslations::TABLE, Events::TABLE, 'frootbox_events_event_categories', 'frootbox_events_categories'];
    }

    protected function extensions(): Extensions
    {
        $events = ExtensionPackage::fromManifest('frootbox/events', (string) realpath(EventsTestCase::ROOT), psr4: ['Frootbox\Events\\' => ['src/'], 'Frootbox\Events\Migrations\\' => ['migrations/']]);

        return new Extensions([self::backend(), $events], 'events-backend');
    }

    protected function config(): array
    {
        return ['languages' => ['de' => ['title' => 'Deutsch'], 'en' => ['title' => 'English']]];
    }

    public function testEditorsWriteAndPublishEvents(): void
    {
        $this->createUser('editor@example.org', ['editor']);
        $session = $this->signIn('editor@example.org');

        $list = $this->html($this->request('GET', '/cms/events', session: $session));
        self::assertStringContainsString('href="/cms/events" aria-current="page"', $list, 'The sidebar has the screen.');
        self::assertStringContainsString('href="/cms/events/new"', $list);
        self::assertStringContainsString('Noch keine Termine', $list);

        $new = $this->html($this->request('GET', '/cms/events/new', session: $session));
        self::assertStringContainsString('<fb-form action="frootbox/events/event.create" next="/cms/events/{id}"', $new);
        self::assertStringContainsString('<fb-rich-text', $new);
        self::assertStringContainsString('<fb-image-input', $new);
        self::assertStringContainsString('src="/cms/queries/media.list"', $new);
        self::assertStringContainsString('name="start"', $new);
        self::assertStringContainsString('name="allDay"', $new);
        self::assertStringContainsString('name="translations.de.venue"', $new);

        $category = $this->id($this->action('category.create', ['group' => 'frootbox/events/categories', 'translations' => ['de' => ['title' => 'Kurse'], 'en' => ['title' => '', 'slug' => '']]], $session));
        $created = $this->action('frootbox/events/event.create', [
            'translations' => ['de' => ['title' => 'Röstkurs', 'venue' => 'Rösterei', 'teaser' => 'Vier Stunden.', 'text' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Fruchtig.']]]]]]],
            'start' => '2030-03-04T09:00:00.000Z',
            'end' => null,
            'allDay' => false,
            'address' => 'Bahrenfelder Straße 80, 22765 Hamburg',
            'coordinates' => '53.5562, 9.9285',
            'image' => [],
            'categories' => [$category],
        ], $session);
        $id = $this->id($created);

        $show = $this->html($this->request('GET', '/cms/events/' . $id, session: $session));
        self::assertStringContainsString('<h1 class="fb-heading-1" tabindex="-1" data-fb-focus>Röstkurs</h1>', $show);
        self::assertStringContainsString('name="translations.de.slug"', $show);
        self::assertStringContainsString('value="roestkurs"', $show);
        self::assertStringContainsString('value="Rösterei"', $show);
        self::assertStringContainsString('value="53.5562, 9.9285"', $show);
        self::assertStringContainsString('&quot;text&quot;:&quot;Fruchtig.&quot;', $show, 'The text goes to <fb-rich-text> as JSON.');
        self::assertMatchesRegularExpression('/name="categories\[\]"[^>]*value="' . $category . '"[^>]*checked/', $show);
        self::assertStringContainsString('<fb-form action="frootbox/events/event.publish"', $show);
        self::assertStringContainsString('commandfor="event-discard"', $show);
        self::assertStringNotContainsString('commandfor="event-unpublish"', $show, 'A draft cannot go offline.');

        $english = $this->html($this->request('GET', '/cms/events/' . $id . '?language=en', session: $session));
        self::assertStringContainsString('Noch keine Fassung in English', $english);
        self::assertStringContainsString('name="translations.en.title" value="Röstkurs"', $english);

        $this->action('frootbox/events/event.publish', ['ids' => [$id]], $session);
        $published = $this->html($this->request('GET', '/cms/events/' . $id, session: $session));
        self::assertStringNotContainsString('<fb-form action="frootbox/events/event.publish"', $published);
        self::assertStringContainsString('commandfor="event-unpublish"', $published);

        $list = $this->html($this->request('GET', '/cms/events', session: $session));
        self::assertStringContainsString('href="/cms/events/' . $id . '">Röstkurs</a>', $list);
        self::assertStringNotContainsString('Röstkurs', $this->html($this->request('GET', '/cms/events?q=konzert', session: $session)));

        self::assertStringContainsString('href="/cms/categories?group=frootbox/events/categories"', $this->html($this->request('GET', '/cms/events', session: $session)), 'The categories are those of the core.');
        $categories = $this->html($this->request('GET', '/cms/categories?group=frootbox/events/categories', session: $session));
        self::assertStringContainsString('data-fb-focus>Kategorien der Termine</h2>', $categories);
        self::assertStringContainsString('?category=kurse', $categories);
    }

    public function testAnEmptyLanguageOfACategoryIsNone(): void
    {
        $this->createUser('editor@example.org', ['editor']);
        $session = $this->signIn('editor@example.org');

        $created = $this->action('category.create', ['group' => 'frootbox/events/categories', 'translations' => ['de' => ['title' => 'Kurse'], 'en' => ['title' => 'Courses']]], $session);
        $updated = $this->action('category.update', ['id' => $this->id($created), 'translations' => ['de' => ['title' => 'Kurse', 'slug' => 'kurse'], 'en' => ['title' => '', 'slug' => '']]], $session);
        $translations = $this->data($updated, 'translations');
        self::assertIsArray($translations);
        self::assertSame(['de'], array_keys($translations));

        $error = $this->action('category.create', ['group' => 'frootbox/events/categories', 'translations' => ['de' => ['title' => '', 'slug' => '']]], $session, 422);
        self::assertStringContainsString('"pointer":"\\/translations\\/de\\/title"', json_encode($error, \JSON_THROW_ON_ERROR));
    }

    public function testOthersSeeNoEvents(): void
    {
        $this->runner()->run(new CreateRole('guest-writer', 'Writer', ['page.tree']), Context::system());
        $this->createUser('writer@example.org', ['guest-writer']);
        $session = $this->signIn('writer@example.org');

        self::assertStringNotContainsString('href="/cms/events"', $this->html($this->request('GET', '/cms', session: $session)));
        self::assertSame(403, $this->request('GET', '/cms/events', session: $session)->getStatusCode());
    }

    /**
     * @param array<mixed> $answer
     */
    private function id(array $answer): string
    {
        $id = $this->data($answer, 'id');
        self::assertIsString($id);

        return $id;
    }

    /**
     * @param array<mixed> $answer
     *
     * @return mixed a value of the result
     */
    private function data(array $answer, string $key): mixed
    {
        self::assertIsArray($answer['data'] ?? null);

        return $answer['data'][$key] ?? null;
    }
}

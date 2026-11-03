<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests;

use DateTimeImmutable;
use Frootbox\Core\Config\Environment;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Http\Exception\HttpException;
use Frootbox\Core\Page\CreatePage;
use Frootbox\Core\Page\PageHandler;
use Frootbox\Core\Page\UpdateBlock;
use Frootbox\Core\Plugin\AssignPlugin;
use Frootbox\Core\Plugin\Space;
use Frootbox\Core\Tests\Support\FixtureSite;
use Frootbox\Core\Tests\Support\NotesTestCase;
use Frootbox\Events\Category\Categories;
use Frootbox\Events\Event\CreateEvent;
use Frootbox\Events\Event\EventCategories;
use Frootbox\Events\Event\Events;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventTranslations;
use Frootbox\Events\Event\PublishEvents;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

/**
 * A website of the core fixtures with the events installed.
 */
abstract class EventsTestCase extends NotesTestCase
{
    public const string ROOT = __DIR__ . '/..';

    protected function tables(): array
    {
        return [EventCategories::TABLE, EventTranslations::TABLE, Events::TABLE, Categories::TABLE];
    }

    protected function environment(): Environment
    {
        return Environment::Production;
    }

    protected function extensions(): Extensions
    {
        $events = ExtensionPackage::fromManifest('frootbox/events', (string) realpath(self::ROOT), psr4: ['Frootbox\Events\\' => ['src/'], 'Frootbox\Events\Migrations\\' => ['migrations/']]);

        return new Extensions([...FixtureSite::extensions('base', 'site')->packages, $events], 'events');
    }

    protected function config(): array
    {
        return ['app' => ['name' => 'Testsite', 'url' => 'https://example.org'], 'theme' => 'acme/site', 'languages' => ['de' => ['title' => 'Deutsch'], 'en' => ['title' => 'English']]];
    }

    /**
     * A page, with the given blocks in its main area.
     *
     * @param list<array<string, mixed>> $blocks
     */
    protected function page(?string $parentId, string $title, array $blocks = [], string $language = 'de'): string
    {
        $content = ['fields' => ['heading' => $title], 'areas' => ['main' => $blocks]];

        return $this->runner()->run(new CreatePage('acme/base/Page', [$language => ['title' => $title, 'content' => $content]], $parentId))->id;
    }

    /**
     * A page that shows the events (decision 0232), with the options of
     * the view of the plugin.
     *
     * @param array<string, mixed> $options
     */
    protected function eventsPage(?string $parentId, string $title, array $options = [], string $space = Space::DEFAULT): string
    {
        $id = $this->page($parentId, $title);
        $details = $this->runner()->run(new AssignPlugin($id, EventStore::TYPE, $space));

        if ([] !== $options) {
            foreach ($details->draft->translations ?? [] as $language => $translation) {
                $view = $translation->content['plugin'] ?? null;

                if (\is_array($view) && \is_string($view['id'] ?? null)) {
                    $this->runner()->run(new UpdateBlock($id, $language, $view['id'], options: $options));
                }
            }
        }

        return $id;
    }

    /**
     * A published event, by default in three days.
     *
     * @param array<string, mixed> $translations
     * @param list<string>         $categories
     */
    protected function event(array $translations, string $start = '+3 days 18:00', ?string $end = null, bool $allDay = false, array $categories = [], string $address = '', string $coordinates = '', bool $publish = true, string $space = Space::DEFAULT): string
    {
        $details = $this->runner()->run(new CreateEvent($translations, new DateTimeImmutable($start), null === $end ? null : new DateTimeImmutable($end), $allDay, $address, $coordinates, categories: $categories, space: $space));

        if ($publish) {
            $this->runner()->run(new PublishEvents([$details->id]));
        }

        return $details->id;
    }

    protected function request(string $path): ResponseInterface
    {
        return $this->get(PageHandler::class)->handle(new ServerRequest('GET', 'https://example.org' . $path));
    }

    protected function html(string $path): string
    {
        return (string) $this->request($path)->getBody();
    }

    protected function statusOf(string $path): int
    {
        try {
            return $this->request($path)->getStatusCode();
        } catch (HttpException $error) {
            return $error->status;
        }
    }

    /**
     * @return array{string, int}
     */
    protected function redirect(string $path): array
    {
        $response = $this->request($path);

        return [$response->getHeaderLine('Location'), $response->getStatusCode()];
    }

    /**
     * Rich text with one paragraph.
     *
     * @return array<string, mixed>
     */
    protected static function text(string $paragraph): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $paragraph]]]]];
    }
}

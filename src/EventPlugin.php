<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events;

use Frootbox\Core\Plugin\Offer;
use Frootbox\Core\Plugin\Plugin;
use Frootbox\Events\Event\Events;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventTranslations;

/**
 * The events as a plugin (decision 0232): a page that shows a space lists
 * its events at plugin(), each published event has its address below
 * that page, its slug. The teaser and the image describe it for search
 * engines and sharing (decision 0223).
 */
final readonly class EventPlugin implements Plugin
{
    public const string LIST_BLOCK = 'frootbox/events/EventList';

    public function __construct(
        private EventTranslations $translations,
        private Events $events,
        private EventStore $store,
    ) {}

    public function type(): string
    {
        return EventStore::TYPE;
    }

    public function label(): string
    {
        return 'Events';
    }

    public function block(): string
    {
        return self::LIST_BLOCK;
    }

    public function details(): Offer
    {
        return Offer::Required;
    }

    public function categories(): Offer
    {
        return Offer::Optional;
    }

    public function spaces(): bool
    {
        return true;
    }

    public function paths(string $space): iterable
    {
        $ids = [];

        foreach ($this->events->fetch(['where' => ['space' => $space]]) as $event) {
            $ids[] = $event->id;
        }

        if ([] === $ids) {
            return;
        }

        $paths = [];

        foreach ($this->translations->fetch(['where' => ['eventId' => $ids], 'order' => 'id']) as $translation) {
            $paths[$translation->eventId][$translation->language] = $translation->slug;
        }

        yield from $paths;
    }

    public function count(string $space): int
    {
        return \count(array_filter($this->store->ids(), fn(string $id): bool => $this->store->current($id)->space === $space));
    }

    public function title(string $id, string $language): ?string
    {
        return $this->translations->fetchOne(['where' => ['eventId' => $id, 'language' => $language]])?->title;
    }

    public function description(string $id, string $language): ?string
    {
        return $this->translations->fetchOne(['where' => ['eventId' => $id, 'language' => $language]])?->teaser;
    }

    public function image(string $id, string $language): ?array
    {
        $image = $this->events->fetchById($id)?->image;
        $media = $image['media'] ?? null;

        if (!\is_string($media) || '' === $media) {
            return null;
        }

        $alt = $image['alt'] ?? null;

        return \is_string($alt) ? ['media' => $media, 'alt' => $alt] : ['media' => $media];
    }
}

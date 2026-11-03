<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events;

use Frootbox\Core\Url\DescribedSubpages;
use Frootbox\Events\Event\Events;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventTranslations;

/**
 * Each published event has an address below the first page with the
 * list of events, its slug (decision 0219). The teaser and the image
 * describe it for search engines and sharing (decision 0223).
 */
final readonly class EventPages implements DescribedSubpages
{
    public const string LIST_BLOCK = 'frootbox/events/EventList';

    public function __construct(
        private EventTranslations $translations,
        private Events $events,
    ) {}

    public function type(): string
    {
        return EventStore::TYPE;
    }

    public function blocks(): array
    {
        return [self::LIST_BLOCK];
    }

    public function paths(): iterable
    {
        $paths = [];

        foreach ($this->translations->fetch(['order' => 'id']) as $translation) {
            $paths[$translation->eventId][$translation->language] = $translation->slug;
        }

        yield from $paths;
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

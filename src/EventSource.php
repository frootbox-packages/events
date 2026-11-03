<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events;

use Frootbox\Core\Revision\Revisions;
use Frootbox\Core\Search\AsSearchSource;
use Frootbox\Core\Search\SearchEntry;
use Frootbox\Core\Search\SearchSource;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Events\Event\EventStore;
use Frootbox\RichText\RichText;

/**
 * Events in the search of the backend, as editors see them:
 * drafts first (decision 0217).
 */
#[AsSearchSource('frootbox/events/event', 'Events')]
final readonly class EventSource implements SearchSource
{
    public function __construct(
        private EventStore $store,
        private RichText $richText,
    ) {}

    public function changed(string $table, array $row): array
    {
        $id = Revisions::TABLE === $table && EventStore::TYPE === ($row['type'] ?? null) ? $row['entity_id'] ?? null : null;

        return \is_string($id) ? [$id] : [];
    }

    public function items(): iterable
    {
        yield from $this->store->ids();
    }

    public function entries(array $items): iterable
    {
        foreach ($items as $id) {
            try {
                $data = $this->store->details($id)->current();
            } catch (EntityNotFoundException) {
                continue;
            }

            foreach ($data->translations as $language => $text) {
                yield new SearchEntry(
                    item: $id,
                    title: '' === $text->title ? $id : $text->title,
                    text: implode("\n", array_filter([$text->teaser, $text->venue, $this->richText->toText($text->text)], static fn(string $part): bool => '' !== trim($part))),
                    permission: 'frootbox/events/event.show',
                    language: $language,
                );
            }
        }
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Core\Language\Languages;

/**
 * @implements Query<EventList>
 */
#[AsQuery('frootbox/events/event.list', 'Lists the events with drafts and published ones, the latest start first')]
final readonly class ListEvents implements Query
{
    public function __construct(
        #[Description('Language of the titles; left out for the first language of the website')]
        public ?string $language = null,
        #[Description('Only events whose title holds this text')]
        #[Length(max: 100)]
        public ?string $search = null,
        #[Range(min: 1)]
        public int $page = 1,
        #[Range(min: 1, max: 200)]
        public int $perPage = 50,
    ) {}

    public function handle(EventStore $store, Languages $languages): EventList
    {
        $language = null !== $this->language && $languages->has($this->language) ? $this->language : $languages->default()->code;
        $search = mb_strtolower(trim($this->search ?? ''));
        $items = [];

        foreach ($store->ids() as $id) {
            $details = $store->details($id);
            $data = $details->current();
            $text = $data->translations[$language] ?? ($data->translations === [] ? null : array_values($data->translations)[0]);
            $title = $text->title ?? '';

            if ('' !== $search && !str_contains(mb_strtolower($title), $search)) {
                continue;
            }

            $items[] = new EventSummary($id, $details->status, $title, $data->start, $data->allDay, array_keys($data->translations), $details->publishAt, $details->unpublishAt);
        }

        usort($items, static fn(EventSummary $a, EventSummary $b): int => [$b->start, $b->id] <=> [$a->start, $a->id]);
        $pages = max(1, (int) ceil(\count($items) / $this->perPage));

        return new EventList(\array_slice($items, ($this->page - 1) * $this->perPage, $this->perPage), \count($items), $this->page, $pages);
    }
}

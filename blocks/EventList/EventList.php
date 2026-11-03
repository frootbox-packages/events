<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Blocks;

use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Url\Urls;
use Frootbox\Events\Category\Categories;
use Frootbox\Events\Event\EventRendering;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventView;
use Frootbox\Events\Event\PublishedEvents;

/**
 * The upcoming events from the next on, page by page and by category, or
 * the past ones. Below the first page with this block each event has its
 * address, there the block shows the event instead (decision 0227).
 */
final readonly class EventList implements BlockController
{
    public function __construct(
        private PublishedEvents $events,
        private Categories $categories,
        private EventRendering $rendering,
        private Urls $urls,
    ) {}

    public function data(BlockContext $context): array
    {
        $language = $context->contentLanguage;
        $base = null === $context->target ? '' : $this->urls->href($context->target, $context->language) ?? '';
        $subpage = $context->subpage;

        if (null !== $subpage && EventStore::TYPE === $subpage->target->type) {
            $event = $this->events->find($subpage->target->id, $language);

            if (null !== $event) {
                return ['event' => $this->withLinks($this->rendering->detail($event, $context->language), $base), 'back' => $base];
            }
        }

        $category = self::text($context->query('category'));
        $category = null === $category ? null : $this->categories->bySlug($language, $category);
        $past = '1' === $context->query('past') && false !== ($context->options['past'] ?? true);
        $perPage = max(1, min(50, is_numeric($context->options['perPage'] ?? null) ? (int) $context->options['perPage'] : 10));
        $total = $this->events->count($language, $category?->id, $past);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($context->query('page') ?? 1)));
        $given = static fn(?string $value): bool => null !== $value;
        $filter = array_filter(['category' => $category?->slug($language), 'past' => $past ? '1' : null], $given);
        $link = static fn(array $query): string => $base . ([] === $query ? '' : '?' . http_build_query($query));
        $images = true === ($context->options['images'] ?? false);
        $filters = [];

        if (false !== ($context->options['categories'] ?? true)) {
            foreach ($this->categories->all() as $item) {
                $slug = $item->slug($language);
                $title = $item->title($language);

                if (null !== $slug && null !== $title) {
                    $filters[] = ['title' => $title, 'href' => $link(array_filter(['category' => $slug, 'past' => $filter['past'] ?? null], $given)), 'current' => $item->id === $category?->id];
                }
            }
        }

        $categoryOnly = array_filter(['category' => $filter['category'] ?? null], $given);

        return [
            'events' => array_map(fn(EventView $event): array => $this->withLinks($this->rendering->teaser($event, $context->language, $images), $base), $this->events->list($language, $category?->id, $past, $perPage, ($page - 1) * $perPage)),
            'filters' => [] === $filters ? [] : [['title' => null, 'href' => $link(array_filter(['past' => $filter['past'] ?? null], $given)), 'current' => null === $category], ...$filters],
            'filtered' => null !== $category,
            'past' => $past,
            // The other half: past events from the upcoming ones and back.
            'switch' => false === ($context->options['past'] ?? true) ? null : $link($past ? $categoryOnly : [...$categoryOnly, 'past' => '1']),
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'previous' => $page > 1 ? $link([...$filter, ...($page > 2 ? ['page' => $page - 1] : [])]) : null,
                'next' => $page < $pages ? $link([...$filter, 'page' => $page + 1]) : null,
            ],
        ];
    }

    /**
     * The categories of an event link to the list filtered by them.
     *
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    private function withLinks(array $event, string $base): array
    {
        $categories = [];

        foreach (\is_array($event['categories'] ?? null) ? $event['categories'] : [] as $category) {
            if (\is_array($category) && \is_string($category['slug'] ?? null)) {
                $categories[] = [...$category, 'href' => $base . '?' . http_build_query(['category' => $category['slug']])];
            }
        }

        return ['categories' => $categories] + $event;
    }

    private static function text(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}

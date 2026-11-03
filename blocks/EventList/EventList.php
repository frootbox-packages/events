<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Blocks;

use Frootbox\Core\Category\ObjectCategories;
use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Plugin\CategoryPages;
use Frootbox\Core\Plugin\Space;
use Frootbox\Core\Tag\ObjectTags;
use Frootbox\Core\Url\Urls;
use Frootbox\Events\Event\EventRendering;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventView;
use Frootbox\Events\Event\PublishedEvents;

/**
 * The upcoming events from the next on, page by page, by category and
 * tag (decision 0239), or
 * the past ones, of the space the page shows: the view of the events at
 * plugin() (decision 0232). Below the page each event has its address,
 * there the view shows the event.
 */
final readonly class EventList implements BlockController
{
    public function __construct(
        private PublishedEvents $events,
        private ObjectCategories $categories,
        private ObjectTags $tags,
        private EventRendering $rendering,
        private Urls $urls,
        private CategoryPages $pages,
    ) {}

    public function data(BlockContext $context): array
    {
        $language = $context->contentLanguage;
        $base = null === $context->target ? '' : $this->urls->href($context->target, $context->language) ?? '';
        $subpage = $context->subpage;
        $spaces = [$context->plugin->space ?? Space::DEFAULT];

        if (null !== $subpage && EventStore::TYPE === $subpage->target->type) {
            $event = $this->events->find($subpage->target->id, $language);

            if (null !== $event) {
                return ['event' => $this->withLinks($this->rendering->detail($event, $context->language), $base, $context), 'back' => $base];
            }
        }

        // A category counts with all below it (decision 0233); on the page of a category it is that one (decision 0243).
        $own = $this->pages->current($context);
        $slug = null === $own ? self::text($context->query('category')) : null;
        [$category, $chosen] = $own ?? (null === $slug ? null : $this->categories->bySlug(EventStore::TYPE, $language, $slug)) ?? [null, null];
        $here = null === $own || null === $subpage ? $base : $this->urls->href($subpage->target, $context->language) ?? $base;
        // Like a category, an unknown tag filters nothing.
        $tagSlug = self::text($context->query('tag'));
        $tag = null === $tagSlug ? null : $this->tags->bySlug($language, $tagSlug);
        $shown = null === $tag ? null : $this->tags->visible([$tag], $language)[0] ?? null;
        $past = '1' === $context->query('past') && false !== ($context->options['past'] ?? true);
        $perPage = max(1, min(50, is_numeric($context->options['perPage'] ?? null) ? (int) $context->options['perPage'] : 10));
        $total = $this->events->count($language, $chosen, $past, spaces: $spaces, tag: $tag?->id);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($context->query('page') ?? 1)));
        $given = static fn(?string $value): bool => null !== $value;
        $filter = array_filter(['category' => null === $own ? $category?->slug($language) : null, 'tag' => $shown['slug'] ?? null, 'past' => $past ? '1' : null], $given);
        $link = static fn(array $query, ?string $to = null): string => ($to ?? $here) . ([] === $query ? '' : '?' . http_build_query($query));
        $images = true === ($context->options['images'] ?? false);
        $filters = [];

        if (false !== ($context->options['categories'] ?? true)) {
            foreach ($this->categories->tree(EventStore::TYPE, EventStore::CATEGORIES) as $item) {
                $itemSlug = $item->slug($language);
                $title = $item->title($language);

                if (null !== $itemSlug && null !== $title) {
                    $address = $this->pages->href($context, $item->id);
                    $filters[] = ['title' => $title, 'href' => $link(array_filter(['category' => null === $address ? $itemSlug : null, 'past' => $filter['past'] ?? null], $given), $address ?? $base), 'current' => $item->id === $category?->id, 'depth' => $item->depth];
                }
            }
        }

        $categoryOnly = array_filter(['category' => $filter['category'] ?? null, 'tag' => $filter['tag'] ?? null], $given);

        return [
            'events' => array_map(fn(EventView $event): array => $this->withLinks($this->rendering->teaser($event, $context->language, $images), $base, $context), $this->events->list($language, $chosen, $past, $perPage, ($page - 1) * $perPage, spaces: $spaces, tag: $tag?->id)),
            'filters' => [] === $filters ? [] : [['title' => null, 'href' => $link(array_filter(['past' => $filter['past'] ?? null], $given), $base), 'current' => null === $category], ...$filters],
            'category' => null === $own ? null : ['title' => $own[0]->titleIn([$language]), 'description' => $own[0]->description($language)],
            'filtered' => null !== $category || null !== $shown,
            'tag' => null === $shown ? null : ['label' => $shown['label'], 'all' => $link(array_diff_key($filter, ['tag' => true]))],
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
     * The categories and tags of an event link to the list filtered by
     * them, a category to its page if it has one (decision 0243).
     *
     * @param array<string, mixed> $event
     *
     * @return array<string, mixed>
     */
    private function withLinks(array $event, string $base, BlockContext $context): array
    {
        $categories = [];

        foreach (\is_array($event['categories'] ?? null) ? $event['categories'] : [] as $category) {
            if (\is_array($category) && \is_string($category['slug'] ?? null)) {
                $id = \is_string($category['id'] ?? null) ? $category['id'] : null;
                $categories[] = [...$category, 'href' => (null === $id ? null : $this->pages->href($context, $id)) ?? $base . '?' . http_build_query(['category' => $category['slug']])];
            }
        }

        $tags = [];

        foreach (\is_array($event['tags'] ?? null) ? $event['tags'] : [] as $tag) {
            if (\is_array($tag) && \is_string($tag['slug'] ?? null)) {
                $tags[] = [...$tag, 'href' => $base . '?' . http_build_query(['tag' => $tag['slug']])];
            }
        }

        return ['categories' => $categories, 'tags' => $tags] + $event;
    }

    private static function text(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
    }
}

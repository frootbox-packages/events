<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Blocks;

use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Plugin\PluginPages;
use Frootbox\Core\Tag\ObjectTags;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\Events\Event\EventRendering;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventView;
use Frootbox\Events\Event\PublishedEvents;

/**
 * The next events, e.g. on the start page, with a link to all.
 */
final readonly class EventTeaser implements BlockController
{
    public function __construct(
        private PublishedEvents $events,
        private EventRendering $rendering,
        private PluginPages $plugins,
        private Urls $urls,
        private ObjectTags $tags,
    ) {}

    public function data(BlockContext $context): array
    {
        $language = $context->contentLanguage;
        $count = max(1, min(12, is_numeric($context->options['count'] ?? null) ? (int) $context->options['count'] : 3));
        $space = \is_string($context->options['space'] ?? null) && '' !== $context->options['space'] ? $context->options['space'] : null;
        $key = \is_string($context->options['tag'] ?? null) && '' !== $context->options['tag'] ? $context->options['tag'] : null;
        // A tag deleted since then shows no events rather than all (decision 0239).
        $tag = null === $key ? null : $this->tags->byKey($key)->id ?? '';
        $base = null === $space ? $this->plugins->main(EventStore::TYPE) : $this->plugins->of(EventStore::TYPE, $space);

        return [
            'events' => array_map(fn(EventView $event): array => $this->rendering->teaser($event, $context->language, false), $this->events->list($language, null, false, $count, spaces: null === $space ? null : [$space], tag: $tag)),
            'all' => null === $base ? null : $this->urls->href(Target::page($base), $context->language),
        ];
    }
}

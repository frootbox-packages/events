<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Blocks;

use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Url\BasePages;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\Events\Event\EventRendering;
use Frootbox\Events\Event\EventView;
use Frootbox\Events\Event\PublishedEvents;
use Frootbox\Events\EventPages;

/**
 * The next events, e.g. on the start page, with a link to all.
 */
final readonly class EventTeaser implements BlockController
{
    public function __construct(
        private PublishedEvents $events,
        private EventRendering $rendering,
        private BasePages $bases,
        private Urls $urls,
    ) {}

    public function data(BlockContext $context): array
    {
        $language = $context->contentLanguage;
        $count = max(1, min(12, is_numeric($context->options['count'] ?? null) ? (int) $context->options['count'] : 3));
        $base = $this->bases->of([EventPages::LIST_BLOCK])[$context->language] ?? null;

        return [
            'events' => array_map(fn(EventView $event): array => $this->rendering->teaser($event, $context->language, false), $this->events->list($language, null, false, $count)),
            'all' => null === $base ? null : $this->urls->href(Target::page($base), $context->language),
        ];
    }
}

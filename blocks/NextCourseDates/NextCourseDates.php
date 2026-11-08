<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Blocks;

use Frootbox\Core\Action\ActionException;
use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Page\EditMode;
use Frootbox\Core\View\Delivery;
use Frootbox\Events\Courses\CourseRendering;
use Frootbox\Events\Courses\Courses;
use Frootbox\Events\Courses\Provider\CourseDate;
use Frootbox\Events\Courses\Provider\DateQuery;

/**
 * The next dates of the courses that can be booked, e.g. "Courses soon"
 * on the start page (decision 0351); sold-out dates are left out.
 */
final readonly class NextCourseDates implements BlockController
{
    public function __construct(
        private Courses $courses,
        private CourseRendering $rendering,
        private Delivery $delivery,
        private EditMode $editMode,
    ) {}

    public function data(BlockContext $context): array
    {
        $id = \is_string($context->options['course'] ?? null) ? $context->options['course'] : '';
        $count = max(1, min(12, is_numeric($context->options['count'] ?? null) ? (int) $context->options['count'] : 4));
        $base = ['editing' => $this->editMode->active()];

        if (!$this->courses->connected()) {
            return ['state' => 'none', ...$base];
        }

        try {
            $dates = $this->courses->dates('frootbox/events/date.list', new DateQuery('' === $id ? null : $id, available: true, limit: $count));
        } catch (ActionException) {
            $this->delivery->withoutCache();

            return ['state' => 'error', ...$base];
        }

        $this->delivery->cacheFor(CourseRendering::LIFETIME);

        return [
            'state' => 'ready',
            ...$base,
            'dates' => array_map(fn(CourseDate $date): array => $this->rendering->date($date, $context->language), array_values(array_filter($dates, static fn(CourseDate $date): bool => $date->bookable()))),
        ];
    }
}

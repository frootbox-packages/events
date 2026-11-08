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
use Frootbox\Events\Courses\Provider\NotFound;

/**
 * A course of the provider with price per attendee, the least and most
 * attendees and its coming dates (decision 0351). The page stays at most
 * a minute in the page cache, so free seats stay fresh.
 */
final readonly class CourseDates implements BlockController
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
        $count = max(1, min(20, is_numeric($context->options['count'] ?? null) ? (int) $context->options['count'] : 6));
        $base = ['editing' => $this->editMode->active(), 'facts' => false !== ($context->options['facts'] ?? true)];

        if (!$this->courses->connected()) {
            return ['state' => 'none', ...$base];
        }

        try {
            $course = '' === $id ? null : $this->courses->course('frootbox/events/course.list', $id);
            $dates = $this->courses->dates('frootbox/events/date.list', new DateQuery('' === $id ? null : $id, limit: $count));
        } catch (ActionException|NotFound) {
            $this->delivery->withoutCache();

            return ['state' => 'error', ...$base];
        }

        $this->delivery->cacheFor(CourseRendering::LIFETIME);

        return [
            'state' => 'ready',
            ...$base,
            'course' => null === $course ? null : $this->rendering->course($course, $context->language),
            'dates' => array_map(fn(CourseDate $date): array => $this->rendering->date($date, $context->language), $dates),
        ];
    }
}

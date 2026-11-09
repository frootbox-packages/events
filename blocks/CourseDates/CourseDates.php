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
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Events\Courses\ChannelAccess;
use Frootbox\Events\Courses\CourseRendering;
use Frootbox\Events\Courses\Courses;
use Frootbox\Events\Courses\ListAttendees;
use Frootbox\Events\Courses\Provider\BookedAttendee;
use Frootbox\Events\Courses\Provider\CourseDate;
use Frootbox\Events\Courses\Provider\DateQuery;
use Frootbox\Events\Courses\Provider\NotFound;

/**
 * A course of the provider with price per attendee, the least and most
 * attendees and its coming dates (decision 0351). The page stays at most
 * a minute in the page cache, so free seats stay fresh. A further channel
 * of the provider shows if it is open or the page has a password
 * (decision 0360); the names of who is booked only on a page with a
 * password (decision 0359).
 */
final readonly class CourseDates implements BlockController
{
    public function __construct(
        private Courses $courses,
        private CourseRendering $rendering,
        private ChannelAccess $access,
        private Delivery $delivery,
        private EditMode $editMode,
    ) {}

    public function data(BlockContext $context): array
    {
        $id = \is_string($context->options['course'] ?? null) ? $context->options['course'] : '';
        $channel = \is_string($context->options['channel'] ?? null) ? $context->options['channel'] : '';
        $count = max(1, min(20, is_numeric($context->options['count'] ?? null) ? (int) $context->options['count'] : 6));
        $base = ['editing' => $this->editMode->active(), 'facts' => false !== ($context->options['facts'] ?? true)];

        if (!$this->courses->connected()) {
            return ['state' => 'none', ...$base];
        }

        if (!$this->access->shows($channel, $context->target)) {
            return ['state' => 'restricted', ...$base];
        }

        try {
            $course = '' === $id ? null : $this->courses->course('frootbox/events/course.list', $id, $channel);
            // A course the channel no longer shows has no dates here, not an error page
            $dates = '' !== $id && null === $course ? [] : $this->courses->dates('frootbox/events/date.list', new DateQuery('' === $id ? null : $id, limit: $count), $channel);
        } catch (ActionException|EntityNotFoundException|NotFound) {
            $this->delivery->withoutCache();

            return ['state' => 'error', ...$base];
        }

        $this->delivery->cacheFor(CourseRendering::LIFETIME);
        [$names, $hint] = true === ($context->options['attendees'] ?? false) ? $this->names($dates, $channel, $context) : [null, null];

        return [
            'state' => 'ready',
            ...$base,
            'attendeesHint' => $hint,
            'course' => null === $course ? null : $this->rendering->course($course, $context->language),
            'dates' => array_map(fn(CourseDate $date): array => [
                ...$this->rendering->date($date, $context->language, channel: $channel),
                'attendees' => null === $names ? null : array_map(fn(BookedAttendee $attendee): array => $this->rendering->attendee($attendee, $context->language), $names[$date->id] ?? []),
            ], $dates),
        ];
    }

    /**
     * The names by date in one call, or why there are none: "password" on
     * a page without, "provider" if it gives no names, "error".
     *
     * @param list<CourseDate> $dates
     *
     * @return array{0: array<int|string, list<BookedAttendee>>|null, 1: string|null}
     */
    private function names(array $dates, string $channel, BlockContext $context): array
    {
        if (null === $this->access->protectedBy($context->target)) {
            return [null, 'password'];
        }

        try {
            $names = $this->courses->attendees(ListAttendees::NAME, array_map(static fn(CourseDate $date): string => $date->id, $dates), $channel);
        } catch (ActionException) {
            return [null, 'error'];
        }

        return null === $names ? [null, 'provider'] : [$names, null];
    }
}

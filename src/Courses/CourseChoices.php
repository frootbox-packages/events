<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\ActionException;
use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\I18n\Translator;
use Frootbox\Events\Courses\Provider\Course;

/**
 * @implements Query<list<array{value: string, label: string}>>
 */
#[AsQuery('frootbox/events/course.choices', 'The courses of the provider as choices for a block, "All" first; empty without a provider', permission: 'frootbox/events/event.list')]
final readonly class CourseChoices implements Query
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function handle(Courses $courses, Translator $translator): array
    {
        if (!$courses->connected()) {
            return [];
        }

        try {
            $list = $courses->courses('frootbox/events/course.choices');
        } catch (ActionException) {
            return [];
        }

        return [
            ['value' => '', 'label' => $translator->get('All')],
            ...array_map(static fn(Course $course): array => ['value' => $course->id, 'label' => '' === $course->subtitle ? $course->title : $course->title . ' – ' . $course->subtitle], $list),
        ];
    }
}

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

        $choices = array_map(static fn(Course $course): array => ['value' => $course->id, 'label' => self::label($course)], $list);
        $known = array_column($choices, 'value');

        // Courses only a further channel shows, with its title (decision 0360).
        foreach ($courses->channels() as $channel) {
            try {
                $more = $courses->courses('frootbox/events/course.choices', $channel->key);
            } catch (ActionException) {
                continue;
            }

            foreach ($more as $course) {
                if (!\in_array($course->id, $known, true)) {
                    $known[] = $course->id;
                    $choices[] = ['value' => $course->id, 'label' => self::label($course) . ' (' . $channel->title . ')'];
                }
            }
        }

        return [['value' => '', 'label' => $translator->get('All')], ...$choices];
    }

    private static function label(Course $course): string
    {
        return '' === $course->subtitle ? $course->title : $course->title . ' – ' . $course->subtitle;
    }
}

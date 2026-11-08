<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Events\Courses\Provider\Course;

/**
 * @implements Query<list<Course>>
 */
#[AsQuery('frootbox/events/course.list', 'The courses of the provider with price per attendee and the least and most attendees', public: true)]
final readonly class ListCourses implements Query
{
    /**
     * @return list<Course>
     */
    public function handle(Courses $courses): array
    {
        return $courses->courses('frootbox/events/course.list');
    }
}

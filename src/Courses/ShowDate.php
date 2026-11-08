<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Events\Courses\Provider\CourseDate;

/**
 * @implements Query<CourseDate>
 */
#[AsQuery('frootbox/events/date.show', 'A date of a course with its seats, whether it can be booked and the fields it asks for', public: true)]
final readonly class ShowDate implements Query
{
    public function __construct(
        #[Length(min: 1, max: 100)]
        public string $id,
    ) {}

    public function handle(Courses $courses): CourseDate
    {
        return $courses->date('frootbox/events/date.show', $this->id);
    }
}

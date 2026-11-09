<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Sensitive;
use Frootbox\Events\Courses\Provider\Course;

/**
 * @implements Query<list<Course>>
 */
#[AsQuery('frootbox/events/course.list', 'The courses of the provider with price per attendee and the least and most attendees', public: true)]
final readonly class ListCourses implements Query
{
    public function __construct(
        #[Description('A further channel of the provider, empty for its own')]
        #[Length(max: 100)]
        public string $channel = '',
        #[Description('The access a page with a password hands out for a restricted channel')]
        #[Length(max: 1000)]
        #[Sensitive]
        public ?string $access = null,
    ) {}

    /**
     * @return list<Course>
     */
    public function handle(Courses $courses, ChannelAccess $access): array
    {
        $access->check($this->channel, $this->access);

        return $courses->courses('frootbox/events/course.list', $this->channel);
    }
}

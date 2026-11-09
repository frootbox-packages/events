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
        #[Description('A further channel of the provider, empty for its own')]
        #[Length(max: 100)]
        public string $channel = '',
        #[Description('The access a page with a password hands out for a restricted channel')]
        #[Length(max: 1000)]
        #[Sensitive]
        public ?string $access = null,
    ) {}

    public function handle(Courses $courses, ChannelAccess $access): CourseDate
    {
        $access->check($this->channel, $this->access);

        return $courses->date('frootbox/events/date.show', $this->id, $this->channel);
    }
}

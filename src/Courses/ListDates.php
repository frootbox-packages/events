<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use DateTimeImmutable;
use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Core\Action\Schema\Sensitive;
use Frootbox\Events\Courses\Provider\CourseDate;
use Frootbox\Events\Courses\Provider\DateQuery;

/**
 * @implements Query<list<CourseDate>>
 */
#[AsQuery('frootbox/events/date.list', 'The coming dates of the courses with free seats, earliest first', public: true)]
final readonly class ListDates implements Query
{
    public function __construct(
        #[Description('The id of a course; empty for all')]
        #[Length(max: 100)]
        public ?string $course = null,
        #[Description('From this moment on, else from now')]
        public ?DateTimeImmutable $from = null,
        #[Description('Only dates that can be booked now')]
        public bool $available = true,
        #[Range(min: 1, max: 100)]
        public int $limit = 20,
        #[Description('A further channel of the provider, empty for its own')]
        #[Length(max: 100)]
        public string $channel = '',
        #[Description('The access a page with a password hands out for a restricted channel')]
        #[Length(max: 1000)]
        #[Sensitive]
        public ?string $access = null,
    ) {}

    /**
     * @return list<CourseDate>
     */
    public function handle(Courses $courses, ChannelAccess $access): array
    {
        $access->check($this->channel, $this->access);

        return $courses->dates('frootbox/events/date.list', new DateQuery('' === $this->course ? null : $this->course, $this->from, $this->available, $this->limit), $this->channel);
    }
}

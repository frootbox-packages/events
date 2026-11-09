<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\ListOf;
use Frootbox\Core\Support\Message;
use Frootbox\Events\Courses\Provider\BookedAttendee;

/**
 * Who is booked for dates (decision 0359), for editors and the API; never
 * public. Blocks show the names only on a page with a password.
 *
 * @implements Query<array<int|string, list<BookedAttendee>>>
 */
#[AsQuery(self::NAME, 'First and last name and time of booking of who is booked for dates of the provider of courses, by date')]
final readonly class ListAttendees implements Query
{
    public const string NAME = 'frootbox/events/attendee.list';

    /**
     * @param list<string> $dates
     */
    public function __construct(
        #[Description('The ids of the dates, at most 50')]
        #[ListOf('string')]
        public array $dates,
        #[Description('A further channel of the provider, empty for its own')]
        #[Length(max: 100)]
        public string $channel = '',
    ) {}

    /**
     * @return array<int|string, list<BookedAttendee>>
     */
    public function handle(Courses $courses): array
    {
        if ([] === $this->dates || \count($this->dates) > 50) {
            throw new InvalidInputException(self::NAME, ['/dates' => new Message('Give the ids of 1 to 50 dates.')]);
        }

        return $courses->attendees(self::NAME, array_values(array_unique($this->dates)), $this->channel)
            ?? throw new ConflictException('The provider of courses gives no names of attendees.');
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A provider that gives the names of who is booked (decision 0359),
 * beside CourseProvider. Only for pages with a password.
 */
interface AttendeeSource
{
    /**
     * Who is booked for these dates, by time of booking; dates the
     * provider does not show are left out.
     *
     * @param list<string> $dates ids of dates
     *
     * @return array<int|string, list<BookedAttendee>> by id of the date; PHP keeps numeric ids as int
     *
     * @throws CoursesUnavailable
     */
    public function attendees(array $dates): array;
}

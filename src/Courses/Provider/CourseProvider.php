<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A provider of courses with dates and bookings, such as avaro/events
 * (decision 0351). It answers with the values of this module and throws
 * its exceptions; any other error counts as unavailable.
 */
interface CourseProvider
{
    /**
     * @throws CoursesUnavailable
     */
    public function channel(): Channel;

    /**
     * @return list<Course>
     *
     * @throws CoursesUnavailable
     */
    public function courses(): array;

    /**
     * @return list<CourseDate> earliest first
     *
     * @throws CoursesUnavailable
     */
    public function dates(DateQuery $query): array;

    /**
     * @throws NotFound
     * @throws CoursesUnavailable
     */
    public function date(string $id): CourseDate;

    /**
     * Books once per key: the same key again gives the same booking.
     *
     * @throws InputRejected
     * @throws SoldOut
     * @throws NotBookable
     * @throws TotalChanged
     * @throws NotFound
     * @throws CoursesUnavailable
     */
    public function book(BookingRequest $request, string $key): BookingConfirmation;
}

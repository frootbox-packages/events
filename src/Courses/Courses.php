<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Events\Courses\Provider\AttendeeSource;
use Frootbox\Events\Courses\Provider\BookedAttendee;
use Frootbox\Events\Courses\Provider\BookingConfirmation;
use Frootbox\Events\Courses\Provider\BookingRequest;
use Frootbox\Events\Courses\Provider\Channel;
use Frootbox\Events\Courses\Provider\ChannelChoice;
use Frootbox\Events\Courses\Provider\Course;
use Frootbox\Events\Courses\Provider\CourseDate;
use Frootbox\Events\Courses\Provider\CourseProvider;
use Frootbox\Events\Courses\Provider\CoursesUnavailable;
use Frootbox\Events\Courses\Provider\DateQuery;
use Frootbox\Events\Courses\Provider\InputRejected;
use Frootbox\Events\Courses\Provider\NotBookable;
use Frootbox\Events\Courses\Provider\NotFound;
use Frootbox\Events\Courses\Provider\SoldOut;
use Frootbox\Events\Courses\Provider\TotalChanged;

/**
 * Calls the provider of courses for actions, queries and blocks and turns
 * its errors into those of actions (decision 0351): unavailable and
 * conflicts 409, rejected input 422, an unknown date 404. Every call
 * takes a channel (decision 0360), empty for the provider's own.
 */
final readonly class Courses
{
    public function __construct(private Providers $providers) {}

    public function connected(): bool
    {
        return null !== $this->providers->active();
    }

    /**
     * @return list<ChannelChoice>
     */
    public function channels(): array
    {
        try {
            return $this->providers->channels();
        } catch (CoursesUnavailable) {
            return [];
        }
    }

    /**
     * A further channel by key, null for the provider's own or an unknown one.
     */
    public function choice(string $channel): ?ChannelChoice
    {
        return '' === $channel ? null : array_find($this->channels(), static fn(ChannelChoice $choice): bool => $choice->key === $channel);
    }

    public function channel(string $action, string $channel = ''): Channel
    {
        return $this->call($action, $channel, static fn(CourseProvider $provider): Channel => $provider->channel());
    }

    /**
     * @return list<Course>
     */
    public function courses(string $action, string $channel = ''): array
    {
        return $this->call($action, $channel, static fn(CourseProvider $provider): array => $provider->courses());
    }

    public function course(string $action, string $id, string $channel = ''): ?Course
    {
        return array_find($this->courses($action, $channel), static fn(Course $course): bool => $course->id === $id);
    }

    /**
     * @return list<CourseDate>
     */
    public function dates(string $action, DateQuery $query, string $channel = ''): array
    {
        return $this->call($action, $channel, static fn(CourseProvider $provider): array => $provider->dates($query));
    }

    public function date(string $action, string $id, string $channel = ''): CourseDate
    {
        return $this->call($action, $channel, static fn(CourseProvider $provider): CourseDate => $provider->date($id));
    }

    public function book(string $action, BookingRequest $request, string $key, string $channel = ''): BookingConfirmation
    {
        return $this->call($action, $channel, static fn(CourseProvider $provider): BookingConfirmation => $provider->book($request, $key));
    }

    /**
     * Who is booked for these dates (decision 0359); null when the provider
     * gives no names.
     *
     * @param list<string> $dates
     *
     * @return array<int|string, list<BookedAttendee>>|null
     */
    public function attendees(string $action, array $dates, string $channel = ''): ?array
    {
        return $this->call($action, $channel, static fn(CourseProvider $provider): ?array => $provider instanceof AttendeeSource ? ([] === $dates ? [] : $provider->attendees($dates)) : null);
    }

    /**
     * @template T
     *
     * @param callable(CourseProvider): T $call
     *
     * @return T
     */
    private function call(string $action, string $channel, callable $call): mixed
    {
        try {
            $provider = $this->providers->active($channel);
        } catch (CoursesUnavailable) {
            throw new ConflictException('The courses cannot be reached right now. Please try again in a moment.');
        }

        if (null === $provider) {
            if ('' !== $channel && $this->connected()) {
                throw new EntityNotFoundException('There is no such channel of the courses.');
            }

            throw new ConflictException('No provider of courses is connected. Install one such as avaro/events, or choose one in the settings of the events.');
        }

        try {
            return $call($provider);
        } catch (InputRejected $error) {
            throw new InvalidInputException($action, $error->errors);
        } catch (SoldOut) {
            throw new ConflictException('Not enough seats are free on this date any more.');
        } catch (NotBookable) {
            throw new ConflictException('This date can no longer be booked.');
        } catch (TotalChanged) {
            throw new ConflictException('The price has changed meanwhile. Please check the booking and send it again.');
        } catch (NotFound) {
            throw EntityNotFoundException::for('course date', $action);
        } catch (CoursesUnavailable) {
            throw new ConflictException('The courses cannot be reached right now. Please try again in a moment.');
        }
    }
}

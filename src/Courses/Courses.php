<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Events\Courses\Provider\BookingConfirmation;
use Frootbox\Events\Courses\Provider\BookingRequest;
use Frootbox\Events\Courses\Provider\Channel;
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
 * conflicts 409, rejected input 422, an unknown date 404.
 */
final readonly class Courses
{
    public function __construct(private Providers $providers) {}

    public function connected(): bool
    {
        return null !== $this->providers->active();
    }

    public function channel(string $action): Channel
    {
        return $this->call($action, static fn(CourseProvider $provider): Channel => $provider->channel());
    }

    /**
     * @return list<Course>
     */
    public function courses(string $action): array
    {
        return $this->call($action, static fn(CourseProvider $provider): array => $provider->courses());
    }

    public function course(string $action, string $id): ?Course
    {
        return array_find($this->courses($action), static fn(Course $course): bool => $course->id === $id);
    }

    /**
     * @return list<CourseDate>
     */
    public function dates(string $action, DateQuery $query): array
    {
        return $this->call($action, static fn(CourseProvider $provider): array => $provider->dates($query));
    }

    public function date(string $action, string $id): CourseDate
    {
        return $this->call($action, static fn(CourseProvider $provider): CourseDate => $provider->date($id));
    }

    public function book(string $action, BookingRequest $request, string $key): BookingConfirmation
    {
        return $this->call($action, static fn(CourseProvider $provider): BookingConfirmation => $provider->book($request, $key));
    }

    /**
     * @template T
     *
     * @param callable(CourseProvider): T $call
     *
     * @return T
     */
    private function call(string $action, callable $call): mixed
    {
        $provider = $this->providers->active() ?? throw new ConflictException('No provider of courses is connected. Install one such as avaro/events, or choose one in the settings of the events.');

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

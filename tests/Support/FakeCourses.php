<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests\Support;

use DateTimeImmutable;
use Frootbox\Events\Courses\Provider\BookingConfirmation;
use Frootbox\Events\Courses\Provider\BookingRequest;
use Frootbox\Events\Courses\Provider\BookingState;
use Frootbox\Events\Courses\Provider\Channel;
use Frootbox\Events\Courses\Provider\Course;
use Frootbox\Events\Courses\Provider\CourseDate;
use Frootbox\Events\Courses\Provider\CourseProvider;
use Frootbox\Events\Courses\Provider\CourseRef;
use Frootbox\Events\Courses\Provider\CoursesException;
use Frootbox\Events\Courses\Provider\CoursesUnavailable;
use Frootbox\Events\Courses\Provider\DateQuery;
use Frootbox\Events\Courses\Provider\Detail;
use Frootbox\Events\Courses\Provider\Field;
use Frootbox\Events\Courses\Provider\FieldKind;
use Frootbox\Events\Courses\Provider\LegalText;
use Frootbox\Events\Courses\Provider\NotFound;
use Frootbox\Events\Courses\Provider\Seats;
use Frootbox\Events\Courses\Provider\SoldOut;
use Frootbox\Events\Courses\Provider\Venue;

/**
 * Courses in memory for the tests: "First aid" at 40.00 per attendee with
 * a date in ten days (2 seats free), a sold-out one and one in twenty days.
 */
final class FakeCourses implements CourseProvider
{
    /** @var array<int|string, CourseDate> by id; PHP keeps numeric ids as int */
    public static array $dates = [];

    /** @var list<array{BookingRequest, string}> */
    public static array $bookings = [];

    /** An error the next call throws. */
    public static ?CoursesException $fail = null;

    /**
     * The bookings so far.
     *
     * @phpstan-impure
     *
     * @return list<array{BookingRequest, string}>
     */
    public static function bookings(): array
    {
        return self::$bookings;
    }

    public static function reset(): void
    {
        self::$fail = null;
        self::$bookings = [];
        self::$dates = [
            '10' => self::make('10', '+10 days 09:00', 2),
            '11' => self::make('11', '+12 days 09:00', 0, BookingState::SoldOut),
            '12' => self::make('12', '+20 days 09:00', 15),
        ];
    }

    public static function make(string $id, string $start, int $free, BookingState $state = BookingState::Open): CourseDate
    {
        $startsAt = new DateTimeImmutable($start);

        return new CourseDate(
            $id,
            'Erste Hilfe',
            $startsAt,
            $startsAt->modify('16:30'),
            'EUR',
            new Seats(20, 20 - $free, $free),
            $state,
            number: 'K-' . $id,
            subtitle: 'Grundkurs',
            course: new CourseRef('2', 'Erste Hilfe'),
            venue: new Venue('Schulungsraum Aachen', 'Theaterstraße 1', '52062', 'Aachen', 'DE'),
            priceGross: 4000,
            attendeesMin: 4,
            attendeesMax: 20,
            fields: [new Field('kosten-ueber-firma', 'Kosten werden über die Firma bzw. BG abgerechnet', FieldKind::Checkbox)],
        );
    }

    public function channel(): Channel
    {
        $this->failing();

        return new Channel('EUR', [
            new LegalText('Terms', 'Verbindliche Anmeldung', '2027-01', true, 'https://example.org/agb'),
            new LegalText('Privacy', 'Datenschutz', '1', false, text: 'Wir speichern Deine Angaben.'),
        ], [Detail::Address, Detail::DateOfBirth], 3);
    }

    public function courses(): array
    {
        $this->failing();

        return [new Course('2', 'Erste Hilfe', 'EUR', 'Grundkurs', priceGross: 4000, attendeesMin: 4, attendeesMax: 20)];
    }

    public function dates(DateQuery $query): array
    {
        $this->failing();
        $dates = array_values(array_filter(self::$dates, static fn(CourseDate $date): bool => (null === $query->course || $date->course?->id === $query->course) && (!$query->available || $date->bookable())));
        usort($dates, static fn(CourseDate $a, CourseDate $b): int => $a->startsAt <=> $b->startsAt);

        return \array_slice($dates, 0, $query->limit);
    }

    public function date(string $id): CourseDate
    {
        $this->failing();

        return self::$dates[$id] ?? throw new NotFound($id);
    }

    public function book(BookingRequest $request, string $key): BookingConfirmation
    {
        $this->failing();

        foreach (self::$bookings as $index => [$sent, $sentKey]) {
            if ($sentKey === $key) {
                return new BookingConfirmation('b-' . $index, 'B-' . ($index + 1), \count($sent->attendees), $sent->expectedGross, 'EUR', true);
            }
        }

        $date = $this->date($request->date);

        if (!$date->seats->fit(\count($request->attendees))) {
            throw new SoldOut($date);
        }

        self::$bookings[] = [$request, $key];
        $index = \count(self::$bookings) - 1;

        return new BookingConfirmation('b-' . $index, 'B-' . ($index + 1), \count($request->attendees), $request->expectedGross, 'EUR');
    }

    private function failing(): void
    {
        if (null !== self::$fail) {
            $error = self::$fail;
            self::$fail = null;

            throw $error;
        }
    }

    public static function unavailable(): void
    {
        self::$fail = new CoursesUnavailable('down');
    }
}

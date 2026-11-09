<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests\Support;

use DateTimeImmutable;
use Frootbox\Events\Courses\Provider\AttendeeSource;
use Frootbox\Events\Courses\Provider\BookedAttendee;
use Frootbox\Events\Courses\Provider\BookingConfirmation;
use Frootbox\Events\Courses\Provider\BookingRequest;
use Frootbox\Events\Courses\Provider\BookingState;
use Frootbox\Events\Courses\Provider\Channel;
use Frootbox\Events\Courses\Provider\ChannelChoice;
use Frootbox\Events\Courses\Provider\ChannelSource;
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
 * A restricted channel "partner" has the course "Partnerkurs" with a date in a
 * week that is booked by two people.
 */
final class FakeCourses implements CourseProvider, ChannelSource, AttendeeSource
{
    /** @var array<int|string, CourseDate> by id; PHP keeps numeric ids as int */
    public static array $dates = [];

    /** @var array<int|string, CourseDate> the dates of the channel "partner" */
    public static array $partner = [];

    /** @var array<int|string, list<BookedAttendee>> by id of the date */
    public static array $attendees = [];

    /** @var list<ChannelChoice> */
    public static array $channels = [];

    /** Whether the names cannot be loaded right now. */
    public static bool $namesDown = false;

    public function __construct(private string $key = '') {}

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
        self::$namesDown = false;
        self::$bookings = [];
        self::$dates = [
            '10' => self::make('10', '+10 days 09:00', 2),
            '11' => self::make('11', '+12 days 09:00', 0, BookingState::SoldOut),
            '12' => self::make('12', '+20 days 09:00', 15),
        ];
        self::$partner = ['30' => self::make('30', '+7 days 09:00', 8, course: new CourseRef('7', 'Partnerkurs'))];
        self::$attendees = ['30' => [
            new BookedAttendee('Jana', 'Kurz', new DateTimeImmutable('2026-10-02 14:05')),
            new BookedAttendee('Malte', 'Lang', new DateTimeImmutable('2026-10-03 09:30')),
        ]];
        self::$channels = [new ChannelChoice('partner', 'Partner')];
    }

    public static function make(string $id, string $start, int $free, BookingState $state = BookingState::Open, ?CourseRef $course = null): CourseDate
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
            course: $course ?? new CourseRef('2', 'Erste Hilfe'),
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

        return '' === $this->key
            ? [new Course('2', 'Erste Hilfe', 'EUR', 'Grundkurs', priceGross: 4000, attendeesMin: 4, attendeesMax: 20)]
            : [new Course('2', 'Erste Hilfe', 'EUR', 'Grundkurs', priceGross: 4000), new Course('7', 'Partnerkurs', 'EUR', priceGross: 0)];
    }

    public function dates(DateQuery $query): array
    {
        $this->failing();
        $dates = array_values(array_filter($this->pool(), static fn(CourseDate $date): bool => (null === $query->course || $date->course?->id === $query->course) && (!$query->available || $date->bookable())));
        usort($dates, static fn(CourseDate $a, CourseDate $b): int => $a->startsAt <=> $b->startsAt);

        return \array_slice($dates, 0, $query->limit);
    }

    public function date(string $id): CourseDate
    {
        $this->failing();

        return $this->pool()[$id] ?? throw new NotFound($id);
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

    public function channels(): array
    {
        return self::$channels;
    }

    public function in(string $key): ?CourseProvider
    {
        return \in_array($key, array_column(self::$channels, 'key'), true) ? new self($key) : null;
    }

    public function attendees(array $dates): array
    {
        $this->failing();

        if (self::$namesDown) {
            throw new CoursesUnavailable('names down');
        }

        return array_intersect_key(self::$attendees, array_intersect_key($this->pool(), array_flip($dates)));
    }

    /**
     * @return array<int|string, CourseDate>
     */
    private function pool(): array
    {
        return '' === $this->key ? self::$dates : self::$partner;
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

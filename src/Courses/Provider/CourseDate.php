<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

use DateTimeImmutable;

/**
 * A date of a course at the provider with its seats and whether it can
 * be booked (decision 0351).
 */
final readonly class CourseDate
{
    /**
     * @param int|null    $priceGross gross per attendee in cents, null without a price
     * @param list<Field> $fields
     */
    public function __construct(
        public string $id,
        public string $title,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $currency,
        public Seats $seats,
        public BookingState $state,
        public string $number = '',
        public string $subtitle = '',
        public ?CourseRef $course = null,
        public ?Venue $venue = null,
        public bool $online = false,
        public ?int $priceGross = null,
        public ?int $attendeesMin = null,
        public ?int $attendeesMax = null,
        public ?DateTimeImmutable $bookingFrom = null,
        public ?DateTimeImmutable $bookingTo = null,
        public array $fields = [],
    ) {}

    /**
     * Whether this many attendees can book it now: open, enough seats,
     * and no required field of a kind the module does not know.
     */
    public function bookable(int $attendees = 1): bool
    {
        return BookingState::Open === $this->state
            && $this->seats->fit($attendees)
            && [] === array_filter($this->fields, static fn(Field $field): bool => $field->required && null === $field->kind);
    }

    /**
     * The gross total in cents: the price per attendee times the
     * attendees, as the provider checks it.
     */
    public function totalGross(int $attendees): int
    {
        return ($this->priceGross ?? 0) * $attendees;
    }

    /**
     * The most attendees one booking may have: what the channel allows,
     * the course and the free seats.
     */
    public function maxAttendees(Channel $channel): int
    {
        return max(1, min($channel->maxAttendees, $this->attendeesMax ?? \PHP_INT_MAX, $this->seats->free ?? \PHP_INT_MAX));
    }

    public function field(string $key): ?Field
    {
        return array_find($this->fields, static fn(Field $field): bool => $field->key === $key);
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Frootbox\Core\Url\BasePages;
use Frootbox\Core\Url\Target;
use Frootbox\Core\Url\Urls;
use Frootbox\Events\Courses\Provider\Course;
use Frootbox\Events\Courses\Provider\CourseDate;
use IntlDateFormatter;
use NumberFormatter;

/**
 * Courses and their dates for the views (decision 0351): times in the
 * time zone of the website, amounts in the language of the page, and the
 * link to the first page with the booking form.
 */
final readonly class CourseRendering
{
    public const string BOOKING_BLOCK = 'frootbox/events/CourseBooking';

    /** Seconds a page with free seats may stay in the page cache. */
    public const int LIFETIME = 60;

    public function __construct(
        private BasePages $pages,
        private Urls $urls,
    ) {}

    /**
     * The address of the booking form for a date, null without such a page.
     */
    public function bookingHref(string $date, string $language): ?string
    {
        $page = $this->pages->of([self::BOOKING_BLOCK])[$language] ?? null;
        $href = null === $page ? null : $this->urls->href(Target::page($page), $language);

        return null === $href ? null : $href . '?' . http_build_query(['date' => $date]);
    }

    /**
     * @return array{title: string, subtitle: string, price: string|null, free: bool, min: int|null, max: int|null}
     */
    public function course(Course $course, string $language): array
    {
        return [
            'title' => $course->title,
            'subtitle' => $course->subtitle,
            'price' => null === $course->priceGross || 0 === $course->priceGross ? null : self::money($course->priceGross, $course->currency, $language),
            'free' => 0 === $course->priceGross,
            'min' => $course->attendeesMin,
            'max' => $course->attendeesMax,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function date(CourseDate $date, string $language, bool $link = true): array
    {
        $start = self::local($date->startsAt);
        $end = self::local($date->endsAt);
        $sameDay = $start->format('Y-m-d') === $end->format('Y-m-d');
        $bookable = $date->bookable();

        return [
            'id' => $date->id,
            'title' => $date->title,
            'subtitle' => $date->subtitle,
            'course' => $date->course->title ?? $date->title,
            'number' => $date->number,
            'start' => $start->format(DateTimeInterface::ATOM),
            'end' => $end->format(DateTimeInterface::ATOM),
            'day' => self::format($start, $language, IntlDateFormatter::FULL, IntlDateFormatter::NONE),
            'dayNumber' => self::pattern($start, $language, 'd'),
            'month' => self::pattern($start, $language, 'MMM'),
            'time' => $sameDay
                ? self::format($start, $language, IntlDateFormatter::NONE, IntlDateFormatter::SHORT) . ' – ' . self::format($end, $language, IntlDateFormatter::NONE, IntlDateFormatter::SHORT)
                : self::format($start, $language, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT) . ' – ' . self::format($end, $language, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT),
            'startTime' => self::format($start, $language, IntlDateFormatter::NONE, IntlDateFormatter::SHORT),
            'endTime' => self::format($end, $language, IntlDateFormatter::NONE, IntlDateFormatter::SHORT),
            'multiDay' => !$sameDay,
            'until' => $sameDay ? null : self::format($end, $language, IntlDateFormatter::FULL, IntlDateFormatter::NONE),
            'venue' => $date->venue->title ?? null,
            'address' => null === $date->venue || '' === $date->venue->address() ? null : $date->venue->address(),
            'online' => $date->online,
            'price' => null === $date->priceGross || 0 === $date->priceGross ? null : self::money($date->priceGross, $date->currency, $language),
            'seats' => $date->seats->free,
            'state' => $date->state->value,
            'bookable' => $bookable,
            'url' => $bookable && $link ? $this->bookingHref($date->id, $language) : null,
        ];
    }

    public static function money(int $cents, string $currency, string $language): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatted = new NumberFormatter($language, NumberFormatter::CURRENCY)->formatCurrency($cents / 100, $currency);

            if (false !== $formatted) {
                return $formatted;
            }
        }

        $german = !str_starts_with($language, 'en');
        $number = number_format($cents / 100, 2, $german ? ',' : '.', $german ? '.' : ',');
        $symbol = 'EUR' === $currency ? '€' : $currency;

        return $german ? $number . ' ' . $symbol : $symbol . ' ' . $number;
    }

    private static function local(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    private static function format(DateTimeInterface $date, string $language, int $dateType, int $timeType): string
    {
        if (class_exists(IntlDateFormatter::class)) {
            $formatted = new IntlDateFormatter($language, $dateType, $timeType, $date->getTimezone())->format($date);

            if (\is_string($formatted)) {
                return $formatted;
            }
        }

        return $date->format(match (true) {
            IntlDateFormatter::NONE === $dateType => 'H:i',
            IntlDateFormatter::NONE === $timeType => 'l, Y-m-d',
            default => 'Y-m-d H:i',
        });
    }

    private static function pattern(DateTimeInterface $date, string $language, string $pattern): string
    {
        if (class_exists(IntlDateFormatter::class)) {
            $formatted = new IntlDateFormatter($language, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $date->getTimezone(), pattern: $pattern)->format($date);

            if (\is_string($formatted)) {
                return $formatted;
            }
        }

        return $date->format('d' === $pattern ? 'j' : 'M');
    }
}

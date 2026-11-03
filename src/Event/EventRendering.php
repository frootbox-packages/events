<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use DateTimeInterface;
use Frootbox\Core\Category\Category;
use Frootbox\Core\Config\Config;
use Frootbox\Core\Field\Field;
use Frootbox\Core\Field\FieldTypes;
use Frootbox\Core\Tag\ObjectTags;
use Frootbox\Core\Url\Urls;
use Frootbox\Events\Calendar\Calendar;
use IntlDateFormatter;
use Twig\Markup;

/**
 * An event as the views of the blocks use it: texts, the address, the
 * time for people and machines, the image as HTML and the structured
 * data for search engines (decision 0227).
 */
final readonly class EventRendering
{
    /** The image of a teaser: three columns on wide screens. */
    public const string TEASER_SIZES = '(min-width: 72rem) 24rem, (min-width: 40rem) 50vw, 100vw';

    /** The image above the text of an event. */
    public const string DETAIL_SIZES = '(min-width: 72rem) 48rem, 100vw';

    public function __construct(
        private FieldTypes $types,
        private Urls $urls,
        private Calendar $calendar,
        private Config $config,
        private ObjectTags $tags,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function teaser(EventView $event, string $language, bool $image = true): array
    {
        return [
            'id' => $event->id,
            // For object_field() and object_value() (decision 0240).
            'target' => EventStore::target($event->id),
            'title' => $event->title,
            'teaser' => $event->teaser,
            'venue' => $event->venue,
            'url' => $this->url($event, $language),
            'start' => self::machine($event->start, $event->allDay),
            'end' => null === $event->end ? null : self::machine($event->end, $event->allDay),
            'day' => self::pattern($event->start, $language, 'd'),
            'month' => self::pattern($event->start, $language, 'MMM'),
            'when' => self::when($event, $language),
            'image' => $image ? $this->image($event, '3:2', self::TEASER_SIZES, false) : '',
            'categories' => array_map(static fn(Category $category): array => [
                'id' => $category->id,
                'title' => $category->title($event->language) ?? '',
                'slug' => $category->slug($event->language) ?? '',
            ], $event->categories),
            // Internal ones never (decision 0239).
            'tags' => $this->tags->visible($event->tags, $event->language),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(EventView $event, string $language): array
    {
        return [
            ...$this->teaser($event, $language),
            'address' => $event->address,
            'coordinates' => $event->coordinates,
            'image' => $this->image($event, null, self::DETAIL_SIZES, true),
            'text' => $this->types->get('richtext')->output($event->text, new Field('text', 'richtext', 'Text')),
            'calendar' => $this->calendar->url($event->id, $event->language),
            'data' => new Markup(self::json($this->structured($event, $language)), 'UTF-8'),
        ];
    }

    public function url(EventView $event, string $language): ?string
    {
        return $this->urls->href(EventStore::target($event->id), $language) ?? $this->urls->href(EventStore::target($event->id), $event->language);
    }

    /**
     * When it takes place in the words of the language, e.g.
     * "5. Dezember 2026, 18:00 – 20:00" or "5. – 7. Dezember 2026".
     */
    public static function when(EventView $event, string $language): string
    {
        $start = $event->start;
        $end = $event->end;

        if ($event->allDay) {
            $first = self::format($start, $language, IntlDateFormatter::LONG, IntlDateFormatter::NONE);

            return null === $end || $end->format('Y-m-d') === $start->format('Y-m-d') ? $first : $first . ' – ' . self::format($end, $language, IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        }

        $begins = self::format($start, $language, IntlDateFormatter::LONG, IntlDateFormatter::SHORT);

        if (null === $end) {
            return $begins;
        }

        $sameDay = $end->format('Y-m-d') === $start->format('Y-m-d');

        return $begins . ' – ' . self::format($end, $language, $sameDay ? IntlDateFormatter::NONE : IntlDateFormatter::LONG, IntlDateFormatter::SHORT);
    }

    /**
     * The date in the words of the language, e.g. "2. November 2026".
     */
    public static function date(DateTimeInterface $date, string $language): string
    {
        return self::format($date, $language, IntlDateFormatter::LONG, IntlDateFormatter::NONE);
    }

    /**
     * An event for search engines (schema.org): times, place and image.
     *
     * @return array<string, mixed>
     */
    private function structured(EventView $event, string $language): array
    {
        $position = '' === $event->coordinates ? null : EventEditor::position($event->coordinates);
        $place = '' === $event->venue && '' === $event->address ? null : array_filter([
            '@type' => 'Place',
            'name' => '' === $event->venue ? null : $event->venue,
            'address' => '' === $event->address ? null : $event->address,
            'geo' => null === $position ? null : ['@type' => 'GeoCoordinates', 'latitude' => $position[0], 'longitude' => $position[1]],
        ], static fn(mixed $value): bool => null !== $value);
        // The address of the website from the config: blocks do not know the request.
        $site = $this->config->string('app.url', '');
        $site = '' === $site ? null : rtrim($site, '/');
        $url = $this->urls->absolute(EventStore::target($event->id), $language, $site) ?? $this->urls->absolute(EventStore::target($event->id), $event->language, $site);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'description' => '' === $event->teaser ? null : $event->teaser,
            'startDate' => self::machine($event->start, $event->allDay),
            'endDate' => null === $event->end ? null : self::machine($event->end, $event->allDay),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => $place,
            'url' => $url,
        ], static fn(mixed $value): bool => null !== $value);
    }

    /**
     * For the datetime attribute: the day of an event all day long, else
     * the moment.
     */
    private static function machine(DateTimeImmutable $moment, bool $allDay): string
    {
        return $allDay ? $moment->format('Y-m-d') : $moment->format(DateTimeInterface::ATOM);
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
            IntlDateFormatter::NONE === $timeType => 'Y-m-d',
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

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data): string
    {
        // "</" cannot end the script early.
        return str_replace('</', '<\/', json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
    }

    private function image(EventView $event, ?string $ratio, string $sizes, bool $eager): Markup|string
    {
        if (null === $event->image) {
            return '';
        }

        $output = $this->types->get('image')->output($event->image, new Field('image', 'image', 'Image', settings: array_filter(['ratio' => $ratio, 'sizes' => $sizes, 'eager' => $eager], static fn(mixed $value): bool => null !== $value)));

        return $output instanceof Markup ? $output : (\is_string($output) ? $output : '');
    }
}

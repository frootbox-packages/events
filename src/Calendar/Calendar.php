<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Url\Urls;
use Frootbox\Events\Event\EventEditor;
use Frootbox\Events\Event\EventStore;
use Frootbox\Events\Event\EventView;
use Frootbox\Events\Event\PublishedEvents;

/**
 * One published event as an iCalendar file (RFC 5545), to put it into a
 * calendar app (decision 0227).
 */
final readonly class Calendar
{
    public const string ROUTE = '/_events/calendar/{id:[0-7][0-9A-HJKMNP-TV-Z]{25}}/{language:[a-z]{2,3}(?:-[A-Za-z0-9]{1,8})*}.ics';

    public function __construct(
        private PublishedEvents $events,
        private Urls $urls,
        private Languages $languages,
    ) {}

    public function url(string $id, string $language): string
    {
        return '/_events/calendar/' . $id . '/' . $language . '.ics';
    }

    /**
     * The file of a published event, null if there is none in the language.
     */
    public function ics(string $id, string $language, ?string $site, ?DateTimeImmutable $now = null): ?string
    {
        $event = $this->languages->has($language) ? $this->events->find($id, $language) : null;

        if (null === $event) {
            return null;
        }

        $host = parse_url($site ?? '', \PHP_URL_HOST);
        $url = $this->urls->absolute(EventStore::target($id), $language, $site);
        $location = implode(', ', array_filter([$event->venue, $event->address], static fn(string $part): bool => '' !== $part));
        $position = '' === $event->coordinates ? null : EventEditor::position($event->coordinates);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//frootbox/events//' . strtoupper($language),
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $id . '@' . (\is_string($host) && '' !== $host ? $host : 'localhost'),
            'DTSTAMP:' . self::utc($now ?? new DateTimeImmutable()),
            ...self::times($event),
            'SUMMARY:' . self::text($event->title),
            ...('' === $event->teaser ? [] : ['DESCRIPTION:' . self::text($event->teaser)]),
            ...('' === $location ? [] : ['LOCATION:' . self::text($location)]),
            ...(null === $position ? [] : ['GEO:' . $position[0] . ';' . $position[1]]),
            ...(null === $url ? [] : ['URL:' . $url]),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    /**
     * @return list<string>
     */
    private static function times(EventView $event): array
    {
        if ($event->allDay) {
            // The end of a day is the start of the next one.
            $last = $event->end ?? $event->start;

            return ['DTSTART;VALUE=DATE:' . $event->start->format('Ymd'), 'DTEND;VALUE=DATE:' . $last->modify('+1 day')->format('Ymd')];
        }

        return ['DTSTART:' . self::utc($event->start), ...(null === $event->end ? [] : ['DTEND:' . self::utc($event->end)])];
    }

    private static function utc(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private static function text(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /**
     * Lines of at most 75 octets, continued with a space, without cutting
     * a character in two.
     */
    private static function fold(string $line): string
    {
        $parts = [];

        while (\strlen($line) > 75) {
            $cut = mb_strcut($line, 0, [] === $parts ? 75 : 74, 'UTF-8');
            $parts[] = $cut;
            $line = substr($line, \strlen($cut));
        }

        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}

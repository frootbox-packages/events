<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Frootbox\Core\Plugin\Space;

/**
 * An event as its draft or published version holds it.
 */
final readonly class EventData
{
    /**
     * @param DateTimeImmutable|null     $end          Left out for an event without a known end; on a day of its own for events over several days
     * @param bool                       $allDay       Without times: start and end count as days
     * @param string                     $address      One line, e.g. "Hafenstraße 12, 20457 Hamburg"
     * @param string                     $coordinates  "53.5444, 9.9663" for the map, or empty
     * @param array<mixed>|null          $image        The value of an image field: {"media": "01J…", "alt": "…"}
     * @param list<string>               $categories   Ids of the categories
     * @param array<string, EventText>   $translations By language
     */
    public function __construct(
        public DateTimeImmutable $start,
        public ?DateTimeImmutable $end = null,
        public bool $allDay = false,
        public string $address = '',
        public string $coordinates = '',
        public ?array $image = null,
        public array $categories = [],
        public array $translations = [],
        /** The space of the events it belongs to (decision 0232) */
        public string $space = Space::DEFAULT,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $translations = [];

        foreach (\is_array($data['translations'] ?? null) ? $data['translations'] : [] as $language => $text) {
            if (\is_array($text)) {
                $translations[(string) $language] = EventText::fromArray($text);
            }
        }

        return new self(
            self::moment($data['start'] ?? null) ?? self::local(new DateTimeImmutable()),
            self::moment($data['end'] ?? null),
            true === ($data['allDay'] ?? false),
            \is_string($data['address'] ?? null) ? $data['address'] : '',
            \is_string($data['coordinates'] ?? null) ? $data['coordinates'] : '',
            \is_array($data['image'] ?? null) ? $data['image'] : null,
            array_values(array_filter(\is_array($data['categories'] ?? null) ? $data['categories'] : [], \is_string(...))),
            $translations,
            \is_string($data['space'] ?? null) && '' !== $data['space'] ? $data['space'] : Space::DEFAULT,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->format(DateTimeInterface::ATOM),
            'end' => $this->end?->format(DateTimeInterface::ATOM),
            'allDay' => $this->allDay,
            'address' => $this->address,
            'coordinates' => $this->coordinates,
            'image' => $this->image,
            'categories' => $this->categories,
            'translations' => array_map(static fn(EventText $text): array => $text->toArray(), $this->translations),
            'space' => $this->space,
        ];
    }

    /**
     * The moment the event is over: its end, else its start; an event
     * all day long lasts until the end of its last day.
     */
    public function until(): DateTimeImmutable
    {
        $until = $this->end ?? $this->start;

        return $this->allDay ? $until->setTime(23, 59, 59) : $until;
    }

    /**
     * The id of the image, if any.
     */
    public function media(): ?string
    {
        return \is_string($this->image['media'] ?? null) ? $this->image['media'] : null;
    }

    /**
     * A moment in the time zone of the website, where days begin and end
     * for events all day long.
     */
    public static function local(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    private static function moment(mixed $value): ?DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return self::local(new DateTimeImmutable($value));
        } catch (Exception) {
            return null;
        }
    }
}

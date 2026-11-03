<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\ListOf;
use Frootbox\Database\Ulid;

/**
 * @implements Action<EventDetails>
 */
#[AsAction('frootbox/events/event.create', 'Creates an event as a draft; frootbox/events/event.publish puts it online')]
final readonly class CreateEvent implements Action
{
    /**
     * @param array<mixed>      $translations
     * @param array<mixed>|null $image
     * @param list<string>      $categories
     */
    public function __construct(
        #[Description('Title, slug, teaser, venue and text by language: {"de": {"title": "Röstkurs", "venue": "Rösterei", "teaser": "…", "text": {"type": "doc", "content": []}}}. The slug is made from the title if left out.')]
        public array $translations,
        #[Description('When it begins, e.g. "2026-12-05T18:00:00+01:00"; for an event all day long the day counts')]
        public DateTimeImmutable $start,
        #[Description('When it ends, left out if unknown; for an event all day long its last day')]
        public ?DateTimeImmutable $end = null,
        #[Description('All day long, without times')]
        public bool $allDay = false,
        #[Length(max: EventEditor::ADDRESS_LENGTH)]
        #[Description('The address in one line, e.g. "Hafenstraße 12, 20457 Hamburg"')]
        public string $address = '',
        #[Description('Latitude and longitude for the map, e.g. "53.5444, 9.9663"')]
        public string $coordinates = '',
        #[Description('An image from the media library: {"media": "<id>", "alt": "…"}')]
        public ?array $image = null,
        #[ListOf('string')]
        #[Description('Ids of categories')]
        public array $categories = [],
    ) {}

    public function handle(EventEditor $editor): EventDetails
    {
        $data = new EventData(
            EventData::local($this->start),
            null === $this->end ? null : EventData::local($this->end),
            $this->allDay,
            trim($this->address),
            trim($this->coordinates),
            [] === $this->image ? null : $this->image,
            $editor->categories('frootbox/events/event.create', $this->categories),
            $editor->translations('frootbox/events/event.create', $this->translations),
        );

        return $editor->saveDraft('frootbox/events/event.create', Ulid::generate(), $data);
    }
}

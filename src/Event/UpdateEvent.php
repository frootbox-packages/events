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
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Database\Ulid;

/**
 * @implements Action<EventDetails>
 */
#[AsAction('frootbox/events/event.update', 'Changes the draft of an event; what is left out stays as it is')]
final readonly class UpdateEvent implements Action
{
    /**
     * @param array<mixed>|null $translations
     * @param array<mixed>|null $image
     * @param list<string>|null $categories
     */
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
        #[Description('Changes by language, e.g. {"de": {"title": "Röstkurs"}, "en": null}: fields left out stay, null removes a language')]
        public ?array $translations = null,
        #[Description('When it begins. Start, end and allDay are set together: with a start, a missing end means none; without a start all three stay')]
        public ?DateTimeImmutable $start = null,
        public ?DateTimeImmutable $end = null,
        public ?bool $allDay = null,
        #[Length(max: EventEditor::ADDRESS_LENGTH)]
        public ?string $address = null,
        #[Description('Latitude and longitude for the map, e.g. "53.5444, 9.9663"; "" removes them')]
        public ?string $coordinates = null,
        #[Description('An image from the media library: {"media": "<id>", "alt": "…"}; {} removes it, left out keeps it')]
        public ?array $image = null,
        #[ListOf('string')]
        #[Description('Ids of categories, replacing the ones before')]
        public ?array $categories = null,
    ) {}

    public function handle(EventEditor $editor, EventStore $store): EventDetails
    {
        $current = $store->current($this->id);
        $timed = null !== $this->start;
        $data = new EventData(
            null === $this->start ? $current->start : EventData::local($this->start),
            $timed ? (null === $this->end ? null : EventData::local($this->end)) : $current->end,
            $timed ? $this->allDay ?? false : $this->allDay ?? $current->allDay,
            null === $this->address ? $current->address : trim($this->address),
            null === $this->coordinates ? $current->coordinates : trim($this->coordinates),
            match (true) {
                null === $this->image => $current->image,
                [] === $this->image => null,
                default => $this->image,
            },
            null === $this->categories ? $current->categories : $editor->categories('frootbox/events/event.update', $this->categories),
            null === $this->translations ? $current->translations : $editor->translations('frootbox/events/event.update', $this->translations, $current->translations),
        );

        return $editor->saveDraft('frootbox/events/event.update', $this->id, $data);
    }
}

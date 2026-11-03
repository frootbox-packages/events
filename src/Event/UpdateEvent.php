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
     * @param list<string>|null $tags
     * @param array<mixed>|null $fields
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
        #[ListOf('string')]
        #[Description('Ids, keys or labels of tags, replacing the ones before; a new word becomes a tag unless only presets count')]
        public ?array $tags = null,
        #[Description('Further fields the views of the theme add, by name; a text one per language, e.g. {"subtitle": {"de": "…"}}, null removes one, the others stay; see object.fields; left out keeps them')]
        public ?array $fields = null,
        #[Description('The space to move it to; with publishing its address moves below the page of that space and the old one redirects')]
        public ?string $space = null,
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
            $this->space ?? $current->space,
            null === $this->tags ? $current->tags : $editor->tags('frootbox/events/event.update', $this->space ?? $current->space, $this->tags),
            null === $this->fields ? $current->fields : $editor->fields('frootbox/events/event.update', $current->fields, $this->fields),
        );

        return $editor->saveDraft('frootbox/events/event.update', $this->id, $data);
    }
}

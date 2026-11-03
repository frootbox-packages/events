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
use Frootbox\Core\Auth\Scope;
use Frootbox\Core\Auth\Scoped;
use Frootbox\Core\Plugin\Space;
use Frootbox\Core\Plugin\SpaceScopes;
use Frootbox\Database\Ulid;

/**
 * @implements Action<EventDetails>
 */
#[AsAction('frootbox/events/event.create', 'Creates an event as a draft; frootbox/events/event.publish puts it online')]
final readonly class CreateEvent implements Action, Scoped
{
    /**
     * @param array<mixed>      $translations
     * @param array<mixed>|null $image
     * @param list<string>      $categories
     * @param list<string>      $tags
     * @param array<mixed>      $fields
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
        #[Length(1, 64)]
        #[Description('The space of the events, "default" for the default one; see space.list')]
        public string $space = Space::DEFAULT,
        #[ListOf('string')]
        #[Description('Ids, keys or labels of tags; a new word becomes a tag unless only presets count, see tag.choices')]
        public array $tags = [],
        #[Description('Further fields the views of the theme add, by name; a text one per language, e.g. {"subtitle": {"de": "…"}}, null removes one; see object.fields')]
        public array $fields = [],
    ) {}

    /**
     * The space it is created in (decision 0245).
     *
     * @return list<Scope>
     */
    public function scopes(): array
    {
        return [SpaceScopes::space(EventStore::TYPE, $this->space)];
    }

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
            $this->space,
            $editor->tags('frootbox/events/event.create', $this->space, $this->tags),
            $editor->fields('frootbox/events/event.create', [], $this->fields),
        );

        return $editor->saveDraft('frootbox/events/event.create', Ulid::generate(), $data);
    }
}

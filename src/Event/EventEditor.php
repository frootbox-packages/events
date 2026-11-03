<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Category\ObjectCategories;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Media\MediaEditor;
use Frootbox\Core\Page\Slug;
use Frootbox\Core\Plugin\PluginSpaces;
use Frootbox\Core\Revision\RevisionStore;
use Frootbox\Core\Support\Message;
use Frootbox\Core\Tag\ObjectTags;
use Frootbox\Core\Url\AddressSync;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\RichText\RichText;

/**
 * Changes events like PageEditor changes pages (decisions 0219, 0227):
 * every change goes into the draft, publishing writes it into the
 * tables. The actions of the events call it.
 */
final readonly class EventEditor
{
    public const int TITLE_LENGTH = 300;

    public const int TEASER_LENGTH = 1000;

    public const int VENUE_LENGTH = 200;

    public const int ADDRESS_LENGTH = 300;

    /** "53.5444, 9.9663": latitude and longitude with up to seven decimals. */
    public const string COORDINATES_PATTERN = '/^\s*(-?\d{1,2}(?:\.\d{1,7})?)\s*,\s*(-?\d{1,3}(?:\.\d{1,7})?)\s*$/';

    private const string MEDIA_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    public function __construct(
        private EventStore $store,
        private RevisionStore $revisions,
        private Events $events,
        private EventTranslations $translations,
        private ObjectCategories $categories,
        private ObjectTags $tags,
        private MediaEditor $media,
        private RichText $richText,
        private AddressSync $addresses,
        private Languages $languages,
        private PluginSpaces $spaces,
    ) {}

    /**
     * Checks a draft and saves it. Drafts may be unfinished: an empty
     * title only counts when publishing (decision 0101).
     *
     * @throws InvalidInputException
     */
    public function saveDraft(string $action, string $id, EventData $data): EventDetails
    {
        $errors = $this->check($id, $data, false);

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        $this->revisions->saveDraft(EventStore::TYPE, $id, $data->toArray());

        return $this->store->details($id);
    }

    /**
     * Applies translations from an action to the current ones. A language
     * set to null is removed; fields left out are kept. A new language
     * without a slug gets one from its title.
     *
     *     {"de": {"title": "Neue Sorten", "teaser": "…"}, "en": null}
     *
     * @param array<mixed>               $input
     * @param array<string, EventText> $current
     *
     * @return array<string, EventText>
     *
     * @throws InvalidInputException
     */
    public function translations(string $action, array $input, array $current = []): array
    {
        if ([] !== $input && array_is_list($input)) {
            throw new InvalidInputException($action, ['/translations' => 'must be an object by language code, e.g. {"de": {"title": "Neue Sorten"}}.']);
        }

        $errors = [];

        foreach ($input as $language => $value) {
            $language = (string) $language;
            $at = '/translations/' . $language;

            if (null === $value) {
                unset($current[$language]);

                continue;
            }

            if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
                $errors[$at] = 'must be an object with "title" and optionally "slug", "teaser", "venue" and "text".';

                continue;
            }

            foreach (['title', 'slug', 'teaser', 'venue'] as $key) {
                if (\array_key_exists($key, $value) && !\is_string($value[$key])) {
                    $errors[$at . '/' . $key] = 'must be a text.';
                }
            }

            if (\array_key_exists('text', $value) && !\is_array($value['text'])) {
                $errors[$at . '/text'] = 'must be rich text as Tiptap JSON, e.g. {"type": "doc", "content": []}.';
            }

            if (isset($errors[$at . '/title']) || isset($errors[$at . '/slug']) || isset($errors[$at . '/teaser']) || isset($errors[$at . '/venue']) || isset($errors[$at . '/text'])) {
                continue;
            }

            $before = $current[$language] ?? null;
            $title = \is_string($value['title'] ?? null) ? trim($value['title']) : $before->title ?? '';
            $slug = \is_string($value['slug'] ?? null) ? trim($value['slug']) : $before->slug ?? '';

            if ('' === $slug && (null === $before || !\array_key_exists('slug', $value))) {
                $slug = Slug::fromTitle($title, $language);
            }

            $current[$language] = new EventText(
                $title,
                $slug,
                \is_string($value['teaser'] ?? null) ? trim($value['teaser']) : $before->teaser ?? '',
                \is_string($value['venue'] ?? null) ? trim($value['venue']) : $before->venue ?? '',
                \is_array($value['text'] ?? null) ? $value['text'] : $before->text ?? EventText::EMPTY_TEXT,
            );
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        return $current;
    }

    /**
     * Publishes the drafts of events together.
     *
     * @param list<string> $ids
     *
     * @return list<EventDetails>
     *
     * @throws InvalidInputException
     */
    public function publish(string $action, array $ids): array
    {
        $ids = array_values(array_unique($ids));

        if ([] === $ids) {
            throw new InvalidInputException($action, ['/ids' => 'needs at least one event id.']);
        }

        $errors = [];
        $drafts = [];

        foreach ($ids as $index => $id) {
            $draft = $this->store->draft($id);

            if (null === $draft) {
                $errors['/ids/' . $index] = \sprintf('The event %s has no draft to publish.', $id);

                continue;
            }

            $drafts[$id] = $draft;

            foreach ($this->check($id, $draft, true, $ids) as $pointer => $message) {
                $errors['/ids/' . $index . $pointer] = $message;
            }
        }

        // Two events of the same batch may not take the same address.
        $taken = [];

        foreach ($drafts as $id => $draft) {
            foreach ($draft->translations as $language => $text) {
                $key = $language . '|' . $text->slug;

                if (isset($taken[$key])) {
                    $errors['/ids/' . array_search($id, $ids, true) . '/translations/' . $language . '/slug'] = new Message('Another event has the slug "{slug}" already.', ['slug' => $text->slug]);
                }

                $taken[$key] = true;
            }
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        $media = [];

        foreach ($drafts as $id => $draft) {
            $this->write($id, $draft);
            $this->revisions->publish(EventStore::TYPE, $id);

            if (null !== $draft->media()) {
                $media[] = $draft->media();
            }
        }

        // Media become public with the first published event that uses them.
        $this->media->makePublic($media);
        $this->addresses->sync();

        return array_map($this->store->details(...), $ids);
    }

    /**
     * Takes an event offline; it stays as a draft.
     *
     * @throws InvalidInputException
     */
    public function unpublish(string $action, string $id): EventDetails
    {
        $published = $this->store->published($id) ?? throw new InvalidInputException($action, ['/id' => \sprintf('The event %s is not published.', $id)]);

        if (null === $this->store->draft($id)) {
            $this->revisions->saveDraft(EventStore::TYPE, $id, $published->toArray());
        }

        $this->removeRows($id);
        $this->revisions->archive(EventStore::TYPE, $id);
        $this->addresses->sync();

        return $this->store->details($id);
    }

    /**
     * Removes the draft. An event that was never published is gone then.
     *
     * @return EventDetails|null null if the event is gone
     *
     * @throws InvalidInputException
     */
    public function discard(string $action, string $id): ?EventDetails
    {
        if (null === $this->store->draft($id)) {
            throw new InvalidInputException($action, ['/id' => 'The event has no draft.']);
        }

        $this->revisions->discard(EventStore::TYPE, $id);

        return null === $this->store->published($id) ? null : $this->store->details($id);
    }

    /**
     * Deletes the published state and the draft. The versions stay.
     *
     * @throws EntityNotFoundException
     */
    public function delete(string $id): void
    {
        $this->store->details($id);
        $published = null !== $this->store->published($id);

        $this->removeRows($id);
        $this->revisions->discard(EventStore::TYPE, $id);
        $this->revisions->archive(EventStore::TYPE, $id);

        if ($published) {
            $this->addresses->sync();
        }
    }

    /**
     * Plans when the draft is published and when the event goes offline.
     *
     * @throws InvalidInputException
     * @throws EntityNotFoundException
     */
    public function schedule(string $action, string $id, ?DateTimeImmutable $publishAt, ?DateTimeImmutable $unpublishAt): EventDetails
    {
        $this->store->details($id);
        $errors = [];

        if (!$this->revisions->publishAt(EventStore::TYPE, $id, $publishAt) && null !== $publishAt) {
            $errors['/publishAt'] = 'The event has no draft to publish.';
        }

        if (!$this->revisions->unpublishAt(EventStore::TYPE, $id, $unpublishAt) && null !== $unpublishAt) {
            $errors['/unpublishAt'] = 'The event is not published.';
        }

        if (null !== $publishAt && null !== $unpublishAt && $unpublishAt <= $publishAt) {
            $errors['/unpublishAt'] = 'must be after "publishAt".';
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        return $this->store->details($id);
    }

    /**
     * The categories of an action's input, each must be one of a group
     * for events (decision 0233). Categories deleted later simply drop
     * out when publishing.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     *
     * @throws InvalidInputException
     */
    public function categories(string $action, array $ids): array
    {
        return $this->categories->check($action, EventStore::TYPE, $ids);
    }

    /**
     * The tags of an action's input (decision 0239): ids, keys or labels;
     * presets of the space and new words become tags here, unless only
     * the presets count.
     *
     * @param list<string> $entries
     *
     * @return list<string>
     *
     * @throws InvalidInputException
     */
    public function tags(string $action, string $space, array $entries): array
    {
        return $this->tags->check($action, EventStore::TYPE, $space, $entries);
    }

    /**
     * The errors of an event by JSON pointer.
     *
     * @param list<string> $batch Events published together, their slugs are checked among themselves
     *
     * @return array<string, string|Message>
     */
    private function check(string $id, EventData $data, bool $publishing, array $batch = []): array
    {
        $errors = [];

        if ([] === $data->translations) {
            $errors['/translations'] = 'needs at least one language, e.g. {"de": {"title": "Neue Sorten"}}.';
        }

        $slugs = $this->slugs([$id, ...$batch], $publishing);

        foreach ($data->translations as $language => $text) {
            $at = '/translations/' . $language;

            if (!$this->languages->has($language)) {
                $errors[$at] = new Message('"{language}" is no language of the website.', ['language' => $language]);

                continue;
            }

            if ('' === $text->title && $publishing) {
                $errors[$at . '/title'] = 'is required.';
            } elseif (mb_strlen($text->title) > self::TITLE_LENGTH) {
                $errors[$at . '/title'] = new Message('must be at most {max} characters.', ['max' => self::TITLE_LENGTH]);
            }

            if ('' === $text->slug) {
                if ($publishing || '' !== $text->title) {
                    $errors[$at . '/slug'] = 'is required.';
                }
            } elseif (mb_strlen($text->slug) > Slug::MAX_LENGTH || 1 !== preg_match('/' . Slug::PATTERN . '/u', $text->slug)) {
                $errors[$at . '/slug'] = new Message('must be at most {max} lower-case letters, digits and hyphens, e.g. "neue-sorten".', ['max' => Slug::MAX_LENGTH]);
            } elseif (isset($slugs[$language . '|' . $text->slug])) {
                $errors[$at . '/slug'] = new Message('Another event has the slug "{slug}" already.', ['slug' => $text->slug]);
            }

            if (mb_strlen($text->teaser) > self::TEASER_LENGTH) {
                $errors[$at . '/teaser'] = new Message('must be at most {max} characters.', ['max' => self::TEASER_LENGTH]);
            }

            if (mb_strlen($text->venue) > self::VENUE_LENGTH) {
                $errors[$at . '/venue'] = new Message('must be at most {max} characters.', ['max' => self::VENUE_LENGTH]);
            }

            foreach ($this->richText->validate($text->text) as $violation) {
                $errors[$at . '/text' . $violation->pointer] = $violation->message;
            }
        }

        if (null !== $data->end && $data->end < ($data->allDay ? $data->start->setTime(0, 0) : $data->start)) {
            $errors['/end'] = 'must not be before "start".';
        }

        if (mb_strlen($data->address) > self::ADDRESS_LENGTH) {
            $errors['/address'] = new Message('must be at most {max} characters.', ['max' => self::ADDRESS_LENGTH]);
        }

        if ('' !== $data->coordinates && null === self::position($data->coordinates)) {
            $errors['/coordinates'] = 'must be latitude and longitude, e.g. "53.5444, 9.9663".';
        }

        if (!$this->spaces->exists(EventStore::TYPE, $data->space)) {
            $errors['/space'] = 'There is no such space of the events.';
        }

        if (null !== $data->image && (null === $data->media() || 1 !== preg_match(self::MEDIA_PATTERN, $data->media()))) {
            $errors['/image'] = 'must be an image as {"media": "<id>", "alt": "…"}.';
        }

        return $errors;
    }

    /**
     * The slugs other events hold, as "language|slug": when publishing
     * those that are published, else also those of drafts.
     *
     * @param list<string> $except
     *
     * @return array<string, true>
     */
    private function slugs(array $except, bool $published): array
    {
        $slugs = [];

        foreach ($this->store->ids() as $id) {
            if (\in_array($id, $except, true)) {
                continue;
            }

            foreach (array_filter([$this->store->published($id), $published ? null : $this->store->draft($id)]) as $data) {
                foreach ($data->translations as $language => $text) {
                    $slugs[$language . '|' . $text->slug] = true;
                }
            }
        }

        return $slugs;
    }

    private function write(string $id, EventData $data): void
    {
        $values = [
            'startsAt' => $data->start,
            'endsAt' => $data->end,
            'allDay' => $data->allDay,
            'lastsUntil' => $data->until(),
            'address' => $data->address,
            'coordinates' => $data->coordinates,
            'image' => $data->image,
            'space' => $data->space,
        ];

        if (null === $this->events->fetchById($id)) {
            $this->events->insert(['id' => $id, ...$values]);
        } else {
            $this->events->update($id, $values);
        }

        $existing = $this->translations->of($id);

        foreach ($data->translations as $language => $text) {
            $row = ['title' => $text->title, 'slug' => $text->slug, 'teaser' => $text->teaser, 'venue' => $text->venue, 'text' => $text->text];

            if (isset($existing[$language])) {
                $this->translations->update($existing[$language]->id, $row);
            } else {
                $this->translations->insert(['eventId' => $id, 'language' => $language, ...$row]);
            }
        }

        foreach (array_diff_key($existing, $data->translations) as $removed) {
            $this->translations->delete($removed->id);
        }

        $this->categories->sync(EventStore::TYPE, $id, $data->categories);
        $this->tags->sync(EventStore::TYPE, $id, $data->tags);
    }

    private function removeRows(string $id): void
    {
        foreach ($this->translations->of($id) as $translation) {
            $this->translations->delete($translation->id);
        }

        $this->categories->clear(EventStore::TYPE, $id);
        $this->tags->clear(EventStore::TYPE, $id);
        $this->events->delete($id);
    }

    /**
     * Latitude and longitude of "53.5444, 9.9663", null if it is none.
     *
     * @return array{float, float}|null
     */
    public static function position(string $coordinates): ?array
    {
        if (1 !== preg_match(self::COORDINATES_PATTERN, $coordinates, $match)) {
            return null;
        }

        [$latitude, $longitude] = [(float) $match[1], (float) $match[2]];

        return abs($latitude) <= 90 && abs($longitude) <= 180 ? [$latitude, $longitude] : null;
    }
}

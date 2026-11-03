<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use Frootbox\Core\Revision\Revisions;
use Frootbox\Core\Revision\RevisionStatus;
use Frootbox\Core\Url\Urls;
use Frootbox\Database\EntityNotFoundException;

/**
 * Reads the drafts and published versions of events from revisions.
 */
final readonly class EventStore
{
    public const string TYPE = 'frootbox/events/event';

    public function __construct(
        private Revisions $revisions,
        private Urls $urls,
    ) {}

    public static function target(string $id): string
    {
        return self::TYPE . ':' . $id;
    }

    public function draft(string $id): ?EventData
    {
        $revision = $this->revisions->draft(self::TYPE, $id);

        return null === $revision ? null : EventData::fromArray($revision->data);
    }

    public function published(string $id): ?EventData
    {
        $revision = $this->revisions->published(self::TYPE, $id);

        return null === $revision ? null : EventData::fromArray($revision->data);
    }

    /**
     * The draft, else the published version.
     *
     * @throws EntityNotFoundException
     */
    public function current(string $id): EventData
    {
        return $this->draft($id) ?? $this->published($id) ?? throw EntityNotFoundException::for(Events::TABLE, $id);
    }

    /**
     * @throws EntityNotFoundException
     */
    public function details(string $id, bool $urls = false): EventDetails
    {
        $draft = $this->revisions->draft(self::TYPE, $id);
        $published = $this->revisions->published(self::TYPE, $id);

        if (null === $draft && null === $published) {
            throw EntityNotFoundException::for(Events::TABLE, $id);
        }

        $status = match (true) {
            null === $published => EventStatus::Draft,
            null === $draft => EventStatus::Published,
            default => EventStatus::Changed,
        };
        $data = null === $published ? null : EventData::fromArray($published->data);
        $addresses = [];

        foreach ($urls && null !== $data ? array_keys($data->translations) : [] as $language) {
            $path = $this->urls->path(self::target($id), $language);

            if (null !== $path) {
                $addresses[$language] = $path;
            }
        }

        return new EventDetails(
            $id,
            $status,
            $published?->version,
            null === $draft ? null : EventData::fromArray($draft->data),
            $data,
            $draft?->publishAt,
            $published?->unpublishAt,
            $addresses,
        );
    }

    /**
     * The ids of all events with a draft or a published version.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        $ids = [];

        foreach ($this->revisions->fetch(['where' => ['type' => self::TYPE, 'status' => [RevisionStatus::Draft->value, RevisionStatus::Published->value]], 'order' => 'id']) as $revision) {
            $ids[$revision->entityId] = true;
        }

        return array_keys($ids);
    }
}

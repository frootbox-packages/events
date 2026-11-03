<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;

/**
 * An event for editors: its draft and its published version.
 */
final readonly class EventDetails
{
    /**
     * @param array<string, string> $urls Where visitors find it by language, e.g. {"de": "/aktuelles/neue-sorten"}
     */
    public function __construct(
        public string $id,
        public EventStatus $status,
        public ?int $version,
        public ?EventData $draft,
        public ?EventData $published,
        public ?DateTimeImmutable $publishAt = null,
        public ?DateTimeImmutable $unpublishAt = null,
        public array $urls = [],
    ) {}

    /**
     * The draft, else the published version.
     */
    public function current(): EventData
    {
        return $this->draft ?? $this->published ?? new EventData(new DateTimeImmutable());
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use Frootbox\Database\Entity;

/**
 * The texts of a published event in one language.
 */
final readonly class EventTranslation extends Entity
{
    /**
     * @param array<mixed> $text Rich text as Tiptap JSON
     */
    public function __construct(
        public int $id,
        public string $eventId,
        public string $language,
        public string $title,
        public string $slug,
        public string $teaser,
        public string $venue,
        public array $text,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
    ) {}
}

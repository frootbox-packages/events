<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use Frootbox\Core\Category\Category;

/**
 * A published event in one language, as lists and its page show it.
 */
final readonly class EventView
{
    /**
     * @param array<mixed>|null $image
     * @param array<mixed>      $text
     * @param list<Category>    $categories
     */
    public function __construct(
        public string $id,
        public string $language,
        public DateTimeImmutable $start,
        public ?DateTimeImmutable $end,
        public bool $allDay,
        public string $title,
        public string $slug,
        public string $teaser,
        public string $venue,
        public string $address,
        public string $coordinates,
        public array $text,
        public ?array $image,
        public array $categories,
    ) {}
}

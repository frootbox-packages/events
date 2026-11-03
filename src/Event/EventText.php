<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

/**
 * The texts of an event in one language.
 */
final readonly class EventText
{
    public const array EMPTY_TEXT = ['type' => 'doc', 'content' => []];

    /**
     * @param string       $venue Where it takes place, e.g. "Rösterei"
     * @param array<mixed> $text  Rich text as Tiptap JSON
     */
    public function __construct(
        public string $title,
        public string $slug,
        public string $teaser = '',
        public string $venue = '',
        public array $text = self::EMPTY_TEXT,
    ) {}

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            \is_string($data['title'] ?? null) ? $data['title'] : '',
            \is_string($data['slug'] ?? null) ? $data['slug'] : '',
            \is_string($data['teaser'] ?? null) ? $data['teaser'] : '',
            \is_string($data['venue'] ?? null) ? $data['venue'] : '',
            \is_array($data['text'] ?? null) ? $data['text'] : self::EMPTY_TEXT,
        );
    }

    /**
     * @return array{title: string, slug: string, teaser: string, venue: string, text: array<mixed>}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'slug' => $this->slug, 'teaser' => $this->teaser, 'venue' => $this->venue, 'text' => $this->text];
    }
}

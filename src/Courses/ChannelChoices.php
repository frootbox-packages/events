<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\I18n\Translator;
use Frootbox\Events\Courses\Provider\ChannelChoice;

/**
 * @implements Query<list<array{value: string, label: string}>>
 */
#[AsQuery('frootbox/events/channel.choices', 'The channels of the provider of courses as choices for a block, its own first; empty with only its own', permission: 'frootbox/events/event.list')]
final readonly class ChannelChoices implements Query
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function handle(Courses $courses, Translator $translator): array
    {
        $channels = $courses->channels();

        if ([] === $channels) {
            return [];
        }

        return [
            ['value' => '', 'label' => $translator->get('Default')],
            ...array_map(static fn(ChannelChoice $channel): array => [
                'value' => $channel->key,
                'label' => $channel->restricted ? $translator->get('{title} (only with password)', ['title' => $channel->title]) : $channel->title,
            ], $channels),
        ];
    }
}

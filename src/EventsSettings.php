<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events;

use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Title;
use Frootbox\Core\Settings\AsSettings;

/**
 * The settings of the events (decision 0351).
 */
#[AsSettings('Events', 'Courses with bookings from a provider')]
final readonly class EventsSettings
{
    public function __construct(
        #[Title('Provider of courses'), Description('The class of the provider, e.g. of avaro/events; empty for the only one installed.')]
        #[Length(max: 200)]
        public string $provider = '',
    ) {}
}

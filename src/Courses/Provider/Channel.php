<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * What the provider asks of every booking (decision 0351).
 */
final readonly class Channel
{
    /**
     * @param list<LegalText> $legal
     * @param list<Detail>    $details details the person who books has to give
     */
    public function __construct(
        public string $currency,
        public array $legal = [],
        public array $details = [],
        public int $maxAttendees = 10,
    ) {}

    public function requires(Detail $detail): bool
    {
        return \in_array($detail, $this->details, true);
    }
}

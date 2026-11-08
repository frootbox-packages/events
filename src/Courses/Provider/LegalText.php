<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * A legal text of the provider: a required one is agreed to with its
 * version, the others are a notice. It links to a page or brings its text.
 */
final readonly class LegalText
{
    public function __construct(
        public string $key,
        public string $title,
        public string $version,
        public bool $required,
        public ?string $url = null,
        public ?string $text = null,
    ) {}
}

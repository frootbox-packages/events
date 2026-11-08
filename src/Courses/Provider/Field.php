<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * An extra field a date asks for, e.g. "Costs are paid by the company".
 * A kind the module does not know is null; such a field cannot be filled
 * in, so a date that requires one cannot be booked.
 */
final readonly class Field
{
    /**
     * @param list<FieldOption> $options
     */
    public function __construct(
        public string $key,
        public string $title,
        public ?FieldKind $kind,
        public bool $required = false,
        public array $options = [],
    ) {}
}

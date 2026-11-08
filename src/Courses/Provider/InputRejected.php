<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

use Throwable;

/**
 * The provider rejected the input, with a message per field as a pointer
 * into the booking, e.g. "/booker/email", "/attendees/0/dateOfBirth" or
 * "/fields/<key>".
 */
final class InputRejected extends CoursesException
{
    /**
     * @param array<string, string> $errors message by pointer
     */
    public function __construct(public readonly array $errors, ?Throwable $previous = null)
    {
        parent::__construct(implode(' ', $errors), 422, $previous);
    }
}

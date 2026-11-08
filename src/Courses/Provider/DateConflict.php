<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

use Throwable;

/**
 * The date cannot be booked like this, with the date as it is now if the
 * provider sent it.
 */
abstract class DateConflict extends CoursesException
{
    public function __construct(public readonly ?CourseDate $date = null, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}

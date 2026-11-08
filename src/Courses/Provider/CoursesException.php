<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

use RuntimeException;

/**
 * An error of a provider of courses.
 */
abstract class CoursesException extends RuntimeException {}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * The date cannot be booked, e.g. its booking window closed.
 */
final class NotBookable extends DateConflict {}

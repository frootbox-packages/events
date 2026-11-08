<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * The kinds of extra fields a date asks for.
 */
enum FieldKind: string
{
    case Checkbox = 'checkbox';
    case Text = 'text';
    case Choice = 'choice';
}

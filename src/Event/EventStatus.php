<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

enum EventStatus: string
{
    /** Only a draft, never published. */
    case Draft = 'draft';

    /** Published, without a draft. */
    case Published = 'published';

    /** Published, with a draft of changes. */
    case Changed = 'changed';
}

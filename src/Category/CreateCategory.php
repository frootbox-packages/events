<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Category;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Range;

/**
 * @implements Action<Category>
 */
#[AsAction('frootbox/events/category.create', 'Creates a category of the events')]
final readonly class CreateCategory implements Action
{
    /**
     * @param array<mixed> $translations
     */
    public function __construct(
        #[Description('Title and slug by language: {"de": {"title": "Kaffee"}}; the slug is made from the title if left out; an empty title and slug count as no language')]
        public array $translations,
        #[Range(min: 0)]
        #[Description('Place in the list, 0 for the first; left out for the last')]
        public ?int $position = null,
    ) {}

    public function handle(CategoryEditor $editor): Category
    {
        return $editor->save('frootbox/events/category.create', null, $this->translations, $this->position);
    }
}

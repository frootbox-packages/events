<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Category;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Pattern;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Database\Ulid;

/**
 * @implements Action<Category>
 */
#[AsAction('frootbox/events/category.update', 'Changes a category of the events')]
final readonly class UpdateCategory implements Action
{
    /**
     * @param array<mixed> $translations
     */
    public function __construct(
        #[Pattern(Ulid::PATTERN)]
        public string $id,
        #[Description('Title and slug by language; languages left out stay, null or an empty title and slug remove one')]
        public array $translations = [],
        #[Range(min: 0)]
        public ?int $position = null,
    ) {}

    public function handle(CategoryEditor $editor): Category
    {
        return $editor->save('frootbox/events/category.update', $this->id, $this->translations, $this->position);
    }
}

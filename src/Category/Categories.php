<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Category;

use Frootbox\Database\Repository;

/**
 * @extends Repository<Category>
 */
final class Categories extends Repository
{
    public const string TABLE = 'frootbox_events_categories';

    /**
     * All categories in their order.
     *
     * @return list<Category>
     */
    public function all(): array
    {
        return $this->fetch(['order' => ['position', 'id']])->all();
    }

    /**
     * The category with this slug in a language.
     */
    public function bySlug(string $language, string $slug): ?Category
    {
        foreach ($this->all() as $category) {
            if ($category->slug($language) === $slug) {
                return $category;
            }
        }

        return null;
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Category::class;
    }
}

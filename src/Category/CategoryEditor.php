<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Category;

use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Page\Slug;
use Frootbox\Core\Support\Message;
use Frootbox\Events\Event\EventCategories;

/**
 * Checks and saves categories; they have no draft (decision 0219).
 */
final readonly class CategoryEditor
{
    public const int TITLE_LENGTH = 100;

    public function __construct(
        private Categories $categories,
        private EventCategories $eventCategories,
        private Languages $languages,
    ) {}

    /**
     * @param array<mixed> $translations {"de": {"title": "Kaffee", "slug": "kaffee"}}, the slug from the title if left out
     *
     * @throws InvalidInputException
     */
    public function save(string $action, ?string $id, array $translations, ?int $position): Category
    {
        $current = null === $id ? null : $this->categories->getById($id);
        $checked = $this->check($action, $id, $translations, $current->translations ?? []);
        $all = $this->categories->all();

        if (null === $current) {
            return $this->categories->insert(['translations' => $checked, 'position' => $position ?? \count($all)]);
        }

        return $this->categories->update($current->id, ['translations' => $checked, 'position' => $position ?? $current->position]);
    }

    public function delete(string $id): void
    {
        $this->categories->getById($id);

        foreach ($this->eventCategories->fetch(['where' => ['categoryId' => $id]]) as $row) {
            $this->eventCategories->delete($row->id);
        }

        $this->categories->delete($id);
    }

    /**
     * @param array<mixed> $input
     * @param array<mixed> $current
     *
     * @return array<string, array{title: string, slug: string}>
     *
     * @throws InvalidInputException
     */
    private function check(string $action, ?string $id, array $input, array $current): array
    {
        if ([] !== $input && array_is_list($input)) {
            throw new InvalidInputException($action, ['/translations' => 'must be an object by language code, e.g. {"de": {"title": "Kaffee"}}.']);
        }

        $errors = [];
        $result = [];

        foreach ($current as $language => $value) {
            if (\is_array($value) && \is_string($value['title'] ?? null) && \is_string($value['slug'] ?? null)) {
                $result[(string) $language] = ['title' => $value['title'], 'slug' => $value['slug']];
            }
        }

        foreach ($input as $language => $value) {
            $language = (string) $language;
            $at = '/translations/' . $language;

            // A form sends every language; one left empty is none.
            if (\is_array($value) && '' === trim(\is_string($value['title'] ?? null) ? $value['title'] : '') && '' === trim(\is_string($value['slug'] ?? null) ? $value['slug'] : '')) {
                $value = null;
            }

            if (null === $value) {
                unset($result[$language]);

                continue;
            }

            if (!$this->languages->has($language)) {
                $errors[$at] = new Message('"{language}" is no language of the website.', ['language' => $language]);

                continue;
            }

            $title = \is_array($value) && \is_string($value['title'] ?? null) ? trim($value['title']) : '';
            $slug = \is_array($value) && \is_string($value['slug'] ?? null) && '' !== trim($value['slug']) ? trim($value['slug']) : Slug::fromTitle($title, $language);

            if ('' === $title) {
                $errors[$at . '/title'] = 'is required.';
            } elseif (mb_strlen($title) > self::TITLE_LENGTH) {
                $errors[$at . '/title'] = new Message('must be at most {max} characters.', ['max' => self::TITLE_LENGTH]);
            }

            if (1 !== preg_match('/' . Slug::PATTERN . '/u', $slug) || mb_strlen($slug) > Slug::MAX_LENGTH) {
                $errors[$at . '/slug'] = 'must be lower-case letters, digits and hyphens, e.g. "kaffee".';
            } else {
                foreach ($this->categories->all() as $other) {
                    if ($other->id !== $id && $other->slug($language) === $slug) {
                        $errors[$at . '/slug'] = new Message('Another category has the slug "{slug}" already.', ['slug' => $slug]);
                    }
                }
            }

            $result[$language] = ['title' => $title, 'slug' => $slug];
        }

        if ([] === $result) {
            $first = array_key_first($input);
            $errors[null === $first ? '/translations' : '/translations/' . $first . '/title'] ??= null === $first ? 'needs at least one language, e.g. {"de": {"title": "Kaffee"}}.' : 'is required.';
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }

        return $result;
    }
}

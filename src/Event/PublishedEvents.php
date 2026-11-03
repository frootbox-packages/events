<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Event;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Frootbox\Database\Values;
use Frootbox\Events\Category\Categories;
use Frootbox\Events\Category\Category;

/**
 * The published events for visitors (decision 0227): upcoming ones from
 * the next on, or past ones from the latest back, by language and
 * category, read from the tables. An event is upcoming until it is over.
 * With spaces only the events of these spaces (decision 0232), null for all.
 */
final readonly class PublishedEvents
{
    public function __construct(
        private Connection $connection,
        private Events $events,
        private EventTranslations $translations,
        private EventCategories $eventCategories,
        private Categories $categories,
    ) {}

    /**
     * @param list<string>|null $spaces
     *
     * @return list<EventView>
     */
    public function list(string $language, ?string $category = null, bool $past = false, int $limit = 10, int $offset = 0, ?DateTimeImmutable $now = null, ?array $spaces = null): array
    {
        $query = $this->query($language, $category, $past, $now, $spaces)
            ->select('e.id')
            ->orderBy('e.starts_at', $past ? 'DESC' : 'ASC')
            ->addOrderBy('e.id', $past ? 'DESC' : 'ASC')
            ->setMaxResults(max(1, $limit))
            ->setFirstResult(max(0, $offset));

        $ids = array_values(array_filter($query->executeQuery()->fetchFirstColumn(), \is_string(...)));

        return $this->views($ids, $language);
    }

    /**
     * @param list<string>|null $spaces
     */
    public function count(string $language, ?string $category = null, bool $past = false, ?DateTimeImmutable $now = null, ?array $spaces = null): int
    {
        $count = $this->query($language, $category, $past, $now, $spaces)->select('COUNT(*)')->executeQuery()->fetchOne();

        return is_numeric($count) ? (int) $count : 0;
    }

    public function find(string $id, string $language): ?EventView
    {
        return $this->views([$id], $language)[0] ?? null;
    }

    /**
     * Whether an event ended after one moment and up to another: then
     * lists of upcoming events have changed.
     */
    public function endedBetween(DateTimeImmutable $after, DateTimeImmutable $until): bool
    {
        $found = $this->connection->createQueryBuilder()
            ->select('e.id')
            ->from(Events::TABLE, 'e')
            ->where('e.lasts_until >= :after')
            ->andWhere('e.lasts_until < :until')
            ->setParameter('after', Values::toDatabase($after))
            ->setParameter('until', Values::toDatabase($until))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return \is_string($found);
    }

    /**
     * @param list<string>|null $spaces
     */
    private function query(string $language, ?string $category, bool $past, ?DateTimeImmutable $now, ?array $spaces): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->from(Events::TABLE, 'e')
            ->innerJoin('e', EventTranslations::TABLE, 't', 't.event_id = e.id AND t.language = :language')
            ->andWhere($past ? 'e.lasts_until < :now' : 'e.lasts_until >= :now')
            ->setParameter('language', $language)
            ->setParameter('now', Values::toDatabase($now ?? new DateTimeImmutable()));

        if (null !== $spaces) {
            $query->andWhere($query->expr()->in('e.space', ':spaces'))
                ->setParameter('spaces', [] === $spaces ? [''] : $spaces, ArrayParameterType::STRING);
        }

        if (null !== $category) {
            $query->innerJoin('e', EventCategories::TABLE, 'c', 'c.event_id = e.id AND c.category_id = :category')
                ->setParameter('category', $category);
        }

        return $query;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<EventView>
     */
    private function views(array $ids, string $language): array
    {
        if ([] === $ids) {
            return [];
        }

        $events = [];

        foreach ($this->events->fetch(['where' => ['id' => $ids]]) as $event) {
            $events[$event->id] = $event;
        }

        $texts = [];

        foreach ($this->translations->fetch(['where' => ['eventId' => $ids, 'language' => $language]]) as $translation) {
            $texts[$translation->eventId] = $translation;
        }

        $categories = [];

        foreach ($this->categories->all() as $category) {
            $categories[$category->id] = $category;
        }

        $assigned = [];

        foreach ($this->eventCategories->fetch(['where' => ['eventId' => $ids], 'order' => 'id']) as $row) {
            if (isset($categories[$row->categoryId])) {
                $assigned[$row->eventId][] = $categories[$row->categoryId];
            }
        }

        $views = [];

        foreach ($ids as $id) {
            $event = $events[$id] ?? null;
            $text = $texts[$id] ?? null;

            if (null === $event || null === $text) {
                continue;
            }

            $views[] = new EventView($id, $language, $event->startsAt, $event->endsAt, $event->allDay, $text->title, $text->slug, $text->teaser, $text->venue, $event->address, $event->coordinates, $text->text, $event->image, self::sorted($assigned[$id] ?? []));
        }

        return $views;
    }

    /**
     * @param list<Category> $categories
     *
     * @return list<Category>
     */
    private static function sorted(array $categories): array
    {
        usort($categories, static fn(Category $a, Category $b): int => [$a->position, $a->id] <=> [$b->position, $b->id]);

        return $categories;
    }
}

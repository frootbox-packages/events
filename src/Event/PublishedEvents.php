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
use Frootbox\Core\Category\CategoryLinks;
use Frootbox\Core\Category\ObjectCategories;
use Frootbox\Database\Values;

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
        private ObjectCategories $categories,
    ) {}

    /**
     * @param list<string>|null $categories A category and all below it, e.g. (decision 0233)
     * @param list<string>|null $spaces
     *
     * @return list<EventView>
     */
    public function list(string $language, ?array $categories = null, bool $past = false, int $limit = 10, int $offset = 0, ?DateTimeImmutable $now = null, ?array $spaces = null): array
    {
        $query = $this->query($language, $categories, $past, $now, $spaces)
            ->select('e.id')
            ->orderBy('e.starts_at', $past ? 'DESC' : 'ASC')
            ->addOrderBy('e.id', $past ? 'DESC' : 'ASC')
            ->setMaxResults(max(1, $limit))
            ->setFirstResult(max(0, $offset));

        $ids = array_values(array_filter($query->executeQuery()->fetchFirstColumn(), \is_string(...)));

        return $this->views($ids, $language);
    }

    /**
     * @param list<string>|null $categories
     * @param list<string>|null $spaces
     */
    public function count(string $language, ?array $categories = null, bool $past = false, ?DateTimeImmutable $now = null, ?array $spaces = null): int
    {
        $count = $this->query($language, $categories, $past, $now, $spaces)->select('COUNT(*)')->executeQuery()->fetchOne();

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
     * @param list<string>|null $categories
     * @param list<string>|null $spaces
     */
    private function query(string $language, ?array $categories, bool $past, ?DateTimeImmutable $now, ?array $spaces): QueryBuilder
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

        if (null !== $categories) {
            $linked = $this->connection->createQueryBuilder()
                ->select('l.target_id')
                ->from(CategoryLinks::TABLE, 'l')
                ->where('l.target_type = :type')
                ->andWhere($query->expr()->in('l.category_id', ':categories'));
            $query->andWhere($query->expr()->in('e.id', $linked->getSQL()))
                ->setParameter('type', EventStore::TYPE)
                ->setParameter('categories', [] === $categories ? [''] : $categories, ArrayParameterType::STRING);
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

        $assigned = $this->categories->of(EventStore::TYPE, $ids);

        $views = [];

        foreach ($ids as $id) {
            $event = $events[$id] ?? null;
            $text = $texts[$id] ?? null;

            if (null === $event || null === $text) {
                continue;
            }

            $views[] = new EventView($id, $language, $event->startsAt, $event->endsAt, $event->allDay, $text->title, $text->slug, $text->teaser, $text->venue, $event->address, $event->coordinates, $text->text, $event->image, $assigned[$id] ?? []);
        }

        return $views;
    }
}

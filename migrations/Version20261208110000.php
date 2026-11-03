<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Frootbox\Database\Migration;
use Frootbox\Database\Schema\Blueprint;

/**
 * The categories of the events move to the core (decision 0233): each one
 * keeps its id in the group "frootbox/events/categories", the categories
 * of the published events become links of the core.
 */
final class Version20261208110000 extends Migration
{
    private const string GROUP = 'frootbox/events/categories';

    private const string TYPE = 'frootbox/events/event';

    public function getDescription(): string
    {
        return 'Moves the categories of the events to the categories of the core';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->connection->fetchAllAssociative('SELECT id, position, translations, created_at, updated_at FROM frootbox_events_categories') as $row) {
            $this->addSql(
                'INSERT INTO categories (id, group_key, parent_id, position, translations, created_at, updated_at) VALUES (?, ?, NULL, ?, ?, ?, ?)',
                [$row['id'], self::GROUP, $row['position'], $row['translations'], $row['created_at'], $row['updated_at']],
            );
        }

        foreach ($this->connection->fetchAllAssociative('SELECT event_id, category_id FROM frootbox_events_event_categories') as $row) {
            $this->addSql('INSERT INTO category_links (category_id, target_type, target_id) VALUES (?, ?, ?)', [$row['category_id'], self::TYPE, $row['event_id']]);
        }

        $this->dropTable('frootbox_events_event_categories');
        $this->dropTable('frootbox_events_categories');
    }

    public function down(Schema $schema): void
    {
        $this->createTable('frootbox_events_categories', function (Blueprint $table): void {
            $table->ulid();
            $table->integer('position');
            $table->json('translations');
            $table->timestamps();
        });

        $this->createTable('frootbox_events_event_categories', function (Blueprint $table): void {
            $table->id();
            $table->ulid('event_id')->index();
            $table->ulid('category_id')->index();
            $table->unique(['event_id', 'category_id']);
        });

        $this->addSql('DELETE FROM category_links WHERE target_type = ?', [self::TYPE]);
        $this->addSql('DELETE FROM categories WHERE group_key = ?', [self::GROUP]);
    }
}

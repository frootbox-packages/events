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
 * Each published event belongs to a space of the events (decision 0232).
 */
final class Version20261207110000 extends Migration
{
    public function getDescription(): string
    {
        return 'Adds the space to the published events';
    }

    public function up(Schema $schema): void
    {
        $this->alterTable('frootbox_events_events', function (Blueprint $table): void {
            $table->string('space', 64)->default('default');
        });
    }

    public function down(Schema $schema): void
    {
        $this->alterTable('frootbox_events_events', function (Blueprint $table): void {
            $table->dropColumn('space');
        });
    }
}

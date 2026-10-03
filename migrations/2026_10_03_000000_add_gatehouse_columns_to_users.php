<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * A member's place in the queue lives on the member: null for everybody
 * Gatehouse never held (every existing member), "pending" while waiting,
 * "approved" or "declined" once decided. Kept on the row so the permission
 * check that runs on every request needs no join.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'gatehouse_status')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->string('gatehouse_status', 16)->nullable()->index();
            $table->dateTime('gatehouse_decided_at')->nullable();
            $table->unsignedInteger('gatehouse_decided_by')->nullable();
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->dropIndex(['gatehouse_status']);
            $table->dropColumn(['gatehouse_status', 'gatehouse_decided_at', 'gatehouse_decided_by']);
        });
    },
];

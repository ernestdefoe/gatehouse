<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * A member's place in the queue, in Gatehouse's own table: a row only for the
 * members Gatehouse held. Until 1.5 it lived on `users`; a forum that has
 * those columns has its applicants copied across and the columns removed.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('gatehouse_applicants')) {
            $schema->create('gatehouse_applicants', function (Blueprint $table) {
                $table->unsignedInteger('user_id')->primary();
                $table->string('status', 16)->nullable()->index();
                $table->text('reason')->nullable();
                $table->dateTime('held_at')->nullable();
                $table->dateTime('decided_at')->nullable();
                $table->unsignedInteger('decided_by')->nullable();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! $schema->hasColumn('users', 'gatehouse_status')) {
            return;
        }

        $db = $schema->getConnection();
        $hasReason = $schema->hasColumn('users', 'gatehouse_reason');

        // The decider through a join, so an account deleted since reads as nobody instead of breaking the key.
        $copy = ['user_id' => 'users.id', 'status' => 'users.gatehouse_status', 'held_at' => 'users.joined_at',
            'decided_at' => 'users.gatehouse_decided_at', 'decided_by' => 'deciders.id'];

        if ($hasReason) {
            $copy['reason'] = 'users.gatehouse_reason';
        }

        $db->table('gatehouse_applicants')->insertUsing(
            array_keys($copy),
            $db->table('users')
                ->leftJoin('users as deciders', 'deciders.id', '=', 'users.gatehouse_decided_by')
                ->whereNotNull('users.gatehouse_status')
                // Rows a half-finished earlier run already copied.
                ->whereNotIn('users.id', $db->table('gatehouse_applicants')->select('user_id'))
                ->select(array_values($copy))
        );

        $schema->table('users', function (Blueprint $table) use ($hasReason) {
            $table->dropIndex(['gatehouse_status']);
            $table->dropColumn(array_merge(
                ['gatehouse_status', 'gatehouse_decided_at', 'gatehouse_decided_by'],
                $hasReason ? ['gatehouse_reason'] : []
            ));
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('gatehouse_applicants');
    },
];

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * The applicant's answer to the sign-up question, read by whoever decides.
 * On the row, beside the status, so the queue needs no join.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('users', 'gatehouse_reason')) {
            return;
        }

        $schema->table('users', function (Blueprint $table) {
            $table->text('gatehouse_reason')->nullable();
        });
    },

    'down' => function (Builder $schema) {
        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('gatehouse_reason');
        });
    },
];

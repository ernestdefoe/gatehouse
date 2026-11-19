<?php

/*
 * Superseded by 2026_11_19_000000_create_gatehouse_applicants_table, which
 * keeps Gatehouse's data in its own table instead of on `users` and moves an
 * older forum's columns there. Kept, doing nothing, because forums that ran it
 * have it on record: rolling back or uninstalling looks for this file.
 */
return [
    'up' => fn () => null,
    'down' => fn () => null,
];

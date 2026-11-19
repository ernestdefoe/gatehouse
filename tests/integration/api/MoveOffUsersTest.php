<?php

namespace Ernestdefoe\Gatehouse\Tests\integration\api;

use Flarum\Extension\ExtensionManager;
use Flarum\Testing\integration\TestCase;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;

/**
 * Forums from before 1.5 kept the queue on `users`. The move copies it into
 * gatehouse_applicants and takes the columns off `users`; a purge then leaves
 * `users` as it found it.
 *
 * Schema changes inside the test: MySQL commits DDL at once, which would leak
 * into the other tests, so this runs on SQLite and PostgreSQL, whose schema
 * changes roll back with the test's transaction.
 */
class MoveOffUsersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-gatehouse');
    }

    #[Test]
    public function an_older_forums_queue_moves_to_its_own_table()
    {
        $this->app();
        $db = $this->database();

        if ($db->getDriverName() === 'mysql' || $db->getDriverName() === 'mariadb') {
            $this->markTestSkipped('MySQL commits schema changes at once.');
        }

        $schema = $db->getSchemaBuilder();
        $schema->drop('gatehouse_applicants');
        $schema->table('users', function (Blueprint $table) {
            $table->string('gatehouse_status', 16)->nullable()->index();
            $table->dateTime('gatehouse_decided_at')->nullable();
            $table->unsignedInteger('gatehouse_decided_by')->nullable();
            $table->text('gatehouse_reason')->nullable();
        });

        foreach ([
            ['id' => 3, 'username' => 'waiting', 'email' => 'w@machine.local', 'joined_at' => '2026-01-02 00:00:00', 'gatehouse_status' => 'pending', 'gatehouse_reason' => 'Hi'],
            ['id' => 4, 'username' => 'refused', 'email' => 'r@machine.local', 'gatehouse_status' => 'declined', 'gatehouse_decided_at' => '2026-01-03 00:00:00', 'gatehouse_decided_by' => 1],
            ['id' => 5, 'username' => 'orphaned', 'email' => 'o@machine.local', 'gatehouse_status' => 'approved', 'gatehouse_decided_by' => 999],
            ['id' => 6, 'username' => 'member', 'email' => 'm@machine.local'],
        ] as $row) {
            $db->table('users')->insert($row + ['password' => '']);
        }

        $migration = require __DIR__.'/../../../migrations/2026_11_19_000000_create_gatehouse_applicants_table.php';
        $migration['up']($schema);

        $rows = $db->table('gatehouse_applicants')->orderBy('user_id')->get()->keyBy('user_id');
        $this->assertSame([3, 4, 5], $rows->keys()->map(fn ($id) => (int) $id)->all(), 'Only the members Gatehouse held');
        $this->assertSame('pending', $rows[3]->status);
        $this->assertSame('Hi', $rows[3]->reason);
        $this->assertStringStartsWith('2026-01-02', (string) $rows[3]->held_at, 'Held since they joined');
        $this->assertEquals(1, $rows[4]->decided_by);
        $this->assertNull($rows[5]->decided_by, 'A decider deleted since reads as nobody');

        foreach (['gatehouse_status', 'gatehouse_decided_at', 'gatehouse_decided_by', 'gatehouse_reason'] as $column) {
            $this->assertFalse($schema->hasColumn('users', $column), "users.$column is gone");
        }
    }

    #[Test]
    public function a_purge_leaves_nothing_behind()
    {
        $manager = $this->app()->getContainer()->make(ExtensionManager::class);
        $db = $this->database();

        if ($db->getDriverName() === 'mysql' || $db->getDriverName() === 'mariadb') {
            $this->markTestSkipped('MySQL commits schema changes at once.');
        }

        // What Admin → Extensions → Purge runs: every Gatehouse migration down, newest first.
        $manager->migrateDown($manager->getExtension('ernestdefoe-gatehouse'));

        $schema = $db->getSchemaBuilder();
        $this->assertFalse($schema->hasTable('gatehouse_applicants'));
        $this->assertSame([], array_values(array_filter($schema->getColumnListing('users'), fn ($column) => str_starts_with($column, 'gatehouse_'))));
        $this->assertSame(0, $db->table('migrations')->where('extension', 'ernestdefoe-gatehouse')->count(), 'Every migration rolled back');
    }
}

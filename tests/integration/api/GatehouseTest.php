<?php

namespace Ernestdefoe\Gatehouse\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class GatehouseTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-gatehouse');
        $this->extend((new Extend\Csrf())->exemptRoute('login')->exemptRoute('users.create'));

        $user = fn (int $id, string $name, ?string $status, array $extra = []) => $extra + [
            'id' => $id, 'username' => $name, 'email' => "$name@machine.local", 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', // too-obscure
            'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subDays(10 - $id), 'gatehouse_status' => $status,
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                $user(3, 'waiting', 'pending'),
                $user(4, 'refused', 'declined', ['gatehouse_decided_at' => Carbon::now()->subDay(), 'gatehouse_decided_by' => 1]),
                $user(5, 'welcomed', 'approved', ['gatehouse_decided_at' => Carbon::now(), 'gatehouse_decided_by' => 1]),
                $user(6, 'alsowaiting', 'pending'),
                $user(7, 'unconfirmed', null, ['is_email_confirmed' => 0]),
            ],
        ]);
    }

    private function json(string $method, string $path, ?int $actor, array $body = [], array $query = []): array
    {
        $request = $this->request($method, $path, ($actor ? ['authenticatedAs' => $actor] : []) + ($body ? ['json' => $body] : []));
        $response = $this->send($query ? $request->withQueryParams($query) : $request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function gatehouseStatus(int $user): ?string
    {
        return $this->database()->table('users')->where('id', $user)->value('gatehouse_status');
    }

    #[Test]
    public function the_queue_is_for_admins()
    {
        $this->assertSame(403, $this->json('GET', '/api/gatehouse/queue', null)[0]);
        $this->assertSame(403, $this->json('GET', '/api/gatehouse/queue', 2)[0]);

        [$status, $body] = $this->json('GET', '/api/gatehouse/queue', 1);
        $this->assertSame(200, $status);
        $this->assertSame(['alsowaiting', 'waiting'], array_column($body['applicants'], 'username'), 'The newest applicant first');
        $this->assertSame(['pending' => 2], $body['counts']);
    }

    #[Test]
    public function the_queue_lists_decided_applicants_with_who_decided()
    {
        [, $declined] = $this->json('GET', '/api/gatehouse/queue', 1, [], ['status' => 'declined']);
        $this->assertSame(['refused'], array_column($declined['applicants'], 'username'));
        $this->assertSame('admin', $declined['applicants'][0]['decidedBy']);

        [, $other] = $this->json('GET', '/api/gatehouse/queue', 1, [], ['status' => 'everyone']);
        $this->assertSame(['alsowaiting', 'waiting'], array_column($other['applicants'], 'username'), 'An unknown status reads as pending');
    }

    #[Test]
    public function only_an_admin_decides_and_only_on_an_applicant()
    {
        $this->assertSame(403, $this->json('POST', '/api/gatehouse/applicants/3/approve', 2)[0]);
        $this->assertSame('pending', $this->gatehouseStatus(3));

        $this->assertSame(409, $this->json('POST', '/api/gatehouse/applicants/2/decline', 1)[0], 'Not a way to lock out an ordinary member');
        $this->assertNull($this->gatehouseStatus(2));
        $this->assertSame(404, $this->json('POST', '/api/gatehouse/applicants/999/approve', 1)[0]);
    }

    #[Test]
    public function approving_lets_an_applicant_in_and_records_who_did_it()
    {
        [$status, $body] = $this->json('POST', '/api/gatehouse/applicants/3/approve', 1);

        $this->assertSame(200, $status);
        $this->assertSame(['status' => 'approved'], $body);
        $row = $this->database()->table('users')->where('id', 3)->first();
        $this->assertSame('approved', $row->gatehouse_status);
        $this->assertEquals(1, $row->gatehouse_decided_by);
        $this->assertNotNull($row->gatehouse_decided_at);
    }

    #[Test]
    public function a_declined_applicant_can_still_be_approved_later()
    {
        $this->json('POST', '/api/gatehouse/applicants/3/decline', 1);
        $this->assertSame('declined', $this->gatehouseStatus(3));

        $this->assertSame(200, $this->json('POST', '/api/gatehouse/applicants/4/approve', 1)[0]);
        $this->assertSame('approved', $this->gatehouseStatus(4));
    }

    #[Test]
    public function a_waiting_or_declined_member_has_a_guests_permissions_and_is_told_why()
    {
        $start = fn (int $actor) => $this->json('POST', '/api/discussions', $actor, ['data' => ['type' => 'discussions', 'attributes' => ['title' => 'Hello', 'content' => 'Hello there']]])[0];
        $forumStatus = fn (int $actor) => $this->json('GET', '/api', $actor)[1]['data']['attributes']['gatehouseStatus'];

        $this->assertSame(403, $start(3));
        $this->assertSame(403, $start(4));
        $this->assertSame(201, $start(5), 'An approved member is a member');

        $this->assertSame('pending', $forumStatus(3));
        $this->assertSame('declined', $forumStatus(4));
        $this->assertNull($forumStatus(5));
    }

    private function login(string $identification, string $password): ResponseInterface
    {
        return $this->send($this->request('POST', '/login', ['json' => ['identification' => $identification, 'password' => $password]]));
    }

    #[Test]
    public function a_waiting_applicant_is_told_so_at_sign_in_but_only_with_the_right_password()
    {
        $right = $this->login('waiting', 'too-obscure');
        $this->assertSame(422, $right->getStatusCode());

        $wrong = $this->login('waiting', 'guess');
        $this->assertSame(401, $wrong->getStatusCode(), 'A stranger learns nothing about who applied');

        $this->assertSame(200, $this->login('welcomed', 'too-obscure')->getStatusCode());
    }

    private function register(string $username, string $email): ResponseInterface
    {
        return $this->send($this->request('POST', '/api/users', [
            'json' => ['data' => ['type' => 'users', 'attributes' => ['username' => $username, 'email' => $email, 'password' => 'a-long-password']]],
        ]));
    }

    #[Test]
    public function a_sign_up_outside_the_allow_list_waits_and_one_inside_walks_in()
    {
        $this->setting('ernestdefoe-gatehouse.enabled', '1');
        $this->setting('ernestdefoe-gatehouse.allow', "uni.test\n# comment\n@staff.example");

        $this->assertSame(201, $this->register('student', 'kim@cs.uni.test')->getStatusCode());
        $this->assertSame(201, $this->register('stranger', 'kim@notuni.test')->getStatusCode());
        $this->assertSame(201, $this->register('lookalike', 'kim@baduni.test')->getStatusCode());

        $status = fn (string $username) => $this->database()->table('users')->where('username', $username)->value('gatehouse_status');
        $this->assertNull($status('student'), 'A subdomain of an allowed domain');
        $this->assertSame('pending', $status('stranger'));
        $this->assertSame('pending', $status('lookalike'), 'Not a look-alike domain');
        $this->assertSame(2, $this->database()->table('notifications')->where('user_id', 1)->where('type', 'gatehouseApplicant')->count(), 'The admin is told');
    }

    #[Test]
    public function nobody_waits_while_gatehouse_is_switched_off()
    {
        $this->assertSame(201, $this->register('stranger', 'kim@notuni.test')->getStatusCode());

        $this->assertNull($this->database()->table('users')->where('username', 'stranger')->value('gatehouse_status'));
    }

    #[Test]
    public function a_refused_address_cannot_sign_up_or_be_switched_to()
    {
        $this->setting('ernestdefoe-gatehouse.enabled', '1');
        $this->setting('ernestdefoe-gatehouse.deny', '*@spam.test');

        $this->assertSame(422, $this->register('spammer', 'bob@spam.test')->getStatusCode());
        $this->assertSame(0, $this->database()->table('users')->where('username', 'spammer')->count());

        [$status] = $this->json('PATCH', '/api/users/2', 2, ['data' => ['type' => 'users', 'id' => '2', 'attributes' => ['email' => 'bob@spam.test']], 'meta' => ['password' => 'too-obscure']]);
        $this->assertSame(422, $status);
    }

    #[Test]
    public function moving_an_unconfirmed_account_to_an_address_the_allow_list_would_hold_puts_it_in_the_queue()
    {
        $this->setting('ernestdefoe-gatehouse.enabled', '1');
        $this->setting('ernestdefoe-gatehouse.allow', 'machine.local');

        $change = fn (int $actor, string $email) => $this->json('PATCH', "/api/users/$actor", $actor, ['data' => ['type' => 'users', 'id' => (string) $actor, 'attributes' => ['email' => $email]], 'meta' => ['password' => 'too-obscure']])[0];

        $this->assertSame(200, $change(7, 'me@elsewhere.test'));
        $this->assertSame('pending', $this->gatehouseStatus(7));

        $this->assertSame(200, $change(2, 'me2@elsewhere.test'));
        $this->assertNull($this->gatehouseStatus(2), 'A member who confirmed an address proved it');
    }

    #[Test]
    public function a_reserved_username_cannot_be_taken_or_renamed_to_except_by_an_admin()
    {
        $this->setting('ernestdefoe-gatehouse.reserved_usernames', "admin*\n/^mod/");

        // User 5 moderates, with the permission to edit other members' names.
        $this->prepareDatabase([
            'group_user' => [['user_id' => 5, 'group_id' => 4]],
            'group_permission' => [['group_id' => 4, 'permission' => 'user.edit'], ['group_id' => 4, 'permission' => 'user.editCredentials']],
        ]);

        $this->assertSame(422, $this->register('Administrator', 'x@example.test')->getStatusCode());
        $this->assertSame(422, $this->register('moderator', 'y@example.test')->getStatusCode());

        $rename = fn (int $actor) => $this->json('PATCH', '/api/users/2', $actor, ['data' => ['type' => 'users', 'id' => '2', 'attributes' => ['username' => 'admin2']]])[0];
        $this->assertSame(422, $rename(5), 'Not even by a moderator');
        $this->assertSame(200, $rename(1), 'An admin decides for themselves');
    }
}

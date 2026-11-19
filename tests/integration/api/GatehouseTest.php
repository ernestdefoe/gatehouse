<?php

namespace Ernestdefoe\Gatehouse\Tests\integration\api;

use Carbon\Carbon;
use Ernestdefoe\Gatehouse\Api\Resource\ApplicantResource;
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

        $user = fn (int $id, string $name, array $extra = []) => $extra + [
            'id' => $id, 'username' => $name, 'email' => "$name@machine.local", 'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', // too-obscure
            'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subDays(10 - $id),
        ];
        $applicant = fn (int $id, string $status, array $extra = []) => $extra + [
            'user_id' => $id, 'status' => $status, 'held_at' => Carbon::now()->subDays(10 - $id),
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                $user(3, 'waiting'),
                $user(4, 'refused'),
                $user(5, 'welcomed'),
                $user(6, 'alsowaiting'),
                $user(7, 'unconfirmed', ['is_email_confirmed' => 0]),
            ],
            'gatehouse_applicants' => [
                $applicant(3, 'pending'),
                $applicant(4, 'declined', ['decided_at' => Carbon::now()->subDay(), 'decided_by' => 1]),
                $applicant(5, 'approved', ['decided_at' => Carbon::now(), 'decided_by' => 1]),
                $applicant(6, 'pending'),
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
        return $this->database()->table('gatehouse_applicants')->where('user_id', $user)->value('status');
    }

    /** The queue for one status: [HTTP status, usernames, the whole body]. */
    private function queue(?int $actor, ?string $status = null, int $offset = 0): array
    {
        $query = array_filter(['filter' => $status ? ['status' => $status] : null, 'page' => $offset ? ['offset' => (string) $offset] : null]);
        [$code, $body] = $this->json('GET', '/api/gatehouse-applicants', $actor, [], $query);

        return [$code, array_map(fn ($row) => $row['attributes']['username'], $body['data'] ?? []), $body];
    }

    /** Approve or decline: the HTTP status. */
    private function decide(int $applicant, string $decision, ?int $actor): int
    {
        return $this->json('PATCH', "/api/gatehouse-applicants/$applicant", $actor, ['data' => [
            'type' => 'gatehouse-applicants', 'id' => (string) $applicant, 'attributes' => ['status' => $decision === 'approve' ? 'approved' : 'declined'],
        ]])[0];
    }

    #[Test]
    public function the_queue_is_for_admins_and_whoever_may_decide()
    {
        $this->assertSame(403, $this->queue(null)[0]);
        $this->assertSame(403, $this->queue(2)[0]);

        [$status, $names, $body] = $this->queue(1);
        $this->assertSame(200, $status);
        $this->assertSame(['alsowaiting', 'waiting'], $names, 'The newest applicant first');
        $this->assertSame(2, $body['meta']['page']['total']);
    }

    #[Test]
    public function the_queue_pages_so_older_applicants_stay_reachable()
    {
        $rows = [];
        for ($i = 0; $i < ApplicantResource::PAGE + 5; $i++) {
            $rows[] = ['id' => 100 + $i, 'username' => "applicant$i", 'email' => "applicant$i@machine.local", 'is_email_confirmed' => 0];
            $held[] = ['user_id' => 100 + $i, 'status' => 'pending', 'held_at' => Carbon::parse('2020-01-01')->addMinutes($i)];
        }
        $this->prepareDatabase([User::class => $rows, 'gatehouse_applicants' => $held]);

        [, $first, $firstBody] = $this->queue(1);
        $this->assertCount(ApplicantResource::PAGE, $first);
        $this->assertArrayHasKey('next', $firstBody['links']);
        $this->assertSame(ApplicantResource::PAGE + 7, $firstBody['meta']['page']['total']);

        [, $rest, $restBody] = $this->queue(1, null, ApplicantResource::PAGE);
        $this->assertCount(7, $rest, 'The rest, ending with the oldest');
        $this->assertArrayNotHasKey('next', $restBody['links'] ?? []);
        $this->assertSame('applicant0', end($rest));
        $this->assertEmpty(array_intersect($first, $rest));
    }

    #[Test]
    public function the_queue_lists_decided_applicants_with_who_decided()
    {
        [, $declined, $body] = $this->queue(1, 'declined');
        $this->assertSame(['refused'], $declined);
        $this->assertSame('admin', $body['data'][0]['attributes']['decidedBy']);

        $this->assertSame(['alsowaiting', 'waiting'], $this->queue(1, 'everyone')[1], 'An unknown status reads as pending');
    }

    #[Test]
    public function only_an_admin_decides_and_only_on_an_applicant()
    {
        $this->assertSame(404, $this->decide(3, 'approve', 2), 'An applicant is not even visible to a member');
        $this->assertSame('pending', $this->gatehouseStatus(3));

        $this->assertSame(404, $this->decide(2, 'decline', 1), 'Not a way to lock out an ordinary member: they have no place in the queue');
        $this->assertNull($this->gatehouseStatus(2));
        $this->assertSame(403, $this->decide(5, 'decline', 1), 'Nor an approved one');
        $this->assertSame('approved', $this->gatehouseStatus(5));
        $this->assertSame(422, $this->json('PATCH', '/api/gatehouse-applicants/3', 1, ['data' => ['type' => 'gatehouse-applicants', 'id' => '3', 'attributes' => ['status' => 'pending']]])[0]);
    }

    #[Test]
    public function approving_lets_an_applicant_in_and_records_who_did_it()
    {
        $this->assertSame(200, $this->decide(3, 'approve', 1));

        $row = $this->database()->table('gatehouse_applicants')->where('user_id', 3)->first();
        $this->assertSame('approved', $row->status);
        $this->assertEquals(1, $row->decided_by);
        $this->assertNotNull($row->decided_at);
    }

    #[Test]
    public function a_declined_applicant_can_still_be_approved_later()
    {
        $this->decide(3, 'decline', 1);
        $this->assertSame('declined', $this->gatehouseStatus(3));

        $this->assertSame(200, $this->decide(4, 'approve', 1));
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

        // The standing is cached per member, and a decision forgets it at once.
        $this->decide(3, 'approve', 1);
        $this->assertNull($forumStatus(3));
        $this->assertSame(201, $start(3), 'Let in the moment they are approved');
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

    private function register(string $username, string $email, array $extra = []): ResponseInterface
    {
        return $this->send($this->request('POST', '/api/users', [
            'json' => ['data' => ['type' => 'users', 'attributes' => ['username' => $username, 'email' => $email, 'password' => 'a-long-password'] + $extra]],
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

        $status = fn (string $username) => $this->gatehouseStatus((int) $this->database()->table('users')->where('username', $username)->value('id'));
        $this->assertNull($status('student'), 'A subdomain of an allowed domain');
        $this->assertSame('pending', $status('stranger'));
        $this->assertSame('pending', $status('lookalike'), 'Not a look-alike domain');
        $tokens = fn (string $username) => $this->database()->table('email_tokens')->where('user_id', $this->database()->table('users')->where('username', $username)->value('id'))->count();
        $this->assertSame(1, $tokens('student'), 'Walks in: gets the activation link now');
        $this->assertSame(0, $tokens('stranger'), 'Waits: the activation link comes with the approval');
        $this->assertSame(2, $this->database()->table('notifications')->where('user_id', 1)->where('type', 'gatehouseApplicant')->count(), 'The admin is told');
    }

    #[Test]
    public function nobody_waits_while_gatehouse_is_switched_off()
    {
        $this->assertSame(201, $this->register('stranger', 'kim@notuni.test')->getStatusCode());

        $this->assertSame(0, $this->database()->table('gatehouse_applicants')->count() - 4, 'Nobody added to the queue');
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

    #[Test]
    public function the_sign_up_question_is_not_asked_while_gatehouse_is_off()
    {
        $this->setting('ernestdefoe-gatehouse.question', 'Why join?');

        $this->assertNull($this->json('GET', '/api', null)[1]['data']['attributes']['gatehouseQuestion']);
    }

    #[Test]
    public function the_sign_up_question_is_asked_while_sign_ups_are_held()
    {
        $this->setting('ernestdefoe-gatehouse.enabled', '1');
        $this->setting('ernestdefoe-gatehouse.question', 'Why join?');
        $this->setting('ernestdefoe-gatehouse.question_required', '1');

        $forum = $this->json('GET', '/api', null)[1]['data']['attributes'];
        $this->assertSame('Why join?', $forum['gatehouseQuestion']);
        $this->assertTrue($forum['gatehouseQuestionRequired']);
        $this->assertSame(1000, $forum['gatehouseReasonMax'], 'The form takes the server\'s limit');
    }

    #[Test]
    public function an_applicants_answer_is_kept_and_shown_only_to_admins()
    {
        $this->setting('ernestdefoe-gatehouse.enabled', '1');
        $this->setting('ernestdefoe-gatehouse.question', 'Why join?');

        $response = $this->register('student', 'kim@notuni.test', ['gatehouseReason' => "  I study here.\nCS101  "]);
        $this->assertSame(201, $response->getStatusCode());
        $id = json_decode((string) $response->getBody(), true)['data']['id'];
        $this->assertArrayNotHasKey('gatehouseReason', json_decode((string) $response->getBody(), true)['data']['attributes'], 'Not echoed to a guest');

        $this->assertSame("I study here.\nCS101", $this->database()->table('gatehouse_applicants')->where('user_id', $id)->value('reason'));
        $this->assertSame('pending', $this->gatehouseStatus((int) $id));

        [, , $queue] = $this->queue(1);
        $this->assertSame("I study here.\nCS101", $queue['data'][0]['attributes']['reason']);

        $alert = $this->json('GET', '/api/notifications', 1)[1]['data'][0]['attributes'];
        $this->assertSame(['reason' => "I study here.\nCS101"], $alert['content'], 'In the alert of whoever decides');

        $this->assertArrayNotHasKey('gatehouseReason', $this->json('GET', "/api/users/$id", 1)[1]['data']['attributes'] ?? [], 'Never sent out on the member');

        $this->assertSame(200, $this->decide((int) $id, 'approve', 1));
        $this->assertSame(0, $this->database()->table('notifications')->where('type', 'gatehouseApplicant')->where('is_deleted', 0)->count(), 'The alert carrying the answer is cleared too');

        $this->assertSame(422, $this->register('essay', 'e@notuni.test', ['gatehouseReason' => str_repeat('x', 1001)])->getStatusCode());
    }

    #[Test]
    public function the_answer_cannot_be_changed_after_sign_up()
    {
        $this->json('PATCH', '/api/users/3', 3, ['data' => ['type' => 'users', 'id' => '3', 'attributes' => ['gatehouseReason' => 'changed']]]);
        $this->json('PATCH', '/api/users/3', 1, ['data' => ['type' => 'users', 'id' => '3', 'attributes' => ['gatehouseReason' => 'changed']]]);

        $this->assertNull($this->database()->table('gatehouse_applicants')->where('user_id', 3)->value('reason'), 'Not by the applicant, not by an admin');
    }

    #[Test]
    public function a_required_answer_is_demanded_only_of_addresses_that_wait()
    {
        $this->setting('ernestdefoe-gatehouse.enabled', '1');
        $this->setting('ernestdefoe-gatehouse.allow', 'uni.test');
        $this->setting('ernestdefoe-gatehouse.question', 'Your student ID?');
        $this->setting('ernestdefoe-gatehouse.question_required', '1');

        $this->assertSame(422, $this->register('blank', 'kim@notuni.test', ['gatehouseReason' => '   '])->getStatusCode());
        $this->assertSame(422, $this->register('missing', 'lee@notuni.test')->getStatusCode());
        $this->assertSame(0, $this->database()->table('users')->whereIn('username', ['blank', 'missing'])->count());

        $this->assertSame(201, $this->register('answered', 'kim@notuni.test', ['gatehouseReason' => '12345'])->getStatusCode());
        $this->assertSame(201, $this->register('trusted', 'kim@cs.uni.test')->getStatusCode(), 'A trusted address never has to answer');
    }

    #[Test]
    public function admins_are_emailed_through_their_notification_settings()
    {
        $this->setting('ernestdefoe-gatehouse.enabled', '1');

        $this->prepareDatabase([
            User::class => [['id' => 8, 'username' => 'quietadmin', 'email' => 'quiet@machine.local', 'is_email_confirmed' => 1, 'preferences' => json_encode(['notify_gatehouseApplicant_email' => false])]],
            'group_user' => [['user_id' => 8, 'group_id' => 1]],
        ]);

        $this->register('stranger', 'kim@notuni.test');

        $this->assertTrue(User::find(1)->shouldEmail('gatehouseApplicant'), 'Email is on by default');
        $this->assertFalse(User::find(8)->shouldEmail('gatehouseApplicant'));
        $this->assertSame(2, $this->database()->table('notifications')->where('type', 'gatehouseApplicant')->count(), 'Both admins get the alert');
    }

    #[Test]
    public function the_admin_email_carries_the_answer_safely()
    {
        // The test forum's mail driver writes each message to the log.
        $log = fn () => implode('', array_map('file_get_contents', glob($this->tmpDir().'/storage/logs/*.log') ?: []));
        $before = strlen($log());

        $this->setting('ernestdefoe-gatehouse.enabled', '1');
        $this->setting('ernestdefoe-gatehouse.question', 'Why join?');

        $this->register('stranger', 'kim@notuni.test', ['gatehouseReason' => '<b>Me & you</b>']);

        $mail = quoted_printable_decode(substr($log(), $before));
        $this->assertStringContainsString('To: admin <admin@machine.local>', $mail);
        $this->assertStringContainsString('List-Unsubscribe:', $mail, 'An ordinary notification email, with an unsubscribe link');
        $this->assertStringContainsString('&lt;b&gt;Me &amp; you&lt;/b&gt;', $mail, 'The answer is escaped in the HTML part');
        $this->assertStringNotContainsString('<blockquote style="white-space: pre-line"><b>', $mail);
        $this->assertStringContainsString('/gatehouse', $mail, 'Links to the forum queue, which moderators can open');
        $this->assertStringNotContainsString('#/extension/', $mail);
    }

    /** User 10 moderates, and the Mods group may approve and decline. */
    private function moderatorWhoDecides(): void
    {
        $this->prepareDatabase([
            User::class => [['id' => 10, 'username' => 'gatekeeper', 'email' => 'gatekeeper@machine.local', 'is_email_confirmed' => 1]],
            'group_user' => [['user_id' => 10, 'group_id' => 4]],
            'group_permission' => [['group_id' => 4, 'permission' => 'ernestdefoe-gatehouse.decide']],
        ]);
    }

    #[Test]
    public function a_moderator_with_the_permission_works_the_queue()
    {
        $this->moderatorWhoDecides();

        [$status, $names, $body] = $this->queue(10);
        $this->assertSame(200, $status);
        $this->assertSame(['alsowaiting', 'waiting'], $names);
        $this->assertSame('waiting@machine.local', $body['data'][1]['attributes']['email'], 'The address, to decide with');

        $this->assertSame(200, $this->decide(3, 'approve', 10));
        $this->assertSame('approved', $this->gatehouseStatus(3));
        $this->assertEquals(10, $this->database()->table('gatehouse_applicants')->where('user_id', 3)->value('decided_by'));

        $this->assertSame(200, $this->decide(6, 'decline', 10));
        $this->assertSame('declined', $this->gatehouseStatus(6));

        [, , $approved] = $this->queue(10, 'approved');
        $rows = array_column(array_column($approved['data'], 'attributes'), null, 'username');
        $this->assertSame('gatekeeper', $rows['waiting']['decidedBy']);
        $this->assertSame([], array_filter(array_column($rows, 'email')), 'An approved member is a member: their address is for admins');
    }

    #[Test]
    public function a_member_without_the_permission_cannot_see_or_decide()
    {
        $this->moderatorWhoDecides();

        $this->assertSame(403, $this->queue(2)[0]);
        $this->assertSame(404, $this->decide(3, 'approve', 2));
        $this->assertSame(404, $this->decide(3, 'decline', 2));
        $this->assertSame(403, $this->queue(null)[0]);
        $this->assertNotSame(200, $this->decide(3, 'approve', null), 'Nor a visitor');
        $this->assertSame('pending', $this->gatehouseStatus(3));

        $forum = fn (?int $actor) => $this->json('GET', '/api', $actor)[1]['data']['attributes'];
        $this->assertArrayNotHasKey('gatehousePending', $forum(2));
        $this->assertArrayNotHasKey('gatehousePending', $forum(null));
        $this->assertSame(2, $forum(10)['gatehousePending']);
        $this->assertSame(2, $forum(1)['gatehousePending']);

        // Cached, and forgotten whenever the queue changes.
        $this->decide(3, 'approve', 10);
        $this->assertSame(1, $forum(10)['gatehousePending'], 'A decision');
        $this->send($this->request('DELETE', '/api/users/6', ['authenticatedAs' => 1]));
        $this->assertNull($this->gatehouseStatus(6), 'Their place in the queue goes with them');
        $this->assertSame(0, $forum(1)['gatehousePending'], 'A deleted applicant');

    }

    #[Test]
    public function a_moderator_cannot_lock_out_a_member_or_an_admin()
    {
        $this->moderatorWhoDecides();
        $this->prepareDatabase(['group_user' => [['user_id' => 6, 'group_id' => 1]]]);

        $this->assertSame(404, $this->decide(2, 'decline', 10), 'A confirmed member is not an applicant');
        $this->assertNull($this->gatehouseStatus(2));

        $this->assertSame(403, $this->decide(6, 'decline', 10), 'A waiting admin is an admin\'s call');
        $this->assertSame('pending', $this->gatehouseStatus(6));
    }

    #[Test]
    public function everyone_who_may_decide_is_told_about_a_new_applicant()
    {
        $this->moderatorWhoDecides();
        $this->setting('ernestdefoe-gatehouse.enabled', '1');
        $this->prepareDatabase([
            // A waiting moderator has a guest's permissions, so is not told.
            'group_user' => [['user_id' => 3, 'group_id' => 4]],
            // Granting it to Members does not alert the whole forum.
            'group_permission' => [['group_id' => 3, 'permission' => 'ernestdefoe-gatehouse.decide']],
        ]);

        $this->register('stranger', 'kim@notuni.test');

        $told = $this->database()->table('notifications')->where('type', 'gatehouseApplicant')->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([1, 10], $told);

        $applicant = (int) $this->database()->table('users')->where('username', 'stranger')->value('id');
        $this->decide($applicant, 'approve', 10);
        $this->assertSame(0, $this->database()->table('notifications')->where('type', 'gatehouseApplicant')->where('is_deleted', 0)->count(), 'Deciding clears it for everyone');
    }
}

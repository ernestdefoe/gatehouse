<?php

namespace Ernestdefoe\Gatehouse\Api;

use Ernestdefoe\Gatehouse\Deciders;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/gatehouse/queue?status=pending|declined|approved&offset=N: the
 * applicants, newest first, a page at a time ("hasMore" says whether to offer more).
 */
class QueueController implements RequestHandlerInterface
{
    public const PAGE = 50;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan(Deciders::PERMISSION);

        $status = Arr::get($request->getQueryParams(), 'status', 'pending');
        $status = in_array($status, ['pending', 'declined', 'approved'], true) ? $status : 'pending';

        $offset = max(0, (int) Arr::get($request->getQueryParams(), 'offset', 0));

        // One extra row says whether there is another page, without a second count.
        $users = User::query()
            ->where('gatehouse_status', $status)
            ->orderByDesc($status === 'pending' ? 'joined_at' : 'gatehouse_decided_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit(self::PAGE + 1)
            ->get();

        $hasMore = $users->count() > self::PAGE;
        $users = $users->take(self::PAGE);

        /*
         * The address and the answer are there to decide with. Once somebody is
         * approved they are an ordinary member, and only an admin sees an
         * ordinary member's address, so the history keeps those to admins.
         */
        $private = $status !== 'approved' || $actor->isAdmin();

        $deciders = User::query()->whereIn('id', $users->pluck('gatehouse_decided_by')->filter()->unique())->get()->keyBy('id');

        return new JsonResponse([
            'counts' => [
                'pending' => User::query()->where('gatehouse_status', 'pending')->count(),
            ],
            'hasMore' => $hasMore,
            'applicants' => $users->map(fn (User $u) => [
                'id' => (int) $u->id,
                'username' => $u->username,
                'displayName' => $u->display_name,
                'email' => $private ? $u->email : null,
                'confirmed' => (bool) $u->is_email_confirmed,
                'reason' => $private ? $u->getAttribute('gatehouse_reason') : null,
                'joinedAt' => optional($u->joined_at)->toIso8601String(),
                'decidedAt' => $u->getAttribute('gatehouse_decided_at') ? \Carbon\Carbon::parse($u->getAttribute('gatehouse_decided_at'))->toIso8601String() : null,
                'decidedBy' => $u->getAttribute('gatehouse_decided_by') ? $deciders->get($u->getAttribute('gatehouse_decided_by'))?->display_name : null,
            ])->values()->all(),
        ]);
    }
}

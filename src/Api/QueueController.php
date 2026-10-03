<?php

namespace Ernestdefoe\Gatehouse\Api;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/gatehouse/queue?status=pending|declined|approved: the applicants, newest first. */
class QueueController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $status = Arr::get($request->getQueryParams(), 'status', 'pending');
        $status = in_array($status, ['pending', 'declined', 'approved'], true) ? $status : 'pending';

        $users = User::query()
            ->where('gatehouse_status', $status)
            ->orderByDesc($status === 'pending' ? 'joined_at' : 'gatehouse_decided_at')
            ->limit(200)
            ->get();

        $deciders = User::query()->whereIn('id', $users->pluck('gatehouse_decided_by')->filter()->unique())->get()->keyBy('id');

        return new JsonResponse([
            'counts' => [
                'pending' => User::query()->where('gatehouse_status', 'pending')->count(),
            ],
            'applicants' => $users->map(fn (User $u) => [
                'id'          => (int) $u->id,
                'username'    => $u->username,
                'displayName' => $u->display_name,
                'email'       => $u->email,
                'confirmed'   => (bool) $u->is_email_confirmed,
                'joinedAt'    => optional($u->joined_at)->toIso8601String(),
                'decidedAt'   => $u->gatehouse_decided_at ? \Carbon\Carbon::parse($u->gatehouse_decided_at)->toIso8601String() : null,
                'decidedBy'   => $u->gatehouse_decided_by ? $deciders->get($u->gatehouse_decided_by)?->display_name : null,
            ])->values()->all(),
        ]);
    }
}

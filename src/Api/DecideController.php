<?php

namespace Ernestdefoe\Gatehouse\Api;

use Carbon\Carbon;
use Ernestdefoe\Gatehouse\Event\Approved;
use Ernestdefoe\Gatehouse\Mailer;
use Ernestdefoe\Gatehouse\Notification\ApplicantBlueprint;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

/** POST /api/gatehouse/applicants/{id}/approve | decline */
class DecideController implements RequestHandlerInterface
{
    public function __construct(
        private Mailer $mailer,
        private NotificationSyncer $notifications,
        private LoggerInterface $log,
        private Dispatcher $events,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $params = ($request->getAttribute('routeParameters') ?? []) + $request->getQueryParams();
        $decision = Arr::get($params, 'decision');
        $user = User::query()->findOrFail((int) Arr::get($params, 'id'));

        // Only someone Gatehouse actually holds or declined can be decided on;
        // this is not a way to lock out an ordinary member.
        if (! in_array($user->gatehouse_status, ['pending', 'declined'], true)) {
            return new JsonResponse(['status' => $user->gatehouse_status], 409);
        }

        $user->gatehouse_status = $decision === 'approve' ? 'approved' : 'declined';
        $user->gatehouse_decided_at = Carbon::now();
        $user->gatehouse_decided_by = $actor->id;
        $user->save();

        if ($decision === 'approve') {
            $this->events->dispatch(new Approved($user, $actor));
        }

        // The admins' "somebody is waiting" alerts are answered now.
        $this->notifications->delete(new ApplicantBlueprint($user));

        try {
            $decision === 'approve' ? $this->mailer->approved($user) : $this->mailer->declined($user);
        } catch (\Throwable $e) {
            $this->log->warning('[gatehouse] decided ' . $user->id . ' but could not email them: ' . $e->getMessage());
        }

        return new JsonResponse(['status' => $user->gatehouse_status]);
    }
}

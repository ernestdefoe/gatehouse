<?php

namespace Ernestdefoe\Gatehouse\Api\Resource;

use Carbon\Carbon;
use Ernestdefoe\Gatehouse\Applicant;
use Ernestdefoe\Gatehouse\Deciders;
use Ernestdefoe\Gatehouse\Event\Approved;
use Ernestdefoe\Gatehouse\Mailer;
use Ernestdefoe\Gatehouse\Notification\ApplicantBlueprint;
use Ernestdefoe\Gatehouse\Standing;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Notification\NotificationSyncer;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;
use Tobyz\JsonApiServer\Context as OriginalContext;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;

/**
 * The queue: GET /api/gatehouse-applicants?filter[status]=pending|declined|approved,
 * newest first, and PATCH one with a status of approved or declined to decide.
 *
 * @extends AbstractDatabaseResource<Applicant>
 */
class ApplicantResource extends AbstractDatabaseResource
{
    public const PAGE = 50;

    public const STATUSES = ['pending', 'declined', 'approved'];

    public function __construct(
        private Mailer $mailer,
        private NotificationSyncer $notifications,
        private Standing $standing,
        private LoggerInterface $log,
    ) {
    }

    public function type(): string
    {
        return 'gatehouse-applicants';
    }

    public function model(): string
    {
        return Applicant::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->can(Deciders::PERMISSION)
                ->eagerLoad(['user', 'decider'])
                ->paginate(self::PAGE, self::PAGE)
                ->query($this->listing(...)),
            Endpoint\Update::make()
                ->can('decide')
                ->eagerLoad(['user', 'decider']),
        ];
    }

    /** One status at a time: waiting by when they were held, the rest by when they were decided. */
    private function listing(Builder $query, ?Pagination $pagination, Context $context, array $filters): Context
    {
        $status = $filters['status'] ?? null;
        $status = in_array($status, self::STATUSES, true) ? $status : 'pending';

        $query->where('status', $status)
            ->orderByDesc($status === 'pending' ? 'held_at' : 'decided_at')
            ->orderByDesc('user_id');

        if ($pagination instanceof OffsetPagination) {
            $pagination->apply($query);
        }

        return $context->withQuery($query);
    }

    public function fields(): array
    {
        // The address and the answer are there to decide with. Once somebody is approved they
        // are an ordinary member, and only an admin sees an ordinary member's address.
        $private = fn (Applicant $applicant, Context $context) => $applicant->status !== 'approved' || $context->getActor()->isAdmin();

        return [
            Schema\Str::make('status')
                ->writable(fn (Applicant $applicant, Context $context) => $context->updating())
                ->required()
                ->in(['approved', 'declined']),
            Schema\Str::make('reason')->visible($private),
            Schema\Str::make('email')->visible($private)->get(fn (Applicant $applicant) => $applicant->user->email),
            Schema\Str::make('username')->get(fn (Applicant $applicant) => $applicant->user->username),
            Schema\Str::make('displayName')->get(fn (Applicant $applicant) => $applicant->user->display_name),
            Schema\Boolean::make('confirmed')->get(fn (Applicant $applicant) => (bool) $applicant->user->is_email_confirmed),
            Schema\DateTime::make('heldAt'),
            Schema\DateTime::make('decidedAt'),
            Schema\Str::make('decidedBy')->get(fn (Applicant $applicant) => $applicant->decider?->display_name),
        ];
    }

    /** @param Applicant $model */
    public function updating(object $model, OriginalContext $context): ?object
    {
        $model->decided_at = Carbon::now();
        $model->decided_by = $context->getActor()->id;

        return $model;
    }

    /** @param Applicant $model */
    public function updated(object $model, OriginalContext $context): ?object
    {
        $this->standing->changed($model->user_id);
        $user = $model->user;

        if ($model->status === 'approved') {
            $this->events->dispatch(new Approved($user, $context->getActor()));
        }

        // Everyone's "somebody is waiting" alerts are answered now.
        $this->notifications->delete(new ApplicantBlueprint($user, $model->reason));

        try {
            $model->status === 'approved' ? $this->mailer->approved($user) : $this->mailer->declined($user);
        } catch (\Throwable $e) {
            // The decision stands; a mail server that is down must not undo it.
            $this->log->warning('[gatehouse] decided '.$user->id.' but could not email them: '.$e->getMessage());
        }

        return $model;
    }
}

<?php

namespace Ernestdefoe\Gatehouse\Listener;

use Ernestdefoe\Gatehouse\Mailer;
use Ernestdefoe\Gatehouse\Notification\ApplicantBlueprint;
use Ernestdefoe\Gatehouse\Rules;
use Flarum\Group\Group;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\Event\Registered;
use Flarum\User\User;
use Psr\Log\LoggerInterface;

/** A new sign-up that the rules hold: mark it, tell the applicant, tell the admins. */
class HoldNewMember
{
    public function __construct(
        private Rules $rules,
        private Mailer $mailer,
        private NotificationSyncer $notifications,
        private LoggerInterface $log,
    ) {
    }

    public function handle(Registered $event): void
    {
        if ($this->rules->holds((string) $event->user->email, $event->actor)) {
            $this->hold($event->user);
        }
    }

    /**
     * Put a member in the queue and tell the admins. The applicant is told too,
     * unless the address on the account may not be theirs (see HoldOnEmailChange).
     */
    public function hold(User $user, bool $tellApplicant = true): void
    {
        $user->gatehouse_status = 'pending';
        $user->save();

        /*
         * 🚨 Everything after the status is best-effort. The hold is what
         * matters; a mail server that is down must not undo it or fail the
         * sign-up, so a failure here is logged and the applicant still waits.
         */
        try {
            if ($tellApplicant) {
                $this->mailer->held($user);
            }

            $admins = User::query()->whereHas('groups', fn ($q) => $q->where('id', Group::ADMINISTRATOR_ID))->get();
            $this->notifications->sync(new ApplicantBlueprint($user), $admins->all());

            foreach ($admins as $admin) {
                $this->mailer->notifyAdmin($admin, $user);
            }
        } catch (\Throwable $e) {
            $this->log->warning('[gatehouse] held ' . $user->id . ' but could not send every notice: ' . $e->getMessage());
        }
    }
}

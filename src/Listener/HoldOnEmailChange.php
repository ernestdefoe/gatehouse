<?php

namespace Ernestdefoe\Gatehouse\Listener;

use Ernestdefoe\Gatehouse\Rules;
use Flarum\User\Event\EmailChangeRequested;

/**
 * 🚨 The allow-list is only worth anything if the address it let in is the one
 * that gets confirmed. Without this, somebody signs up with an allow-listed
 * address they do not own (so nobody holds them), changes it to their own
 * before confirming, and confirming the change activates the account: a full
 * member the queue never saw.
 *
 * So a member who has never confirmed an address and whom Gatehouse has never
 * decided on goes into the queue when they ask to move to an address the
 * allow-list would have held. Members who already confirmed an address are
 * not affected; they proved the address they came in with.
 *
 * The applicant mail is skipped: it would go to the address being replaced,
 * which may well not be theirs. The forum itself tells them they are waiting.
 */
class HoldOnEmailChange
{
    public function __construct(private Rules $rules, private HoldNewMember $holder)
    {
    }

    public function handle(EmailChangeRequested $event): void
    {
        $user = $event->user;

        if ($user->is_email_confirmed || $user->gatehouse_status !== null || $user->isAdmin()) {
            return;
        }

        if ($this->rules->holds($event->email)) {
            $this->holder->hold($user, false);
        }
    }
}

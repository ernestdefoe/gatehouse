<?php

namespace Ernestdefoe\Gatehouse;

use Flarum\User\AccountActivationMailer;
use Flarum\User\Event\Registered;

/**
 * Core's activation mailer, with one exception: an applicant who is being held
 * for approval does not get the activation link yet. They get it with the
 * approval (Mailer::approved), so confirming the address and being let in
 * happen together instead of in the wrong order.
 *
 * Swapped in through the container, so core's own listener registration picks
 * it up unchanged.
 */
class ActivationMailer extends AccountActivationMailer
{
    public function handle(Registered $event): void
    {
        if (resolve(Rules::class)->holds((string) $event->user->email, $event->actor)) {
            return;
        }

        parent::handle($event);
    }
}

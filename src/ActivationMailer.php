<?php

namespace Ernestdefoe\Gatehouse;

use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\AccountActivationMailer;
use Flarum\User\Event\Registered;
use Illuminate\Contracts\Queue\Queue;

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
    public function __construct(
        SettingsRepositoryInterface $settings,
        Queue $queue,
        UrlGenerator $url,
        TranslatorInterface $translator,
        private Rules $rules
    ) {
        parent::__construct($settings, $queue, $url, $translator);
    }

    public function handle(Registered $event): void
    {
        if ($this->rules->holds((string) $event->user->email, $event->actor)) {
            return;
        }

        parent::handle($event);
    }
}

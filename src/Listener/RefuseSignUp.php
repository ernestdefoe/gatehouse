<?php

namespace Ernestdefoe\Gatehouse\Listener;

use Ernestdefoe\Gatehouse\Rules;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Event\Saving;
use Illuminate\Support\Arr;

/**
 * Addresses on the refuse list cannot sign up at all. Checked while the new
 * account is being saved, so nothing is created, and the reason appears on the
 * sign-up form's email field.
 */
class RefuseSignUp
{
    public function __construct(private Rules $rules, private TranslatorInterface $translator)
    {
    }

    public function handle(Saving $event): void
    {
        if ($event->user->exists || ($event->actor && $event->actor->isAdmin())) {
            return;
        }

        $email = (string) (Arr::get($event->data, 'attributes.email') ?? $event->user->email);

        if ($email !== '' && $this->rules->refuses($email)) {
            throw new ValidationException([
                'email' => $this->translator->trans('ernestdefoe-gatehouse.lib.refused'),
            ]);
        }
    }
}

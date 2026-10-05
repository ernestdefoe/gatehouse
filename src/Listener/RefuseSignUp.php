<?php

namespace Ernestdefoe\Gatehouse\Listener;

use Ernestdefoe\Gatehouse\Rules;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Event\Saving;
use Illuminate\Support\Arr;

/**
 * Addresses on the refuse list cannot sign up at all or be switched to later,
 * and reserved usernames cannot be taken, at sign-up or by a rename. Checked while the account is
 * being saved, so nothing is created, and the reason appears on the form's
 * own field.
 */
class RefuseSignUp
{
    public function __construct(private Rules $rules, private TranslatorInterface $translator)
    {
    }

    public function handle(Saving $event): void
    {
        // Admins decide for themselves, including taking a reserved name.
        if ($event->actor && $event->actor->isAdmin()) {
            return;
        }

        /*
         * 🚨 Renames too, not only sign-up. Otherwise anybody can register as
         * "dave" and become "admin" next week, which is the very thing a
         * reserved list exists to stop.
         */
        $username = Arr::get($event->data, 'attributes.username');

        if ($username !== null
            && (! $event->user->exists || mb_strtolower((string) $username) !== mb_strtolower((string) $event->user->getOriginal('username')))
            && $this->rules->reservesUsername((string) $username)) {
            throw new ValidationException([
                'username' => $this->translator->trans('ernestdefoe-gatehouse.lib.username_reserved'),
            ]);
        }

        /*
         * 🚨 A change of address too, not only sign-up. Otherwise anybody can
         * register with a clean address and switch to a refused one after.
         */
        $email = $event->user->exists
            ? (string) Arr::get($event->data, 'attributes.email')
            : (string) (Arr::get($event->data, 'attributes.email') ?? $event->user->email);

        if ($email !== ''
            && (! $event->user->exists || mb_strtolower($email) !== mb_strtolower((string) $event->user->getOriginal('email')))
            && $this->rules->refuses($email)) {
            throw new ValidationException([
                'email' => $this->translator->trans('ernestdefoe-gatehouse.lib.refused'),
            ]);
        }
    }
}

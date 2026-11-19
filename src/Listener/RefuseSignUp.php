<?php

namespace Ernestdefoe\Gatehouse\Listener;

use Ernestdefoe\Gatehouse\Rules;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Event\Saving;
use Flarum\User\User;
use Illuminate\Support\Arr;

/**
 * Addresses on the refuse list cannot sign up at all or be switched to later,
 * reserved usernames cannot be taken, at sign-up or by a rename, and a required
 * sign-up question must be answered. Checked while the account is being saved,
 * so nothing is created, and the reason appears on the form's own field.
 */
class RefuseSignUp
{
    public function __construct(private Rules $rules, private TranslatorInterface $translator)
    {
    }

    public function handle(Saving $event): void
    {
        // Admins decide for themselves, including taking a reserved name.
        if ($event->actor->isAdmin()) {
            return;
        }

        $this->checkUsername($event->user, Arr::get($event->data, 'attributes.username'));

        $email = $event->user->exists
            ? (string) Arr::get($event->data, 'attributes.email')
            : (string) (Arr::get($event->data, 'attributes.email') ?? $event->user->email);

        $this->checkEmail($event->user, $email);

        if (! $event->user->exists) {
            $this->checkAnswer($email, (string) Arr::get($event->data, 'attributes.gatehouseReason'), $event->actor);
        }
    }

    /**
     * 🚨 Renames too, not only sign-up. Otherwise anybody can register as
     * "dave" and become "admin" next week, which is the very thing a
     * reserved list exists to stop.
     */
    private function checkUsername(User $user, mixed $username): void
    {
        if ($username === null || ! $this->changes($user, 'username', (string) $username)) {
            return;
        }

        if ($this->rules->reservesUsername((string) $username)) {
            $this->refuse('username', 'username_reserved');
        }
    }

    /**
     * 🚨 A change of address too, not only sign-up. Otherwise anybody can
     * register with a clean address and switch to a refused one after.
     */
    private function checkEmail(User $user, string $email): void
    {
        if ($email !== '' && $this->changes($user, 'email', $email) && $this->rules->refuses($email)) {
            $this->refuse('email', 'refused');
        }
    }

    /**
     * The sign-up question, when the admin made it required. Only for an
     * address that will wait: a trusted address walks straight in, so
     * nobody reads the answer and it is never demanded.
     */
    private function checkAnswer(string $email, string $answer, User $actor): void
    {
        if ($this->rules->questionRequired() && trim($answer) === '' && $this->rules->holds($email, $actor)) {
            $this->refuse('gatehouseReason', 'reason_required');
        }
    }

    /** A new account, or a value other than the one it has (case aside). */
    private function changes(User $user, string $attribute, string $value): bool
    {
        return ! $user->exists || mb_strtolower($value) !== mb_strtolower((string) $user->getOriginal($attribute));
    }

    private function refuse(string $field, string $key): never
    {
        throw new ValidationException([$field => $this->translator->trans('ernestdefoe-gatehouse.lib.'.$key)]);
    }
}

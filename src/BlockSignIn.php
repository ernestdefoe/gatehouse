<?php

namespace Ernestdefoe\Gatehouse;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * A password checker that turns away applicants who are waiting or declined,
 * with a message that says why instead of "incorrect password".
 *
 * 🚨 Only once the password is RIGHT. Answering "this account is pending" to
 * anybody who types an email would tell a stranger which addresses applied.
 */
class BlockSignIn
{
    public function __construct(private Hasher $hasher, private TranslatorInterface $translator)
    {
    }

    public function __invoke(User $user, string $password): ?bool
    {
        $status = $user->gatehouse_status;

        if (! in_array($status, ['pending', 'declined'], true)) {
            return null; // No opinion: core decides.
        }

        if (! $user->password || ! $this->hasher->check($password, $user->password)) {
            return null;
        }

        throw new ValidationException([
            'identification' => $this->translator->trans('ernestdefoe-gatehouse.lib.signin_' . $status),
        ]);
    }
}

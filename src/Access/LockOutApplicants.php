<?php

namespace Ernestdefoe\Gatehouse\Access;

use Ernestdefoe\Gatehouse\Standing;
use Flarum\Group\Group;
use Flarum\User\User;

/**
 * 🚨 The real lock. Sign-up logs the new member straight in, and a
 * confirmation link can be requested again from the forum, so neither the
 * login check nor holding back the email can be the whole of it. While
 * waiting or declined, a member has exactly a guest's permissions — they
 * can read what a visitor can, and do nothing a visitor cannot.
 */
class LockOutApplicants
{
    public function __construct(private Standing $standing)
    {
    }

    public function __invoke(User $actor, array $groupIds): array
    {
        // Core already gives an unconfirmed account only the guest group: nothing to look up.
        if ($groupIds === [Group::GUEST_ID]) {
            return $groupIds;
        }

        return $this->standing->locksOut($actor) ? [Group::GUEST_ID] : $groupIds;
    }
}

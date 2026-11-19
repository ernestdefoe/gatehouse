<?php

namespace Ernestdefoe\Gatehouse\Access;

use Ernestdefoe\Gatehouse\Applicant;
use Ernestdefoe\Gatehouse\Deciders;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class ApplicantPolicy extends AbstractPolicy
{
    public function decide(User $actor, Applicant $applicant): ?string
    {
        if (! $actor->hasPermission(Deciders::PERMISSION)) {
            return $this->deny();
        }

        // Only someone waiting or declined can be decided on: an approved member is an ordinary member now.
        if (! $applicant->isLockedOut()) {
            return $this->deny();
        }

        // Somebody an admin has already put in the admin group is an admin's call.
        if ($applicant->user?->isAdmin() && ! $actor->isAdmin()) {
            return $this->deny();
        }

        return $this->allow();
    }
}

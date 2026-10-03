<?php

namespace Ernestdefoe\Gatehouse\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;

/** "Somebody is waiting to be let in", in the admins' notification list. */
class ApplicantBlueprint implements BlueprintInterface, AlertableInterface
{
    public function __construct(public User $applicant)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->applicant;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->applicant;
    }

    public function getData(): mixed
    {
        return null;
    }

    public static function getType(): string
    {
        return 'gatehouseApplicant';
    }

    public static function getSubjectModel(): string
    {
        return User::class;
    }
}

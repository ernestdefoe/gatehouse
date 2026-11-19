<?php

namespace Ernestdefoe\Gatehouse\Api;

use Ernestdefoe\Gatehouse\Applicant;
use Ernestdefoe\Gatehouse\Rules;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\User\User;

class UserResourceFields
{
    public function __construct(private Rules $rules)
    {
    }

    public function __invoke(): array
    {
        return [
            /*
             * The applicant's answer to the sign-up question: written once, at
             * sign-up, and never sent back out on a member. Those who decide read
             * it in the queue and in their alert.
             */
            Schema\Str::make('gatehouseReason')
                ->visible(false)
                ->writable(fn (User $user, Context $context) => $context->creating())
                ->nullable()
                ->maxLength(Rules::REASON_MAX)
                ->set(fn (User $user, ?string $value, Context $context) => $this->keep($user, $value, $context->getActor())),
        ];
    }

    /**
     * Kept once the account exists, and only for an address that will wait: a
     * trusted address walks straight in, so nobody would ever read the answer.
     */
    private function keep(User $user, ?string $value, User $actor): void
    {
        $reason = trim((string) $value);

        if ($reason === '') {
            return;
        }

        $user->afterSave(function (User $user) use ($reason, $actor) {
            if (! $this->rules->holds((string) $user->email, $actor)) {
                return;
            }

            $applicant = new Applicant();
            $applicant->user_id = $user->id;
            $applicant->reason = $reason;
            $applicant->save();

            $user->setRelation('gatehouseApplicant', $applicant);
        });
    }
}

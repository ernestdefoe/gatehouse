<?php

namespace Ernestdefoe\Gatehouse\Api;

use Ernestdefoe\Gatehouse\Applicant;
use Ernestdefoe\Gatehouse\Deciders;
use Ernestdefoe\Gatehouse\PendingCount;
use Ernestdefoe\Gatehouse\Rules;
use Ernestdefoe\Gatehouse\Standing;
use Flarum\Api\Context;
use Flarum\Api\Schema;

class ForumResourceFields
{
    public function __construct(private Rules $rules, private PendingCount $pending, private Standing $standing)
    {
    }

    public function __invoke(): array
    {
        return [
            // The signed-in member's own standing, so the forum can say "you're in the queue".
            Schema\Str::make('gatehouseStatus')
                ->get(function ($forum, Context $context) {
                    $status = $this->standing->of($context->getActor());

                    return Applicant::locksOutStatus($status) ? $status : null;
                }),
            // How many are waiting, and so whether to show the queue at all: only for those who may decide.
            Schema\Integer::make('gatehousePending')
                ->visible(fn ($forum, Context $context) => $context->getActor()->hasPermission(Deciders::PERMISSION))
                ->get(fn () => $this->pending->get()),
            // The sign-up form's extra question, only while sign-ups are being held.
            Schema\Str::make('gatehouseQuestion')
                ->get(fn () => $this->rules->question()),
            Schema\Boolean::make('gatehouseQuestionRequired')
                ->get(fn () => $this->rules->questionRequired()),
            // The longest answer the server takes, for the form's own limit.
            Schema\Integer::make('gatehouseReasonMax')
                ->visible(fn () => $this->rules->question() !== null)
                ->get(fn () => Rules::REASON_MAX),
        ];
    }
}

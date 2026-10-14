<?php

/*
 * Gatehouse — approve new members before they get in.
 */

use Ernestdefoe\Gatehouse\Api\DecideController;
use Ernestdefoe\Gatehouse\Api\QueueController;
use Ernestdefoe\Gatehouse\BlockSignIn;
use Ernestdefoe\Gatehouse\Listener\HoldNewMember;
use Ernestdefoe\Gatehouse\Listener\HoldOnEmailChange;
use Ernestdefoe\Gatehouse\Listener\RefuseSignUp;
use Ernestdefoe\Gatehouse\Notification\ApplicantBlueprint;
use Ernestdefoe\Gatehouse\Rules;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Group\Group;
use Flarum\User\Event\EmailChangeRequested;
use Flarum\User\Event\Registered;
use Flarum\User\Event\Saving;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ernestdefoe-gatehouse', __DIR__.'/views'),

    (new Extend\Settings())
        ->default('ernestdefoe-gatehouse.enabled', false),

    // Hold the activation link back from applicants until they are approved.
    (new Extend\ServiceProvider())
        ->register(\Ernestdefoe\Gatehouse\Provider::class),

    (new Extend\Event())
        ->listen(Registered::class, HoldNewMember::class)
        ->listen(EmailChangeRequested::class, HoldOnEmailChange::class)
        ->listen(Saving::class, RefuseSignUp::class),

    (new Extend\Auth())
        ->addPasswordChecker('gatehouse', BlockSignIn::class),

    /*
     * 🚨 The real lock. Sign-up logs the new member straight in, and a
     * confirmation link can be requested again from the forum, so neither the
     * login check nor holding back the email can be the whole of it. While
     * waiting or declined, a member has exactly a guest's permissions — they
     * can read what a visitor can, and do nothing a visitor cannot.
     */
    (new Extend\User())
        ->permissionGroups(function (User $actor, array $groupIds) {
            return in_array($actor->getAttribute('gatehouse_status'), ['pending', 'declined'], true)
                ? [Group::GUEST_ID]
                : $groupIds;
        }),

    (new Extend\Notification())
        ->type(ApplicantBlueprint::class, ['alert', 'email']),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            // The signed-in member's own standing, so the forum can say "you're in the queue".
            Schema\Str::make('gatehouseStatus')
                ->get(fn ($model, Context $context) => in_array($context->getActor()->getAttribute('gatehouse_status'), ['pending', 'declined'], true)
                    ? $context->getActor()->getAttribute('gatehouse_status')
                    : null),
            // The sign-up form's extra question, only while sign-ups are being held.
            Schema\Str::make('gatehouseQuestion')
                ->get(fn () => resolve(Rules::class)->question()),
            Schema\Boolean::make('gatehouseQuestionRequired')
                ->get(fn () => resolve(Rules::class)->questionRequired()),
        ]),

    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            /*
             * The applicant's answer: written once, at sign-up, and read only by
             * admins, and only while the applicant is waiting or declined.
             */
            Schema\Str::make('gatehouseReason')
                ->visible(fn (User $user, Context $context) => $context->getActor()->isAdmin()
                    && in_array($user->getAttribute('gatehouse_status'), ['pending', 'declined'], true))
                ->writable(fn (User $user, Context $context) => $context->creating())
                ->nullable()
                ->maxLength(Rules::REASON_MAX)
                ->get(fn (User $user) => $user->getAttribute('gatehouse_reason'))
                ->set(fn (User $user, ?string $value) => $user->setAttribute('gatehouse_reason', trim((string) $value) ?: null)),
        ]),

    (new Extend\Routes('api'))
        ->get('/gatehouse/queue', 'gatehouse.queue', QueueController::class)
        ->post('/gatehouse/applicants/{id}/{decision:approve|decline}', 'gatehouse.decide', DecideController::class),
];

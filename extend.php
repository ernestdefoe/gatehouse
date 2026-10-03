<?php

/*
 * Gatehouse — approve new members before they get in.
 */

use Ernestdefoe\Gatehouse\Api\DecideController;
use Ernestdefoe\Gatehouse\Api\QueueController;
use Ernestdefoe\Gatehouse\BlockSignIn;
use Ernestdefoe\Gatehouse\Listener\HoldNewMember;
use Ernestdefoe\Gatehouse\Listener\RefuseSignUp;
use Ernestdefoe\Gatehouse\Notification\ApplicantBlueprint;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Group\Group;
use Flarum\User\Event\Registered;
use Flarum\User\Event\Saving;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Settings())
        ->default('ernestdefoe-gatehouse.enabled', false),

    // Hold the activation link back from applicants until they are approved.
    (new Extend\ServiceProvider())
        ->register(\Ernestdefoe\Gatehouse\Provider::class),

    (new Extend\Event())
        ->listen(Registered::class, HoldNewMember::class)
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
            return in_array($actor->gatehouse_status, ['pending', 'declined'], true)
                ? [Group::GUEST_ID]
                : $groupIds;
        }),

    (new Extend\Notification())
        ->type(ApplicantBlueprint::class, ['alert']),

    // The signed-in member's own standing, so the forum can say "you're in the queue".
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Str::make('gatehouseStatus')
                ->get(fn ($model, Context $context) => in_array($context->getActor()->gatehouse_status, ['pending', 'declined'], true)
                    ? $context->getActor()->gatehouse_status
                    : null),
        ]),

    (new Extend\Routes('api'))
        ->get('/gatehouse/queue', 'gatehouse.queue', QueueController::class)
        ->post('/gatehouse/applicants/{id}/{decision:approve|decline}', 'gatehouse.decide', DecideController::class),
];

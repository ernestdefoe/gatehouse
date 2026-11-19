<?php

/*
 * Gatehouse — approve new members before they get in.
 */

use Ernestdefoe\Gatehouse\Access\ApplicantPolicy;
use Ernestdefoe\Gatehouse\Access\ScopeApplicantVisibility;
use Ernestdefoe\Gatehouse\Api\ForumResourceFields;
use Ernestdefoe\Gatehouse\Api\Resource\ApplicantResource;
use Ernestdefoe\Gatehouse\Api\UserResourceFields;
use Ernestdefoe\Gatehouse\Applicant;
use Ernestdefoe\Gatehouse\BlockSignIn;
use Ernestdefoe\Gatehouse\Listener\RemoveFromQueue;
use Ernestdefoe\Gatehouse\Listener\HoldNewMember;
use Ernestdefoe\Gatehouse\Listener\HoldOnEmailChange;
use Ernestdefoe\Gatehouse\Listener\RefuseSignUp;
use Ernestdefoe\Gatehouse\Notification\ApplicantBlueprint;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Extend;
use Flarum\Group\Group;
use Flarum\User\Event\Deleted;
use Flarum\User\Event\EmailChangeRequested;
use Flarum\User\Event\Registered;
use Flarum\User\Event\Saving;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        /*
         * The queue, for everyone who may decide. 🚨 Not /applicants: realtime
         * setups proxy `location /app` (no slash) to the websocket server, and
         * nginx prefix-matches /applicants into it, a bare 404.
         */
        ->route('/gatehouse', 'gatehouse.applicants'),

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
        ->listen(Saving::class, RefuseSignUp::class)
        ->listen(Deleted::class, RemoveFromQueue::class),

    (new Extend\Auth())
        ->addPasswordChecker('gatehouse', BlockSignIn::class),

    /*
     * 🚨 The real lock. Sign-up logs the new member straight in, and a
     * confirmation link can be requested again from the forum, so neither the
     * login check nor holding back the email can be the whole of it. While
     * waiting or declined, a member has exactly a guest's permissions — they
     * can read what a visitor can, and do nothing a visitor cannot.
     */
    (new Extend\Model(User::class))
        ->hasOne('gatehouseApplicant', Applicant::class, 'user_id'),

    /*
     * 🚨 The real lock. Sign-up logs the new member straight in, and a
     * confirmation link can be requested again from the forum, so neither the
     * login check nor holding back the email can be the whole of it. While
     * waiting or declined, a member has exactly a guest's permissions — they
     * can read what a visitor can, and do nothing a visitor cannot.
     *
     * Core already gives an unconfirmed account only the guest group, so the
     * applicant row is looked up (once a request) only for confirmed members.
     */
    (new Extend\User())
        ->permissionGroups(fn (User $actor, array $groupIds) => $groupIds !== [Group::GUEST_ID] && Applicant::locksOut($actor)
            ? [Group::GUEST_ID]
            : $groupIds),

    (new Extend\Notification())
        ->type(ApplicantBlueprint::class, ['alert', 'email']),

    // The queue, as a JSON:API resource, for those who may decide (ScopeApplicantVisibility).
    new Extend\ApiResource(ApplicantResource::class),

    (new Extend\ModelVisibility(Applicant::class))
        ->scope(ScopeApplicantVisibility::class),

    (new Extend\Policy())
        ->modelPolicy(Applicant::class, ApplicantPolicy::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(ForumResourceFields::class),

    (new Extend\ApiResource(UserResource::class))
        ->fields(UserResourceFields::class),
];

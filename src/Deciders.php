<?php

namespace Ernestdefoe\Gatehouse;

use Flarum\Group\Group;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who may approve and decline applicants: admins always, and any group an admin
 * gives "Approve and decline applicants" in the Permissions grid.
 */
class Deciders
{
    public const PERMISSION = 'ernestdefoe-gatehouse.decide';

    /**
     * Everyone to tell about a new applicant, in ONE query: members of the
     * admin group or of a group holding the permission, read through a
     * subquery rather than a per-user permission check.
     *
     * The Guest and Member groups are left out on purpose. Nobody is stored
     * as a member of either, and alerting every member of the forum is never
     * what granting it to "Members" meant. A waiting or declined account has
     * a guest's permissions whatever its groups, so it is left out too.
     *
     * @return Builder<User>
     */
    public static function query(): Builder
    {
        return User::query()
            ->whereHas('groups', fn ($groups) => $groups->where(fn ($q) => $q
                ->where('groups.id', Group::ADMINISTRATOR_ID)
                ->orWhereIn('groups.id', fn ($granted) => $granted->select('group_id')
                    ->from('group_permission')
                    ->where('permission', self::PERMISSION)
                    ->whereNotIn('group_id', [Group::GUEST_ID, Group::MEMBER_ID]))))
            ->where(fn ($q) => $q->whereNull('gatehouse_status')->orWhere('gatehouse_status', 'approved'));
    }
}

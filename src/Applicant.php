<?php

namespace Ernestdefoe\Gatehouse;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's place in the queue, in Gatehouse's own table beside `users`:
 * a row only for the members Gatehouse held, so every other member has none.
 *
 * @property int $user_id
 * @property string|null $status pending, approved or declined
 * @property string|null $reason their answer to the sign-up question
 * @property Carbon|null $held_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property-read User $user
 * @property-read User|null $decider
 */
class Applicant extends AbstractModel
{
    use ScopeVisibilityTrait;

    /** The two standings that leave a member with a guest's permissions. */
    public const LOCKED_OUT = ['pending', 'declined'];

    protected $table = 'gatehouse_applicants';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'user_id' => 'integer',
        'decided_by' => 'integer',
        'held_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public static function locksOutStatus(?string $status): bool
    {
        return in_array($status, self::LOCKED_OUT, true);
    }

    public function isLockedOut(): bool
    {
        return self::locksOutStatus($this->status);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

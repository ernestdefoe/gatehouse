<?php

namespace Ernestdefoe\Gatehouse\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/**
 * "Somebody is waiting to be let in", to everyone who may decide: an alert, and an email.
 * Both are ordinary notification types, so each one turns either off in their
 * own notification settings, and the email carries an unsubscribe link.
 *
 * The applicant's answer travels in the notification's data, so the alert can
 * show it without a lookup per notification. Only those who decide receive it.
 */
class ApplicantBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    /**
     * Plain values, not services: blueprints are built with `new` and serialized
     * into the queued mail job. The forum title is only needed for the email.
     */
    public function __construct(
        public User $applicant,
        public ?string $reason = null,
        public string $forumTitle = '',
    ) {
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
        return $this->reason !== null ? ['reason' => $this->reason] : null;
    }

    public static function getType(): string
    {
        return 'gatehouseApplicant';
    }

    public static function getSubjectModel(): string
    {
        return User::class;
    }

    public function getEmailViews(): array
    {
        return ['text' => 'ernestdefoe-gatehouse::emails.plain.applicant', 'html' => 'ernestdefoe-gatehouse::emails.html.applicant'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ernestdefoe-gatehouse.email.admin_new.subject', [
            'applicant' => $this->applicant->display_name,
            'forum' => $this->forumTitle,
        ]);
    }
}

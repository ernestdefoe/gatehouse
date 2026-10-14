<?php

namespace Ernestdefoe\Gatehouse\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/**
 * "Somebody is waiting to be let in", to every admin: an alert, and an email.
 * Both are ordinary notification types, so each admin turns either off in
 * their own notification settings, and the email carries an unsubscribe link.
 */
class ApplicantBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
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

    public function getEmailViews(): array
    {
        return ['text' => 'ernestdefoe-gatehouse::emails.plain.applicant', 'html' => 'ernestdefoe-gatehouse::emails.html.applicant'];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ernestdefoe-gatehouse.email.admin_new.subject', [
            'applicant' => $this->applicant->display_name,
            'forum' => resolve('flarum.settings')->get('forum_title'),
        ]);
    }
}

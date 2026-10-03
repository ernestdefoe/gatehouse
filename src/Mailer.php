<?php

namespace Ernestdefoe\Gatehouse;

use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Mail\Job\SendInformationalEmailJob;
use Flarum\Mail\SafeSubstitution;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\AccountActivationMailerTrait;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;

/**
 * The four emails Gatehouse sends, in core's own informational-email layout so
 * they look like the rest of the forum's mail.
 */
class Mailer
{
    use AccountActivationMailerTrait;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Queue $queue,
        protected UrlGenerator $url,
        protected TranslatorInterface $translator,
    ) {
    }

    /** To the applicant: received, and waiting. Carries the admin's own words. */
    public function held(User $user): void
    {
        $this->send($user, 'held', ['message' => $this->custom('held_message')]);
    }

    /**
     * To the applicant: approved. If their address was never confirmed, the
     * activation link core would have sent at sign-up comes with it now —
     * that link was held back, not lost.
     */
    public function approved(User $user): void
    {
        if (! $user->is_email_confirmed) {
            $token = $this->generateToken($user, $user->email);
            $data = $this->getEmailData($user, $token);
            $this->send($user, 'approved_activate', ['url' => $data['url']]);

            return;
        }

        $this->send($user, 'approved', ['url' => $this->url->to('forum')->base()]);
    }

    public function declined(User $user): void
    {
        $this->send($user, 'declined', ['message' => $this->custom('declined_message')]);
    }

    /** To each admin: somebody is waiting. */
    public function notifyAdmin(User $admin, User $applicant): void
    {
        $this->send($admin, 'admin_new', [
            'applicant' => $applicant->display_name,
            'email'     => $applicant->email,
            'url'       => $this->url->to('admin')->base() . '#/extension/ernestdefoe-gatehouse',
        ]);
    }

    private function custom(string $key): string
    {
        return trim((string) $this->settings->get('ernestdefoe-gatehouse.' . $key, ''));
    }

    private function send(User $to, string $kind, array $params): void
    {
        $locale = $to->getPreference('locale') ?? $this->settings->get('default_locale');
        $previous = $this->translator->getLocale();
        $this->translator->setLocale($locale);

        $forum = (string) $this->settings->get('forum_title');
        $params += ['username' => $to->display_name, 'forum' => $forum];

        try {
            /*
             * 🚨 The body's values go in as core's safe-substitution markers.
             * The email layout escapes the body, and the finished message is
             * escaped again, so a plain value is escaped TWICE: an admin's
             * "Don't" arrived as "Don&amp;#039;t". A marked value is put back
             * once, escaped once, by core (MutateEmail) — the same route core's
             * own MailTranslator takes.
             *
             * The subject is a plain header that never passes through the
             * layout or the restore step, so it takes the raw values; a marker
             * there would be delivered as-is.
             */
            $subject = $this->translator->trans("ernestdefoe-gatehouse.email.$kind.subject", $params);
            $body = $this->translator->trans("ernestdefoe-gatehouse.email.$kind.body", SafeSubstitution::mark($params));
        } finally {
            $this->translator->setLocale($previous);
        }

        $this->queue->push(new SendInformationalEmailJob(
            email: $to->email,
            displayName: $to->display_name,
            subject: $subject,
            body: $body,
            forumTitle: $forum,
            locale: $locale,
        ));
    }
}

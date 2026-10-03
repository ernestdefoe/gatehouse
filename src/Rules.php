<?php

namespace Ernestdefoe\Gatehouse;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

/**
 * Who walks straight in, who waits, and who is turned away.
 *
 * Each list holds one pattern per line:
 *
 *   uni-freiburg.de     that domain, and any of its subdomains
 *   @zv.uni-freiburg.de exactly that domain
 *   .edu                any address whose domain ends that way
 *   *@example.com       a wildcard over the whole address (* and ?)
 *   /^staff\..+@x\.org$/i  a regular expression, between slashes
 *
 * Blank lines and lines starting with # are ignored.
 */
class Rules
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('ernestdefoe-gatehouse.enabled');
    }

    /**
     * Whether a new sign-up waits for approval.
     *
     * 🚨 An empty allow-list holds EVERYBODY. That is deliberate — "approve
     * every new member by hand" is the simplest use of the extension — and it
     * is what the settings page says next to the field.
     */
    public function holds(string $email, ?User $actor = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        // An admin adding a member by hand has already decided.
        if ($actor !== null && $actor->isAdmin()) {
            return false;
        }

        return ! $this->matchesAny($this->patterns('allow'), $email);
    }

    /** Whether a sign-up is refused outright. */
    public function refuses(string $email): bool
    {
        return $this->enabled() && $this->matchesAny($this->patterns('deny'), $email);
    }

    /**
     * Whether a username is reserved: nobody but an admin may take it, at
     * sign-up or by renaming themselves later.
     *
     * Independent of the approval switch: a forum can reserve "admin" without
     * holding anybody for review.
     *
     * One per line: an exact name (case-insensitive), a wildcard (admin*,
     * *moderator*), or a regular expression between slashes.
     */
    public function reservesUsername(string $username): bool
    {
        $username = mb_strtolower(trim($username));

        if ($username === '') {
            return false;
        }

        foreach ($this->patterns('reserved_usernames') as $pattern) {
            if (strlen($pattern) > 2 && $pattern[0] === '/' && preg_match('#^/.+/[a-zA-Z]*$#s', $pattern)) {
                if (@preg_match($pattern, $username) === 1) {
                    return true;
                }
                continue;
            }

            $pattern = mb_strtolower($pattern);

            if (str_contains($pattern, '*') || str_contains($pattern, '?')
                ? fnmatch($pattern, $username, FNM_CASEFOLD)
                : $pattern === $username) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    public function patterns(string $list): array
    {
        $raw = (string) $this->settings->get('ernestdefoe-gatehouse.' . $list, '');
        $lines = preg_split('/\R/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== '' && $l[0] !== '#'));
    }

    /** @param string[] $patterns */
    public function matchesAny(array $patterns, string $email): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matches($pattern, $email)) {
                return true;
            }
        }

        return false;
    }

    public function matches(string $pattern, string $email): bool
    {
        $email = mb_strtolower(trim($email));
        $at = strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $domain = substr($email, $at + 1);

        // /regex/flags
        if (strlen($pattern) > 2 && $pattern[0] === '/' && preg_match('#^/.+/[a-zA-Z]*$#s', $pattern)) {
            // An invalid expression matches nothing rather than breaking sign-up.
            return @preg_match($pattern, $email) === 1;
        }

        $pattern = mb_strtolower($pattern);

        if (str_contains($pattern, '*') || str_contains($pattern, '?')) {
            return fnmatch($pattern, $email, FNM_CASEFOLD);
        }

        // @domain: exactly that domain.
        if ($pattern[0] === '@') {
            return $domain === substr($pattern, 1);
        }

        // .suffix: the domain ends with it.
        if ($pattern[0] === '.') {
            return str_ends_with($domain, $pattern);
        }

        // A bare domain: it, or any subdomain of it — never a look-alike.
        // "example.com" must not let in "badexample.com".
        return $domain === $pattern || str_ends_with($domain, '.' . $pattern);
    }
}

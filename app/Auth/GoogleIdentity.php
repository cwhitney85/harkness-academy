<?php

namespace App\Auth;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Who Google said the visitor is, held in the session between the callback
 * and Connect Google or Finish signing up (spec 18.2.4).
 *
 * Never read from a form: a POST to either screen acts on what Google told
 * this session, or on nothing.
 */
final class GoogleIdentity
{
    private const SESSION_KEY = 'google_identity';

    private function __construct(
        /** Google's identifier for the person, which never changes (18.2.3). */
        public readonly string $sub,
        public readonly string $email,
        public readonly string $name,
    ) {}

    /**
     * Emails are compared lowercased, as Fortify stores them.
     */
    public static function from(SocialiteUser $user): self
    {
        return new self((string) $user->getId(), Str::lower((string) $user->getEmail()), (string) $user->getName());
    }

    public static function in(Session $session): ?self
    {
        $state = $session->get(self::SESSION_KEY);

        return $state === null ? null : new self($state['sub'], $state['email'], $state['name']);
    }

    public function keep(Session $session): void
    {
        $session->put(self::SESSION_KEY, ['sub' => $this->sub, 'email' => $this->email, 'name' => $this->name]);
    }

    public static function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}

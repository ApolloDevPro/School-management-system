<?php
declare(strict_types=1);

/**
 * Cross-site request forgery token stored in the session.
 *
 * A token is generated on first use and regenerated at login. Every POST is
 * checked by Router::dispatch() before the controller runs.
 */
final class Csrf
{
    /**
     * Return the session token, creating it when absent.
     */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /**
     * Compare a submitted token in constant time.
     */
    public static function check(mixed $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }
}

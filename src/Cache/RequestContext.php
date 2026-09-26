<?php

/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Cache;

/**
 * Plain facts about a request and its response, gathered by the platform and
 * judged by Policy. Keeping the judgement free of platform calls is what lets
 * the rules be tested exhaustively.
 */
final class RequestContext
{
    /**
     * @param array<string, string> $query            Query parameters.
     * @param list<string>          $cookieNames      Names of the request cookies.
     * @param bool                  $isPage           A rendered storefront page (not admin, AJAX, API, cron, CLI, feed).
     * @param bool                  $isLoggedIn       The platform recognised a logged-in user.
     * @param bool                  $isPrivatePage    Cart, checkout, account… (the platform decides).
     * @param bool                  $isPreview        Unpublished content.
     * @param bool                  $passwordProtected Personal by construction.
     * @param bool                  $doNotCache       The platform's own "never cache" flag.
     * @param bool                  $setsCookie       The response sets a cookie.
     * @param string                $cacheControl     Cache-Control the application already set ('' if none).
     */
    public function __construct(
        public readonly string $method = 'GET',
        public readonly string $path = '/',
        public readonly array $query = [],
        public readonly array $cookieNames = [],
        public readonly bool $isPage = true,
        public readonly bool $isLoggedIn = false,
        public readonly bool $isPrivatePage = false,
        public readonly bool $isPreview = false,
        public readonly bool $passwordProtected = false,
        public readonly bool $doNotCache = false,
        public readonly int $status = 200,
        public readonly bool $setsCookie = false,
        public readonly string $cacheControl = ''
    ) {
    }
}

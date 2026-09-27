<?php

declare(strict_types=1);

namespace Qoliber\Trident\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Qoliber\Trident\Delivery\Psr18Transport;
use Qoliber\Trident\Delivery\Transport;

/**
 * The HTTP client every call to a Trident admin API goes through (1.7.0).
 *
 * Needs `guzzlehttp/guzzle` ^7.5 and `guzzlehttp/psr7` ^2.4 (suggested, not
 * required: a platform that brings its own PSR-18 client can use
 * {@see Psr18Transport} directly, and should then apply {@see NetworkGuard}
 * itself).
 *
 * - libcurl; the target is checked by {@see NetworkGuard} and the checked
 *   address is pinned for the connection (`CURLOPT_RESOLVE`), so DNS rebinding
 *   cannot swap it;
 * - **no proxy**: `CURLOPT_NOPROXY=*` — neither Guzzle nor libcurl uses an
 *   environment proxy (`http_proxy`, `HTTPS_PROXY`, …) for admin traffic. A
 *   proxy would resolve the target itself (defeating the pin and the guard)
 *   and, on `http://`, receive the bearer token in clear. A deployment that
 *   must go through a proxy passes it explicitly (`$proxy`); pinning then does
 *   not apply — the proxy decides where the request goes;
 * - no redirects: a redirect is not the admin API, and the token must not
 *   follow it elsewhere.
 */
class TransportFactory
{
    private readonly NetworkGuard $guard;

    /**
     * @param \Closure|null $handler                  A Guzzle handler instead of libcurl — only one
     *                                                that honours the `curl` options (CURLOPT_RESOLVE,
     *                                                CURLOPT_NOPROXY), declared with $handlerHonoursCurlOptions;
     *                                                any other would silently drop the pinning.
     * @param string|null   $proxy                    Explicit proxy URL (opt-in; disables pinning).
     */
    public function __construct(
        private readonly float $timeout = 5.0,
        private readonly float $connectTimeout = 2.0,
        ?NetworkGuard $guard = null,
        private readonly ?\Closure $handler = null,
        bool $handlerHonoursCurlOptions = false,
        private readonly ?string $proxy = null,
    ) {
        if ($handler !== null && !$handlerHonoursCurlOptions) {
            throw new \LogicException('A custom handler must honour the curl options (pinning, no proxy): pass $handlerHonoursCurlOptions = true, or use libcurl');
        }
        if ($proxy !== null && preg_match('#^https?://[^/?\#]+/?$#i', $proxy) !== 1) {
            throw new \InvalidArgumentException('The proxy must be http(s)://host[:port]');
        }
        $this->guard = $guard ?? new NetworkGuard();
    }

    public function client(?float $timeout = null): ClientInterface
    {
        return $this->guzzle($timeout);
    }

    /**
     * The same client as Guzzle (streaming reads — e.g. the admin event stream —
     * need its request options).
     */
    public function guzzle(?float $timeout = null): Client
    {
        $stack = HandlerStack::create($this->handler ?? new CurlHandler());
        $stack->push($this->guardMiddleware(), 'trident_network_guard');

        return new Client([
            'handler' => $stack,
            'timeout' => $timeout ?? $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'allow_redirects' => false,
            'http_errors' => false,
            'proxy' => $this->proxy ?? [],
        ]);
    }

    /**
     * Resolve, refuse link-local / metadata targets, pin the checked address
     * for curl and switch environment proxies off.
     */
    private function guardMiddleware(): callable
    {
        $guard = $this->guard;
        $proxied = $this->proxy !== null;

        return static fn (callable $handler): callable => static function (RequestInterface $request, array $options) use ($handler, $guard, $proxied) {
            $uri = $request->getUri();
            $host = $uri->getHost();
            $port = $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80);
            try {
                $address = $guard->address($host);
            } catch (\RuntimeException $e) {
                return Create::rejectionFor(new ConnectException($e->getMessage(), $request));
            }
            if (!$proxied) {
                $options['curl'][\CURLOPT_NOPROXY] = '*';
                if (@inet_pton(trim($host, '[]')) === false) {
                    $options['curl'][\CURLOPT_RESOLVE] = [sprintf('%s:%d:%s', $host, $port, str_contains($address, ':') ? '[' . $address . ']' : $address)];
                }
            }

            return $handler($request, $options);
        };
    }

    public function transport(?float $timeout = null): Transport
    {
        $factory = new HttpFactory();

        return new Psr18Transport($this->client($timeout), $factory, $factory);
    }
}

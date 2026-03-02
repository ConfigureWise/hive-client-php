<?php

declare(strict_types=1);

namespace HiveCpq\Client\Authentication;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;

class HiveOAuth2TokenProvider
{
    private ?TokenResult $tokenResult = null;
    private readonly HiveAuthOptions $options;

    public function __construct(HiveAuthOptions $options)
    {
        $options->validate();
        $this->options = $options;
    }

    public function getToken(): string
    {
        if ($this->tokenResult !== null && time() < $this->tokenResult->expiresAt - $this->options->tokenRefreshBuffer) {
            return $this->tokenResult->accessToken;
        }

        $this->tokenResult = $this->obtainToken();
        return $this->tokenResult->accessToken;
    }

    public function invalidateToken(): void
    {
        $this->tokenResult = null;
    }

    private function obtainToken(): TokenResult
    {
        $cookieJar = new CookieJar();

        $loginTicket = $this->getLoginTicket($cookieJar);
        return $this->exchangeLoginTicket($loginTicket, $cookieJar);
    }

    private function getLoginTicket(CookieJar $cookieJar): string
    {
        $client = new Client(['cookies' => $cookieJar]);

        $response = $client->post("https://{$this->options->authDomain}/co/authenticate", [
            RequestOptions::JSON => [
                'client_id' => $this->options->clientId,
                'credential_type' => 'http://auth0.com/oauth/grant-type/password-realm',
                'username' => $this->options->username,
                'password' => $this->options->password,
                'realm' => $this->options->realm,
            ],
            RequestOptions::HEADERS => [
                'Origin' => $this->options->redirectUri,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['login_ticket'])) {
            throw new AuthenticationException('No login_ticket in response: ' . json_encode($data));
        }

        return $data['login_ticket'];
    }

    private function exchangeLoginTicket(string $loginTicket, CookieJar $cookieJar): TokenResult
    {
        $state = base64_encode(random_bytes(16));
        $nonce = base64_encode(random_bytes(16));

        $authorizeUrl = "https://{$this->options->authDomain}/authorize?" . http_build_query([
            'client_id' => $this->options->clientId,
            'response_type' => 'token id_token',
            'response_mode' => 'fragment',
            'redirect_uri' => $this->options->redirectUri,
            'scope' => 'openid profile email',
            'realm' => $this->options->realm,
            'login_ticket' => $loginTicket,
            'state' => $state,
            'nonce' => $nonce,
        ]);

        $finalUrl = $this->followRedirects($authorizeUrl, $cookieJar);
        return $this->parseTokenFromUrl($finalUrl);
    }

    private function followRedirects(string $url, CookieJar $cookieJar): string
    {
        $client = new Client([
            'cookies' => $cookieJar,
            'allow_redirects' => false,
        ]);

        $currentUrl = $url;

        for ($i = 0; $i < 10; $i++) {
            $response = $client->get($currentUrl);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 300 && $statusCode < 400) {
                $location = $response->getHeaderLine('Location');
                if (empty($location)) {
                    throw new AuthenticationException('Redirect without Location header');
                }

                if (str_starts_with($location, '/')) {
                    $parsed = parse_url($currentUrl);
                    $currentUrl = "{$parsed['scheme']}://{$parsed['host']}{$location}";
                } else {
                    $currentUrl = $location;
                }

                if (str_contains($currentUrl, 'access_token=')) {
                    return $currentUrl;
                }
            } else {
                return $currentUrl;
            }
        }

        throw new AuthenticationException('Too many redirects during authentication');
    }

    private function parseTokenFromUrl(string $url): TokenResult
    {
        $fragmentPos = strpos($url, '#');
        if ($fragmentPos === false) {
            throw new AuthenticationException("No fragment in redirect URL: {$url}");
        }

        $fragment = substr($url, $fragmentPos + 1);
        parse_str($fragment, $params);

        $accessToken = $params['access_token'] ?? null;
        if (empty($accessToken)) {
            throw new AuthenticationException("No access_token in redirect URL: {$url}");
        }

        $expiresIn = (int) ($params['expires_in'] ?? 86400);

        return new TokenResult(
            accessToken: $accessToken,
            expiresAt: time() + $expiresIn,
            idToken: $params['id_token'] ?? null,
            tokenType: $params['token_type'] ?? 'Bearer',
        );
    }
}

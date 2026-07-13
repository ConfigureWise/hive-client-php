<?php

declare(strict_types=1);

namespace HiveCpq\Client\Authentication;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\SimpleCache\CacheInterface;

class ClientCredentialsTokenProvider
{
    private ?TokenResult $tokenResult = null;
    private readonly ClientCredentialsOptions $options;

    public function __construct(
        ClientCredentialsOptions $options,
        private readonly ?CacheInterface $cache = null,
        private readonly ?string $cacheKey = null,
    ) {
        $options->validate();
        $this->options = $options;
    }

    public function getToken(): string
    {
        if ($this->isUsable($this->tokenResult)) {
            return $this->tokenResult->accessToken;
        }

        $cached = $this->readFromCache();
        if ($this->isUsable($cached)) {
            $this->tokenResult = $cached;

            return $cached->accessToken;
        }

        $this->tokenResult = $this->obtainToken();
        $this->writeToCache($this->tokenResult);

        return $this->tokenResult->accessToken;
    }

    public function invalidateToken(): void
    {
        $this->tokenResult = null;

        if ($this->cache !== null && $this->cacheKey !== null) {
            try {
                $this->cache->delete($this->cacheKey);
            } catch (\Throwable) {
            }
        }
    }

    private function isUsable(?TokenResult $result): bool
    {
        return $result !== null && time() < $result->expiresAt - $this->options->tokenRefreshBuffer;
    }

    private function readFromCache(): ?TokenResult
    {
        if ($this->cache === null || $this->cacheKey === null) {
            return null;
        }

        try {
            $data = $this->cache->get($this->cacheKey);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($data) || !isset($data['access_token'], $data['expires_at'])) {
            return null;
        }

        return new TokenResult(
            accessToken: (string) $data['access_token'],
            expiresAt: (int) $data['expires_at'],
            tokenType: (string) ($data['token_type'] ?? 'Bearer'),
        );
    }

    private function writeToCache(TokenResult $result): void
    {
        if ($this->cache === null || $this->cacheKey === null) {
            return;
        }

        $ttl = $result->expiresAt - time() - $this->options->tokenRefreshBuffer;
        if ($ttl <= 0) {
            return;
        }

        try {
            $this->cache->set($this->cacheKey, [
                'access_token' => $result->accessToken,
                'expires_at' => $result->expiresAt,
                'token_type' => $result->tokenType,
            ], $ttl);
        } catch (\Throwable) {
        }
    }

    private function obtainToken(): TokenResult
    {
        $client = new Client([
            RequestOptions::CONNECT_TIMEOUT => $this->options->connectTimeout,
            RequestOptions::TIMEOUT => $this->options->requestTimeout,
        ]);

        $response = $client->post("https://{$this->options->authDomain}/oauth/token", [
            RequestOptions::JSON => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->options->clientId,
                'client_secret' => $this->options->clientSecret,
                'audience' => $this->options->audience,
                'domain' => $this->options->domain,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['access_token'])) {
            throw new AuthenticationException('No access_token in response: ' . json_encode($data));
        }

        return new TokenResult(
            accessToken: $data['access_token'],
            expiresAt: time() + (int) ($data['expires_in'] ?? 86400),
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }
}

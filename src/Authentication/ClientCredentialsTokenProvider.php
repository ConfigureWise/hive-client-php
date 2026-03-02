<?php

declare(strict_types=1);

namespace HiveCpq\Client\Authentication;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class ClientCredentialsTokenProvider
{
    private ?TokenResult $tokenResult = null;
    private readonly ClientCredentialsOptions $options;

    public function __construct(ClientCredentialsOptions $options)
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
        $client = new Client();

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

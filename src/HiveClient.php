<?php

declare(strict_types=1);

namespace HiveCpq\Client;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use HiveCpq\Client\Authentication\BearerTokenProvider;
use HiveCpq\Client\Authentication\ClientCredentialsOptions;
use HiveCpq\Client\Authentication\ClientCredentialsTokenProvider;
use HiveCpq\Client\Authentication\HiveAuthOptions;
use HiveCpq\Client\Authentication\HiveOAuth2TokenProvider;
use HiveCpq\Client\Generated\Api\CategoryApi;
use HiveCpq\Client\Generated\Api\CommonApi;
use HiveCpq\Client\Generated\Api\CompanyApi;
use HiveCpq\Client\Generated\Api\ComplaintApi;
use HiveCpq\Client\Generated\Api\ComponentApi;
use HiveCpq\Client\Generated\Api\ConfigurationApi;
use HiveCpq\Client\Generated\Api\ConfiguratorApi;
use HiveCpq\Client\Generated\Api\ContactApi;
use HiveCpq\Client\Generated\Api\CustomObjectApi;
use HiveCpq\Client\Generated\Api\FeatureApi;
use HiveCpq\Client\Generated\Api\InviteApi;
use HiveCpq\Client\Generated\Api\ManufacturerApi;
use HiveCpq\Client\Generated\Api\OutputDocumentApi;
use HiveCpq\Client\Generated\Api\PluginApi;
use HiveCpq\Client\Generated\Api\PriceCatalogApi;
use HiveCpq\Client\Generated\Api\ProjectApi;
use HiveCpq\Client\Generated\Api\ProjectSegmentApi;
use HiveCpq\Client\Generated\Api\ProjectSegmentItemApi;
use HiveCpq\Client\Generated\Api\PropertyApi;
use HiveCpq\Client\Generated\Api\SalesConditionsApi;
use HiveCpq\Client\Generated\Api\SecurityApi;
use HiveCpq\Client\Generated\Api\UnitApi;
use HiveCpq\Client\Generated\Api\UserAccountApi;
use HiveCpq\Client\Generated\Api\VersioningApi;
use HiveCpq\Client\Generated\Api\WebhookApi;
use HiveCpq\Client\Generated\Configuration as GeneratedConfiguration;
use HiveCpq\Client\Middleware\DefaultHeadersMiddleware;
use HiveCpq\Client\Middleware\LoggingMiddleware;
use HiveCpq\Client\Middleware\RetryMiddleware;
use Psr\Log\LoggerInterface;

class HiveClient
{
    private readonly Client $httpClient;
    private readonly GeneratedConfiguration $configuration;

    public function __construct(HiveClientOptions $options, ?LoggerInterface $logger = null)
    {
        $options->validate();

        $stack = HandlerStack::create();

        $stack->push(RetryMiddleware::create($options->maxRetries, $options->retryDelay, $logger));

        $stack->push(DefaultHeadersMiddleware::create(
            $options->defaultHeaders,
            $options->userAgent,
            $options->correlationIdHeaderName,
            $options->correlationIdProvider,
        ));

        if ($logger !== null) {
            $stack->push(LoggingMiddleware::create($logger));
        }

        $tokenProvider = $this->resolveTokenProvider($options);
        $stack->push(new BearerTokenProvider($tokenProvider));

        $this->httpClient = new Client([
            'handler' => $stack,
            'timeout' => $options->timeout,
        ]);

        $this->configuration = new GeneratedConfiguration();
        $this->configuration->setHost(rtrim($options->baseUrl, '/'));
    }

    public static function create(string $bearerToken, ?string $baseUrl = null): self
    {
        $options = new HiveClientOptions();
        $options->bearerToken = $bearerToken;

        if ($baseUrl !== null) {
            $options->baseUrl = $baseUrl;
        }

        return new self($options);
    }

    public static function createWithOAuth2(HiveAuthOptions $authOptions, ?string $baseUrl = null, ?LoggerInterface $logger = null): self
    {
        $tokenProvider = new HiveOAuth2TokenProvider($authOptions);

        $options = new HiveClientOptions();
        $options->tokenProvider = $tokenProvider->getToken(...);

        if ($baseUrl !== null) {
            $options->baseUrl = $baseUrl;
        }

        return new self($options, $logger);
    }

    public static function createWithClientCredentials(ClientCredentialsOptions $credentialsOptions, ?string $baseUrl = null, ?LoggerInterface $logger = null): self
    {
        $tokenProvider = new ClientCredentialsTokenProvider($credentialsOptions);

        $options = new HiveClientOptions();
        $options->tokenProvider = $tokenProvider->getToken(...);

        if ($baseUrl !== null) {
            $options->baseUrl = $baseUrl;
        }

        return new self($options, $logger);
    }

    public function categories(): CategoryApi
    {
        return new CategoryApi($this->httpClient, $this->configuration);
    }

    public function common(): CommonApi
    {
        return new CommonApi($this->httpClient, $this->configuration);
    }

    public function companies(): CompanyApi
    {
        return new CompanyApi($this->httpClient, $this->configuration);
    }

    public function complaints(): ComplaintApi
    {
        return new ComplaintApi($this->httpClient, $this->configuration);
    }

    public function components(): ComponentApi
    {
        return new ComponentApi($this->httpClient, $this->configuration);
    }

    public function configurations(): ConfigurationApi
    {
        return new ConfigurationApi($this->httpClient, $this->configuration);
    }

    public function configurators(): ConfiguratorApi
    {
        return new ConfiguratorApi($this->httpClient, $this->configuration);
    }

    public function contacts(): ContactApi
    {
        return new ContactApi($this->httpClient, $this->configuration);
    }

    public function customObjects(): CustomObjectApi
    {
        return new CustomObjectApi($this->httpClient, $this->configuration);
    }

    public function features(): FeatureApi
    {
        return new FeatureApi($this->httpClient, $this->configuration);
    }

    public function invites(): InviteApi
    {
        return new InviteApi($this->httpClient, $this->configuration);
    }

    public function manufacturers(): ManufacturerApi
    {
        return new ManufacturerApi($this->httpClient, $this->configuration);
    }

    public function outputDocuments(): OutputDocumentApi
    {
        return new OutputDocumentApi($this->httpClient, $this->configuration);
    }

    public function plugins(): PluginApi
    {
        return new PluginApi($this->httpClient, $this->configuration);
    }

    public function priceCatalogs(): PriceCatalogApi
    {
        return new PriceCatalogApi($this->httpClient, $this->configuration);
    }

    public function projects(): ProjectApi
    {
        return new ProjectApi($this->httpClient, $this->configuration);
    }

    public function projectSegments(): ProjectSegmentApi
    {
        return new ProjectSegmentApi($this->httpClient, $this->configuration);
    }

    public function projectSegmentItems(): ProjectSegmentItemApi
    {
        return new ProjectSegmentItemApi($this->httpClient, $this->configuration);
    }

    public function properties(): PropertyApi
    {
        return new PropertyApi($this->httpClient, $this->configuration);
    }

    public function salesConditions(): SalesConditionsApi
    {
        return new SalesConditionsApi($this->httpClient, $this->configuration);
    }

    public function security(): SecurityApi
    {
        return new SecurityApi($this->httpClient, $this->configuration);
    }

    public function units(): UnitApi
    {
        return new UnitApi($this->httpClient, $this->configuration);
    }

    public function userAccounts(): UserAccountApi
    {
        return new UserAccountApi($this->httpClient, $this->configuration);
    }

    public function versioning(): VersioningApi
    {
        return new VersioningApi($this->httpClient, $this->configuration);
    }

    public function webhooks(): WebhookApi
    {
        return new WebhookApi($this->httpClient, $this->configuration);
    }

    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    public function getConfiguration(): GeneratedConfiguration
    {
        return $this->configuration;
    }

    private function resolveTokenProvider(HiveClientOptions $options): string|callable
    {
        if ($options->tokenProvider !== null) {
            return $options->tokenProvider;
        }

        return $options->bearerToken;
    }
}

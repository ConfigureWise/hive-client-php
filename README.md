# HiveCpq.Client (PHP)

A PHP client library for the Hive CPQ Product Configurator API, generated with [OpenAPI Generator](https://openapi-generator.tech/).

> Looking for the .NET version? See [hive-client-dotnet](https://github.com/ConfigureWise/hive-client-dotnet).

## Installation

```bash
composer require hivecpq/client
```

## Quick Start

### Client Credentials (recommended for server-to-server)

```php
use HiveCpq\Client\Authentication\ClientCredentialsOptions;
use HiveCpq\Client\HiveClient;

$credentials = new ClientCredentialsOptions();
$credentials->clientId = 'your-client-id';
$credentials->clientSecret = 'your-client-secret';

$client = HiveClient::createWithClientCredentials($credentials);

$manufacturers = $client->manufacturers()->getManufacturersList();

foreach ($manufacturers->getItems() as $manufacturer) {
    echo $manufacturer->getName() . PHP_EOL;
}
```

### Bearer Token

```php
use HiveCpq\Client\HiveClient;

$client = HiveClient::create('your-jwt-token');
```

### OAuth2 (Username/Password)

```php
use HiveCpq\Client\Authentication\HiveAuthOptions;
use HiveCpq\Client\HiveClient;

$authOptions = new HiveAuthOptions();
$authOptions->username = 'your-email@example.com';
$authOptions->password = 'your-password';

$client = HiveClient::createWithOAuth2($authOptions);
```

## Configuration Options

```php
use HiveCpq\Client\HiveClientOptions;
use HiveCpq\Client\HiveClient;

$options = new HiveClientOptions();
$options->bearerToken = 'your-jwt-token';
$options->baseUrl = 'https://connect.hivecpq.com/api/v1'; // default
$options->timeout = 30;        // seconds, total per request
$options->connectTimeout = 10; // seconds, until the connection is established
$options->maxRetries = 3;      // retry attempts for transient failures
$options->retryDelay = 1.0;    // initial retry delay in seconds
$options->maxRetryDelay = 30.0;  // cap on any retry delay, including Retry-After
$options->maxRequestsPerSecond = 0; // client-side pacing, 0 disables
$options->userAgent = 'MyApp/1.0.0';
$options->defaultHeaders = [
    'X-Custom-Header' => 'custom-value',
];
$options->correlationIdHeaderName = 'X-Correlation-ID';
$options->correlationIdProvider = fn() => bin2hex(random_bytes(8));

$client = new HiveClient($options);
```

### Retry Behaviour

A `429 Too Many Requests` is always retried: the server rejected the call without processing it. Server errors (5xx) and connection errors, including timeouts, are only retried when the request is safe to repeat:

- idempotent methods (`GET`, `HEAD`, `PUT`, `DELETE`, `OPTIONS`, `TRACE`)
- `POST` to `/bulkUpsert`, `/bulkDelete` and `/checkConfiguration`, which are keyed by the caller or set values on an existing resource
- any request carrying the `X-Hive-Retry-Unsafe` header (`RetryMiddleware::RETRY_UNSAFE_HEADER`)

Other `POST` calls, such as creating a project or adding a configuration, fail on the first error so a lost response never turns into a duplicate. A `Retry-After` header is honoured up to `maxRetryDelay`.

### With Logging (PSR-3)

```php
use HiveCpq\Client\HiveClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('hive');
$logger->pushHandler(new StreamHandler('php://stdout'));

$client = HiveClient::create('your-jwt-token');
// or
$client = new HiveClient($options, $logger);
```

## Symfony Integration

### 1. Register the bundle

```php
// config/bundles.php
return [
    // ...
    HiveCpq\Client\Symfony\HiveCpqBundle::class => ['all' => true],
];
```

### 2. Configure

```yaml
# config/packages/hive_cpq.yaml
hive_cpq:
    base_url: 'https://connect.hivecpq.com/api/v1'
    timeout: 30
    connect_timeout: 10
    max_retries: 3

    auth:
        type: client_credentials  # or: oauth2, bearer_token
        client_id: '%env(HIVE_CLIENT_ID)%'
        client_secret: '%env(HIVE_CLIENT_SECRET)%'
```

```dotenv
# .env.local
HIVE_CLIENT_ID=your-client-id
HIVE_CLIENT_SECRET=your-client-secret
```

**Other auth types:**

```yaml
# OAuth2 (username/password)
hive_cpq:
    auth:
        type: oauth2
        username: '%env(HIVE_AUTH_USERNAME)%'
        password: '%env(HIVE_AUTH_PASSWORD)%'

# Static bearer token
hive_cpq:
    auth:
        type: bearer_token
        bearer_token: '%env(HIVE_BEARER_TOKEN)%'
```

### 3. Inject and use

```php
use HiveCpq\Client\HiveClient;

class ManufacturerService
{
    public function __construct(
        private readonly HiveClient $hiveClient,
    ) {}

    public function getManufacturers(): array
    {
        $result = $this->hiveClient->manufacturers()->getManufacturersList();
        return $result->getItems();
    }
}
```

## API Usage Examples

### Manufacturers

```php
$manufacturers = $client->manufacturers()->getManufacturersList();

foreach ($manufacturers->getItems() as $manufacturer) {
    echo $manufacturer->getName() . ' (ID: ' . $manufacturer->getId() . ')' . PHP_EOL;
}
```

### Companies

```php
$companies = $client->companies()->getManufacturerCompanies($manufacturerId);
```

### Projects

```php
$projects = $client->projects()->getProjectsList($manufacturerId);
```

### Configurations

```php
$configs = $client->configurations()->getConfigurationsList($manufacturerId);
```

## Available API Methods

The client exposes all API endpoints through typed accessor methods:

| Method | API |
|--------|-----|
| `$client->manufacturers()` | Manufacturers |
| `$client->companies()` | Companies |
| `$client->projects()` | Projects |
| `$client->projectSegments()` | Project Segments |
| `$client->projectSegmentItems()` | Project Segment Items |
| `$client->configurations()` | Configurations |
| `$client->configurators()` | Configurators |
| `$client->contacts()` | Contacts |
| `$client->components()` | Components |
| `$client->features()` | Features |
| `$client->properties()` | Properties |
| `$client->categories()` | Categories |
| `$client->customObjects()` | Custom Objects |
| `$client->units()` | Units |
| `$client->priceCatalogs()` | Price Catalogs |
| `$client->salesConditions()` | Sales Conditions |
| `$client->outputDocuments()` | Output Documents |
| `$client->invites()` | Invites |
| `$client->complaints()` | Complaints |
| `$client->plugins()` | Plugins |
| `$client->webhooks()` | Webhooks |
| `$client->security()` | Security |
| `$client->userAccounts()` | User Accounts |
| `$client->versioning()` | Versioning |
| `$client->common()` | Common |

## Error Handling

```php
use HiveCpq\Client\Generated\ApiException;

try {
    $result = $client->manufacturers()->getManufacturersList();
} catch (ApiException $e) {
    echo "API Error: {$e->getCode()} - {$e->getMessage()}" . PHP_EOL;
}
```

## Regenerating the Client

When the OpenAPI specification changes:

```bash
./scripts/regenerate.sh         # regenerate
./scripts/regenerate.sh --clean # clean + regenerate
```

Requires [OpenAPI Generator](https://openapi-generator.tech/docs/installation):
```bash
brew install openapi-generator
```

## Project Structure

```
hive-client-php/
├── OpenAPI Specification.json    # API specification (source of truth)
├── composer.json
├── phpunit.xml
├── scripts/
│   └── regenerate.sh
├── src/
│   ├── HiveClient.php            # Main wrapper (hand-written)
│   ├── HiveClientOptions.php     # Configuration (hand-written)
│   ├── Authentication/           # Auth providers (hand-written)
│   ├── Middleware/               # Guzzle middleware (hand-written)
│   ├── Symfony/                  # Symfony bundle (hand-written)
│   └── Generated/               # OpenAPI Generator output (DO NOT EDIT)
└── tests/
```

## Contributing

1. Do not manually edit files in the `Generated/` folder
2. All customizations should be in separate files
3. Run regeneration after any OpenAPI spec changes
4. Ensure all tests pass before submitting

## License

MIT

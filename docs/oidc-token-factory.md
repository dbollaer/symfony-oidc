# OidcTokenFactory

The `OidcTokenFactory` is a new service that works similarly to `OidcHttpClientFactory` but returns access tokens instead of HTTP clients.

## Features

- **Token Exchange**: Exchanges the original access token for one with the target scope/audience
- **Caching**: Caches exchanged tokens to avoid repeated token exchanges
- **Multiple Clients**: Supports multiple OIDC clients with different configurations
- **Error Handling**: Proper error handling for token exchange failures

## Usage

### Basic Usage

```php
use Drenso\OidcBundle\Http\OidcTokenFactoryInterface;

class MyService
{
    public function __construct(
        private readonly OidcTokenFactoryInterface $tokenFactory,
    ) {
    }

    public function getAccessToken(): string
    {
        return $this->tokenFactory->getAccessToken();
    }
}
```

### Configuration

To enable the token factory, add the following to your OIDC client configuration:

```yaml
drenso_oidc:
  clients:
    default:
      # ... other configuration ...
      enable_token_factory: true
      token_factory_cache_time: 3600  # Optional, defaults to http_client_factory_cache_time
```

### Service Names

The token factory creates services with the following naming pattern:
- `drenso.oidc.token_factory.{client_name}` - Individual token factory for each client
- `drenso.oidc.token_factory_locator` - Locator service to get token factories by name

### Interface

```php
interface OidcTokenFactoryInterface
{
    public function getAccessToken(): string;
}
```

## Implementation Details

The `OidcTokenFactory`:

1. **Retrieves the original token** from the session storage
2. **Exchanges the token** for one with the target scope/audience
3. **Caches the result** to avoid repeated exchanges
4. **Returns the access token** as a string

### Caching

The factory uses the same caching mechanism as `OidcHttpClientFactory`:
- Cache key is based on original token, scope, and audience
- Cache expiry is based on token expiry or configured cache time
- Falls back to direct token exchange if cache fails

### Error Handling

The factory throws `LogicException` if:
- Session storage is not set
- Token exchange fails

## Comparison with OidcHttpClientFactory

| Feature | OidcHttpClientFactory | OidcTokenFactory |
|---------|----------------------|------------------|
| Returns | HttpClientInterface | string (access token) |
| Caching | ✅ | ✅ |
| Token Exchange | ✅ | ✅ |
| Multiple Clients | ✅ | ✅ |
| Error Handling | ✅ | ✅ |

## Migration from OidcHttpClientFactory

If you need just the access token instead of an HTTP client:

```php
// Before (with OidcHttpClientFactory)
$httpClient = $this->httpClientFactory->createHttpClientWithToken();
$response = $httpClient->request('GET', 'https://api.example.com/data');

// After (with OidcTokenFactory)
$accessToken = $this->tokenFactory->getAccessToken();
// Use the token directly in your HTTP requests
``` 
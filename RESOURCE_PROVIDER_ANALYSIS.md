# Resource Provider Authenticator - Analysis of Reusable Logic

## Current Implementation Issues

1. **OidcTokenExchangeAuthenticator** (line 62-65):
   - Creates `OidcTokens` with access token as both `access_token` and `id_token`
   - This is a workaround because `OidcTokens` constructor requires both tokens
   - Only uses introspection (remote API call)

2. **Missing JWT Validation**:
   - `OidcClient` already has `OidcJwtHelper` integrated
   - `OidcJwtHelper::verifyAccessToken()` handles both JWT and opaque tokens
   - Not being used in resource provider authenticator

## Reusable Logic from OidcClient

### 1. JWT Validation Pattern (exchangeTokens method, line 119-136)

```php
// Pattern used in OidcClient::exchangeTokens()
$tokens = new OidcTokens($unvalidatedTokens);
$this->jwtHelper->verifyAccessToken($this->getIssuer(), $this->getJwksUri(), $tokens, false);
```

**Key Points**:
- `verifyAccessToken()` handles both JWT and opaque tokens gracefully
- For opaque tokens: throws `InvalidJwtTokenException` on parse, caught and ignored (OidcJwtHelper line 141-144)
- Validates JWT signature, issuer, expiration when token is JWT
- No network call for JWT validation (local validation)

### 2. OidcJwtHelper Integration

- Already available in `OidcClient` as protected `jwtHelper` property
- `verifyAccessToken()` method signature:
  ```php
  public function verifyAccessToken(string $issuer, string $jwksUri, OidcTokens $tokens, bool $verifyNonce): void
  ```
- Gracefully handles opaque tokens (catches `InvalidJwtTokenException`)

### 3. Configuration Access

- `getIssuer()` and `getJwksUri()` are protected methods in `OidcClient`
- Currently not accessible via `OidcClientInterface`
- Need to either:
  - Expose via interface, OR
  - Add helper method to validate resource server tokens

### 4. Token Introspection

- Already available via `OidcClientInterface::introspect()`
- Can be used as fallback when JWT validation not applicable
- Current implementation uses this exclusively

### 5. OidcTokens Requirement

- `OidcTokens` constructor requires both `access_token` and `id_token`
- Current workaround (setting both to access token) is acceptable
- `introspect()` and `verifyAccessToken()` only use access token anyway

## Recommended Implementation Strategy

### Option A: Use OidcClient Pattern Directly

1. **Extend OidcClientInterface**:
   - Add `getIssuer(): string`
   - Add `getJwksUri(): string`

2. **Update OidcTokenExchangeAuthenticator**:
   - Get issuer and JWKS URI from OidcClient
   - Use `OidcJwtHelper::verifyAccessToken()` via OidcClient's jwtHelper
   - Extract user data from JWT claims when validation succeeds
   - Fall back to introspection for opaque tokens

### Option B: Add Helper Method to OidcClient

1. **Add to OidcClientInterface**:
   ```php
   public function validateResourceServerToken(string $accessToken): array
   ```
   Returns: `['userData' => OidcUserData, 'userIdentifier' => string, 'isJwt' => bool]`

2. **Implement in OidcClient**:
   - Try JWT validation first
   - Extract claims if JWT
   - Fall back to introspection if opaque
   - Return user data and identifier

3. **Update OidcTokenExchangeAuthenticator**:
   - Call `validateResourceServerToken()` method
   - Use returned data

### Option C: Direct JWT Helper Injection

1. **Inject OidcJwtHelper** into authenticator
2. **Get issuer/JWKS URI** from OidcClient (requires interface extension)
3. **Use JWT helper directly** for validation

## Recommendation

**Option A** is cleanest and reuses existing patterns:
- Follows same pattern as `exchangeTokens()`
- Minimal interface changes
- Leverages existing OidcJwtHelper integration
- Maintains separation of concerns

## Implementation Steps

1. **Extend OidcClientInterface** with `getIssuer()` and `getJwksUri()`
2. **Implement in OidcClient** (already exists, just make public)
3. **Update OidcTokenExchangeAuthenticator**:
   - Get issuer and JWKS URI from OidcClient
   - Access jwtHelper (requires making it accessible or adding method)
   - Try JWT validation first
   - Extract user data from JWT claims
   - Fall back to introspection

## Key Code Patterns

### JWT Validation (from OidcClient::exchangeTokens)
```php
$tokens = new OidcTokens((object)['access_token' => $accessToken, 'id_token' => $accessToken]);
$this->jwtHelper->verifyAccessToken($this->getIssuer(), $this->getJwksUri(), $tokens, false);
```

### JWT Claim Extraction
```php
$parsedToken = OidcJwtHelper::parseToken($accessToken);
$claims = $parsedToken->claims()->all();
$userData = new OidcUserData($claims);
$userIdentifier = $parsedToken->claims()->get('sub');
```

### Introspection Fallback
```php
$introspectionData = $this->oidcClient->introspect($tokens, OidcTokenType::ACCESS);
$userData = new OidcUserData($introspectionData->getIntrospectionDataArray());
$userIdentifier = $introspectionData->getSub();
```


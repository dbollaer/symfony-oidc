<?php

namespace Drenso\OidcBundle\Http;

use Drenso\OidcBundle\Exception\OidcException;
use Drenso\OidcBundle\OidcClient;
use Drenso\OidcBundle\OidcSessionStorage;
use LogicException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class OidcHttpClientFactory implements OidcHttpClientFactoryInterface
{
  public function __construct(
    private ?HttpClientInterface $httpClient,
    private ?OidcSessionStorage $sessionStorage,
    private OidcClient $oidcClient,
    private string $scope,
    private string $audience,
    private ?CacheInterface $cache = null,
    private ?int $cacheTime = null,
  ) {
  }

  public function createHttpClientWithToken(): HttpClientInterface
  {
    if (null === $this->httpClient) {
      throw new LogicException('HttpClient is not set.');
    }
    if (null === $this->sessionStorage) {
      throw new LogicException('Session storage is not set.');
    }

    $tokens = $this->getExchangedTokensWithCaching();

    return $this->httpClient->withOptions([
      'headers' => [
        'Authorization' => 'Bearer ' . $tokens->getAccessToken(),
      ],
    ]);
  }

  public function getAccessToken(): string
  {
    return $this->getExchangedTokensWithCaching()->getAccessToken();
  }

  private function getExchangedTokensWithCaching()
  {
    $originalToken = $this->sessionStorage->getAccessToken();
    
    // Exchange the original access token for one with the target scope/audience
    // Create a cache key based on the original token, scope, and audience
    $cacheKey = $this->generateCacheKey($originalToken, $this->scope, $this->audience);
    
    if ($this->isCacheEnabled()) {
      try {
        $exchangedTokens = $this->cache->get($cacheKey, function (ItemInterface $item) use ($originalToken) {
          // Exchange the original token for one with target scope/audience
          $tokens = $this->exchangeTokens($originalToken);
          
          // Set cache expiry based on the token's actual expiry time
          $expiry = $tokens->getExpiry();
          if ($expiry) {
            $item->expiresAt($expiry);
          } else {
            // Fallback to configured cache time if no expiry is provided
            $cacheTime = $this->cacheTime ?? 3600; // Default 1 hour
            $item->expiresAfter($cacheTime);
          }
          
          return $tokens;
        });
        
        return $exchangedTokens;
      } catch (\Psr\Cache\InvalidArgumentException $e) {
        // If cache fails, fall back to direct token exchange
        $exchangedTokens = $this->exchangeTokens($originalToken);
        return $exchangedTokens;
      }
    } else {
      $exchangedTokens = $this->exchangeTokens($originalToken);
      return $exchangedTokens;
    }
    
    // No cache available, perform direct token exchange
    return $this->exchangeTokens($originalToken);
  }

  private function exchangeTokens(string $accessToken)
  {
    
    $exchangeToken = $this->oidcClient->exchangeTokens(
      accessToken: $accessToken,
      targetScope: $this->scope,
      targetAudience: $this->audience,
      subjectTokenType: 'urn:ietf:params:oauth:token-type:access_token'
    );
    $exchangeToken2 = $this->oidcClient->exchangeTokens(
      accessToken: $exchangeToken->getAccessToken(),
      targetScope: $this->scope,
      targetAudience: $this->audience,
      subjectTokenType: 'urn:ietf:params:oauth:token-type:access_token'
    );

    return $exchangeToken;
  }

  private function generateCacheKey(string $accessToken, string $scope, string $audience): string
  {
    $slugger = new AsciiSlugger('en');
    $tokenHash = hash('sha256', $accessToken);
    
    return sprintf(
      '_drenso_oidc_http_client_factory__token_exchange__%s__%s__%s__%s',
      $slugger->slug($scope),
      $slugger->slug($audience),
      $tokenHash,
      substr($tokenHash, 0, 8)
    );
  }

  private function isCacheEnabled(): bool
  {
    return $this->cache !== null && $this->cacheTime !== null;
  }
}


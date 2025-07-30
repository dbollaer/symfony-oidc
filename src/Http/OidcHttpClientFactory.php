<?php

namespace Drenso\OidcBundle\Http;

use Drenso\OidcBundle\Exception\OidcException;
use Drenso\OidcBundle\OidcClient;
use Drenso\OidcBundle\OidcSessionStorage;
use LogicException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OidcHttpClientFactory implements OidcHttpClientFactoryInterface
{
  public function __construct(
    private ?HttpClientInterface $httpClient,
    private ?OidcSessionStorage $sessionStorage,
    private OidcClient $oidcClient,
    private string $scope,
    private string $audience,
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

      $tokens = $this->oidcClient->exchangeTokens(
        accessToken: $this->sessionStorage->getAccessToken(),
        targetScope: $this->scope,
        targetAudience: $this->audience,
        subjectTokenType: 'urn:ietf:params:oauth:token-type:access_token'
      );

      return $this->httpClient->withOptions([
        'headers' => [
          'Authorization' => 'Bearer ' . $tokens->getAccessToken(),
        ],
      ]);

  }
}


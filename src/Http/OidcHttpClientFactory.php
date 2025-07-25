<?php

namespace Drenso\OidcBundle\Http;

use Drenso\OidcBundle\OidcSessionStorage;
use LogicException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OidcHttpClientFactory implements OidcHttpClientFactoryInterface
{
  public function __construct(private ?HttpClientInterface $httpClient, private ?OidcSessionStorage $sessionStorage)
  {
  }

  public function createHttpClientWithToken(): HttpClientInterface
  {
    if (null === $this->httpClient) {
      throw new LogicException('HttpClient is not set.');
    }
    if (null === $this->sessionStorage) {
      throw new LogicException('Session storage is not set.');
    }

    return $this->httpClient->withOptions([
      'headers' => [
        'Authorization' => 'Bearer ' . $this->sessionStorage->getAccessToken(),
      ],
    ]);
  }
}

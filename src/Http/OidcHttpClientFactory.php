<?php

namespace Drenso\OidcBundle\Http;

use Drenso\OidcBundle\OidcSessionStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OidcHttpClientFactory implements OidcHttpClientFactoryInterface
{
    public function __construct(private HttpClientInterface $httpClient, private OidcSessionStorage $sessionStorage) {}

    public function createHttpClientWithToken(): HttpClientInterface
    {
        return $this->httpClient->withOptions([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->sessionStorage->getAccessToken(),
            ],
        ]);
    }
} 
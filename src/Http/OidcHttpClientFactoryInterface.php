<?php

namespace Drenso\OidcBundle\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;

interface OidcHttpClientFactoryInterface
{
    public function createHttpClientWithToken(): HttpClientInterface;

    public function getAccessToken(): string;
} 
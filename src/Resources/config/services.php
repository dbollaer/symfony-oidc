<?php

use Drenso\OidcBundle\DependencyInjection\DrensoOidcExtension;
use Drenso\OidcBundle\Http\OidcHttpClientFactory;
use Drenso\OidcBundle\Http\OidcHttpClientFactoryLocator;
use Drenso\OidcBundle\Http\OidcTokenFactory;
use Drenso\OidcBundle\Http\OidcTokenFactoryLocator;
use Drenso\OidcBundle\OidcClient;
use Drenso\OidcBundle\OidcClientLocator;
use Drenso\OidcBundle\OidcJwtHelper;
use Drenso\OidcBundle\OidcSessionStorage;
use Drenso\OidcBundle\OidcUrlFetcher;
use Drenso\OidcBundle\Security\OidcAuthenticator;
use Drenso\OidcBundle\Security\OidcTokenExchangeAuthenticator;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator): void {
  $configurator->services()
    ->set(DrensoOidcExtension::AUTHENTICATOR_ID, OidcAuthenticator::class)
    ->abstract()

    ->set(DrensoOidcExtension::URL_FETCHER_ID, OidcUrlFetcher::class)
    ->abstract()

    ->set(DrensoOidcExtension::SESSION_STORAGE_ID, OidcSessionStorage::class)
    ->args([
      service(RequestStack::class),
    ])
    ->abstract()

    ->set(DrensoOidcExtension::JWT_HELPER_ID, OidcJwtHelper::class)
    ->args([
      service(CacheInterface::class)->nullOnInvalid(),
      service(ClockInterface::class)->nullOnInvalid(),
    ])
    ->abstract()

    ->set(DrensoOidcExtension::CLIENT_ID, OidcClient::class)
    ->args([
      service(RequestStack::class),
      service(HttpUtils::class),
      service(CacheInterface::class)->nullOnInvalid(),
    ])
    ->abstract()

    ->set(DrensoOidcExtension::TOKEN_EXCHANGE_AUTHENTICATOR_ID, OidcTokenExchangeAuthenticator::class)
    ->abstract()

    ->set(DrensoOidcExtension::CLIENT_LOCATOR_ID, OidcClientLocator::class)
    ->alias(OidcClientLocator::class, DrensoOidcExtension::CLIENT_LOCATOR_ID)

    ->set(DrensoOidcExtension::HTTP_CLIENT_FACTORY_ID, OidcHttpClientFactory::class)
    ->args([
      service(HttpClientInterface::class)->nullOnInvalid(),
      service(DrensoOidcExtension::SESSION_STORAGE_ID)->nullOnInvalid(),
      service(DrensoOidcExtension::CLIENT_ID)->nullOnInvalid(),
      '%drenso_oidc.clients.default.scope%',
      '%drenso_oidc.clients.default.audience%',
      service(CacheInterface::class)->nullOnInvalid(),
      '%drenso_oidc.clients.default.http_client_factory_cache_time%',
    ])->abstract()

    ->set(DrensoOidcExtension::HTTP_CLIENT_FACTORY_LOCATOR_ID, OidcHttpClientFactoryLocator::class)
    ->alias(OidcHttpClientFactoryLocator::class, DrensoOidcExtension::HTTP_CLIENT_FACTORY_LOCATOR_ID)

    ->set(DrensoOidcExtension::TOKEN_FACTORY_ID, OidcTokenFactory::class)
    ->args([
      service(DrensoOidcExtension::SESSION_STORAGE_ID)->nullOnInvalid(),
      service(DrensoOidcExtension::CLIENT_ID)->nullOnInvalid(),
      '%drenso_oidc.clients.default.scope%',
      '%drenso_oidc.clients.default.audience%',
      service(CacheInterface::class)->nullOnInvalid(),
      '%drenso_oidc.clients.default.token_factory_cache_time%',
    ])->abstract()

    ->set(DrensoOidcExtension::TOKEN_FACTORY_LOCATOR_ID, OidcTokenFactoryLocator::class)
    ->alias(OidcTokenFactoryLocator::class, DrensoOidcExtension::TOKEN_FACTORY_LOCATOR_ID)

    ->set(DrensoOidcExtension::HTTP_CLIENT_ID, HttpClient::class)
    ->factory([HttpClient::class, 'create'])
  ;
};

<?php

namespace Drenso\OidcBundle\DependencyInjection;

use Drenso\OidcBundle\Http\OidcHttpClientFactory;
use Drenso\OidcBundle\Http\OidcHttpClientFactoryInterface;
use Drenso\OidcBundle\Http\OidcTokenFactory;
use Drenso\OidcBundle\Http\OidcTokenFactoryInterface;
use Drenso\OidcBundle\OidcClientInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DrensoOidcExtension extends ConfigurableExtension
{
  public const BASE_ID                         = 'drenso.oidc.';
  public const AUTHENTICATOR_ID                = self::BASE_ID . 'authenticator';
  public const URL_FETCHER_ID                  = self::BASE_ID . 'url_fetcher';
  public const JWT_HELPER_ID                   = self::BASE_ID . 'jwt_helper';
  public const SESSION_STORAGE_ID              = self::BASE_ID . 'session_storage';
  public const CLIENT_ID                       = self::BASE_ID . 'client';
  public const CLIENT_LOCATOR_ID               = self::BASE_ID . 'client_locator';
  public const END_SESSION_LISTENER_ID         = self::BASE_ID . 'end_session_listener';
  public const TOKEN_EXCHANGE_AUTHENTICATOR_ID = self::BASE_ID . 'token_exchange_authenticator';
  public const HTTP_CLIENT_FACTORY_ID          = self::BASE_ID . 'http_client_factory';
  public const HTTP_CLIENT_FACTORY_LOCATOR_ID  = self::BASE_ID . 'http_client_factory_locator';
  public const HTTP_CLIENT_ID                  = self::BASE_ID . 'http_client';
  public const TOKEN_FACTORY_ID                = self::BASE_ID . 'token_factory';
  public const TOKEN_FACTORY_LOCATOR_ID        = self::BASE_ID . 'token_factory_locator';

  /** @param array<string, mixed> $mergedConfig */
  public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
  {
    // Autoload configured services
    $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
    $loader->load('services.php');

    // Load the configured clients
    $clientServices            = [];
    $httpClientFactoryServices = [];
    $tokenFactoryServices      = [];
    foreach ($mergedConfig['clients'] as $clientName => $clientConfig) {
      $clientServices[$clientName] = $this->registerClient($container, $clientName, $clientConfig);

      // Register OIDC HTTP client factory if enabled
      if (!empty($clientConfig['enable_http_client'])) {
        $factoryServiceId = sprintf('drenso.oidc.http_client_factory.%s', $clientName);
        $sessionStorageId = sprintf('%s.%s', self::SESSION_STORAGE_ID, $clientName);
        $container
          ->register($factoryServiceId, OidcHttpClientFactory::class)
          ->addArgument(new Reference(HttpClientInterface::class))
          ->addArgument(new Reference($sessionStorageId))
          ->addArgument(new Reference(sprintf('drenso.oidc.client.%s', $clientName)))
          ->addArgument($clientConfig['scope'])
          ->addArgument($clientConfig['audience'])
          ->addArgument(new Reference('cache.app'))
          ->addArgument($clientConfig['http_client_factory_cache_time']);
        $container->registerAliasForArgument($factoryServiceId, OidcHttpClientFactoryInterface::class, sprintf('%sOidcHttpClientFactory', $clientName));

        $httpClientFactoryServices[$clientName] = new Reference($factoryServiceId);
      }

      // Register OIDC token factory if enabled
      if (!empty($clientConfig['enable_token_factory'])) {
        $tokenFactoryServiceId = sprintf('drenso.oidc.token_factory.%s', $clientName);
        $sessionStorageId = sprintf('%s.%s', self::SESSION_STORAGE_ID, $clientName);
        $container
          ->register($tokenFactoryServiceId, OidcTokenFactory::class)
          ->addArgument(new Reference($sessionStorageId))
          ->addArgument(new Reference(sprintf('drenso.oidc.client.%s', $clientName)))
          ->addArgument($clientConfig['scope'])
          ->addArgument($clientConfig['audience'])
          ->addArgument(new Reference('cache.app'))
          ->addArgument($clientConfig['token_factory_cache_time'] ?? $clientConfig['http_client_factory_cache_time'] ?? 3600);
        $container->registerAliasForArgument($tokenFactoryServiceId, OidcTokenFactoryInterface::class, sprintf('%sOidcTokenFactory', $clientName));

        $tokenFactoryServices[$clientName] = new Reference($tokenFactoryServiceId);
      }
    }

    // Setup default alias
    $container
      ->setAlias(OidcClientInterface::class, sprintf('drenso.oidc.client.%s', $mergedConfig['default_client']));

    // Configure client locator
    $container
      ->getDefinition(self::CLIENT_LOCATOR_ID)
      ->addArgument(ServiceLocatorTagPass::register($container, $clientServices))
      ->addArgument($mergedConfig['default_client']);

    // Configure HTTP client factory locator
    if (!empty($httpClientFactoryServices)) {
      $container
        ->getDefinition(self::HTTP_CLIENT_FACTORY_LOCATOR_ID)
        ->addArgument(ServiceLocatorTagPass::register($container, $httpClientFactoryServices))
        ->addArgument($mergedConfig['default_client']);
    }

    // Configure token factory locator
    if (!empty($tokenFactoryServices)) {
      $container
        ->getDefinition(self::TOKEN_FACTORY_LOCATOR_ID)
        ->addArgument(ServiceLocatorTagPass::register($container, $tokenFactoryServices))
        ->addArgument($mergedConfig['default_client']);
    }
  }

  /** @param array<string, mixed> $config */
  private function registerClient(ContainerBuilder $container, string $name, array $config): Reference
  {
    $urlFetcherId = sprintf('%s.%s', self::URL_FETCHER_ID, $name);
    $container
      ->setDefinition($urlFetcherId, new ChildDefinition(self::URL_FETCHER_ID))
      ->addArgument($config['custom_client_headers'])
      ->addArgument($config['custom_client_options']);

    $sessionStorageId = sprintf('%s.%s', self::SESSION_STORAGE_ID, $name);
    $container
      ->setDefinition($sessionStorageId, new ChildDefinition(self::SESSION_STORAGE_ID))
      ->addArgument($name);

    $jwtHelperId                          = sprintf('%s.%s', self::JWT_HELPER_ID, $name);
    $additionalTokenConstraintsProviderId = $config['additional_token_constraints_provider'];
    $container
      ->setDefinition($jwtHelperId, new ChildDefinition(self::JWT_HELPER_ID))
      ->addArgument(new Reference($urlFetcherId))
      ->addArgument(new Reference($sessionStorageId))
      ->addArgument($config['client_id'])
      ->addArgument($config['jwks_cache_time'])
      ->addArgument($config['token_leeway_seconds'])
      ->addArgument($additionalTokenConstraintsProviderId ? new Reference($additionalTokenConstraintsProviderId) : null);

    $clientId          = sprintf('%s.%s', self::CLIENT_ID, $name);
    $wellKnownParserId = $config['well_known_parser'];
    $container
      ->setDefinition($clientId, new ChildDefinition(self::CLIENT_ID))
      ->addArgument(new Reference($urlFetcherId))
      ->addArgument(new Reference($sessionStorageId))
      ->addArgument(new Reference($jwtHelperId))
      ->addArgument($config['well_known_url'])
      ->addArgument($config['well_known_cache_time'])
      ->addArgument($config['client_id'])
      ->addArgument($config['client_secret'])
      ->addArgument($config['redirect_route'])
      ->addArgument($config['remember_me_parameter'])
      ->addArgument($wellKnownParserId ? new Reference($wellKnownParserId) : null)
      ->addArgument($config['code_challenge_method'])
      ->addArgument($config['disable_nonce'])
      ->addArgument($config['audience'])
      ->addArgument($config['scope']);

    $container
      ->registerAliasForArgument($clientId, OidcClientInterface::class, sprintf('%sOidcClient', $name));

    return new Reference($clientId);
  }
}

<?php

namespace Drenso\OidcBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder(): TreeBuilder
  {
    $treeBuilder = new TreeBuilder('drenso_oidc');

    $treeBuilder->getRootNode()
        ->fixXmlConfig('client')
        ->children()
          ->scalarNode('default_client')
            ->info('The default client to use')
            ->defaultValue('default')
          ->end()
          ->arrayNode('clients')
            ->useAttributeAsKey('name')
            ->requiresAtLeastOneElement()
            ->arrayPrototype()
              ->children()
                ->scalarNode('well_known_url')
                  ->isRequired()
                ->end() // well_known_url
                ->scalarNode('well_known_parser')
                  ->defaultNull()
                ->end() // well_known_parser
                ->scalarNode('additional_token_constraints_provider')
                  ->defaultNull()
                ->end() // additional_token_constraints_provider
                ->scalarNode('well_known_cache_time')
                  ->defaultValue(3600)
                  ->validate()
                    ->ifTrue(fn ($value) => $value !== null && !is_int($value))
                    ->thenInvalid('Must be either null or an integer value')
                  ->end()
                ->end() // well_known_cache_time
                ->scalarNode('jwks_cache_time')
                  ->defaultValue(3600)
                  ->validate()
                    ->ifTrue(fn ($value) => $value !== null && !is_int($value))
                    ->thenInvalid('Must be either null or an integer value')
                  ->end()
                ->end() // jwks_cache_time
                ->scalarNode('token_leeway_seconds')
                  ->defaultValue(300)
                  ->validate()
                    ->ifTrue(fn ($value) => !is_int($value) || $value < 0)
                    ->thenInvalid('Must be an integer value and greater or equal to zero')
                  ->end()
                ->end() // token_leeway_seconds
                ->scalarNode('client_id')
                  ->isRequired()
                ->end() // client_id
                ->scalarNode('client_secret')
                  ->isRequired()
                ->end() // client_secret
                ->scalarNode('redirect_route')
                  ->defaultValue('/login_check')
                ->end() // redirect_route
                ->arrayNode('custom_client_headers')
                  ->scalarPrototype()->end()
                ->end() // custom_client_headers
                ->arrayNode('custom_client_options')
                  ->scalarPrototype()->end()
                ->end() // custom_client_options
                ->scalarNode('remember_me_parameter')
                  ->defaultValue('_remember_me')
                ->end() // remember_me_parameter
                ->scalarNode('code_challenge_method')
                  ->defaultNull()
                  ->validate()
                    ->ifNotInArray([null, 'S256', 'plain'])
                    ->thenInvalid('Invalid code challenge method %s')
                  ->end()
                ->end() // code_challenge_method
                ->booleanNode('disable_nonce')
                  ->defaultFalse()
                ->end() // disable_nonce
                ->scalarNode('audience')
                  ->defaultNull()
                ->end() // audience
                ->scalarNode('scope')
                  ->defaultNull()
                ->end() // scope
                ->booleanNode('enable_http_client')
                  ->defaultFalse()
                  ->info('Enable generation of an HttpClientInterface with the access token set for this client')
                ->end() // enable_http_client
                ->scalarNode('http_client_factory_cache_time')
                  ->defaultValue(3600)
                  ->info('Cache time in seconds for HTTP client factory token exchange')
                  ->validate()
                    ->ifTrue(fn ($value) => $value !== null && !is_int($value))
                    ->thenInvalid('Must be either null or an integer value')
                  ->end()
                ->end() // http_client_factory_cache_time
                ->booleanNode('enable_token_factory')
                  ->defaultFalse()
                  ->info('Enable generation of an OidcTokenFactoryInterface that returns access tokens for this client')
                ->end() // enable_token_factory
                ->scalarNode('token_factory_cache_time')
                  ->defaultValue(3600)
                  ->info('Cache time in seconds for token factory token exchange')
                  ->validate()
                    ->ifTrue(fn ($value) => $value !== null && !is_int($value))
                    ->thenInvalid('Must be either null or an integer value')
                  ->end()
                ->end() // token_factory_cache_time
              ->end() // array prototype children
            ->end() // array prototype
          ->end() // clients
        ->end(); // root children

    return $treeBuilder;
  }
}

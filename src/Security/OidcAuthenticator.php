<?php

namespace Drenso\OidcBundle\Security;

use Lcobucci\JWT\UnencryptedToken;
use Drenso\OidcBundle\OidcJwtHelper;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\OidcSessionStorage;
use Drenso\OidcBundle\OidcClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Drenso\OidcBundle\Exception\OidcException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\HttpUtils;
use Drenso\OidcBundle\Security\Token\OidcToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Drenso\OidcBundle\Security\Exception\OidcAuthenticationException;
use Drenso\OidcBundle\Security\Exception\UnsupportedManagerException;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Drenso\OidcBundle\Exception\OidcConfigurationDisableUserInfoNotSupportedException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * @template-covariant TUser of UserInterface
 */
class OidcAuthenticator implements InteractiveAuthenticatorInterface, AuthenticationEntryPointInterface
{
  /**
   * @param OidcUserProviderInterface<TUser> $oidcUserProvider
   * @param non-empty-string                 $userIdentifierProperty
   */
  public function __construct(
    private readonly HttpUtils $httpUtils,
    private readonly OidcClientInterface $oidcClient,
    private readonly OidcSessionStorage $sessionStorage,
    private readonly OidcUserProviderInterface $oidcUserProvider,
    private readonly AuthenticationSuccessHandlerInterface $successHandler,
    private readonly AuthenticationFailureHandlerInterface $failureHandler,
    private readonly string $checkPath,
    private readonly string $loginPath,
    private readonly string $userIdentifierProperty,
    private readonly bool $enableRememberMe,
    private readonly bool $userIdentifierFromIdToken = false,
    private readonly bool $enableRetrieveUserInfo = true,
    private readonly bool $userInfoFromIdToken = false,
  ) {
  }

  public function supports(Request $request): ?bool
  {
    return
        $this->httpUtils->checkRequestPath($request, $this->checkPath)
        && $request->query->has('code')
        && $request->query->has('state');
  }

  public function start(Request $request, ?AuthenticationException $authException = null): Response
  {
    return $this->httpUtils->createRedirectResponse($request, $this->loginPath);
  }

  public function authenticate(Request $request): Passport
  {
    try {
      // Try to authenticate the request
      $authData = $this->oidcClient->authenticate($request);

      // Parse ID token if necessary
      $idToken = null;
      if ($this->userIdentifierFromIdToken || $this->userInfoFromIdToken) {
        $idToken = OidcJwtHelper::parseToken($authData->getIdToken());
      }

      // Optionally retrieve the user data with the authentication data
      if ($this->enableRetrieveUserInfo) {
        $userData = $this->oidcClient->retrieveUserInfo($authData);
      } else {
        if (!$this->userIdentifierFromIdToken) {
          throw new OidcConfigurationDisableUserInfoNotSupportedException();
        }

        if ($this->userInfoFromIdToken) {
          /** @var UnencryptedToken $idToken */
          $userData = new OidcUserData($idToken->claims()->all());
        } else {
          $userData = new OidcUserData([]);
        }
     
      }

      // Look for the user identifier in either the id_token or the userinfo endpoint
      if ($this->userIdentifierFromIdToken) {
        /** @var UnencryptedToken $idToken */
        $userIdentifier = $idToken->claims()->get($this->userIdentifierProperty);
      } else {
        $userIdentifier = $userData->getUserDataString($this->userIdentifierProperty);
      }

      // Ensure the user exists
      if (!$userIdentifier) {
        throw new UserNotFoundException(
          sprintf('User identifier property (%s) yielded empty user identifier', $this->userIdentifierProperty));
      }
      $this->oidcUserProvider->ensureUserExists($userIdentifier, $userData, $authData);

      // Create the passport
      $passport = new SelfValidatingPassport(new UserBadge(
        $userIdentifier,
        fn (string $userIdentifier) => $this->oidcUserProvider->loadOidcUser($userIdentifier),
      ));
      $passport->setAttribute(OidcToken::AUTH_DATA_ATTR, $authData);
      $passport->setAttribute(OidcToken::USER_DATA_ATTR, $userData);

      if ($this->enableRememberMe && $this->sessionStorage->getRememberMe()) {
        // Add remember me badge when enabled
        $passport->addBadge((new RememberMeBadge())->enable());
        $this->sessionStorage->clearRememberMe();
      }
      $this->sessionStorage->storeAccessToken($authData->getAccessToken());

      return $passport;
    } catch (OidcException $e) {
      throw new OidcAuthenticationException('OIDC authentication failed', $e);
    }
  }

  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    return $this->successHandler->onAuthenticationSuccess($request, $token);
  }

  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
  {
    return $this->failureHandler->onAuthenticationFailure($request, $exception);
  }

  public function createToken(Passport $passport, string $firewallName): TokenInterface
  {
    return new OidcToken($passport, $firewallName);
  }

  /** @todo: Remove when dropping support for Symfony 5.4 */
  public function createAuthenticatedToken(
    PassportInterface $passport,
    string $firewallName): TokenInterface
  {
    throw new UnsupportedManagerException();
  }

  public function isInteractive(): bool
  {
    return true;
  }

}

<?php

namespace Drenso\OidcBundle\Security;

use Drenso\OidcBundle\Exception\OidcException;
use Drenso\OidcBundle\OidcClientInterface;
use Drenso\OidcBundle\Security\Exception\OidcAuthenticationException;
use Drenso\OidcBundle\Security\Token\OidcToken;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticator that validates access tokens in resource provider mode (JWT validation).
 * Uses local JWT validation with JWKS instead of token introspection.
 */
class OidcResourceProviderAuthenticator implements AuthenticatorInterface
{
  /**
   * @param OidcUserProviderInterface<\Symfony\Component\Security\Core\User\UserInterface> $oidcUserProvider
   */
  public function __construct(
    private readonly OidcClientInterface $oidcClient,
    private readonly OidcUserProviderInterface $oidcUserProvider,
    private readonly string $userIdentifierProperty = 'sub',
  ) {
  }

  /** Decide if this authenticator should be used for the request. */
  public function supports(Request $request): ?bool
  {
    $authHeader = $request->headers->get('Authorization');

    return is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
  }

  public function authenticate(Request $request): Passport
  {
    try {
      // Extract bearer token from Authorization header
      $authHeader = $request->headers->get('Authorization');
      if (!is_string($authHeader) || !str_starts_with(trim($authHeader), 'Bearer ')) {
        throw new AuthenticationException('No Bearer token found in Authorization header.');
      }
      $accessToken = trim(substr($authHeader, 7));
      if ($accessToken === '') {
        throw new AuthenticationException('Bearer token is empty.');
      }

      // Validate token using JWT validation (resource provider mode)
      $tokens = $this->oidcClient->validateAccessTokenResourceProvider($accessToken);

      // Extract user data from JWT claims
      $result = $this->oidcClient->extractUserDataFromAccessTokenResourceProvider(
        $tokens,
        $this->userIdentifierProperty
      );
      $userData       = $result['userData'];
      $userIdentifier = $result['userIdentifier'];

      // Ensure the user exists
      if (!$userIdentifier) {
        throw new UserNotFoundException(
          sprintf('User identifier property (%s) yielded empty user identifier', $this->userIdentifierProperty));
      }
      $this->oidcUserProvider->ensureUserExists($userIdentifier, $userData, $tokens);

      // Create the passport with user data for RRN extraction
      $passport = new SelfValidatingPassport(new UserBadge(
        $userIdentifier,
        fn (string $userIdentifier) => $this->oidcUserProvider->loadOidcUser($userIdentifier),
      ));
      $passport->setAttribute(OidcToken::AUTH_DATA_ATTR, $tokens);
      $passport->setAttribute(OidcToken::USER_DATA_ATTR, $userData);

      return $passport;
    } catch (OidcException $e) {
      throw new OidcAuthenticationException('OIDC authentication failed', $e);
    }
  }

  /** Create an authenticated token for the given user. */
  public function createToken(Passport $passport, string $firewallName): TokenInterface
  {
    // Create a token for the authenticated user
    return new OidcToken($passport, $firewallName);
  }

  /** Called when authentication executed and was successful. */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    // On success, let the request continue (API style)
    return null;
  }

  /** Called when authentication executed, but failed. */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
  {
    // Return a 401 JSON response with the error message
    return new \Symfony\Component\HttpFoundation\JsonResponse([
      'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
    ], Response::HTTP_UNAUTHORIZED);
  }
}


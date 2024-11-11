<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderManifestInterface;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderRegistry;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Security\NonceException;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Security\RequestTokenException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class MfaUtility
{
    final public const PARAM_NAME = '__MfaRequestToken';

    final public const TOKEN_SCOPE = 'ig-mfa-frontend/user-auth/fe';

    protected static ?MfaProviderRegistry $mfaProviderRegistry = null;

    protected static array $allowedProviders;

    protected static bool $mfaRequired;

    public static function isProviderAllowed(string $identifier): bool
    {
        return isset(static::$allowedProviders[$identifier]);
    }

    public static function isValidIdentifier(string $identifier): bool
    {
        return $identifier !== ''
                            && static::isProviderAllowed($identifier)
                            && static::getMfaProviderRegistry()->hasProvider($identifier);
    }

    public static function getProviderByIdentifier(
        string $identifier,
        ?bool $neededProviderActive = null
    ): ?MfaProviderManifestInterface {
        if (static::isValidIdentifier($identifier)) {
            return static::$mfaProviderRegistry->getProvider($identifier);
        }

        return null;
    }

    public static function getProviderIdentifierByRequest(ServerRequestInterface $request): ?string
    {
        if (isset($request->getParsedBody()['identifier'])) {
            return $request->getParsedBody()['identifier'];
        }

        if (isset($request->getqueryParams()['identifier'])) {
            return $request->getqueryParams()['identifier'];
        }

        $frontendUserAuthentication = static::getFrontendUserAuthentication();
        return $frontendUserAuthentication ? (string)(static::getFrontendUserAuthentication()->uc['mfa']['defaultProvider'] ?? '') : '';
    }

    /**
     * get hashSignedJwt for requestToken from request
     */
    public static function getHashSignedJwtByRequest(ServerRequestInterface $request): ?string
    {
        return $request->getParsedBody()[static::PARAM_NAME] ?? $request->getQueryParams()[static::PARAM_NAME] ?? $request->getAttribute(
            static::PARAM_NAME
        ) ?? null;
    }

    public static function getRequestTokenByHashSignedJwt(?string $hashSignedJwt): ?RequestToken
    {
        if ($hashSignedJwt) {
            $context = GeneralUtility::makeInstance(Context::class);
            $securityAspect = SecurityAspect::provideIn($context);
            $signingSecretResolver = $securityAspect->getSigningSecretResolver();
            //$signingSecretResolver = static::getSigningSecretResolver($request);
            //$this->securityAspect->setReceivedRequestToken($this->resolveReceivedRequestToken($request));
            try {
                $requestToken = RequestToken::fromHashSignedJwt($hashSignedJwt, $signingSecretResolver);
            } catch (RequestTokenException) {
                return null;
            }

            /*catch (NonceException $exception) {
                die('NonceException in getRequestTokenByRequest existiert dasx');
            }
            */

            if ($requestToken->scope === static::TOKEN_SCOPE) {
                return $requestToken;
            }

            return $requestToken;
        }

        return null;
    }

    /**
     * get RequestToken for mfa from request
     */
    public static function getRequestTokenByRequest(ServerRequestInterface $request): ?RequestToken
    {
        $hashSignedJwt = static::getHashSignedJwtByRequest($request);
        return static::getRequestTokenByHashSignedJwt($hashSignedJwt);
    }

    /**
     * Token to store first login method success
     */
    public static function createToken(array $params): RequestToken
    {
        return RequestToken::create(static::TOKEN_SCOPE)->withMergedParams($params);
    }

    public static function getHashSignedJwtByToken(RequestToken $requestToken): string
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $securityAspect = SecurityAspect::provideIn($context);
        $signingType = 'nonce';
        $signingProvider = $securityAspect->getSigningSecretResolver()
->findByType($signingType);
        $signingSecret = $signingProvider->provideSigningSecret();
        return $requestToken->toHashSignedJwt($signingSecret);
    }

    /**
     * Token to store first login method success
     */
    /*
    public static function provideRequestTokenJwt(array $params): string
    {
        $requestToken = static::createToken($params);
        $context = GeneralUtility::makeInstance(Context::class);
        $nonce = SecurityAspect::provideIn($context)->provideNonce();
        $token = $requestToken->toHashSignedJwt($nonce);
        return $token;
    }
    */

    /**
     * is token valid and matches the given username
     */
    public static function isTokenValid(?RequestToken $requestToken, ?string $requestUsername): bool
    {
        if ($requestUsername === null || !($requestToken instanceof RequestToken)) {
            return false;
        }

        $tokenUsername = $requestToken->params['username'] ?? null;
        return $tokenUsername === $requestUsername;
    }

    public static function getMfaRequired()
    {
        return static::$mfaRequired;
    }

    public static function getAllowedProviders()
    {
        return static::$allowedProviders;
    }

    /**
     * Fetch alternative (activated and allowed) providers for the user to chose from
     *
     * @return ProviderInterface[]
     */
    public static function getAlternativeProviders(MfaProviderManifestInterface $mfaProvider): array
    {
        return array_filter(static::$allowedProviders, static function ($provider) use ($mfaProvider) {
            $propertyManager = MfaProviderPropertyManager::create($provider, static::getFrontendUserAuthentication());
            return $provider !== $mfaProvider
                              && $provider->isActive(
                                  MfaProviderPropertyManager::create($provider, static::getFrontendUserAuthentication())
                              );
        });
    }

    /**
     * Initialize MFA configuration based on available providers and typoscript settings
     */
    public static function initializeMfaConfiguration(
        ?FrontendUserAuthentication $frontendUserAuthentication = null,
        array $mfaSettings = []
    ): void {
        $frontendUserAuthentication ??= static::getFrontendUserAuthentication();
        static::$mfaRequired = $frontendUserAuthentication->isMfaSetupRequired();


        // Set up allowed providers based on available Providers and allowed providers in settings
        $availableProviders = static::getMfaProviderRegistry()->getProviders();
        $mfaSettingsAllowedProviders = $mfaSettings['allowedProviders'] ?? '';
        if ($mfaSettingsAllowedProviders === '') {
            static::$allowedProviders = $availableProviders;
        } else {
            $settingsAllowedProviders = GeneralUtility::trimExplode(',', $mfaSettingsAllowedProviders);
            static::$allowedProviders = array_filter(static::getMfaProviderRegistry()->getProviders(), static function ($identifier) use ($settingsAllowedProviders) {
                return in_array($identifier, $settingsAllowedProviders);
                //$frontendUser->check('mfa_providers', $identifier)
                //&& !GeneralUtility::inList(($this->frontendUserMfa['disableProviders'] ?? ''), $identifier);
            }, ARRAY_FILTER_USE_KEY);
        }
    }

    public static function getFrontendUserAuthentication(): ?FrontendUserAuthentication
    {
        return isset($GLOBALS['TYPO3_REQUEST']) ? $GLOBALS['TYPO3_REQUEST']->getAttribute('frontend.user') : null;
    }

    protected static function getMfaProviderRegistry(): MfaProviderRegistry
    {
        static::$mfaProviderRegistry ??= GeneralUtility::makeInstance(MfaProviderRegistry::class);

        return static::$mfaProviderRegistry;
    }
}

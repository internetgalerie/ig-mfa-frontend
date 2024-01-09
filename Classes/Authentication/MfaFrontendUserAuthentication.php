<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Authentication;

use Internetgalerie\IgMfaFrontend\Service\MfaAuthenticationService;
use Internetgalerie\IgMfaFrontend\Utility\MfaUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * MFA Frontend User Authentication class
 */
class MfaFrontendUserAuthentication extends FrontendUserAuthentication
{
    /**
     * when we pass the login data with get, no login is triggered -> so we forward and check the data for ourselves
     */
    public function startWithQueryParams(ServerRequestInterface $request): bool
    {
        $logintype = $request->getQueryParams()['logintype'] ?? '';

        if ($logintype !== 'mfa') {
            return false;
        }

        $requestUsername = $request->getQueryParams()['user'] ?? null;
        $requestToken = MfaUtility::getRequestTokenByRequest($request);
        if (MfaUtility::isTokenValid($requestToken, $requestUsername)) {
            // Make certain that NO user is set initially
            $this->user = null;

            if (!isset($this->userSessionManager)) {
                $this->initializeUserSessionManager();
            }

            $this->checkPid = false;
            $authInfo = $this->getAuthInfoArray($request);
            $subType = 'getUser' . $this->loginType;
            $loginData = $this->getLoginFormData($request);
            $mfaAuthenticationService = GeneralUtility::makeInstance(MfaAuthenticationService::class);
            $mfaAuthenticationService->initAuth($subType, $loginData, $authInfo, $this);
            $tokenUsername = $requestToken->params['username'] ?? null;

            // pid is missing
            $userRecordCandidate = $mfaAuthenticationService->fetchUserRecord($tokenUsername);
            $this->createUserSession($userRecordCandidate);
            //$sessionData = $this->userSession->getData();
            $this->userSession = $this->createUserSession($userRecordCandidate);
            $this->user = array_merge($userRecordCandidate, $this->user ?? []);
            $this->loginSessionStarted = true;

            $this->regenerateSessionId();
            $this->unpack_uc();
            return true;
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Internetgalerie\IgMfaFrontend\Service;

use Internetgalerie\IgMfaFrontend\Utility\MfaUtility;
use TYPO3\CMS\Core\Authentication\AuthenticationService;
use TYPO3\CMS\Core\Authentication\LoginType;
use TYPO3\CMS\Core\SysLog\Action\Login as SystemLogLoginAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\SysLog\Type as SystemLogType;

/**
 * MFA Authentication services class
 */
class MfaAuthenticationService extends AuthenticationService
{
    /**
     * Find a user (eg. look up the user record in database when a login is sent and we are in authentification with a mfa provider identifier)
     *
     * @return array<string, mixed>|false User array or FALSE
     */
    public function getUser()
    {
        $request = $this->authInfo['request'];

        $identifier = MfaUtility::getProviderIdentifierByRequest($request);
        // own status 'mfa' is not defined in login controller and is only used for changing the mfa provider with links
        $status = $this->login['status'] ?? '';
        if (method_exists(LoginType::class, 'tryFrom')) {
            // TYPO3 v13
            $isLoggedIn = LoginType::tryFrom($status) !== LoginType::LOGIN;
        } else {
            // TYPO3 v12
            $isLoggedIn = $status !== LoginType::LOGIN;
        }
        if (($isLoggedIn && $status !== 'mfa') || $identifier === '') {
            return false;
        }

        $user = $this->fetchUserRecord($this->login['uname']);

        if (!is_array($user)) {
            // Failed login attempt (no username found)
            $this->writelog(
                SystemLogType::LOGIN,
                SystemLogLoginAction::ATTEMPT,
                SystemLogErrorClassification::SECURITY_NOTICE,
                2,
                "Login-attempt from ###IP###, username '%s' not found!",
                [$this->login['uname']]
            );
            $this->logger->info('Login-attempt from username "{username}" not found!', [
                'username' => $this->login['uname'],
                'REMOTE_ADDR' => $this->authInfo['REMOTE_ADDR'],
            ]);
        } else {
            $this->logger->debug('User found', [
                $this->db_user['userid_column'] => $user[$this->db_user['userid_column']],
                $this->db_user['username_column'] => $user[$this->db_user['username_column']],
            ]);
        }

        return $user;
    }

    /**
     * Authenticate a user: Check submitted user if we got an mfa provider identifier
     *
     * Returns one of the following status codes:
     *  >= 200: User authenticated successfully. No more checking is needed by other auth services.
     *  >= 100: User not authenticated; this service is not responsible. Other auth services will be asked.
     *  > 0:    User authenticated successfully. Other auth services will still be asked.
     *  <= 0:   Authentication failed, no more checking needed by other auth services.
     *
     * @param array<string, mixed> $user User data
     * @return int Authentication status code, one of 0, 100, 200
     */
    public function authUser(array $user): int
    {
        $request = $this->authInfo['request'];
        $identifier = MfaUtility::getProviderIdentifierByRequest($request);
        $hashSignedJwt = MfaUtility::getHashSignedJwtByRequest($request);

        // Early 100 "not responsible, check other services" if username or password is empty
        if (
            !isset($identifier) || (string)$identifier === ''
            || $hashSignedJwt === null || (string)$hashSignedJwt === ''
            || !isset($this->login['uname']) || (string)$this->login['uname'] === '') {
            return 100;
        }

        if (empty($this->db_user['table'])) {
            throw new \RuntimeException('User database table not set', 1533159150);
        }

        $requestToken = MfaUtility::getRequestTokenByHashSignedJwt($hashSignedJwt);
        if (MfaUtility::isTokenValid($requestToken, $user['username'])) {
            // authentification is ok, but mfa verification is still open
            return 200;
        }

        return 0;
    }
}

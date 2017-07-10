<?php
namespace Qbus\ContentPassword\Controller;

use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 Benjamin Franzke <bfr@qbus.de>, Qbus GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

class ContentPasswordController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    protected $mode = '';

    public function initializeAction()
    {
        $cObj = $this->configurationManager->getContentObject();
        if ($cObj->data['CType'] !== 'gridelements_pi1') {
            // throw exception?
            // this controller should only be used inside the gridelement "content_password"
        }

        $layout = $cObj->data['tx_gridelements_backend_layout'];
        if (!in_array($layout, ['content_password', 'content_password_ldap'])) {
            // throw exception?
            // this controller should only be used inside the gridelement "content_password"
        }

        if ($layout == 'content_password') {
            $this->mode = 'password';
        }

        if ($layout == 'content_password_ldap') {
            $this->mode = 'ldap';
        }
    }

    /**
     * action main
     *
     * @return void
     */
    public function mainAction()
    {
        $cObj = $this->configurationManager->getContentObject();
        $until = (int) $cObj->data['flexform_protection_until'];

        if ($this->mode == 'password') {
            if ($cObj->data['flexform_password'] === '') {
                return $cObj->data['tx_gridelements_view_column_0'];
            }
        }

        if ($until) {
            $timeout = $until - time();
            if ($timeout <= 0) {
                return $cObj->data['tx_gridelements_view_column_0'];
            }
            $this->setCacheMaxExpiry($timeout);
        }

        $this->view->assign('contentObject', $cObj->data);
        $this->view->assign('mode', $this->mode);
    }

    /**
     * action unlock
     *
     * @param  string $username
     * @param  string $password
     * @param  int    $unlockid
     * @return void
     */
    public function unlockAction($username = '', $password = '', $unlockid = 0)
    {
        $cObj = $this->configurationManager->getContentObject();

        if ($unlockid != $cObj->data['uid']) {
            // render main, another content_password element one the same page was triggered
            $this->forward('main');
        }

        $desired_password = $cObj->data['flexform_password'];

        if ($this->mode == 'password') {
            if (!($desired_password === '' ||
                  $desired_password === $password)) {
                $message = LocalizationUtility::translate('password_incorrect', 'content_password');
                $this->addFlashMessage($message, '', AbstractMessage::ERROR, false);
                $this->forward('main');
            }
        } elseif ($this->mode == 'ldap') {
            if (!$this->checkLdap($username, $password)) {
                $message = LocalizationUtility::translate('user_or_password_incorrect', 'content_password');
                $this->addFlashMessage($message, '', AbstractMessage::ERROR, false);
                $this->forward('main');
            }
        }

        return $cObj->data['tx_gridelements_view_column_0'];
    }

    protected function checkLdap($username, $password)
    {
        $rawUsername = $username;
        if ($username == '' || $password == '')
            return false;

        $server = '';
        $domain = '';
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['content_password'])) {
            $extensionConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['content_password']);
        } else {
            return false;
        }

        if (isset($extensionConfig['ldapServer'])) {
            $server = $extensionConfig['ldapServer'];
        } else {
            // TODO
            return false;
        }

        if (isset($extensionConfig['ldapDomain']) && $extensionConfig['ldapDomain']) {
            $domain = $extensionConfig['ldapDomain'];
            $username = $username . '@' . $domain;
        }

        putenv('LDAPTLS_REQCERT=never');
        $con = ldap_connect($server);
        ldap_set_option($con, LDAP_OPT_PROTOCOL_VERSION, 3);
        if (!$con)
            return false;
        $result = @ldap_bind($con, $username, $password);


        if ($result !== true) {
            return false;
        }

        if (isset($extensionConfig['ldapLockBaseDN']) && $extensionConfig['ldapLockBaseDN']) {
            $baseDNs = GeneralUtility::trimExplode('|', $extensionConfig['ldapLockBaseDN'], true);
            $validBaseDN = true;
            if (!empty($baseDNs)) {
                $validBaseDN = false;
                foreach ($baseDNs as $baseDN) {
                    $query = '(&(objectClass=person)(sAMAccountName=' . ldap_escape($rawUsername, '', LDAP_ESCAPE_FILTER) . '))';
                    $resultSearch = ldap_search($con, $baseDN, $query, array('dn'), 1, 0);
                    $count = ldap_count_entries($con, $resultSearch);
                    ldap_free_result($resultSearch);
                    if ($count > 0) {
                        $validBaseDN = true;
                        break;
                    }
                }
                if (!$validBaseDN) {
                    return false;
                }
            }
        }

        return $result === true;
    }

    /* FIXME: This function is a HACK
     *
     * we modify the TSFE cache_timeout value as soon as we are rendered, but the cache_timeout may have been requested earlier.
     * (see typo3/sysext/frontend/Classes/ContentObject/Menu/AbstractMenuContentObject.php)
     */
    protected function setCacheMaxExpiry($timeout)
    {
        $current_page_timeout = (int)$GLOBALS['TSFE']->page['cache_timeout'];
        if ($current_page_timeout > $timeout || $current_page_timeout == 0) {
            $GLOBALS['TSFE']->page['cache_timeout'] = $timeout;
        }

        /** @var $runtimeCache \TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend */
        $runtimeCache = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager')->getCache('cache_runtime');
        $cachedCacheLifetimeIdentifier = 'core-tslib_fe-get_cache_timeout';
        $cachedCacheLifetime = $runtimeCache->get($cachedCacheLifetimeIdentifier);

        /* if the page timeout was cached already, overwrite the cached value as well,
         * see: typo3/sysext/frontend/Classes/Controller/TypoScriptFrontendController.php->get_cache_timeout */
        if ($cachedCacheLifetime !== false) {
            if ($cachedCacheLifetime > $timeout) {
                $runtimeCache->set($cachedCacheLifetimeIdentifier, $timeout);
            }
        }
    }
}

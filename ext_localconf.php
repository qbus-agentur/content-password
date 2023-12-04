<?php

defined('TYPO3_MODE') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:content_password/Configuration/TSConfig/ContentPassword.pagets">');

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Qbus.ContentPassword',
    'ContentPassword',
    [\Qbus\ContentPassword\Controller\ContentPasswordController::class => 'main, unlock'],
    [\Qbus\ContentPassword\Controller\ContentPasswordController::class => 'unlock'],
    //\TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

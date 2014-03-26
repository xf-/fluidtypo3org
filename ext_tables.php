<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Provider extension for pages content');
\FluidTYPO3\Flux\Core::registerProviderExtensionKey('FluidTYPO3.Fluidtypo3org', 'Page');
\FluidTYPO3\Flux\Core::registerProviderExtensionKey('FluidTYPO3.Fluidtypo3org', 'Content');

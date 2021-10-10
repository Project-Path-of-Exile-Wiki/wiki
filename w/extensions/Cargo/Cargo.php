<?php
/**
 * Initialization file for Cargo.
 *
 * @ingroup Cargo
 * @author Yaron Koren
 */

wfLoadExtension( 'Cargo' );
// Keep i18n globals so mergeMessageFileList.php doesn't break
$wgMessagesDirs['Cargo'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['CargoMagic'] = __DIR__ . '/Cargo.i18n.magic.php';
$wgExtensionMessagesFiles['CargoAlias'] = __DIR__ . '/Cargo.alias.php';
/* wfWarn(
	'Deprecated PHP entry point used for Cargo extension. Please use wfLoadExtension instead, ' .
	'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
); */

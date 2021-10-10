<?php
if ( version_compare( $wgVersion, '1.30', '>=' ) ) {
	wfLoadExtension( 'Widgets' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Widgets'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['WidgetsMagic'] = __DIR__ . '/Widgets.i18n.magic.php';
	$wgExtensionMessagesFiles['WidgetsNamespaces'] = __DIR__ . '/Widgets.i18n.namespaces.php';
	return;
} else {
	die( 'This version of the Widgets extension requires MediaWiki 1.30+' );
}

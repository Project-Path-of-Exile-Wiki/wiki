<?php

$namespaceNames = [];

// For wikis without Widgets installed.
if ( !defined( 'NS_WIDGET' ) ) {
	define( 'NS_WIDGET', 274 );
	define( 'NS_WIDGET_TALK', 275 );
}

$namespaceNames['en'] = [
	NS_WIDGET       => 'Widget',
	NS_WIDGET_TALK  => 'Widget_talk',
];

$namespaceNames['de'] = [
	NS_WIDGET_TALK  => 'Widget_Diskussion',
];

$namespaceNames['ko'] = [
	NS_WIDGET       => '위젯',
	NS_WIDGET_TALK  => '위젯토론',
];

<?php

class WidgetSecurity extends Smarty_Security {
	/** @var int How <?php ?> tags are handled in templates */
	public $php_handling = Smarty::PHP_REMOVE;

	/** @var array|null Trusted static classes; empty array allows all, null allows none */
	public $static_classes = null;

	/** @var array|null Trusted methods from static classes; empty array allows all, null allows none */
	public $trusted_static_methods = null;

	/** @var array|null Trusted methods from static properties; empty array allows all, null allows none */
	public $trusted_static_properties = null;

	/** @var array|null Trusted streams; empty array allows all, null allows none */
	public $streams = null;

	/** @var bool If true, templates can access constants */
	public $allow_constants = false;

	/** @var bool If true, templates can access superglobals */
	public $allow_super_globals = false;

	/** @var array Blacklist of $smarty.* variables */
	public $disabled_special_smarty_vars = [
		'template',
		'template_object',
		'current_dir'
	];
}

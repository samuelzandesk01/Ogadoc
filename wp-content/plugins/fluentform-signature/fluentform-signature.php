<?php
/*
Plugin Name: Fluent Forms Signature Addon
Description: The signature field for Fluent Forms, the most advanced drag and drop form builder plugin for WordPress.
Version: 4.3.11
Author: WPFluentForm
Author URI: https://wpfluentform.com
Plugin URI: https://wpmanageninja.com/downloads/signature-add-on-for-wp-fluentform
License: GPLv2 or later
Text Domain: fluentform-signature
Domain Path: /resources/languages
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    die;
}

// Define plugin specific constants.
defined('FLUENTFORM_SIGNATURE') or define('FLUENTFORM_SIGNATURE', true);
define('FLUENTFORM_SIGNATURE_VERSION', '4.3.11');
define('FLUENTFORM_SIGNATURE_DIR', __DIR__);
define('FLUENTFORM_SIGNATURE_PATH', __FILE__);
define('FLUENTFORM_SIGNATURE_URL', plugin_dir_url(__FILE__));

// Include the classes.
include FLUENTFORM_SIGNATURE_DIR.'/src/Application.php';
include FLUENTFORM_SIGNATURE_DIR.'/src/Signature.php';
include FLUENTFORM_SIGNATURE_DIR.'/src/Component.php';
include FLUENTFORM_SIGNATURE_DIR.'/src/libs/ff_plugin_updater/ff-signature-update.php';

// Boot the plugin.
add_action('plugins_loaded', function () {
    (new \FluentFormSignature\Application)->boot();
});
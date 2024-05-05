<?php

defined( 'ABSPATH' ) or die( 'No script kittays please!' );

if ( !class_exists('FluentFormSignatureAddOnUpdater') ) {
    // load our custom updater
    require_once(dirname(__FILE__) . '/updater/FluentFormSignatureAddOnUpdater.php');
}

if ( !class_exists('FluentFormSignatureAddOnChecker') ) {
	require_once( dirname( __FILE__ ) . '/updater/FluentFormSignatureAddOnChecker.php' );
}

// Kick off our EDD class
new FluentFormSignatureAddOnChecker( array(
	// The plugin file, if this array is defined in the plugin
    'plugin_file' => FLUENTFORM_SIGNATURE_PATH,
    // The current version of the plugin.
    // Also need to change in readme.txt and plugin header.
    'version' => FLUENTFORM_SIGNATURE_VERSION,
	// The main URL of your store for license verification
	'store_url' => 'https://apiv2.wpmanageninja.com/plugin',
	'store_site' => 'https://wpmanageninja.com',
	// Your name
	'author' => 'WP Manage Ninja',
	// The URL to renew or purchase a license
	'purchase_url' => 'https://wpmanageninja.com/downloads/signature-add-on-for-wp-fluentform',
	// The URL of your contact page
	'contact_url' => 'https://wpmanageninja.com/contact',
	// This should match the download name exactly
	'item_id' => '504',
	// The option names to store the license key and activation status
    'license_key' => '_ff_signature_license_key',
    'license_status' => '_ff_signature_license_status',
    // Option group param for the settings api
    'option_group' => '_ff_signature_license',
	// The plugin settings admin page slug
    'admin_page_slug' => 'fluent_forms_add_ons',
	// If using add_menu_page, this is the parent slug to add a submenu item underneath.
	'activate_url' => admin_url('admin.php?page=fluent_forms_add_ons&sub_page=fluentform-signature'),
	// The translatable title of the plugin
    'plugin_title' => __( 'Signature Add On', 'fluentform-signature' ),
    'menu_slug' => 'fluentform-signature',
    'menu_title' => __('Signature Add-On', 'fluentform-signature'),
    'cache_time' => 48 * 60 * 60
));

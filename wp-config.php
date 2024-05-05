<?php

/** WP 2FA plugin data encryption key. For more information please visit melapress.com */
define( 'WP2FA_ENCRYPT_KEY', 'boK/K7eOMNElRwYj+ZEHSQ==' );

define( 'WP_CACHE', true ); 
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */
// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', "ogadoc_wp991" );
/** Database username */
define( 'DB_USER', "ogadoc_wp991" );
/** Database password */
define( 'DB_PASSWORD', "XpS78T9-]O(W)-8Q" );
/** Database hostname */
define( 'DB_HOST', "localhost" );
/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );
/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );
/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'awfeqbyhlc1rhn3lplkqkisupznw9qt5p7jjms5fpvwlipjs5vlhqlmismcaixhy' );
define( 'SECURE_AUTH_KEY',  'm8wo4ypunz4osbcfdpf5kqhhgnlw1oxgyguvnikpyhbfocljhvwgabyi1jarjqhi' );
define( 'LOGGED_IN_KEY',    'snuermtahnddjauvpa8o9euv3a77hu1w5gwt2qednrv8jkogrby1vdlxqaqgorbu' );
define( 'NONCE_KEY',        'k18mcyqtlifkgexetrbaeqat6pug4j6bv67e8wx5xayz0venzqlqmufvmgyhjbwd' );
define( 'AUTH_SALT',        'fzjm3iyk22heda5el9oegajk2zvpt4hygikuxmqkbpkipumd9bkmdut0vs1lavvp' );
define( 'SECURE_AUTH_SALT', 'zh44mvf6jyiyyqpflbvzjxlh1mbp1jtghfbd9f3ibmdqxq3oxb15uosgx9ulwdiu' );
define( 'LOGGED_IN_SALT',   'xbpugramvmzyqexetouknuv5cilpjmpmirczlzxdcmcbga82tsktlrsmdumialkj' );
define( 'NONCE_SALT',       'is4brnzs1zubjb2avbxf42q4ugl6fadsgmkyusciedoytdqmrkwvcpwhwuogumau' );
/**#@-*/
/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = "wp4p_";
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );
/* Add any custom values between this line and the "stop editing" line. */
/* That's all, stop editing! Happy publishing. */
/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
define ( 'DISALLOW_FILE_EDIT', TRUE );
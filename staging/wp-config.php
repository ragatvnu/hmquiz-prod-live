<?php
require_once __DIR__ . "/hmqz-ob.php";


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
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u290655997_4Pzhh' );

/** Database username */
define( 'DB_USER', 'u290655997_ZT0Nu' );

/** Database password */
define( 'DB_PASSWORD', 'KkMXrCyOqJ' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

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
define( 'AUTH_KEY',          'L=04:[*62w/:]60k:T`FGBEa,wPY/bi-U/M|75@iw$O0qZ<NE@^ijZy;oSeOtC2-' );
define( 'SECURE_AUTH_KEY',   '^:168$8+G[D4/zS$Yagg6|nt}z$hVV^??f_uBNf<W,KeZ,=};]1X8 1%0Bn]6yB_' );
define( 'LOGGED_IN_KEY',     '.@aE+jNMpQJV7{I^/[]i`^ZG,?h5A* S&*C` -%j_VtRRBVTZ:FJ9Rt!L&dOgplg' );
define( 'NONCE_KEY',         '8+9TQ36GV7gZy>`&EkIjTL(dr>M|)gH%m{@tZ6g6}4,Uofa~8QAe:I}(?-r;WVE ' );
define( 'AUTH_SALT',         ']cp!kghQnb4;zL%+>c->E<F69EYQ2n2zv<>FNd$j@ !;^K?%%+9qIa^Y1X~3alK?' );
define( 'SECURE_AUTH_SALT',  '48Gahp/9Y6).)|3CCikz(H~w4h`>MflxbYI#XF)Ik~V>8|d:Y?bZMd`-U5S(M_P`' );
define( 'LOGGED_IN_SALT',    'u<zh{0;&m+ YKh$i%G_(*~Vb[2[AsqFfSTOJKyu`3W6bf-wwlfbs/kc%4I&JKovF' );
define( 'NONCE_SALT',        '<D1s92^gl5tCkQ3:5S# =jeMK90vdy$XJ*c/+}KecBn!9eNcfxcjT<^M`]tJp3RE' );
define( 'WP_CACHE_KEY_SALT', '@~d/JvKZ^T%MlB%787D-D18nOv=sPh&H[T8w!3&0$zkB19l1i%-#o0o%#R#r*bu>' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '25660b4abd3d4d2bd215ff484ba089bf' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

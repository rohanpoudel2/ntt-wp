<?php
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
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'nepal_tibet_travel' );

/** Database username */
define( 'DB_USER', 'rohan' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',         'eweSFx:fA!|v.YF_4$sG_42FF@U_mHt2(LQ)`e{8U_v!X&2InITck`n_%mW~V4@@' );
define( 'SECURE_AUTH_KEY',  '*&PER?2Oa}:*^=#=o{ilwoz}R!-~#xiQ;.FWOH_1TGJ$0)SrKga*j~FeXUUN=2[W' );
define( 'LOGGED_IN_KEY',    'qNfLmq_@:-WL1Y^5slv.#b:_<M9D,1VL{-Vf^%Z,BLx/v, QFNrm5C.~B.!}Jj +' );
define( 'NONCE_KEY',        'J_%a:Qz4(SmFr4)y5v{n:Ls!8DO&c2%,MZc|!/en?UERH)(gy%a@Kfz9{d_->A=@' );
define( 'AUTH_SALT',        'x4*eL-Orv0GS=%;J&9=.r~sDya* RzTh[)WGd0#Q303$pq(Guof6GA)xKPqw}x2$' );
define( 'SECURE_AUTH_SALT', 'aQ8G^O9}i(o^(n,>h^.e_tOFWc@!=FK7iZpFqmDZx6PT5MH1cJ#x#mF7_zR,v6x=' );
define( 'LOGGED_IN_SALT',   '~J)e34ZrXL{[|~eCU_J|4L)U8n!uLXT%*xidI_S]x5(aYp![:8l<m__D9cH{#?<m' );
define( 'NONCE_SALT',       'kTb3WHlL?#Te4zBc2_#V_y+nSq;tWVG;nnc{4WK9/z&dON|kMU1ayXrG18k.D5CM' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
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

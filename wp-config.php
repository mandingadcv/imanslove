<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'imanslove' );

/** MySQL database username */
define( 'DB_USER', 'imanslove' );

/** MySQL database password */
define( 'DB_PASSWORD', '12345' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'W}{R~sLvFzQ0K5U@]9=[;Nf@?Zp/$tFjawBr6[!5I<ki{&3>^RxJSQfzoE:D>5r)' );
define( 'SECURE_AUTH_KEY',  'oJn~[73Ls]y^e0Xxn!ZK*Lh._eeT8D|3iT[%[zDj#HDH~uo!K%-WW8o1vZObTw3t' );
define( 'LOGGED_IN_KEY',    ';(#;[c4tc^zz%*4P.coDUuYO;G)h%LD?KLsT47Ei(}VKqxs}H@),E&(>oKlQ5gaX' );
define( 'NONCE_KEY',        'JUC9:~,vP0im/G@yYZrpbnxAaO59b0u#f2i_Qufgk;Ea5W@nb1e@&.9@<gI%yscZ' );
define( 'AUTH_SALT',        ' exOVIsmVl0jjr~uitNv}QE~KL}jH#zu/}+U3q4%t.Gh$]kH4&AyS(Yoob*jUBI ' );
define( 'SECURE_AUTH_SALT', '9`K:IIzl?&a!l(]>86;?<b Ek)gO~J;^,B,Vs6nPyM6!(,#02U7BL}6=1CE)vyOv' );
define( 'LOGGED_IN_SALT',   'FRv-4$ywv(I_GXI {+M?O~TN}%DI{j_bc#EwQ>;d?Ux)h5|-ASQO?kuaN/}vZa3<' );
define( 'NONCE_SALT',       'LL0e,[lw;.qZfI#k?.QJ|m0VyrRmSG4HdSBCdGEsM<3#`H7/;ZMh(MGK#8e$fiYC' );

/**#@-*/

/**
 * WordPress Database Table prefix.
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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

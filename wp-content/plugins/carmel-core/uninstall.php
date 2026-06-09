<?php
/**
 * Uninstall: remove roles and the caps added to the administrator.
 * Custom post type *content* is intentionally left intact to avoid
 * accidental data loss; delete it manually if a full purge is required.
 *
 * @package CarmelCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-carmel-post-types.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-carmel-roles.php';

Carmel_Roles::remove_roles_and_caps();

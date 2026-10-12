<?php
/**
 * Vendor role.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * Registers the vendor role. Vendors get almost no wp-admin capabilities on
 * purpose: they'll manage everything from a front-end dashboard.
 */
class Roles {

	const ROLE = 'hexxsy_vendor';

	/**
	 * Add the role on activation.
	 */
	public static function install(): void {
		add_role(
			self::ROLE,
			__( 'Hexxsy Vendor', 'hexxsy-vendors' ),
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);

		// Dashboard endpoints register on init, after activation runs.
		update_option( Dashboard::FLUSH_FLAG, 1 );

		if ( false === get_option( Commission::OPTION ) ) {
			add_option( Commission::OPTION, Commission::DEFAULT_RATE );
		}
	}

	/**
	 * Remove the role on deactivation. Users keep the role assignment and
	 * status meta, so reactivating restores every vendor as they were.
	 */
	public static function uninstall(): void {
		remove_role( self::ROLE );
	}
}

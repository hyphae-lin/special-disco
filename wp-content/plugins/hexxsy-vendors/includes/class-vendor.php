<?php
/**
 * Vendor status and profile helpers.
 *
 * @package Hexxsy\Vendors
 */

namespace Hexxsy\Vendors;

/**
 * A vendor's application status lives in user meta. The vendor role is only
 * granted on approval, so a pending applicant can't sell anything.
 */
class Vendor {

	const STATUS_META = 'hexxsy_vendor_status';

	const PENDING  = 'pending';
	const APPROVED = 'approved';
	const REJECTED = 'rejected';

	/**
	 * Profile fields collected on the application, keyed by meta key.
	 */
	const PROFILE_FIELDS = array(
		'hexxsy_shop_name'   => 'Shop name',
		'hexxsy_bio'         => 'About you',
		'hexxsy_specialties' => 'Specialties',
		'hexxsy_experience'  => 'Experience',
	);

	/**
	 * Current status, or '' if the user never applied.
	 *
	 * @param int $user_id User ID.
	 */
	public static function status( int $user_id ): string {
		return (string) get_user_meta( $user_id, self::STATUS_META, true );
	}

	/**
	 * Whether the user is an approved vendor.
	 *
	 * @param int $user_id User ID.
	 */
	public static function is_approved( int $user_id ): bool {
		return self::APPROVED === self::status( $user_id ) && user_can( $user_id, Roles::ROLE );
	}

	/**
	 * Approve an applicant and give them the vendor role.
	 *
	 * @param int $user_id User ID.
	 */
	public static function approve( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		update_user_meta( $user_id, self::STATUS_META, self::APPROVED );
		$user->add_role( Roles::ROLE );

		Emails::send( 'application_approved', $user->user_email );

		do_action( 'hexxsy_vendor_approved', $user_id );
	}

	/**
	 * Reject an applicant, or revoke an approved vendor.
	 *
	 * @param int $user_id User ID.
	 */
	public static function reject( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		update_user_meta( $user_id, self::STATUS_META, self::REJECTED );
		$user->remove_role( Roles::ROLE );

		Emails::send( 'application_rejected', $user->user_email );

		do_action( 'hexxsy_vendor_rejected', $user_id );
	}
}

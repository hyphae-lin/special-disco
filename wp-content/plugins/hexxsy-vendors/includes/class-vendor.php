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
	 * Revoked, with paid orders still to deliver. A closing reader can't sell
	 * and holds no seat; they keep Reading orders until the last one is done.
	 */
	const CLOSING = 'closing';

	const PAUSED_META = '_hexxsy_paused_on_revoke';

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
	 * Whether the user may still deliver readings: an approved vendor, or a
	 * revoked one finishing their open orders.
	 *
	 * @param int $user_id User ID.
	 */
	public static function can_fulfil( int $user_id ): bool {
		return self::is_approved( $user_id ) || self::CLOSING === self::status( $user_id );
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

		self::notify(
			$user,
			__( 'Your Hexxsy vendor application was approved', 'hexxsy-vendors' ),
			__( 'Welcome to Hexxsy. Your vendor account is approved, and you can now set up your readings.', 'hexxsy-vendors' )
		);

		do_action( 'hexxsy_vendor_approved', $user_id );
	}

	/**
	 * Reject an applicant. Approved vendors are revoked instead (see revoke()).
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

		self::notify(
			$user,
			__( 'About your Hexxsy vendor application', 'hexxsy-vendors' ),
			__( 'Thank you for applying to sell on Hexxsy. We are not able to approve your application at this time.', 'hexxsy-vendors' )
		);

		do_action( 'hexxsy_vendor_rejected', $user_id );
	}

	/**
	 * Revoke an approved vendor. Their readings come off sale at once. Orders
	 * they've been paid for and haven't delivered are either refunded now, or
	 * left for them to finish from Reading orders. Live calls can't be
	 * fulfilled, so those are refunded either way.
	 *
	 * @param int  $user_id User ID.
	 * @param bool $refund  Refund open orders, rather than let the reader finish them.
	 */
	public static function revoke( int $user_id, bool $refund ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( self::status( $user_id ), array( self::APPROVED, self::CLOSING ), true ) ) {
			return;
		}

		foreach ( wc_get_products( array( 'author' => $user_id, 'status' => 'publish', 'limit' => -1 ) ) as $product ) {
			$product->set_status( 'draft' );
			$product->update_meta_data( self::PAUSED_META, time() );
			$product->save();
		}

		$user->remove_role( Roles::ROLE );

		foreach ( Refunds::open_lines( $user_id ) as list( $order, $item ) ) {
			if ( $refund || ! Delivery::is_written( $item ) ) {
				Refunds::cancel_line( $order, $item, Refunds::REVOKED );
			}
		}

		$left = count( Refunds::open_lines( $user_id ) );
		update_user_meta( $user_id, self::STATUS_META, $left ? self::CLOSING : self::REJECTED );

		wp_mail(
			$user->user_email,
			Lines::text( 'revoke_subject' ),
			$left
				? Lines::text(
					'revoke_finish_body',
					array(
						'count'    => (string) $left,
						'link_url' => wc_get_account_endpoint_url( Dashboard::ORDERS ),
					)
				)
				: Lines::text( 'revoke_body' )
		);

		do_action( 'hexxsy_vendor_rejected', $user_id );
	}

	/**
	 * Close a revoked reader out once nothing is left for them to deliver.
	 *
	 * @param int $user_id User ID.
	 */
	public static function settle_closing( int $user_id ): void {
		if ( $user_id && self::CLOSING === self::status( $user_id ) && ! Refunds::open_lines( $user_id ) ) {
			update_user_meta( $user_id, self::STATUS_META, self::REJECTED );
		}
	}

	/**
	 * Email a vendor.
	 *
	 * @param \WP_User $user    Recipient.
	 * @param string   $subject Subject.
	 * @param string   $message Body.
	 */
	private static function notify( \WP_User $user, string $subject, string $message ): void {
		wp_mail( $user->user_email, $subject, $message );
	}
}

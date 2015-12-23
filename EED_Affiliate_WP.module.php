<?php
/**
 * This file contains the module for the EE Affiliate addon
 *
 * @since 1.0.0
 * @package  EE Affiliate Addon
 * @subpackage modules
 */
if ( ! defined('EVENT_ESPRESSO_VERSION')) exit('No direct script access allowed');
/**
 *
 * EE Affiliate module.  Regsiters hooks and callbacks for processing affiliate conversion tracking.
 *
 * @since 1.0.0
 *
 * @package		EE Affiliate Addon
 * @subpackage	modules
 * @author 		Darren Ethier
 *
 * ------------------------------------------------------------------------
 */
class EED_Affiliate_WP extends EED_Module {


	/**
	 * This is used for the affwp context resource reference for each affiliate record
	 * in the affwp tables.
	 *
	 * @type string
	 */
	protected static $_context = 'event_espresso';


	public static function instance() {
		return parent::get_instance( __CLASS__ );
	}

	public static function set_hooks() {
		add_action( 'AHEE__EE_Transaction_Processor__update_transaction_and_registrations_after_checkout_or_payment', array( 'EED_Affiliate_WP', 'track_conversion' ), 10, 2 );
	}

	public static function set_hooks_admin() {
		//covers ajax requests
		add_action( 'AHEE__EE_Transaction_Processor__update_transaction_and_registrations_after_checkout_or_payment', array( 'EED_Affiliate_WP', 'track_conversion' ), 10, 2 );
	}


	public function run( $WP ) {
		//this shouldn't ever be instantiated really which is why we're doing nothing here.
		return;
	}



	/**
	 * Callback for AHEE__thank_you_page_transaction_details_template__after_transaction_table_row that we'll use to track
	 * any conversions for Affiliates.
	 *
	 * @param EE_Transaction|null $transaction
	 */
	public static function track_conversion( $transaction, $update_params ) {
		do_action( 'AHEE_log', __FILE__, __FUNCTION__, $transaction->is_completed(), 'transaction is completed for affiliate wp callback' );

		$awp = function_exists( 'affiliate_wp' ) ? affiliate_wp() : null;

		//first determine if we have a valid affiliate_wp instance or EE_Transaction object and can even work with it!
		if (
			! $awp instanceof Affiliate_WP
			|| ! $transaction instanceof EE_Transaction
		) {
			return;
		}

		//next see if this transaction is already being tracked and if it is, then we update the affiliate record if necessary.
		if (
			$referral = $awp->referrals->get_by( 'reference', $transaction->ID(), self::$_context )
		) {
			self::_maybe_update_affiliate_status( $transaction, $awp, $referral );
		} else {
			//so there is no affiliate record for this transaction so we'll create one if possible.
			self::_maybe_initiate_affiliate_tracking( $transaction, $awp );
		}

	}
	

	/**
	 * This will update a AffiliateWP referral record if it hasn't been paid according to the transaction status.
	 *
	 * @param EE_Transaction $transaction
	 * @param Affiliate_WP   $awp
	 * @param                $referral
	 */
	protected static function _maybe_update_affiliate_status( EE_Transaction $transaction, Affiliate_WP $awp, $referral ) {
		//if $referral isn't an object or has already been paid, then get out
		if (
			! is_object( $referral )
			|| $referral->status === 'paid'
		) {
			return;
		}

		//if transaction is complete, then let's update the status to unpaid. Otherwise we make sure the status is pending.
		if ( $transaction->is_completed() ) {
			$awp->referrals->update( $referral->referral_id, array( 'status' => 'unpaid' ), '', 'referral' );
		} else {
			$awp->referrals->update( $referral->referral_id, array( 'status' => 'pending' ), '', 'referral' );
		}
	}




	/**
	 * This will create an affiliate wp referral record if there is an affiliate ID in the request.  Note, this does NOT
	 * check if an affiliate record has already been created.  That needs to be done by client code.
	 *
	 * @param EE_Transaction $transaction
	 * @param Affiliate_WP   $awp
	 */
	protected static function _maybe_initiate_affiliate_tracking( EE_Transaction $transaction, Affiliate_WP $awp ) {

		//only do this if we have a valid affiliate id.
		if ( ! $awp->tracking->is_valid_affiliate( $awp->tracking->get_affiliate_id() ) ) {
			return;
		}

		//valid affiliate so let's get creating the initial affiliate record.
		$invoice_amount = $transaction->total() > 0 ? affwp_calc_referral_amount( $transaction->total() ) : 0;

		//get events on transaction so we can setup the description for this purchase.
		$registrations = $transaction->registrations();
		$event_titles = array();
		foreach ( $registrations as $registration ) {
			if ( $registration instanceof EE_Registration ) {
				if ( $registration->event_name() ) {
					$event_titles[] = $registration->event_name();
				}
			}
		}

		if ( $event_titles ) {
			$description = count( $event_titles ) > 1 ? sprintf( __( 'Registration for the events: %s', 'event_espresso' ), implode( ', ', $event_titles ) ) : sprintf( 'Registration for the event: %s ', 'event_espresso', $event_titles[0] );
		} else {
			$description = '';
		}

		//set status depending on Transaction status
		$aff_status = $transaction->is_completed() ? 'unpaid' : 'pending';

		//store visit in db
		$referral_id = $awp->referrals->add( array(
			'affiliate_id' => $awp->tracking->get_affiliate_id(),
			'amount' => $invoice_amount,
			'status' => $aff_status,
			'description' => $description,
			'context' => self::$_context,
			'campaign' => $awp->tracking->get_campaign(),
			'reference' => $transaction->ID(),
			'visit_id' => $awp->tracking->get_visit_id()
		));

		$awp->visits->update( $awp->tracking->get_visit_id(), array( 'referral_id' => $referral_id ), '', 'visit' );
	}

}
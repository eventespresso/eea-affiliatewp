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
		add_action( 'AHEE__EE_Payment_Processor__update_txn_based_on_payment', array( 'EED_Affiliate_WP', 'create_referral_record' ), 10, 2 );
		add_action( 'AHEE__EE_Transaction_Processor__update_transaction_and_registrations_after_checkout_or_payment', array( 'EED_Affiliate_WP', 'update_referral_record' ), 10, 2 );
		add_action( 'AHEE__EEM_Transaction__delete_junk_transactions__successful_deletion', array( 'EED_Affiliate_WP', 'set_affiliate_referral_status_after_deleted_transaction' ) );
	}

	public static function set_hooks_admin() {
		//covers ajax requests
		add_action( 'AHEE__EE_Payment_Processor__update_txn_based_on_payment', array( 'EED_Affiliate_WP', 'create_referral_record' ), 10, 2 );
		add_action( 'AHEE__EE_Transaction_Processor__update_transaction_and_registrations_after_checkout_or_payment', array( 'EED_Affiliate_WP', 'update_referral_record' ), 10, 2 );
		add_action( 'AHEE__EEM_Transaction__delete_junk_transactions__successful_deletion', array( 'EED_Affiliate_WP', 'set_affiliate_referral_status_after_deleted_transaction' ) );
	}


	public function run( $WP ) {
		//this shouldn't ever be instantiated really which is why we're doing nothing here.
		return;
	}



	/**
	 * Callback for AHEE__thank_you_page_transaction_details_template__after_transaction_table_row that we'll use to update
	 * any referrals for Affiliates that have already been created.
	 *
	 * @param EE_Transaction|null $transaction
	 */
	public static function update_referral_record( $transaction, $update_params ) {
		do_action( 'AHEE_log', __FILE__, __FUNCTION__, $transaction->is_completed(), 'transaction is completed for affiliate wp callback' );

		$awp = function_exists( 'affiliate_wp' ) ? affiliate_wp() : null;

		//first determine if we have a valid affiliate_wp instance or EE_Transaction object and can even work with it!
		if (
			! $awp instanceof Affiliate_WP
			|| ! $transaction instanceof EE_Transaction
		) {
			return;
		}

		//IF we have a referral, update.  Otherwise we do nothing.
		if ( $referral = $awp->referrals->get_by( 'reference', $transaction->ID(), self::$_context ) ) {
			self::_maybe_update_affiliate_referral_status( $transaction, $awp, $referral );
		}

	}


	/**
	 * Callback for AHEE__EE_Payment_Processor__update_txn_based_on_payment that is used to create the initial referral record
	 * if possible.
	 *
	 * @param EE_Transaction $transaction
	 * @param EE_Payment     $payment
	 */
	public static function create_referral_record( EE_Transaction $transaction, EE_Payment $payment ) {
		$awp = function_exists( 'affiliate_wp' ) ? affiliate_wp() : null;
		if (
			$awp instanceof Affiliate_WP
			&& ! $awp->referrals->get_by( 'reference', $transaction->ID(), self::$_context ) //don't create if its already present.
		) {
			self::_maybe_initiate_affiliate_tracking( $transaction, $awp );
		}
	}




	/**
	 * Callback for the AHEE__EEM_Transaction__delete_junk_transactions__successful_deletion action hook.
	 * Used to ensure that if there are any affiliate records matching the deleted transaction id that we delete those
	 * referrals.
	 *
	 * @param $deleted_transaction_ids
	 */
	public function set_affiliate_referral_status_after_deleted_transaction( $deleted_transaction_ids ) {
		if ( is_array( $deleted_transaction_ids ) ) {
			$awp = function_exists( 'affiliate_wp' ) ? affiliate_wp() : null;
			if (
				$awp instanceof Affiliate_WP
			) {
				foreach ( $deleted_transaction_ids as $txn_id ) {
					if ( $referral = $awp->referrals->get_by( 'reference', $txn_id, self::$_context ) ) {
						if (
							is_object( $referral )
							&& function_exists( 'affwp_delete_referral' )
							&& $referral->status !== 'paid'
						) {
							affwp_delete_referral( $referral );
						}
					}
				}
			}
		}
	}


	/**
	 * This will update a AffiliateWP referral record if it hasn't been paid according to the transaction status.
	 *
	 * @param EE_Transaction $transaction
	 * @param Affiliate_WP   $awp
	 * @param                $referral
	 */
	protected static function _maybe_update_affiliate_referral_status( EE_Transaction $transaction, Affiliate_WP $awp, $referral ) {
		//if $referral isn't an object or has already been paid, then get out
		if (
			! is_object( $referral )
			|| $referral->status === 'paid'
			|| ! function_exists( 'affwp_set_referral_status' )
		) {
			return;
		}

		//if transaction is complete, then let's update the status to unpaid and also ensure the total is correct for
		// the current transaction. Otherwise we make sure the status is pending.
		if ( $transaction->is_completed() ) {
			//valid affiliate so let's get creating the initial affiliate record.
			$invoice_amount = $transaction->total() > 0 ? affwp_calc_referral_amount( $transaction->total(), $referral->affiliate_id ) : 0;
			affwp_set_referral_status( $referral->referral_id, 'unpaid' );

			if ( $invoice_amount !== $referral->amount ) {
				$awp->referrals->update( $referral->referral_id, array( 'amount' => $invoice_amount ) );
			}
		} else {
			affwp_set_referral_status( $referral->referral_id, 'pending' );
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
			$description = count( $event_titles ) > 1 ? sprintf( __( 'Registration for the events: %s', 'event_espresso' ), implode( ', ', $event_titles ) ) : sprintf( __( 'Registration for the event: %s ', 'event_espresso' ), $event_titles[0] );
		} else {
			$description = '';
		}

		//store visit in db
		$referral_id = $awp->referrals->add( array(
			'affiliate_id' => $awp->tracking->get_affiliate_id(),
			'amount' => $invoice_amount,
			'status' => 'pending',
			'description' => $description,
			'context' => self::$_context,
			'campaign' => $awp->tracking->get_campaign(),
			'reference' => $transaction->ID(),
			'visit_id' => $awp->tracking->get_visit_id()
		));

		//reset status if transaction is completed because AffiliateWP seems to have this as the canonical method for changing
		//referral status (with actions etc on this method).
		if (
			$transaction->is_completed()
			&& function_exists( 'affwp_set_referral_status' )
		) {
			affwp_set_referral_status( $referral_id, 'unpaid' );
		}

		$awp->visits->update( $awp->tracking->get_visit_id(), array( 'referral_id' => $referral_id ), '', 'visit' );
	}

}
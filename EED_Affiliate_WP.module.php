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
	public static function instance() {
		return parent::get_instance( __CLASS__ );
	}

	public static function set_hooks() {
		add_action( 'AHEE__EE_Transaction_Processor__update_transaction_and_registrations_after_checkout_or_payment', array( 'EED_Affiliate_WP', 'track_conversion' ), 10, 2 );
	}

	public static function set_hooks_admin() {}


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
		//let's get the affiliate ID and cookie
		$ref   = isset( $_COOKIE['affwp_ref'] ) ? $_COOKIE['affwp_ref'] : '';
		$visit = isset( $_COOKIE['affwp_ref_visit_id'] ) ? $_COOKIE['affwp_ref_visit_id'] : 0;
		$campaign = isset( $_COOKIE['affwp_campaign'] ) ? $_COOKIE['affwp_campaign'] : '';

		//only execute if valid affiliate, if this visit hasn't already been tracked, and IF we have a valid transaction object
		//and the transaction is complete.
		$awp = function_exists( 'affiliate_wp' ) ? affiliate_wp() : null;
		if (
			$awp instanceof Affiliate_WP
			&& $awp->tracking instanceof Affiliate_WP_Tracking
			&& $awp->tracking->is_valid_affiliate( $ref )
			&& ! affiliate_wp()->referrals->get_by( 'visit_id', $awp->tracking->get_visit_id() )
			&& $transaction instanceof EE_Transaction
			&& $transaction->is_completed()
		) {
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
				$description = count( $event_titles ) > 1 ? 'Registration for the events: ' . implode( ', ', $event_titles ) : 'Registration for the event: ' . $event_titles[0];
			} else {
				$description = '';
			}

			//store visit in db
			$referral_id = $awp->referrals->add( array(
				'affiliate_id' => $ref,
				'amount' => $invoice_amount,
				'status' => 'pending',
				'description' => $description,
				'context' => 'Event Registration - Complete Transaction',
				'campaign' => $campaign,
				'reference' => $transaction->ID(),
				'visit_id' => $awp->tracking->get_visit_id()
			));

			affwp_set_referral_status( $referral_id, 'unpaid' );
			$awp->visits->update( $awp->tracking->get_visit_id(), array( 'referral_id' => $referral_id ), '', 'visit' );
		}
	}
}
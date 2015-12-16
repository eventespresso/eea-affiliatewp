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
		//only execute if valid affiliate, if this visit hasn't already been tracked, and IF we have a valid transaction object
		//and the transaction is complete.
		$awp = function_exists( 'affiliate_wp' ) ? affiliate_wp() : null;
		if (
			$awp instanceof Affiliate_WP
			&& $awp->tracking instanceof Affiliate_WP_Tracking
			&& $awp->tracking->is_valid_affiliate( $awp->tracking->get_affiliate_id() )
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
				$description = count( $event_titles ) > 1 ? sprintf( __( 'Registration for the events: %s', 'event_espresso' ), implode( ', ', $event_titles ) ) : sprintf( 'Registration for the event: %s ', 'event_espresso', $event_titles[0] );
			} else {
				$description = '';
			}

			//store visit in db
			$referral_id = $awp->referrals->add( array(
				'affiliate_id' => $awp->tracking->get_affiliate_id(),
				'amount' => $invoice_amount,
				'status' => 'pending', //not localized, this is an internal reference
				'description' => $description,
				'context' => __( 'Event Registration - Complete Transaction', 'event_espresso' ),
				'campaign' => $awp->tracking->get_campaign(),
				'reference' => $transaction->ID(),
				'visit_id' => $awp->tracking->get_visit_id()
			));

			affwp_set_referral_status( $referral_id, 'unpaid' );
			$awp->visits->update( $awp->tracking->get_visit_id(), array( 'referral_id' => $referral_id ), '', 'visit' );
		}
	}
}
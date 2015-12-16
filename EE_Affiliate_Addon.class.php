<?php if ( ! defined( 'EVENT_ESPRESSO_VERSION' )) { exit(); }

// define the plugin directory path and URL
define( 'EE_AFFILIATE_WP_BASENAME', plugin_basename( EE_AFFILIATE_WP_PLUGIN_FILE ) );
define( 'EE_AFFILIATE_WP_PATH', plugin_dir_path( __FILE__ ) );
define( 'EE_AFFILIATE_WP_URL', plugin_dir_url( __FILE__ ) );



/**
 *
 * Class  EE_Affiliate_Addon
 *
 * @package			Event Espresso
 * @subpackage		eea-new-addon
 * @author			Darren Ethier
 *
 */
Class  EE_Affiliate_Addon extends EE_Addon {

	public static function register_addon() {
		// register addon via Plugin API
		EE_Register_Addon::register(
			'EE_Affiliate_Addon',
			array(
				'version' 					=> EE_AFFILIATE_WP_VERSION,
				'plugin_slug' 			=> 'eea-affiliate-wp',
				'min_core_version' => EE_AFFILIATE_WP_CORE_VERSION_REQUIRED,
				'main_file_path' 		=> EE_AFFILIATE_WP_PLUGIN_FILE,
				'autoloader_paths' => array(
					'EE_Affiliate_Addon' 						=> EE_AFFILIATE_WP_PATH . 'EE_Affiliate_Addon.class.php',
				),
				// if plugin update engine is being used for auto-updates. not needed if PUE is not being used.
				'pue_options'			=> array(
					'pue_plugin_slug' 		=> 'eea-affiliate-wp',
					'plugin_basename' 	=> EE_AFFILIATE_WP_BASENAME,
					'checkPeriod' 				=> '24',
					'use_wp_update' 		=> false,
				)
			)
		);

		add_action( 'AHEE__thank_you_page_transaction_details_template__after_transaction_table_row', array( 'EE_Affiliate_Addon', 'track_conversion' ) );
	}




	/**
	 * Callback for AHEE__thank_you_page_transaction_details_template__after_transaction_table_row that we'll use to track
	 * any conversions for Affiliates.
	 *
	 * @param EE_Transaction|null $transaction
	 */
	public static function track_conversion( $transaction ) {
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
// End of file EE_AffiliateWP_Addon.class.phplass.php
// Location: wp-content/plugins/eea-new-addon/EE_AffiliateWP_Addon.class.phplass.php

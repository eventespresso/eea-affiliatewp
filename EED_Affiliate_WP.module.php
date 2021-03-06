<?php

/**
 *
 * EE Affiliate module.  Regsiters hooks and callbacks for processing affiliate conversion tracking.
 *
 * @since 1.0.0
 *
 * @package     EE Affiliate Addon
 * @subpackage  modules
 * @author      Darren Ethier
 *
 * ------------------------------------------------------------------------
 */
class EED_Affiliate_WP extends EED_Module
{


    /**
     * This is used for the affwp context resource reference for each affiliate record
     * in the affwp tables.
     *
     * @type string
     */
    protected static $_context = 'event_espresso';


    public static function instance()
    {
        return parent::get_instance(__CLASS__);
    }

    public static function set_hooks()
    {
        add_action('AHEE__EE_Payment_Processor__update_txn_based_on_payment', array( 'EED_Affiliate_WP', 'create_referral_record' ), 10, 2);
        add_action('AHEE__EE_Transaction_Processor__update_transaction_and_registrations_after_checkout_or_payment', array( 'EED_Affiliate_WP', 'update_referral_record' ), 10);
        add_action('AHEE__EEM_Transaction__delete_junk_transactions__successful_deletion', array( 'EED_Affiliate_WP', 'set_affiliate_referral_status_after_deleted_transaction' ));
        add_action('AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed', array( 'EED_Affiliate_WP', 'maybe_create_referral_record_from_finalize_registration_step' ), 10, 2);
    }

    public static function set_hooks_admin()
    {
        // covers ajax requests
        add_action('AHEE__EE_Payment_Processor__update_txn_based_on_payment', array( 'EED_Affiliate_WP', 'create_referral_record' ), 10, 2);
        add_action('AHEE__EE_Transaction_Processor__update_transaction_and_registrations_after_checkout_or_payment', array( 'EED_Affiliate_WP', 'update_referral_record' ), 10);
        add_action('AHEE__EEM_Transaction__delete_junk_transactions__successful_deletion', array( 'EED_Affiliate_WP', 'set_affiliate_referral_status_after_deleted_transaction' ));
        add_action('AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed', array( 'EED_Affiliate_WP', 'maybe_create_referral_record_from_finalize_registration_step' ), 10, 2);
        add_action('AHEE__Transactions_Admin_Page__apply_payments_or_refund__after_recording', array( 'EED_Affiliate_WP', 'update_referral_record' ), 10);
    }


    public function run($WP)
    {
        // this shouldn't ever be instantiated really which is why we're doing nothing here.
        return;
    }



    /**
     * Callback for AHEE__thank_you_page_transaction_details_template__after_transaction_table_row that we'll use to update
     * any referrals for Affiliates that have already been created.
     *
     * @param EE_Transaction|null $transaction
     */
    public static function update_referral_record($transaction)
    {
        do_action('AHEE_log', __FILE__, __FUNCTION__, $transaction->is_completed(), 'transaction is completed check for affiliate wp callback');

        $awp = function_exists('affiliate_wp') ? affiliate_wp() : null;

        // first determine if we have a valid affiliate_wp instance or EE_Transaction object and can even work with it!
        if (! $awp instanceof Affiliate_WP
            || ! $transaction instanceof EE_Transaction
        ) {
            return;
        }

        // IF we have a referral, update.  Otherwise we do nothing.
        if ($referral = $awp->referrals->get_by('reference', $transaction->ID(), self::$_context)) {
            self::_maybe_update_affiliate_referral_status($transaction, $awp, $referral);
        }
    }


    /**
     * Callback for AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed that is used for creating
     * a referral record in the case where a payment isn't being made (example when an Event has the Default Registration
     * status set to Not Approved)
     *
     * @param EE_Checkout $checkout
     * @param array       $transaction_update_parameters
     */
    public static function maybe_create_referral_record_from_finalize_registration_step(EE_Checkout $checkout, $transaction_update_parameters)
    {
        // this just wraps our existing create referral method.
        self::create_referral_record($checkout->transaction, $checkout->payment);
    }


    /**
     * Callback for AHEE__EE_Payment_Processor__update_txn_based_on_payment that is used to create the initial referral record
     * if possible.
     *
     * @param EE_Transaction $transaction
     * @param EE_Payment|null     $payment
     */
    public static function create_referral_record(EE_Transaction $transaction, $payment)
    {
        $awp = function_exists('affiliate_wp') ? affiliate_wp() : null;
        if ($awp instanceof Affiliate_WP
            && ! $awp->referrals->get_by('reference', $transaction->ID(), self::$_context) // don't create if its already present.
        ) {
            self::_maybe_initiate_affiliate_tracking($transaction, $awp);
        }
    }




    /**
     * Callback for the AHEE__EEM_Transaction__delete_junk_transactions__successful_deletion action hook.
     * Used to ensure that if there are any affiliate records matching the deleted transaction id that we delete those
     * referrals.
     *
     * @param $deleted_transaction_ids
     */
    public static function set_affiliate_referral_status_after_deleted_transaction($deleted_transaction_ids)
    {
        if (is_array($deleted_transaction_ids)) {
            $awp = function_exists('affiliate_wp') ? affiliate_wp() : null;
            if ($awp instanceof Affiliate_WP
            ) {
                foreach ($deleted_transaction_ids as $txn_id) {
                    if ($referral = $awp->referrals->get_by('reference', $txn_id, self::$_context)) {
                        if (is_object($referral)
                            && function_exists('affwp_delete_referral')
                            && $referral->status !== 'paid'
                        ) {
                            affwp_delete_referral($referral);
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
    protected static function _maybe_update_affiliate_referral_status(EE_Transaction $transaction, Affiliate_WP $awp, $referral)
    {
        // if $referral isn't an object or has already been paid, then get out
        if (! is_object($referral)
            || $referral->status === 'paid'
            || ! function_exists('affwp_set_referral_status')
        ) {
            return;
        }

        // if transaction is complete, then let's update the status to unpaid and also ensure the total is correct for
        // the current transaction. Otherwise we make sure the status is pending.
        if ($transaction->is_completed()) {
            // valid affiliate so let's get creating the initial affiliate record.
            $invoice_amount = self::getInvoiceAmount($transaction, $awp);
            $referral_amount = $invoice_amount > 0 ? affwp_calc_referral_amount($invoice_amount, $referral->affiliate_id) : 0;
            affwp_set_referral_status($referral->referral_id, 'unpaid');

            if ($referral_amount !== $referral->amount) {
                $awp->referrals->update($referral->referral_id, array( 'amount' => $referral_amount ));
            }
        } else {
            affwp_set_referral_status($referral->referral_id, 'pending');
        }
    }




    /**
     * This will create an affiliate wp referral record if there is an affiliate ID in the request.  Note, this does NOT
     * check if an affiliate record has already been created.  That needs to be done by client code.
     *
     * @param EE_Transaction $transaction
     * @param Affiliate_WP   $awp
     */
    protected static function _maybe_initiate_affiliate_tracking(EE_Transaction $transaction, Affiliate_WP $awp)
    {

        // only do this if we have a valid affiliate id.
        if (! $awp->tracking->is_valid_affiliate($awp->tracking->get_affiliate_id())) {
            return;
        }

        // valid affiliate so let's get creating the initial affiliate record.
        $invoice_amount = self::getInvoiceAmount($transaction, $awp);
        $referral_amount = $invoice_amount > 0 ? affwp_calc_referral_amount($invoice_amount, $awp->tracking->get_affiliate_id()) : 0;

        // get events on transaction so we can setup the description for this purchase.
        $registrations = $transaction->registrations();
        $event_titles = array();
        foreach ($registrations as $registration) {
            if ($registration instanceof EE_Registration) {
                if ($registration->event_name()) {
                    $event_titles[] = $registration->event_name();
                }
            }
        }

        if ($event_titles) {
            $description = count($event_titles) > 1 ? implode(', ', $event_titles) : $event_titles[0];
        } else {
            $description = '';
        }

        // store visit in db
        $referral_id = $awp->referrals->add(
            array(
                'affiliate_id' => $awp->tracking->get_affiliate_id(),
                'amount' => $referral_amount,
                'status' => 'pending',
                'description' => apply_filters('AHEE__EED_Affiliate_WP___maybe_initiate_affiliate_tracking__description', $description, $transaction, $event_titles),
                'context' => self::$_context,
                'campaign' => $awp->tracking->get_campaign(),
                'reference' => $transaction->ID(),
                'visit_id' => $awp->tracking->get_visit_id()
            )
        );

        // reset status if transaction is completed because AffiliateWP seems to have this as the canonical method for changing
        // referral status (with actions etc on this method).
        if ($transaction->is_completed()
            && function_exists('affwp_set_referral_status')
        ) {
            affwp_set_referral_status($referral_id, 'unpaid');
        }

        $awp->visits->update($awp->tracking->get_visit_id(), array( 'referral_id' => $referral_id ), '', 'visit');
    }



    /**
     * Gets the base amount to calculate the referral amount from. Usually the Transaction Total.
     *
     * @param EE_Transaction $transaction
     * @param Affiliate_WP   $awp
     * @return float
     */
    public static function getInvoiceAmount(
        EE_Transaction $transaction,
        Affiliate_WP $awp
    ) {
        if ($awp->settings->get('exclude_tax')) {
            $amount = EEH_Line_Item::get_pre_tax_subtotal($transaction->total_line_item())->total();
        } else {
            $amount = $transaction->total();
        }
        return apply_filters(
            'FHEE__EED_Affiliate_WP__getInvoiceAmount__amount',
            $amount,
            $transaction,
            $awp
        );
    }
}

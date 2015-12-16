<?php
/**
 * Contains test class for espresso_addon_skeleton.php
 *
 * @since  		0.0.1.dev.002
 * @package 		EE4 Addon Skeleton
 * @subpackage 	tests
 */


/**
 * Test class for espresso_addon_skeleton.php
 *
 * @since 		0.0.1.dev.002
 * @package 		EE4 Addon Skeleton
 * @subpackage 	tests
 */
class eea_affiliate_addon_tests extends EE_UnitTestCase {

	/**
	 * Tests the loading of the main file
	 *
	 * @since 0.0.1.dev.002
	 */
	function test_loading_ee_wp_affiliate() {
		$this->assertEquals( has_action('AHEE__EE_System__load_espresso_addons', 'load_espresso_affiliate_wp'), 10 );
		$this->assertTrue( class_exists( 'EE_AffiliateWP_Addon' ) );
	}
}

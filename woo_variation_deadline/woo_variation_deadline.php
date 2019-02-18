<?php
/*
Plugin Name: Woocommerce Variation Deadline
Plugin URI: https://tobier.de
Description: Adds a deadline to the product variations and disables the variation if the deadline is exceeded
Version: 0.1
Author: Tobias Keller
Author URI: https://tobier.de
Text Domain: woo_variation_deadline
*/
if ( ! defined( 'ABSPATH' ) ) exit;

$add_deadlines = new woo_variation_deadline();

class woo_variation_deadline {
	public function __construct() {
		/* add field to variation attributes */
		add_action( 'woocommerce_variation_options_tax', array( $this, 'addFieldtoBackend' ), 10, 3 );

		/* save deadline date */
		add_action( 'woocommerce_save_product_variation', array( $this, 'saveDeadline' ), 10, 2 );

		/* register new cronjob on plugin activation */
		register_activation_hook( __FILE__, array( $this, 'onPluginActivation' ) );

		/* deregister cronjob on plugin deactivation */
		register_deactivation_hook(__FILE__, array( $this, 'onPluginDeactivation' ) );

		/* cronjob - delete variations where deadline is passed */
		add_action( 'deleteOldVariations', array( $this, 'variationDeadlineJob' ) );
	}

	public function addFieldtoBackend( $loop, $variation_data, $variation ){

		echo '<div class="variation_deadline">';
		woocommerce_wp_text_input( array(
			'id'    => '_variation_deadline['. $loop .']',
			'label' => '<abbr title="' . esc_html__( 'Variation Deadline', 'woo_variation_deadline' ) . '">' . __( 'Variation Deadline', 'woo_variation_deadline' ) . '</abbr>',
			'type'  => 'date',
			'desc_tip' => true,
			'description' => esc_html__( 'Set a date when this variation should be deleted', 'woo_variation_deadline' ),
			'wrapper_class' => 'form-row form-row-full',
			'value' => get_post_meta( $variation->ID, '_variation_deadline', true ),
			'style' => 'width: 100%;',
			)
		);
		echo '</div>';
	}

	public function saveDeadline( $variation_id, $i ){
		$text_field = stripslashes( $_POST['_variation_deadline'][$i] );
		update_post_meta( $variation_id, '_variation_deadline', esc_attr( $text_field ) );
	}

	public function variationDeadlineJob(){
		bcis_main::bcis_logger( 'Variationen überprüft' );
		/* get all variations with deadline */
		global $wpdb;
		$variations = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key = '_variation_deadline' AND NOT meta_value = '' ");

		foreach ( $variations as $variation ){
			/* if deadline is exceeded and variation is not disabled */
			if ( $variation->meta_value <= date('Y-m-d') AND get_post_status( $variation->meta_id ) != 'private' ){
				/* disable variation */
				wp_update_post(array(
					'ID'    => $variation->post_id,
					'post_status'   => 'private'
				));
			}
		}
	}

	public function onPluginActivation(){
		if (! wp_next_scheduled ( 'deleteVariations' )) {
			wp_schedule_event( strtotime('04:00:00'), 'daily', 'deleteOldVariations' );
		}
	}

	public function onPluginDeactivation(){
		wp_clear_scheduled_hook( 'deleteOldVariations' );
	}
}
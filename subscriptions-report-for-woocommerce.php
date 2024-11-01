<?php
/**
 * Plugin Name: Subscriptions Report for WooCommerce
 * Description: Export WooCommerce Subscriptions in CSV (Comma Seperated Values) format, with various details on the subscription, customer, and subscribed products.
 * Version: 1.0.0
 * Author: Potent Plugins
 * Author URI: http://potentplugins.com/?utm_source=subscriptions-report-for-woocommerce&utm_medium=link&utm_campaign=wp-plugin-credit-link
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 */

// Add Subscriptions Report to the WordPress admin
add_action('admin_menu', 'hm_srwc_admin_menu');
function hm_srwc_admin_menu() {
	add_submenu_page('woocommerce', 'Subscriptions Report', 'Subscriptions Report', 'view_woocommerce_reports', 'hm_srwc', 'hm_srwc_page');
}

function hm_srwc_default_report_settings() {
	return array(
		'subscription_statuses' => array('wc-active'),
		'orderby' => 'subscription_id',
		'orderdir' => 'asc',
		'fields' => array('subscription_id', 'subscription_status', 'subscription_freq', 'billing_name', 'billing_email', 'next_payment_date', 'product_names_qty', 'total_amount'),
		'include_header' => 1
	);
}

function hm_swrc_field_options() {
	return array(
		'subscription_id' => 'Subscription ID',
		'subscription_status' => 'Subscription Status',
		'subscription_freq' => 'Subscription Frequency',
		'start_date' => 'Start Date',
		'last_payment_date' => 'Last Payment Date',
		'next_payment_date' => 'Next Payment Date',
		'billing_name' => 'Billing Name',
		'billing_phone' => 'Billing Phone',
		'billing_email' => 'Billing Email',
		'billing_address' => 'Billing Address',
		'shipping_name' => 'Shipping Name',
		'shipping_address' => 'Shipping Address',
		'product_id_qty' => 'Product IDs & Quantities',
		'product_name_qty' => 'Product Names & Quantities',
		'product_sku_qty' => 'Item SKUs & Quantities',
		'total_amount' => 'Total Amount'
	);
}

// This function generates the Subscriptions Report page HTML
function hm_srwc_page() {

	$savedReportSettings = get_option('hm_srwc_report_settings');
	
	$reportSettings = (empty($savedReportSettings) ?
						hm_srwc_default_report_settings() :
						array_merge(hm_srwc_default_report_settings(),
								$savedReportSettings[0]
						));
	
	$fieldOptions = hm_swrc_field_options();
		
	include(dirname(__FILE__).'/admin.php');
}

// Hook into WordPress init; this function performs report generation when
// the admin form is submitted
add_action('init', 'hm_srwc_on_init');
function hm_srwc_on_init() {
	global $pagenow;
	
	// Check if we are in admin and on the report page
	if (!is_admin())
		return;
	if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'hm_srwc' && !empty($_POST['hm_srwc_do_export'])) {
		
		// Verify the nonce
		check_admin_referer('hm_srwc_do_export');
		
		$newSettings = array_intersect_key($_POST, hm_srwc_default_report_settings());
		foreach ($newSettings as $key => $value)
			if (!is_array($value))
				$newSettings[$key] = htmlspecialchars($value);
		
		// Update the saved report settings
		$savedReportSettings = get_option('hm_srwc_report_settings');
		$savedReportSettings[0] = array_merge(hm_srwc_default_report_settings(), $newSettings);

		update_option('hm_srwc_report_settings', $savedReportSettings);
		
		// Check if no fields are selected
		if (empty($_POST['fields']))
			return;
		
		// Assemble the filename for the report download
		$filename =  'Subscriptions Report - '.date('Y-m-d', current_time('timestamp')).'.csv';
		
		// Send headers
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		
		// Output the report header row (if applicable) and body
		$stdout = fopen('php://output', 'w');
		if (!empty($_POST['include_header']))
			hm_srwc_export_header($stdout);
		hm_srwc_export_body($stdout);
		
		exit;
	}
}

// This function outputs the report header row
function hm_srwc_export_header($dest, $return=false) {
	$header = array();
	
	$fieldOptions = hm_swrc_field_options();
	
	foreach ($_POST['fields'] as $field) {
		$header[] = $fieldOptions[$field];
	}
	
	if ($return)
		return $header;
	fputcsv($dest, $header);
}

// This function generates and outputs the report body rows
function hm_srwc_export_body($dest, $return=false) {
	
	$subscriptionStatuses = wcs_get_subscription_statuses();
	$wcSubscriptionsGte220 = version_compare(WC_Subscriptions::$version, '2.2.0', '>=');
	
	$args = array(
		'nopaging' => true,
		'fields' => 'ids',
		'post_type' => 'shop_subscription',
		'post_status' => array_intersect(array_keys($subscriptionStatuses), $_POST['subscription_statuses']),
		'order' => ($_POST['orderdir'] == 'desc' ? 'desc' : 'asc')
	);
	
	switch ($_POST['orderby']) {
		case 'next_payment_date':
			$args['meta_key'] = '_schedule_next_payment';
			$args['orderby'] = 'meta_value';
			break;
		case 'total_amount':
			$args['meta_key'] = '_order_total';
			$args['orderby'] = 'meta_value_num';
			break;
		default:
			$args['orderby'] = 'ID';
	}
	
	$subscriptions = get_posts($args);
	
	// Output report rows
	foreach ($subscriptions as $subscriptionId) {
		$subscription = wcs_get_subscription($subscriptionId);
		if (!method_exists($subscription, 'get_id')) {
			// Compatibility with WooCommerce pre-3.0.0
			if (!class_exists('PP_WC_Order_Compat')) {
				require_once(dirname(__FILE__).'/PP_WC_Order_Compat.class.php');
			}
			$subscription = new PP_WC_Order_Compat($subscription);
		}
		
		$row = array();
		
		foreach ($_POST['fields'] as $field) {
			switch ($field) {
				case 'subscription_id':
					$row[] = $subscription->get_id();
					break;
				case 'subscription_status':
					$status = $subscription->get_status();
					$row[] = (isset($subscriptionStatuses['wc-'.$status]) ? $subscriptionStatuses['wc-'.$status] : $status);
					break;
				case 'subscription_freq':
					$row[] = $subscription->get_billing_interval().' '.$subscription->get_billing_period();
					break;
				case 'start_date':
					$row[] = date(get_option('date_format'), strtotime($subscription->get_date($wcSubscriptionsGte220 ? 'date_created' : 'start', 'site')));
					break;
				case 'last_payment_date':
					$row[] = date(get_option('date_format'), strtotime($subscription->get_date($wcSubscriptionsGte220 ? 'last_order_date_paid' : 'last_payment', 'site')));
					break;
				case 'next_payment_date':
					$row[] = date(get_option('date_format'), strtotime($subscription->get_date('next_payment', 'site')));
					break;
				case 'billing_name':
					$row[] = $subscription->get_billing_first_name().' '. $subscription->get_billing_last_name();
					break;
				case 'billing_phone':
					$row[] = $subscription->get_billing_phone();
					break;
				case 'billing_email':
					$row[] = $subscription->get_billing_email();
					break;
				case 'billing_address':
					$addressComponents = array(
						$subscription->get_billing_address_1(),
						$subscription->get_billing_address_2(),
						$subscription->get_billing_city(),
						$subscription->get_billing_state(),
						$subscription->get_billing_postcode(),
						$subscription->get_billing_country()
					);
					$row[] = implode(', ', array_filter($addressComponents));
					break;
				case 'shipping_name':
					$row[] = $subscription->get_shipping_first_name().' '. $subscription->get_shipping_last_name();
					break;
				case 'shipping_address':
					$addressComponents = array(
						$subscription->get_shipping_address_1(),
						$subscription->get_shipping_address_2(),
						$subscription->get_shipping_city(),
						$subscription->get_shipping_state(),
						$subscription->get_shipping_postcode(),
						$subscription->get_shipping_country()
					);
					$row[] = implode(', ', array_filter($addressComponents));
					break;
				case 'product_id_qty':
					$str = '';
					foreach ($subscription->get_items() as $item) {
						$str .= (empty($str) ? '' : ', ').$item['product_id'].' x '.$item['qty'];
					}
					$row[] = $str;
					break;
				case 'product_sku_qty':
					$str = '';
					foreach ($subscription->get_items() as $item) {
						$product = wc_get_product(empty($item['variation_id']) ? $item['product_id'] : $item['variation_id']);
						if (empty($product)) {
							$sku = '[Unknown]';
						} else {
							$sku = $product->get_sku();
							if (empty($sku)) {
								$sku = '[No SKU]';
							}
						}
						$str .= (empty($str) ? '' : ', ').$sku.' x '.$item['qty'];
					}
					$row[] = $str;
					break;
				case 'product_name_qty':
					$str = '';
					foreach ($subscription->get_items() as $item) {
						$product = wc_get_product($item['product_id']);
						$str .= (empty($str) ? '' : ', ').(empty($product) ? '[Unknown]' : $product->get_title()).' x '.$item['qty'];
					}
					$row[] = $str;
					break;
				case 'total_amount':
					$row[] = $subscription->get_total();
					break;
			}
		}
			
		if ($return)
			$rows[] = $row;
		else
			fputcsv($dest, $row);
	}
	if ($return)
		return $rows;
}

add_action('admin_enqueue_scripts', 'hm_srwc_admin_enqueue_scripts');
function hm_srwc_admin_enqueue_scripts() {
	wp_enqueue_style('hm_srwc_admin_style', plugins_url('css/admin.css', __FILE__));
}
?>
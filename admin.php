<?php
if (!defined('ABSPATH')) die();
// Print header
echo('
	<div class="wrap">
		<h2>Subscriptions Report for WooCommerce</h2>
');

// Check for WooCommerce & WooCommerce Subscriptions
if (!class_exists('WC_Subscription')) {
	echo('<div class="error"><p>This plugin requires that WooCommerce Subscriptions is installed and activated.</p></div></div>');
	return;
} else if (!class_exists('WooCommerce')) {
	echo('<div class="error"><p>This plugin requires that WooCommerce is installed and activated.</p></div></div>');
	return;
}

// Print form
echo('<div id="poststuff">
		<div id="post-body" class="columns-2">
			<div id="post-body-content" style="position: relative;">
			<form action="" method="post">
			<input type="hidden" name="hm_srwc_do_export" value="1" />
	');
wp_nonce_field('hm_srwc_do_export');
echo('
			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label>Show Subscriptions With Status:</label>
					</th>
					<td>');
foreach (wcs_get_subscription_statuses() as $status => $statusName) {
	echo('<label><input type="checkbox" name="subscription_statuses[]"'.(in_array($status, $reportSettings['subscription_statuses']) ? ' checked="checked"' : '').' value="'.$status.'" /> '.$statusName.'</label><br />');
}
			echo('</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label>Report Fields:</label>
					</th>
					<td id="hm_srwc_report_field_selection">');
$fieldOptions2 = $fieldOptions;
foreach ($reportSettings['fields'] as $fieldId) {
	if (!isset($fieldOptions2[$fieldId]))
		continue;
	echo('<label><input type="checkbox" name="fields[]" checked="checked" value="'.$fieldId.'"'.(in_array($fieldId, array('variation_id', 'variation_attributes')) ? ' class="variation-field"' : '').' /> '.esc_html($fieldOptions2[$fieldId]).'</label>');
	unset($fieldOptions2[$fieldId]);
}
foreach ($fieldOptions2 as $fieldId => $fieldDisplay) {
	echo('<label><input type="checkbox" name="fields[]" value="'.$fieldId.'"'.(in_array($fieldId, array('variation_id', 'variation_attributes')) ? ' class="variation-field"' : '').' /> '.esc_html($fieldDisplay).'</label>');
}
unset($fieldOptions2);
			echo('</td>
				<tr valign="top">
					<th scope="row">
						<label for="hm_srwc_field_orderby">Sort By:</label>
					</th>
					<td>
						<select name="orderby" id="hm_srwc_field_orderby">
							<option value="subscription_id"'.($reportSettings['orderby'] == 'subscription_id' ? ' selected="selected"' : '').'>Subscription ID</option>
							<option value="next_payment_date"'.($reportSettings['orderby'] == 'next_payment_date' ? ' selected="selected"' : '').'>Next Payment Date</option>
							<option value="total_amount"'.($reportSettings['orderby'] == 'total_amount' ? ' selected="selected"' : '').'>Total Amount</option>
						</select>
						<select name="orderdir">
							<option value="asc"'.($reportSettings['orderdir'] == 'asc' ? ' selected="selected"' : '').'>ascending</option>
							<option value="desc"'.($reportSettings['orderdir'] == 'desc' ? ' selected="selected"' : '').'>descending</option>
						</select>
					</td>
				</tr>
				</tr>
				<tr valign="top">
					<th scope="row" colspan="2" class="th-full">
						<label>
							<input type="checkbox" name="include_header"'.(empty($reportSettings['include_header']) ? '' : ' checked="checked"').' />
							Include header row
						</label>
					</th>
				</tr>
			</table>');
			echo('<p class="submit">
				<button type="submit" class="button-primary">Download Report</button>
			</p>
		</form>');
		
		$potent_slug = 'subscriptions-report-for-woocommerce';
		include(__DIR__.'/plugin-credit.php');
		echo('</div> <!-- /post-body-content -->
		
		
		</div> <!-- /post-body -->
		<br class="clear" />
		</div> <!-- /poststuff -->
	</div>
');
?>
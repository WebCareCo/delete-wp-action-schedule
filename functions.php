<?php
/** @see https://wpcodebook.com/woocommerce-action-scheduler-cleanup-php/ */
add_filter( 'action_scheduler_default_cleaner_statuses', function ( $statuses ) {
	$statuses[] = 'failed';
	return $statuses;
} );

// Default is 20
add_filter( 'action_scheduler_cleanup_batch_size', function ( $batch_size ) {
	return 100;
} );

// Default is 31 days
add_filter( 'action_scheduler_retention_period', function ( $period ) {
	return 3 * DAY_IN_SECONDS;
} );

// Calls the function that purges Action Scheduler database tables.
add_action( 'wp_loaded', function () {

	// Timestamp examples, in seconds
	// 1 day = 86400
	// 3 days = 259200
	// 1 week = 604800

	// MailPoet
	webcare_core_purge_action_scheduler_group_actions( 'mailpoet-cron', 259200 );

	// Rank Math SEO
	webcare_core_purge_action_scheduler_group_actions( 'rank-math' );

} );

/**
 * Purges Action Scheduler tables of complete, failed, or cancelled actions
 * Starts with oldest actions first.
 *
 * @link https://blackhillswebworks.com/2024/08/29/automatically-clean-action-scheduler-database-tables/
 *
 * @param string   $group   The group associated with a specific plugin.
 * @param int|null $seconds Number of seconds. Defaults to 86400 (1 day).
 */
function webcare_core_purge_action_scheduler_group_actions( string $group, int $seconds = 86400 ) {

	// If there's no group specified, we're done.
	if ( empty( $group ) )
		return;

	// If for some reason Action Scheduler is not in use, do not proceed.
	if ( ! function_exists( 'as_get_scheduled_actions' ) )
		return;

	// time() returns the current Unix timestamp (GMT)
	$time_to_check = time() - $seconds; // Current time minus number of seconds

	// @link https://actionscheduler.org/api/
	$args = array(
		'date'         => $time_to_check,
		'date_compare' => '<', // older than $time_to_check
		'group'        => $group,
		'per_page'     => 200,  // default is 5; -1 for all results
	);

	// Action Scheduler function that returns an array of ids
	$actions_to_delete = as_get_scheduled_actions( $args, 'ids' );

	// Need to implode the array to use with SQL
	$actions_to_delete = implode( ', ', $actions_to_delete );

	if ( empty( $actions_to_delete ) )
		return;

	global $wpdb;

	$sql_actions = "DELETE FROM $wpdb->actionscheduler_actions
					WHERE status
					IN ( 'complete','failed','canceled' )
					AND action_id
					IN ( $actions_to_delete )";

	$wpdb->query( $sql_actions );

	$sql_logs = "DELETE FROM $wpdb->actionscheduler_logs
				 WHERE action_id
				 IN ( $actions_to_delete )";

	$wpdb->query( $sql_logs );

}

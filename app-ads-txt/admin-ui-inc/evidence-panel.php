<?php
/**
 * Local file status panel for App Ads TXT evidence.
 *
 * @package App_Ads_Txt
 */

use Pagup\AppAdsTxt\Evidence\App_Ads_Evidence_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$adtm_evidence_payload = App_Ads_Evidence_Store::get_latest();
$adtm_evidence_observations = isset( $adtm_evidence_payload['observations'] ) && is_array( $adtm_evidence_payload['observations'] ) ? $adtm_evidence_payload['observations'] : array();
$adtm_evidence_summary = adtm_evidence_file_summary( $adtm_evidence_observations );
$adtm_evidence_last_scan = isset( $adtm_evidence_payload['finished_at'] ) ? strtotime( $adtm_evidence_payload['finished_at'] ) : false;
?>

<div class="adtm-note" style="margin-top: 20px;">
	<h2><?php echo esc_html__( 'File status', 'app-ads-txt' ); ?></h2>

	<p>
		<strong><?php echo esc_html__( 'Last scan:', 'app-ads-txt' ); ?></strong>
		<?php
		if ( $adtm_evidence_last_scan ) {
			echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $adtm_evidence_last_scan ) );
		} else {
			echo esc_html__( 'Not scanned yet.', 'app-ads-txt' );
		}
		?>
	</p>

	<ul>
		<li><strong><?php echo esc_html( 'ads.txt' ); ?>:</strong> <?php echo esc_html( adtm_evidence_summary_label( $adtm_evidence_summary, 'ads.txt' ) ); ?></li>
		<li><strong><?php echo esc_html( 'app-ads.txt' ); ?>:</strong> <?php echo esc_html( adtm_evidence_summary_label( $adtm_evidence_summary, 'app-ads.txt' ) ); ?></li>
	</ul>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 8px;">
		<input type="hidden" name="action" value="adtm_evidence_run">
		<?php wp_nonce_field( 'adtm_evidence_run' ); ?>
		<?php submit_button( esc_html__( 'Run validation', 'app-ads-txt' ), 'primary', 'submit', false ); ?>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
		<input type="hidden" name="action" value="adtm_evidence_export">
		<?php wp_nonce_field( 'adtm_evidence_export' ); ?>
		<?php submit_button( esc_html__( 'Export JSON', 'app-ads-txt' ), 'secondary', 'submit', false, $adtm_evidence_payload ? array() : array( 'disabled' => 'disabled' ) ); ?>
	</form>

	<?php if ( ! empty( $adtm_evidence_observations ) ) : ?>
		<table class="widefat striped" style="margin-top: 16px;">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'File', 'app-ads-txt' ); ?></th>
					<th><?php echo esc_html__( 'Line', 'app-ads-txt' ); ?></th>
					<th><?php echo esc_html__( 'Severity', 'app-ads-txt' ); ?></th>
					<th><?php echo esc_html__( 'Issue', 'app-ads-txt' ); ?></th>
					<th><?php echo esc_html__( 'Evidence', 'app-ads-txt' ); ?></th>
					<th><?php echo esc_html__( 'Suggested fix', 'app-ads-txt' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $adtm_evidence_observations as $observation ) : ?>
					<?php $adtm_evidence = isset( $observation['evidence'] ) && is_array( $observation['evidence'] ) ? $observation['evidence'] : array(); ?>
					<tr>
						<td><?php echo esc_html( isset( $adtm_evidence['file'] ) ? $adtm_evidence['file'] : adtm_evidence_object_file( $observation ) ); ?></td>
						<td><?php echo esc_html( isset( $adtm_evidence['line'] ) ? $adtm_evidence['line'] : '-' ); ?></td>
						<td><?php echo esc_html( adtm_evidence_severity_label( isset( $observation['severity'] ) ? $observation['severity'] : 'info' ) ); ?></td>
						<td><?php echo esc_html( adtm_evidence_issue_label( isset( $observation['check_id'] ) ? $observation['check_id'] : '' ) ); ?></td>
						<td><code><?php echo esc_html( adtm_evidence_raw_value( $adtm_evidence ) ); ?></code></td>
						<td><?php echo esc_html( isset( $observation['suggested_fix']['message'] ) ? $observation['suggested_fix']['message'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<?php
function adtm_evidence_file_summary( $observations ) {
	$summary = array(
		'ads.txt'     => array( 'errors' => 0, 'warnings' => 0 ),
		'app-ads.txt' => array( 'errors' => 0, 'warnings' => 0 ),
	);

	foreach ( $observations as $observation ) {
		$file = adtm_evidence_object_file( $observation );
		$severity = isset( $observation['severity'] ) ? $observation['severity'] : 'info';

		if ( ! isset( $summary[ $file ] ) ) {
			continue;
		}

		if ( in_array( $severity, array( 'medium', 'high', 'critical' ), true ) ) {
			$summary[ $file ]['errors']++;
		} elseif ( 'low' === $severity ) {
			$summary[ $file ]['warnings']++;
		}
	}

	return $summary;
}

function adtm_evidence_summary_label( $summary, $file ) {
	if ( ! isset( $summary[ $file ] ) ) {
		return __( 'Not scanned yet.', 'app-ads-txt' );
	}

	if ( 0 === $summary[ $file ]['errors'] && 0 === $summary[ $file ]['warnings'] ) {
		return __( 'No blocking issue found.', 'app-ads-txt' );
	}

	return sprintf(
		/* translators: 1: error count, 2: warning count. */
		__( '%1$d errors, %2$d warnings', 'app-ads-txt' ),
		$summary[ $file ]['errors'],
		$summary[ $file ]['warnings']
	);
}

function adtm_evidence_object_file( $observation ) {
	$object_type = isset( $observation['object_type'] ) ? $observation['object_type'] : '';

	if ( false !== strpos( $object_type, 'app_ads_txt' ) ) {
		return 'app-ads.txt';
	}

	return 'ads.txt';
}

function adtm_evidence_raw_value( $evidence ) {
	if ( isset( $evidence['raw'] ) ) {
		return $evidence['raw'];
	}

	if ( isset( $evidence['path'] ) ) {
		return $evidence['path'];
	}

	if ( isset( $evidence['variable'] ) ) {
		return $evidence['variable'];
	}

	if ( isset( $evidence['reason'] ) ) {
		return $evidence['reason'];
	}

	return '';
}

function adtm_evidence_issue_label( $check_id ) {
	$labels = array(
		'app_ads.ads_txt.invalid_line'                  => __( 'Invalid line format', 'app-ads-txt' ),
		'app_ads.app_ads_txt.invalid_line'              => __( 'Invalid line format', 'app-ads-txt' ),
		'app_ads.ads_txt.invalid_relationship'          => __( 'Invalid relationship field', 'app-ads-txt' ),
		'app_ads.app_ads_txt.invalid_relationship'      => __( 'Invalid relationship field', 'app-ads-txt' ),
		'app_ads.ads_txt.duplicate_record'              => __( 'Duplicate record', 'app-ads-txt' ),
		'app_ads.app_ads_txt.duplicate_record'          => __( 'Duplicate record', 'app-ads-txt' ),
		'app_ads.ads_txt.empty'                         => __( 'No seller record found', 'app-ads-txt' ),
		'app_ads.app_ads_txt.empty'                     => __( 'No seller record found', 'app-ads-txt' ),
		'app_ads.ads_txt.variable_unknown'              => __( 'Unknown variable', 'app-ads-txt' ),
		'app_ads.ads_txt.ownerdomain_missing'           => __( 'OWNERDOMAIN is not set', 'app-ads-txt' ),
		'app_ads.ads_txt.managerdomain_missing'         => __( 'MANAGERDOMAIN is not set', 'app-ads-txt' ),
		'app_ads.ads_txt.physical_file_conflict'        => __( 'Physical file conflict', 'app-ads-txt' ),
		'app_ads.app_ads_txt.physical_file_conflict'    => __( 'Physical file conflict', 'app-ads-txt' ),
		'app_ads.ads_txt.cross_host_redirect'           => __( 'Cross-host redirect', 'app-ads-txt' ),
		'app_ads.app_ads_txt.cross_host_redirect'       => __( 'Cross-host redirect', 'app-ads-txt' ),
		'app_ads.ads_txt.valid'                         => __( 'No blocking issue found', 'app-ads-txt' ),
		'app_ads.app_ads_txt.valid'                     => __( 'No blocking issue found', 'app-ads-txt' ),
	);

	return isset( $labels[ $check_id ] ) ? $labels[ $check_id ] : $check_id;
}

function adtm_evidence_severity_label( $severity ) {
	$labels = array(
		'critical' => __( 'Critical', 'app-ads-txt' ),
		'high'     => __( 'High', 'app-ads-txt' ),
		'medium'   => __( 'Medium', 'app-ads-txt' ),
		'low'      => __( 'Low', 'app-ads-txt' ),
		'info'     => __( 'Info', 'app-ads-txt' ),
	);

	return isset( $labels[ $severity ] ) ? $labels[ $severity ] : $severity;
}

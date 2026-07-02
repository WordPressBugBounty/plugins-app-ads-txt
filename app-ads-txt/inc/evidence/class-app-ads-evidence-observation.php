<?php
/**
 * Evidence observation value builder for App Ads TXT.
 *
 * @package App_Ads_Txt
 */

namespace Pagup\AppAdsTxt\Evidence;

if ( ! defined( 'ABSPATH' ) && ! defined( 'PAGUP_APP_ADS_EVIDENCE_TESTING' ) ) {
	exit;
}

class App_Ads_Evidence_Observation {
	const SCHEMA_VERSION = 'pagup-evidence-v0.1-app-ads';

	public static function create( $data ) {
		$observation = array(
			'schema_version'         => self::SCHEMA_VERSION,
			'observation_id'         => isset( $data['observation_id'] ) ? (string) $data['observation_id'] : '',
			'fingerprint'            => isset( $data['fingerprint'] ) ? (string) $data['fingerprint'] : '',
			'source_plugin'          => 'Ads.txt & App-ads.txt Manager',
			'source_plugin_slug'     => 'app-ads-txt',
			'source_plugin_version'  => isset( $data['source_plugin_version'] ) ? (string) $data['source_plugin_version'] : '',
			'check_id'               => isset( $data['check_id'] ) ? (string) $data['check_id'] : '',
			'category'               => 'ads_integrity',
			'object_type'            => isset( $data['object_type'] ) ? (string) $data['object_type'] : '',
			'object_id'              => isset( $data['object_id'] ) ? (string) $data['object_id'] : '',
			'url'                    => isset( $data['url'] ) ? $data['url'] : null,
			'severity'               => isset( $data['severity'] ) ? (string) $data['severity'] : 'info',
			'basis'                  => isset( $data['basis'] ) ? (string) $data['basis'] : 'unknown',
			'status'                 => isset( $data['status'] ) ? (string) $data['status'] : 'open',
			'evidence'               => isset( $data['evidence'] ) && is_array( $data['evidence'] ) ? $data['evidence'] : array(),
			'suggested_fix'          => isset( $data['suggested_fix'] ) && is_array( $data['suggested_fix'] ) ? $data['suggested_fix'] : array(),
			'is_reversible'          => isset( $data['is_reversible'] ) ? (bool) $data['is_reversible'] : true,
			'requires_external_call' => false,
			'privacy_classification' => 'site_config',
			'created_at'             => isset( $data['created_at'] ) ? (string) $data['created_at'] : gmdate( 'c' ),
			'last_seen_at'           => isset( $data['last_seen_at'] ) ? (string) $data['last_seen_at'] : gmdate( 'c' ),
		);

		if ( empty( $observation['fingerprint'] ) ) {
			$observation['fingerprint'] = self::fingerprint( $observation );
		}

		return $observation;
	}

	public static function fingerprint( $observation ) {
		$parts = array(
			isset( $observation['check_id'] ) ? $observation['check_id'] : '',
			isset( $observation['object_id'] ) ? $observation['object_id'] : '',
			isset( $observation['evidence'] ) ? self::json_encode( $observation['evidence'] ) : '',
		);

		return 'sha256:' . hash( 'sha256', implode( '|', $parts ) );
	}

	private static function json_encode( $data ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $data );
		}

		return json_encode( $data );
	}
}

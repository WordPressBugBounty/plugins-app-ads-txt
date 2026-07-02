<?php
/**
 * Minimal JSON export builder for App Ads TXT evidence.
 *
 * @package App_Ads_Txt
 */

namespace Pagup\AppAdsTxt\Evidence;

if ( ! defined( 'ABSPATH' ) && ! defined( 'PAGUP_APP_ADS_EVIDENCE_TESTING' ) ) {
	exit;
}

class App_Ads_Evidence_Export {
	public static function build_payload( $observations, $site_url, $plugin_version ) {
		$observations = is_array( $observations ) ? array_values( $observations ) : array();
		$started_at = gmdate( 'c' );

		return array(
			'schema_version' => App_Ads_Evidence_Observation::SCHEMA_VERSION,
			'scan_id'        => self::scan_id( $site_url, $plugin_version, $observations ),
			'site_url'       => (string) $site_url,
			'plugin_version' => (string) $plugin_version,
			'started_at'     => $started_at,
			'finished_at'    => $started_at,
			'summary'        => self::summarize( $observations ),
			'observations'   => $observations,
		);
	}

	public static function encode( $payload ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $payload, JSON_PRETTY_PRINT );
		}

		return json_encode( $payload, JSON_PRETTY_PRINT );
	}

	private static function summarize( $observations ) {
		$summary = array(
			'total'   => count( $observations ),
			'high'    => 0,
			'medium'  => 0,
			'low'     => 0,
			'info'    => 0,
			'unknown' => 0,
		);

		foreach ( $observations as $observation ) {
			$severity = isset( $observation['severity'] ) ? $observation['severity'] : 'info';
			if ( isset( $summary[ $severity ] ) ) {
				$summary[ $severity ]++;
			}

			if ( isset( $observation['basis'] ) && 'unknown' === $observation['basis'] ) {
				$summary['unknown']++;
			}
		}

		return $summary;
	}

	private static function scan_id( $site_url, $plugin_version, $observations ) {
		$parts = array(
			(string) $site_url,
			(string) $plugin_version,
			self::json_encode( $observations ),
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

<?php
/**
 * Local evidence scan storage for App Ads TXT.
 *
 * @package App_Ads_Txt
 */

namespace Pagup\AppAdsTxt\Evidence;

if ( ! defined( 'ABSPATH' ) && ! defined( 'PAGUP_APP_ADS_EVIDENCE_TESTING' ) ) {
	exit;
}

class App_Ads_Evidence_Store {
	const OPTION_NAME = 'app_ads_txt_evidence_v01';

	public static function get_latest() {
		if ( ! function_exists( 'get_option' ) ) {
			return null;
		}

		$payload = get_option( self::OPTION_NAME );

		return self::is_current_payload( $payload ) ? $payload : null;
	}

	public static function run( $options, $site_url, $plugin_version, $base_path ) {
		$options = is_array( $options ) ? $options : array();
		$validator = new App_Ads_Validator( $plugin_version );
		$observations = array();

		$ads_url = trailingslashit( $site_url ) . 'ads.txt';
		$app_ads_url = trailingslashit( $site_url ) . 'app-ads.txt';

		$observations = array_merge(
			$observations,
			$validator->validate_content( 'ads_txt', self::build_content( $options, 'ads-txt', 'ads-txt-custom' ), $ads_url )
		);
		$observations = array_merge(
			$observations,
			$validator->validate_content( 'app_ads_txt', self::build_content( $options, 'app-ads', 'app-ads-custom' ), $app_ads_url )
		);

		foreach ( array( 'ads_txt' => 'ads.txt', 'app_ads_txt' => 'app-ads.txt' ) as $file_type => $file_name ) {
			$path = trailingslashit( $base_path ) . $file_name;

			if ( file_exists( $path ) ) {
				$observations[] = $validator->physical_file_conflict_observation(
					$file_type,
					$path,
					'app_ads_txt' === $file_type ? $app_ads_url : $ads_url
				);
			}
		}

		$payload = App_Ads_Evidence_Export::build_payload( $observations, $site_url, $plugin_version );
		self::save( $payload );

		return $payload;
	}

	public static function save( $payload ) {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'add_option' ) || ! function_exists( 'update_option' ) ) {
			return false;
		}

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			return add_option( self::OPTION_NAME, $payload, '', 'no' );
		}

		return update_option( self::OPTION_NAME, $payload );
	}

	private static function build_content( $options, $records_key, $custom_key ) {
		$lines = array();

		if ( isset( $options[ $records_key ] ) && is_array( $options[ $records_key ] ) ) {
			foreach ( $options[ $records_key ] as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}

				$domain = isset( $record['domain'] ) ? trim( (string) $record['domain'] ) : '';
				$publisher = isset( $record['pub'] ) ? trim( (string) $record['pub'] ) : '';
				$relation = isset( $record['relation'] ) ? trim( (string) $record['relation'] ) : '';
				$cert = isset( $record['cert'] ) ? trim( (string) $record['cert'] ) : '';

				if ( '' === $domain && '' === $publisher && '' === $relation && '' === $cert ) {
					continue;
				}

				$line = $domain;

				if ( '' !== $publisher ) {
					$line .= ', ' . $publisher;
				}

				if ( '' !== $relation ) {
					$line .= ', ' . $relation;
				}

				if ( '' !== $cert ) {
					$line .= ', ' . $cert;
				}

				$lines[] = $line;
			}
		}

		if ( isset( $options[ $custom_key ] ) && '' !== trim( (string) $options[ $custom_key ] ) ) {
			$lines[] = trim( (string) $options[ $custom_key ] );
		}

		return implode( "\n", $lines );
	}

	private static function is_current_payload( $payload ) {
		return (
			is_array( $payload )
			&& isset( $payload['schema_version'] )
			&& App_Ads_Evidence_Observation::SCHEMA_VERSION === $payload['schema_version']
			&& isset( $payload['observations'] )
			&& is_array( $payload['observations'] )
		);
	}
}

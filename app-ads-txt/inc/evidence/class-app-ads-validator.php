<?php
/**
 * Local ads.txt / app-ads.txt parser and evidence validator.
 *
 * @package App_Ads_Txt
 */

namespace Pagup\AppAdsTxt\Evidence;

if ( ! defined( 'ABSPATH' ) && ! defined( 'PAGUP_APP_ADS_EVIDENCE_TESTING' ) ) {
	exit;
}

class App_Ads_Validator {
	private $plugin_version;

	private $recognized_variables = array(
		'OWNERDOMAIN',
		'MANAGERDOMAIN',
		'CONTACT',
		'SUBDOMAIN',
		'INVENTORYPARTNERDOMAIN',
	);

	public function __construct( $plugin_version ) {
		$this->plugin_version = (string) $plugin_version;
	}

	public function validate_content( $file_type, $content, $url ) {
		$file_type = $this->normalize_file_type( $file_type );
		$file_name = $this->file_name( $file_type );
		$prefix = $this->check_prefix( $file_type );
		$observations = array();
		$records = array();
		$seen = array();
		$has_ownerdomain = false;
		$has_managerdomain = false;
		$lines = $this->split_lines( $content );

		foreach ( $lines as $index => $raw_line ) {
			$line_number = $index + 1;
			$normalized_line = 1 === $line_number ? $this->strip_utf8_bom( $raw_line ) : $raw_line;
			$trimmed = trim( $this->strip_inline_comment( $normalized_line ) );

			if ( '' === $trimmed ) {
				continue;
			}

			if ( $this->is_variable_line( $trimmed ) ) {
				$variable_observation = $this->validate_variable( $file_name, $prefix, $line_number, $trimmed, $raw_line, $url );

				if ( 'OWNERDOMAIN' === $variable_observation['key'] ) {
					$has_ownerdomain = true;
				}

				if ( 'MANAGERDOMAIN' === $variable_observation['key'] ) {
					$has_managerdomain = true;
				}

				if ( isset( $variable_observation['observation'] ) ) {
					$observations[] = $variable_observation['observation'];
				}

				continue;
			}

			$record = $this->parse_record_line( $trimmed );

			if ( ! $record['valid'] ) {
				$observations[] = $this->observation(
					$prefix . '.invalid_line',
					'medium',
					'observed',
					$file_type . '_line',
					$file_name . ':' . $line_number,
					$url,
					array(
						'file'   => $file_name,
						'line'   => $line_number,
						'raw'    => $raw_line,
						'reason' => $record['reason'],
					),
					array(
						'action'  => 'edit_line',
						'message' => 'Use 3 or 4 comma-separated fields: domain, publisher ID, DIRECT or RESELLER, and optional certification ID.',
					)
				);
				continue;
			}

			$records[] = $record;

			if ( ! in_array( strtoupper( $record['relation'] ), array( 'DIRECT', 'RESELLER' ), true ) ) {
				$observations[] = $this->observation(
					$prefix . '.invalid_relationship',
					'medium',
					'observed',
					$file_type . '_line',
					$file_name . ':' . $line_number,
					$url,
					array(
						'file'     => $file_name,
						'line'     => $line_number,
						'raw'      => $raw_line,
						'relation' => $record['relation'],
					),
					array(
						'action'  => 'edit_line',
						'message' => 'Use DIRECT or RESELLER as the relationship field.',
					)
				);
			}

			$duplicate_key = $this->duplicate_key( $record );
			if ( isset( $seen[ $duplicate_key ] ) ) {
				$observations[] = $this->observation(
					$prefix . '.duplicate_record',
					'low',
					'derived',
					$file_type . '_line',
					$file_name . ':' . $line_number,
					$url,
					array(
						'file'           => $file_name,
						'line'           => $line_number,
						'raw'            => $raw_line,
						'first_seen_line' => $seen[ $duplicate_key ],
					),
					array(
						'action'  => 'remove_duplicate',
						'message' => 'Remove this duplicate record or keep only one normalized copy.',
					)
				);
			} else {
				$seen[ $duplicate_key ] = $line_number;
			}
		}

		if ( 0 === count( $records ) ) {
			$observations[] = $this->observation(
				$prefix . '.empty',
				'medium',
				'observed',
				$file_type . '_file',
				$file_name,
				$url,
				array(
					'file'          => $file_name,
					'useful_lines'  => 0,
					'total_lines'   => count( $lines ),
				),
				array(
					'action'  => 'add_record',
					'message' => 'Add at least one authorized seller record.',
				)
			);
		}

		if ( 'ads_txt' === $file_type && count( $records ) > 0 ) {
			if ( ! $has_ownerdomain ) {
				$observations[] = $this->observation(
					$prefix . '.ownerdomain_missing',
					'info',
					'derived',
					$file_type . '_file',
					$file_name,
					$url,
					array(
						'file'     => $file_name,
						'variable' => 'OWNERDOMAIN',
					),
					array(
						'action'  => 'consider_variable',
						'message' => 'Consider adding OWNERDOMAIN when it applies to your setup.',
					)
				);
			}

			if ( ! $has_managerdomain ) {
				$observations[] = $this->observation(
					$prefix . '.managerdomain_missing',
					'info',
					'derived',
					$file_type . '_file',
					$file_name,
					$url,
					array(
						'file'     => $file_name,
						'variable' => 'MANAGERDOMAIN',
					),
					array(
						'action'  => 'consider_variable',
						'message' => 'Consider adding MANAGERDOMAIN when a manager domain applies.',
					)
				);
			}
		}

		if ( 0 === $this->count_blocking_observations( $observations ) ) {
			$observations[] = $this->observation(
				$prefix . '.valid',
				'info',
				'derived',
				$file_type . '_file',
				$file_name,
				$url,
				array(
					'file'          => $file_name,
					'useful_lines'  => count( $records ),
					'total_lines'   => count( $lines ),
				),
				array(
					'action'  => 'none',
					'message' => 'No blocking issue was found in the local validation.',
				),
				'fixed'
			);
		}

		return $observations;
	}

	public function physical_file_conflict_observation( $file_type, $path, $url ) {
		$file_type = $this->normalize_file_type( $file_type );
		$file_name = $this->file_name( $file_type );

		return $this->observation(
			$this->check_prefix( $file_type ) . '.physical_file_conflict',
			'high',
			'observed',
			'physical_file',
			$file_name,
			$url,
			array(
				'file' => $file_name,
				'path' => $path,
			),
			array(
				'action'  => 'review_physical_file',
				'message' => 'A physical file can bypass the virtual file generated by the plugin.',
			)
		);
	}

	public function cross_host_redirect_observation( $file_type, $from_url, $to_url ) {
		$file_type = $this->normalize_file_type( $file_type );

		return $this->observation(
			$this->check_prefix( $file_type ) . '.cross_host_redirect',
			'medium',
			'unknown',
			$file_type . '_file',
			$this->file_name( $file_type ),
			$from_url,
			array(
				'from_url' => $from_url,
				'to_url'   => $to_url,
				'reason'   => 'cross_host_redirect_not_followed',
			),
			array(
				'action'  => 'review_redirect',
				'message' => 'Review the redirect target before allowing an external validation.',
			),
			'unknown'
		);
	}

	private function validate_variable( $file_name, $prefix, $line_number, $line, $raw_line, $url ) {
		$parts = explode( '=', $line, 2 );
		$key = strtoupper( trim( $parts[0] ) );
		$value = isset( $parts[1] ) ? trim( $parts[1] ) : '';
		$result = array( 'key' => $key );

		if ( '' === $key || '' === $value ) {
			$result['observation'] = $this->observation(
				$prefix . '.invalid_line',
				'medium',
				'observed',
				$this->object_type_from_file_name( $file_name ) . '_line',
				$file_name . ':' . $line_number,
				$url,
				array(
					'file'   => $file_name,
					'line'   => $line_number,
					'raw'    => $raw_line,
					'reason' => 'empty_variable_key_or_value',
				),
				array(
					'action'  => 'edit_line',
					'message' => 'Use KEY=VALUE with a non-empty key and value.',
				)
			);
			return $result;
		}

		if ( ! in_array( $key, $this->recognized_variables, true ) ) {
			$result['observation'] = $this->observation(
				$prefix . '.variable_unknown',
				'info',
				'observed',
				$this->object_type_from_file_name( $file_name ) . '_line',
				$file_name . ':' . $line_number,
				$url,
				array(
					'file'     => $file_name,
					'line'     => $line_number,
					'raw'      => $raw_line,
					'variable' => $key,
				),
				array(
					'action'  => 'review_variable',
					'message' => 'Review this variable before relying on it.',
				)
			);
		}

		return $result;
	}

	private function strip_inline_comment( $line ) {
		$comment_position = strpos( $line, '#' );

		if ( false === $comment_position ) {
			return $line;
		}

		return substr( $line, 0, $comment_position );
	}

	private function strip_utf8_bom( $line ) {
		if ( 0 === strncmp( $line, "\xEF\xBB\xBF", 3 ) ) {
			return substr( $line, 3 );
		}

		return $line;
	}

	private function split_lines( $content ) {
		$content = (string) $content;

		if ( '' === $content ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $content );

		if ( count( $lines ) > 1 && '' === end( $lines ) ) {
			array_pop( $lines );
		}

		return $lines;
	}

	private function is_variable_line( $line ) {
		return 1 === preg_match( '/^[A-Za-z][A-Za-z0-9_-]*\s*=/', $line );
	}

	private function parse_record_line( $line ) {
		$fields = array_map( 'trim', explode( ',', $line ) );
		$count = count( $fields );

		if ( $count < 3 || $count > 4 ) {
			return array(
				'valid'  => false,
				'reason' => 'expected_3_or_4_fields',
			);
		}

		if ( '' === $fields[0] || '' === $fields[1] || '' === $fields[2] ) {
			return array(
				'valid'  => false,
				'reason' => 'empty_required_field',
			);
		}

		$relation = $this->primary_field_value( $fields[2] );
		$cert = isset( $fields[3] ) ? $this->primary_field_value( $fields[3] ) : '';

		if ( '' === $relation ) {
			return array(
				'valid'  => false,
				'reason' => 'empty_required_field',
			);
		}

		return array(
			'valid'     => true,
			'domain'    => $fields[0],
			'publisher' => $fields[1],
			'relation'  => $relation,
			'cert'      => $cert,
		);
	}

	private function primary_field_value( $field ) {
		$parts = explode( ';', (string) $field, 2 );

		return trim( $parts[0] );
	}

	private function duplicate_key( $record ) {
		return strtolower( $record['domain'] ) . '|' . $record['publisher'] . '|' . strtoupper( $record['relation'] ) . '|' . strtolower( $record['cert'] );
	}

	private function observation( $check_id, $severity, $basis, $object_type, $object_id, $url, $evidence, $suggested_fix, $status = 'open' ) {
		return App_Ads_Evidence_Observation::create(
			array(
				'observation_id'        => $check_id . '.' . preg_replace( '/[^a-z0-9_\-:.]+/i', '_', $object_id ),
				'source_plugin_version' => $this->plugin_version,
				'check_id'              => $check_id,
				'object_type'           => $object_type,
				'object_id'             => $object_id,
				'url'                   => $url,
				'severity'              => $severity,
				'basis'                 => $basis,
				'status'                => $status,
				'evidence'              => $evidence,
				'suggested_fix'         => $suggested_fix,
			)
		);
	}

	private function count_blocking_observations( $observations ) {
		$count = 0;

		foreach ( $observations as $observation ) {
			if ( isset( $observation['severity'] ) && in_array( $observation['severity'], array( 'medium', 'high', 'critical' ), true ) ) {
				$count++;
			}
		}

		return $count;
	}

	private function normalize_file_type( $file_type ) {
		return 'app_ads_txt' === $file_type ? 'app_ads_txt' : 'ads_txt';
	}

	private function file_name( $file_type ) {
		return 'app_ads_txt' === $file_type ? 'app-ads.txt' : 'ads.txt';
	}

	private function check_prefix( $file_type ) {
		return 'app_ads_txt' === $file_type ? 'app_ads.app_ads_txt' : 'app_ads.ads_txt';
	}

	private function object_type_from_file_name( $file_name ) {
		return 'app-ads.txt' === $file_name ? 'app_ads_txt' : 'ads_txt';
	}
}

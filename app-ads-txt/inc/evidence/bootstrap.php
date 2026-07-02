<?php
/**
 * App Ads TXT evidence module bootstrap.
 *
 * @package App_Ads_Txt
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PAGUP_APP_ADS_EVIDENCE_TESTING' ) ) {
	exit;
}

require_once __DIR__ . '/class-app-ads-evidence-observation.php';
require_once __DIR__ . '/class-app-ads-validator.php';
require_once __DIR__ . '/class-app-ads-evidence-export.php';
require_once __DIR__ . '/class-app-ads-evidence-store.php';

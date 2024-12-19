<?php
namespace Stanford\Duster;
/** @var $module Duster */

/**
 * service page to retrieve DUSTER's cached STARR metadata for new-project Vue app via STARR-API
 */

$search_url = $module->getSystemSetting("starrapi-metadata-url") . '/cache/';
//$module->emLog($search_url);
$results = $module->starrApiGetRequest($search_url, 'ddp');
//$module->emLog($results);

// error handled by starrApiGetRequest
if ($results === null) {
  http_response_code(500);
} else if (array_key_exists('status', $results)) {
  http_response_code($results['status']);
}

echo json_encode($results);
?>

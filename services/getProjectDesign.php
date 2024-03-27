<?php
namespace Stanford\Duster;
/** @var $module Duster */

use RedCapDB;

/**
 * service page to get a design for an existing DUSTER project
 */

// TODO DELETE THIS PAGE

/* send STARR-API GET request to get dataset designs from postgres table */

// Retrieve the data URL that is saved in the config file
$dataset_design_url = $module->getSystemSetting("starrapi-dataset-design-url")
    . '/project' . '/' . SERVER_NAME . '/' . $_GET['pid'];

// send GET request to DUSTER's config route in STARR-API
$get_designs_results = $module->starrApiGetRequest($dataset_design_url, 'ddp');
if ($get_designs_results === null) {
  http_response_code(500);
  echo "STARR-API Get Request failed to retrieve dataset designs";
  exit();
} else if (array_key_exists('status', $get_designs_results)) {
  http_response_code($get_designs_results['status']);
  exit();
}
$module->emLog($get_designs_results);
echo json_encode($get_designs_results);
exit();
?>


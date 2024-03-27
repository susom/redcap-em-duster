<?php
namespace Stanford\Duster;
/** @var $module Duster */

use Project;
use Vanderbilt\REDCap\Classes\ProjectDesigner;
use Throwable;
use Exception;

require_once $module->getModulePath() . "classes/RedcapToStarrLinkConfig.php";

/**
 * service page to update a DUSTER project based on user edits
 */

/**
 * helper function that returns a CSV-formatted string of a REDCap field's configuration used by REDCap API
 * @param $field
 * @param string $form_name
 * @param $section_header
 * @return string
 */
function getFieldParams($field, string $form_name = "", string $section_header = ""):string {
    $params = "{$field['redcap_field_name']},$form_name,$section_header,{$field['redcap_field_type']},\"{$field['label']}\",";

    switch ($field['redcap_field_type']) {
        case 'checkbox':
        case 'radio':
        case 'calc':
            $params .= "\"{$field['redcap_options']}\",";
            break;
        default:
            $params .= ",";
            break;
    }

    if (array_key_exists('redcap_field_note', $field) && is_string($field['redcap_field_note']) === true) {
        $params .= "{$field['redcap_field_note']}";
    }
    $params .= ",";

    if ($field['value_type'] === 'datetime') {
        $params .= "datetime_seconds_ymd";
    } else if ($field['value_type'] === 'date') {
        $params .= "date_ymd";
    }
    $params .= ",,,";

    $params .= $field['phi'] === 't' ? "y" : "";
    $params .= ",,,,,,,";

    if (array_key_exists('field_annotation', $field)) {
        $params .= "\"{$field['field_annotation']}\"";
    }

    return $params . "\n";
}

/**
 * avoiding false-positive Psalm TaintedSSRF on $_POST['data']
 * @psalm-taint-escape ssrf
 */

/* get JSON from POST request */
$data = json_decode($_POST['data'], true);
$project_id = $data['redcap_project_id'];

/* Update REDCap project's data dictionary */
try {
    $config = $data['config'];
    $project_object = new Project($project_id, false); // not the same object returned by $module->getProject()
    $project_designer = new ProjectDesigner($project_object);
    $new_forms = [];

    $project_metadata = "\"Variable / Field Name\",\"Form Name\",\"Section Header\",\"Field Type\",\"Field Label\",\"Choices, Calculations, OR Slider Labels\",\"Field Note\",\"Text Validation Type OR Show Slider Number\",\"Text Validation Min\",\"Text Validation Max\",Identifier?,\"Branching Logic (Show field only if...)\",\"Required Field?\",\"Custom Alignment\",\"Question Number (surveys only)\",\"Matrix Group Name\",\"Matrix Ranking?\",\"Field Annotation\"\n";

    // REDCap Record ID
    $project_metadata .= "redcap_record_id,researcher_provided_information,,text,\"REDCap Record ID\",,,,,,,,,,,,,\n";

    // Researcher-Provided Identifier
    $project_metadata .= "mrn,researcher_provided_information,Identifiers,text,\"Medical Record Number (MRN)\",,\"8-digit number (including leading zeros, e.g., '01234567')\",,,,y,,,,,,,\n";

    // Researcher-Provided Dates/Datetimes
    if (array_key_exists('rp_info', $config)) {
        foreach ($config['rp_info']['rp_dates'] as $key => $date) {
            $rp_dates_header = $key === 0 ? 'Dates': '';
            $project_metadata .= getFieldParams($date, 'researcher_provided_information', $rp_dates_header);
        }
    }

    // Demographics
    if (array_key_exists('demographics', $config)) {
        foreach ($config['demographics'] as $demographic) {
            $project_metadata .= getFieldParams($demographic, 'demographics', '');
        }
    }

    // Data Collection Windows
    if (array_key_exists('collection_windows', $config)) {
        foreach ($config['collection_windows'] as $collection_window) {
            $form_name = $collection_window['form_name'];

            if (!$project_designer->formExists($form_name)) {
                $new_forms[$form_name] = $collection_window['label'];
            }

            // Timing
            $timing_header = 'Timing';
            if ($collection_window['type'] === 'repeating') {
                $repeat_field_params = array(
                    'label' => 'Unique Instance Token',
                    'redcap_field_name' => $form_name,
                    'redcap_field_type' => 'text',
                    'field_annotation' => ' @HIDDEN'
                );
                $project_metadata .= getFieldParams($repeat_field_params, $form_name, 'Timing');
                $timing_header = '';
            }
            $project_metadata .= getFieldParams($collection_window['timing']['start'], $form_name, $timing_header);
            $project_metadata .= getFieldParams($collection_window['timing']['end'], $form_name, '');

            // Closest Event Aggregation
            if (!empty($collection_window['event'])) {
                $project_metadata .= getFieldParams($collection_window['event'][0], $form_name, 'Closest Event Aggregation');
            }

            // Labs
            $labs = $collection_window['data']['labs'];
            if (!empty($labs)) {
                foreach ($labs as $key => $lab) {
                    $labs_header = $key === 0 ? 'Labs': '';
                    $project_metadata .= getFieldParams($lab, $form_name, $labs_header);
                }
            }

            // Vitals
            $vitals = $collection_window['data']['vitals'];
            if (!empty($vitals)) {
                foreach ($vitals as $key => $vital) {
                    $vitals_header = $key === 0 ? 'Vitals': '';
                    $project_metadata .=  getFieldParams($vital, $form_name, $vitals_header);
                }
            }

            // Outcomes
            $outcomes = $collection_window['data']['outcomes'];
            if (!empty($outcomes)) {
                foreach ($outcomes as $key => $outcome) {
                    $outcomes_header = $key === 0 ? 'Outcomes' : '';
                    $project_metadata .= getFieldParams($outcome, $form_name, $outcomes_header);
                }
            }

            // Scores
            foreach ($collection_window['data']['scores'] as $score) {
                foreach ($score['subscores'] as $subscore_key => $subscore) {
                    foreach ($subscore['dependencies'] as $dependency_key => $dependency) {
                        $outcomes_header = $subscore_key === 0 && $dependency_key === 0 ? $score['label'] : '';
                        $project_metadata .= getFieldParams($dependency, $form_name, $outcomes_header);
                    }
                    $project_metadata .= getFieldParams($subscore, $form_name, '');
                }
                $project_metadata .= getFieldParams($score, $form_name, '');
            }
        }
    }

    // REDCap API: Import Metadata (Data Dictionary)
    $token = $module->getUser()->getRights()['api_token'];
    $fields = array(
        'token'        => $token,
        'content'      => 'metadata',
        'format'       => 'csv',
        'data'         => $project_metadata,
        'returnFormat' => 'json'
    );

    $ch = curl_init();
    $api_url = APP_PATH_WEBROOT_FULL . "api/";

    $module->emDebug("Import Metadata POST Request to REDCap API URL $api_url");
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

    $redcap_api_response = curl_exec($ch);
    $redcap_api_response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $redcap_api_response_error = curl_error($ch);
    curl_close($ch);

    if ($redcap_api_response_code !== 200) {
        http_response_code(500);
        $msg = $module->handleError('DUSTER Error: Project Update',
            "Import Metadata POST Request to REDCap API failed.\n"
            . "REDCap API Response: $redcap_api_response\n"
            . "REDCap API Response Code: $redcap_api_response_code\n"
            . "REDCap API Response Error: $redcap_api_response_error\n");
        echo "fail_import";
        exit();
    }

    // Update form labels to "correct" labeling for each new data collection window
    foreach ($new_forms as $form_name => $label) {
        $module->query(
            '
                UPDATE redcap_metadata
                SET form_menu_description = ?
                WHERE 
                    project_id = ?
                    AND form_name = ?
                    AND form_menu_description IS NOT NULL
            ',
            [
                db_escape($label),
                intval($project_id),
                db_escape($form_name)
            ]
        );
    }

} catch (Throwable $ex) {
    http_response_code(400);
    $msg = $module->handleError('DUSTER Error: Project Update',  "Failed to correctly update the REDCap's project data dictionary for pid $project_id.", $ex );
    echo "fail_import";
    exit();
}

/* send POST request to DUSTER's config route in STARR-API
   updates config in postgres and generates new REDCap to STARR Link queries
*/

/**
 * @var string $config_data
 * @psalm-ignore-var
 */
// set up the POST body as an array
$config_data = array(
  'redcap_project_id' => intval($project_id),
  'redcap_user' => $module->getUser()->getUserName(),
  'config' => $data['config'],
  'design_config' => $data['design_config'],
  'linkinfo' => array('redcap_server_name' => SERVER_NAME),
  'is_update' => true
);

// Retrieve the data URL that is saved in the config file
$config_url = $module->getSystemSetting("starrapi-config-url");

// send POST request to DUSTER's config route in STARR-API
$save_config_results = $module->starrApiPostRequest($config_url, 'ddp', $config_data);
if ($save_config_results === null) {
    http_response_code(500);
  echo "fail_duster_config";
  exit();
} else if (array_key_exists('status', $save_config_results)) {
  http_response_code($save_config_results['status']);
  echo "fail_duster_config";
  exit();
}

/* Re-configure REDCap to STARR Link EM on REDCap project */
$module->emLog("Re-configuring REDCap to STARR Link EM on pid $project_id.");

if ($save_config_results['success'] && !empty($save_config_results['rcToStarrLinkConfig'])) {
  $rctostarr_config = new RedcapToStarrLinkConfig($project_id, $module);
  $rctostarr_config->removeRedcapToStarrLinkEmSettings();
  $rctostarr_config->configureRedcapToStarrLink($save_config_results);
  $module->emDebug(APP_PATH_WEBROOT_FULL . substr(APP_PATH_WEBROOT, 1) . "ProjectSetup/index.php?pid=$project_id");
  http_response_code(200);
  echo APP_PATH_WEBROOT_FULL . substr(APP_PATH_WEBROOT, 1) . "ProjectSetup/index.php?pid=$project_id";
  exit();
} else {
  http_response_code(500);
  $msg = $module->handleError("DUSTER Error: Project Update",  "Could not retrieve RtoS configuration for project_id $project_id. Error:" . $save_config_results['error']);
  echo "fail_rtosl_config";
  exit();
}

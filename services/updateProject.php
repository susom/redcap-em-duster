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
 * helper function that returns an associated array of a REDCap field's configuration as part of a larger array used by REDCap API
 * @param $field
 * @param string $form_name
 * @param string $section_header
 * @return array
 */
function getFieldParams($field, string $form_name = "", string $section_header = ""):array {
    return array(
        'field_name' => $field['redcap_field_name'],
        'form_name' => $form_name,
        'section_header' => $section_header,
        'field_type' => $field['redcap_field_type'],
        'field_label' => $field['label'],
        'select_choices_or_calculations' => in_array($field['redcap_field_type'], ['checkbox', 'radio', 'calc'], true) ? $field['redcap_options'] : "",
        'field_note' => array_key_exists('redcap_field_note', $field) && is_string($field['redcap_field_note']) === true ? $field['redcap_field_note'] : "",
        'text_validation_type_or_show_slider_number' => $field['value_type'] === 'datetime' ? "datetime_seconds_ymd" : ($field['value_type'] === 'date' ? "date_ymd" : ""),
        'text_validation_min' => "",
        'text_validation_max' => "",
        'identifier' => $field['phi'] === "t" ? "y" : "",
        'branching_logic' => "",
        'required_field' => "",
        'custom_alignment' => "",
        'question_number' => "",
        'matrix_group_name' => "",
        'matrix_ranking' => "",
        'field_annotation' => $field['field_annotation'] ? : ""
    );
}

$get_field_params = function($field, string $form_name = "", string $section_header = ""):array {
    return getFieldParams($field, $form_name, $section_header);
};

/**
 * avoiding false-positive Psalm TaintedSSRF on $_POST['data']
 * @psalm-taint-escape ssrf
 */

/* get JSON from POST request */
$data = json_decode($_POST['data'], true);
$project_id = $data['redcap_project_id'];

/* Update REDCap project's data dictionary */
try {
    $api_url = $module->getRedcapUrl("api");
    $config = $data['config'];
    $module->emDebug($config);
    $project_object = new Project($project_id, false); // not the same object returned by $module->getProject()
    $project_designer = new ProjectDesigner($project_object);
    $repeatable_forms = [];
    $new_forms = [];

    // REDCap API: Export Metadata (Data Dictionary)
    // get the current data dictionary
    $token = $module->getUser()->getRights()['api_token'];
    $fields = array(
        'token'        => $token,
        'content'      => 'metadata',
        'format'       => 'json'
    );

    $ch = curl_init();
    $module->emLog("Export Metadata POST Request to REDCap API URL $api_url");
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
            "Export Metadata POST Request to REDCap API failed.\n"
            . "REDCap API Response: $redcap_api_response\n"
            . "REDCap API Response Code: $redcap_api_response_code\n"
            . "REDCap API Response Error: $redcap_api_response_error\n");
        echo "fail_import";
        exit();
    }

    $old_metadata = json_decode($redcap_api_response, true);
    $project_metadata = array();
    $rp_info = array();
    $demographics = array();

    // REDCap Record ID
    $rrid = array(
        'label' => "REDCap Record ID",
        'redcap_field_name' => "redcap_record_id",
        'redcap_field_type' => "text"
    );
    $rp_info[] = getFieldParams($rrid, "researcher_provided_information");

    // Researcher-Provided Identifier
    $mrn_params = array(
        'label' => "Medical Record Number (MRN)",
        'redcap_field_name' => "mrn",
        'redcap_field_type' => "text",
        'redcap_field_note' => "8-digit number (including leading zeros, e.g., '01234567') or 10-digit number (no leading zeros)",
        'phi' => "t"
    );
    $rp_info[] = getFieldParams($mrn_params, "researcher_provided_information", "Identifiers");

    // Researcher-Provided Dates/Datetimes
    if (array_key_exists('rp_info', $config)) {
        foreach ($config['rp_info']['rp_dates'] as $key => $date) {
            $rp_dates_header = $key === 0 ? 'Dates': '';
            $rp_info[] = getFieldParams($date, "researcher_provided_information", $rp_dates_header);
        }
    }

    // add non-DUSTER metadata in Researcher-Provided Info from current data dictionary to the new data dictionary
    $rp_field_names = array_column($rp_info, 'field_name');
    $old_rp_info = array_filter($old_metadata, function($field) use($rp_field_names) {
        return $field['form_name'] === "researcher_provided_information"
            && !in_array($field['field_name'], $rp_field_names);
    });
    $rp_info = array_merge($rp_info, $old_rp_info);

    // add Researcher-Provided Info to the new data dictionary
    $project_metadata = array_merge($project_metadata, $rp_info);

    // Demographics
    if (array_key_exists('demographics', $config)) {
        foreach ($config['demographics'] as $demographic) {
            $demographics[] = getFieldParams($demographic, "demographics");
        }
    }

    // add non-DUSTER metadata in Demographics from current data dictionary to the new data dictionary
    $demographics_field_names = array_column($demographics, 'field_name');
    $old_demographics = array_filter($old_metadata, function($field) use($demographics_field_names) {
        return $field['form_name'] === "demographics"
            && !in_array($field['field_name'], $demographics_field_names);
    });
    $demographics = array_merge($demographics, $old_demographics);

    // add Demographics to the new data dictionary
    $project_metadata = array_merge($project_metadata, $demographics);

    // Data Collection Windows
    if (array_key_exists('collection_windows', $config)) {
        foreach ($config['collection_windows'] as $collection_window) {
            $cw = array();
            $form_name = $collection_window['form_name'];

            if (!$project_designer->formExists($form_name)) {
                $new_forms[$form_name] = $collection_window['label'];
            }

            // Timing
            $cw[] = getFieldParams($collection_window['timing']['start'], $form_name, "Timing");
            $cw[] = getFieldParams($collection_window['timing']['end'], $form_name);
            if ($collection_window['type'] === 'repeating') {
                $repeatable_forms[] = $form_name;

                $repeat_field_params = array(
                    'label' => 'Unique Instance Token',
                    'redcap_field_name' => $form_name,
                    'redcap_field_type' => 'text',
                    'field_annotation' => ' @HIDDEN'
                );
                $cw[] = getFieldParams($repeat_field_params, $form_name, "Repeat Instance");
                $cw[] = getFieldParams($collection_window['timing']['repeat_interval']['start_instance'], $form_name);
                $cw[] = getFieldParams($collection_window['timing']['repeat_interval']['end_instance'], $form_name);
            }

            // Closest Event Aggregation
            if (!empty($collection_window['event'])) {
                $cw[] = getFieldParams($collection_window['event'][0], $form_name, "Closest Event Aggregation");
            }

            // Labs
            $labs = $collection_window['data']['labs'];
            if (!empty($labs)) {
                foreach ($labs as $key => $lab) {
                    $labs_header = $key === 0 ? 'Labs' : '';
                    $cw[] = getFieldParams($lab, $form_name, $labs_header);
                }
            }

            // User-Defined Labs
            $ud_labs = $collection_window['data']['ud_labs'];
            if (!empty($ud_labs)) {
                $ud_labs_fields = array_merge(...array_column($ud_labs, 'fields'));
                $num_fields = count($ud_labs_fields);
                $ud_form_name_arr = array_fill(0, $num_fields, $form_name);
                $ud_section_header_arr = array_merge(['User-Defined Labs'], array_fill(0, $num_fields - 1, ''));
                $cw = array_merge($cw, array_map($get_field_params, $ud_labs_fields, $ud_form_name_arr, $ud_section_header_arr));
            }

            // Vitals
            $vitals = $collection_window['data']['vitals'];
            if (!empty($vitals)) {
                foreach ($vitals as $key => $vital) {
                    $vitals_header = $key === 0 ? 'Vitals': '';
                    $cw[] =  getFieldParams($vital, $form_name, $vitals_header);
                }
            }

            // Medications
            $medications = $collection_window['data']['medications'];
            if (!empty($medications)) {
                $medications_fields = array_merge(...array_column($medications, 'fields'));
                $num_fields = count($medications_fields);
                $medications_form_name_arr = array_fill(0, $num_fields, $form_name);
                $medications_section_header_arr = array_merge(['Medications'], array_fill(0, $num_fields - 1, ''));
                $cw = array_merge($cw, array_map($get_field_params, $medications_fields, $medications_form_name_arr, $medications_section_header_arr));
            }

            // Outcomes
            $outcomes = $collection_window['data']['outcomes'];
            if (!empty($outcomes)) {
                foreach ($outcomes as $key => $outcome) {
                    $outcomes_header = $key === 0 ? 'Outcomes' : '';
                    $cw[] = getFieldParams($outcome, $form_name, $outcomes_header);
                }
            }

            // Scores
            foreach ($collection_window['data']['scores'] as $score) {
                foreach ($score['subscores'] as $subscore_key => $subscore) {
                    foreach ($subscore['dependencies'] as $dependency_key => $dependency) {
                        $outcomes_header = $subscore_key === 0 && $dependency_key === 0 ? $score['label'] : '';
                        $cw[] = getFieldParams($dependency, $form_name, $outcomes_header);
                    }
                    $cw[] = getFieldParams($subscore, $form_name);
                }
                $cw[] = getFieldParams($score, $form_name);
            }

            // add non-DUSTER metadata in this collection window from current data dictionary to the new data dictionary
            $cw_field_names = array_column($cw, 'field_name');
            $old_cw = array_filter($old_metadata, function($field) use($cw_field_names, $form_name) {
                return $field['form_name'] === $form_name
                    && !in_array($field['field_name'], $cw_field_names);
            });
            $cw = array_merge($cw, $old_cw);

            // add this collection window to the new data dictionary
            $project_metadata = array_merge($project_metadata, $cw);
        }
    }

    // add non-DUSTER metadata in other forms from current data dictionary to the new data dictionary
    $new_field_names = array_column($project_metadata, 'field_name');
    $non_duster_form_fields = array_filter($old_metadata, function($field) use($new_field_names) {
        return !in_array($field['field_name'], $new_field_names);
    });
    $project_metadata = array_merge($project_metadata, $non_duster_form_fields);

    // REDCap API: Import Metadata (Data Dictionary)
    $token = $module->getUser()->getRights()['api_token'];
    $fields = array(
        'token'        => $token,
        'content'      => 'metadata',
        'format'       => 'json',
        'data'         => json_encode($project_metadata),
        'returnFormat' => 'json'
    );

    $ch = curl_init();

    $module->emLog("Import Metadata POST Request to REDCap API URL $api_url using the following metadata:");
    $module->emLog($project_metadata);
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

    // Make forms repeatable
    foreach ($repeatable_forms as $form_name) {
        $project_designer->makeFormRepeatable($form_name, $module->getEventId(), NULL);
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

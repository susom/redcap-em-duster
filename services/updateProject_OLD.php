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
 * helper function that formats the field parameters needed for ProjectDesigner::createField()
 * @param $field
 * @return array
 */
function getFieldParams($field):array {
    global $module;
    $module->emDebug($field);
    $params = array(
        'field_label' => $field['label'],
        'field_name' => $field['redcap_field_name'],
        'field_phi' => $field['phi'] === 't',
        'field_type' => $field['redcap_field_type'],
        'field_note' => $field['redcap_field_note']
    );

    switch ($field['redcap_field_type']) {
        case 'checkbox':
        case 'radio':
            $module->emDebug("Entered case checkbox or radio");
            $params['element_enum'] = str_replace('|', '\n', $field['redcap_options']);
            break;
        case 'calc':
            $module->emDebug("Entered case calc");
            $params['element_enum'] = $field['redcap_options'];
            break;
        case 'text':
            switch ($field['value_type']) {
                case 'datetime':
                    $params['val_type'] = 'datetime_seconds_ymd';
                    break;
                case 'date':
                    $params['val_type'] = 'date_ymd';
                    break;
                default:
                    break;
            }
            break;
        default: // 'yesno'
            break;
    }
    $module->emDebug($params);
    return $params;
}

/**
 * avoiding false-positive Psalm TaintedSSRF on $_POST['data']
 * @psalm-taint-escape ssrf
 */

/**
 * TODO
 * remove lines of code for removing user and project
 * update REDCap project's fields and instruments
 * update DUSTER config in STARR-API
 * update REDCap to STARR Link queries
 * update REDCap to STARR Link config on REDCap side
 */

/* get JSON from POST request */
$data = json_decode($_POST['data'], true);
$project_id = $data['redcap_project_id'];

/* TODO
 0. safety checks, like if the project is in draft mode
 1. get all fields and instruments from REDCap (or maybe we don't need to do this up front and instead iteratively get/check as we try to add new fields)
 2. for all Researcher-Provided dates/datetimes in the new config, add a REDCap field if it doesn't already exist
 3. for all demographics in the new config, add a REDCap field if it doesn't already exist (in order?)
 4. for all data collection windows in the new config:
 4. a. if the data collection window is new, add the form
    b. if the "closest to" is new, add it
    c. for each category of data, add the section header and fields that don't exist and need to. also, when does order of the fields matter?
 */

/* Update REDCap project's data dictionary */
try {
    $config = $data['config'];
    $project_object = new Project($project_id, false); // not the same object returned by $module->getProject()
    $project_designer = new ProjectDesigner($project_object);

    // Researcher-Provided Dates/Datetimes
    if (array_key_exists('rp_info', $config)) {
        $rp_fields_current = $module->getFieldNames('researcher_provided_information');
        foreach ($config['rp_info']['rp_dates'] as $date) {
            if (in_array($date['redcap_field_name'], $rp_fields_current) === false) {
                // add $date to form for RP Info
                // "label":"Study Enrollment Date","redcap_field_name":"enroll_date","redcap_field_type":"text","value_type":"date","phi":"t"
                $date_params = getFieldParams($date);
                $project_designer->createField('researcher_provided_information', $date_params, '', false, '', NULL, NULL, '');
            }
        }
    }

    // Demographics
    if (array_key_exists('demographics', $config)) {
        $had_demographics = false;
        if ($project_designer->formExists('demographics')) {
            $had_demographics = true;
        } else {
            $project_designer->createForm('demographics', 'researcher_provided_information', 'Demographics');
        }
        $demographics_fields_current = $module->getFieldNames('demographics');
        foreach ($config['demographics'] as $demographic) {
            if ($had_demographics === false || in_array($demographic['redcap_field_name'], $demographics_fields_current) === false) {
                $demographic_params = getFieldParams($demographic);
                if ($demographic['value_type'] === 'datetime') {
                    $demographic_params['val_type'] = 'datetime_seconds_ymd';
                } else if ($demographic['value_type'] === 'date') {
                    $demographic_params['val_type'] = 'date_ymd';
                }
                $project_designer->createField('demographics', $demographic_params, '', false, '', NULL, NULL, '');
            }
        }
    }

    // Data Collection Windows
    if (array_key_exists('collection_windows', $config)) {
        foreach ($config['collection_windows'] as $collection_window) {
            $form_name = $collection_window['form_name'];
            $had_window = false;
            if ($project_designer->formExists($form_name)) {
                $had_window = true;
            } else {
                // $project_designer->createForm($form_name, NULL, $collection_window['label']);
                $project_designer->createForm($form_name, NULL, NULL);
                // TODO db update form menu description i.e., form label
            }
            $collection_window_fields_current = $module->getFieldNames($form_name);

            // Timing
            if ($had_window === false) {
                $start_timing = $collection_window['timing']['start'];
                $start_params = getFieldParams($start_timing);
                $project_designer->createField($form_name, $start_params, '', false, '', NULL, NULL, '');
                $project_designer->createSectionHeader('Timing', $start_params['field_name']);

                $end_timing = $collection_window['timing']['end'];
                $end_params = getFieldParams($end_timing);

                //$project_designer->createField($form_name, $start_params, '', false, '', NULL, NULL, '');
                $project_designer->createField($form_name, $end_params, '', false, '', NULL, NULL, '');
                //$project_designer->createField($form_name, $start_params, '', false, '', NULL, NULL, '');
                //$project_designer->createField($form_name, $end_params, $start_params['field_name'], false, '', NULL, NULL, '');

                // $project_designer->createField($form_name, $start_params, $end_params['field_name'], false, '', NULL, NULL, '');

                if ($collection_window['type'] === 'repeating') {
                    $project_designer->makeFormRepeatable($form_name, $module->getEventId(), $collection_window['label']);
                    $repeat_field_params = array(
                        'field_label' => 'Unique Instance Token',
                        'field_name' => $form_name,
                        'field_type' => 'text',
                        'field_annotation' => '@HIDDEN'
                    );
                    // $project_designer->createField($form_name, $repeat_field_params, '', false, '', NULL, NULL, '');
                    $project_designer->createField($form_name, $repeat_field_params, $start_params['field_name'], false, '', NULL, NULL, '');
                    $project_designer->moveSectionHeader($start_params['field_name'], $repeat_field_params['field_name']);
                }
            }

            // Closest Event Aggregation
            if (!empty($collection_window['event']) && in_array($collection_window['event']['redcap_field_name'], $collection_window_fields_current) === false) {
                $closest_event = $collection_window['event'][0];
                $event_params = getFieldParams($closest_event);
                $module->emLog($event_params);
                $project_designer->createField($form_name, $event_params, '', false, '', NULL, NULL, '');
                // $project_designer->moveFieldAfterField($event_params['field_name'], $collection_window['timing']['end']['redcap_field_name']);
                $project_designer->createSectionHeader('Closest Event Aggregation', $event_params['field_name']);
            }

            // Labs
            $labs = $collection_window['data']['labs'];
            if (!empty($labs)) {
                $first_lab = NULL;
                $i = 0;
                do {
                    if (in_array($labs[$i]['redcap_field_name'], $collection_window_fields_current)) {
                        $first_lab = $labs[$i]['redcap_field_name'];
                    }
                    $i++;
                } while ($first_lab === NULL && $i < count($labs));

                if ($first_lab !== NULL) {
                    foreach ($labs as $lab) {
                        if (in_array($lab['redcap_field_name'], $collection_window_fields_current) === false) {
                            $lab_params = getFieldParams($lab);
                            $project_designer->createField($form_name, $lab_params, $first_lab, false, '', NULL, NULL, '');
                            // $project_designer->moveSectionHeader($first_lab, $lab_params['field_name']);
                            $first_lab = $lab_params['field_name'];
                        }
                    }
                } else {
                    foreach ($labs as $lab) {
                        if (in_array($lab['redcap_field_name'], $collection_window_fields_current) === false) {
                            $lab_params = getFieldParams($lab);
                            $project_designer->createField($form_name, $lab_params, '', false, '', NULL, NULL, '');
                        }
                    }
                    $project_designer->createSectionHeader('Labs', $labs[0]['redcap_field_name']);
                }
            }

            // Vitals
            $vitals = $collection_window['data']['vitals'];
            if (!empty($vitals)) {
                $first_vital = NULL;
                $i = 0;
                do {
                    if (in_array($vitals[$i]['redcap_field_name'], $collection_window_fields_current)) {
                        $first_vital = $vitals[$i]['redcap_field_name'];
                    }
                    $i++;
                } while ($first_vital === NULL && $i < count($vitals));

                if ($first_vital !== NULL) {
                    foreach ($vitals as $vital) {
                        if (in_array($vital['redcap_field_name'], $collection_window_fields_current) === false) {
                            $vital_params = getFieldParams($vital);
                            $project_designer->createField($form_name, $vital_params, $first_vital, false, '', NULL, NULL, '');
                            // $project_designer->moveSectionHeader($first_vital, $vital_params['field_name']);
                            $first_vital = $vital_params['field_name'];
                        }
                    }
                } else {
                    foreach ($vitals as $vital) {
                        if (in_array($vital['redcap_field_name'], $collection_window_fields_current) === false) {
                            $vital_params = getFieldParams($vital);
                            $project_designer->createField($form_name, $vital_params, '', false, '', NULL, NULL, '');
                        }
                    }
                    $project_designer->createSectionHeader('Vitals', $vitals[0]['redcap_field_name']);
                }
            }

            // Outcomes
            $outcomes = $collection_window['data']['outcomes'];
            if (!empty($outcomes)) {
                $first_outcome = NULL;
                $i = 0;
                do {
                    if (in_array($outcomes[$i]['redcap_field_name'], $collection_window_fields_current)) {
                        $first_outcome = $outcomes[$i]['redcap_field_name'];
                    }
                    $i++;
                } while ($first_outcome === NULL && $i < count($outcomes));

                if ($first_outcome !== NULL) {
                    foreach ($outcomes as $outcome) {
                        if (in_array($outcome['redcap_field_name'], $collection_window_fields_current) === false) {
                            $outcome_params = getFieldParams($outcome);
                            $project_designer->createField($form_name, $outcome_params, $first_outcome, false, '', NULL, NULL, '');
                            // $project_designer->moveSectionHeader($first_lab, $outcome_params['field_name']);
                            $first_outcome = $outcome_params['field_name'];
                        }
                    }
                } else {
                    foreach ($outcomes as $outcome) {
                        if (in_array($outcome['redcap_field_name'], $collection_window_fields_current) === false) {
                            $outcome_params = getFieldParams($outcome);
                            $project_designer->createField($form_name, $outcome_params, '', false, '', NULL, NULL, '');
                        }
                    }
                    $project_designer->createSectionHeader('Outcomes', $outcomes[0]['redcap_field_name']);
                }
            }

            // Scores
            foreach ($collection_window['data']['scores'] as $score) {
                if (in_array($score['redcap_field_name'], $collection_window_fields_current) === false) {
                    foreach ($score['subscores'] as $subscore) {
                        foreach ($subscore['dependencies'] as $dependency) {
                            $dependency_params = getFieldParams($dependency);
                            $project_designer->createField($form_name, $dependency_params, '', false, '', NULL, NULL, '');
                        }
                        $subscore_params = getFieldParams($subscore);
                        $project_designer->createField($form_name, $subscore_params, '', false, '', NULL, NULL, '');
                    }
                    $score_params = getFieldParams($score);
                    $project_designer->createField($form_name, $score_params, '', false, '', NULL, NULL, '');
                    $project_designer->createSectionHeader($score['label'], $score['subscores'][0]['dependencies'][0]['redcap_field_name']);
                }
            }
        }
    }
} catch (Throwable $ex) {
    // TODO
    http_response_code(400);
    $msg = $module->handleError('DUSTER Error: Project Update',  "Failed to correctly update the REDCap's project data dictionary for pid $project_id.", $ex );
    echo "Failed to correctly update the REDCap's project data dictionary for pid $project_id.";
  // print "Error: Failed to create project. " . $msg;
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
  // $module->removeUser(USERID);
  http_response_code(500);
  echo "fail_project_post";
  exit();
} else if (array_key_exists('status', $save_config_results)) {
  // $module->removeUser(USERID);
  http_response_code($save_config_results['status']);
  echo "fail_project_post";
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
  // $module->removeUser(USERID);
  http_response_code(500);
  $msg = $module->handleError("DUSTER Error: Project Update",  "Could not retrieve RtoS configuration for project_id $project_id. Error:" . $save_config_results['error']);
  echo "Could not retrieve RtoS configuration for project_id $project_id. Error:" . $save_config_results['error'];
  //  print "Error: A new REDCap project was created (pid $project_id), but DUSTER's data queries for this project failed to set up. " . $msg;
  exit();
}

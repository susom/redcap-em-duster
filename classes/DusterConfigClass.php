<?php

namespace Stanford\Duster;
/** @var $module Duster */

/**
 * Class: DusterDataClass
 * This class handles the API to retrieve the duster config and validate it against the redcap data dictionary.
 */

use REDCap;

class DusterConfigClass
{
    private $project_id, $duster_config, $module;
    private $rp_info_form = array('form_name' => 'rp_info', 'form_label' => 'Researcher-Provided Information');
    private $demographics_form = array('form_name' => 'demographics', 'form_label' => 'Demographics');

    public function __construct($pid, $module)
    {
        $this->project_id = $pid;
        $this->module = $module;
    }

    public function loadConfig()
    {
        // build and send GET request to config webservice
        $config_url = $this->module->getSystemSetting("starrapi-config-url");
        // add a '/' at the end of the url if it's not there
        $config_url = $config_url .
            ((substr($config_url, -1) === '/') ? "" : "/") . SERVER_NAME . '/' . $this->project_id
            . '?redcap_user=' . $this->module->getUser()->getUserName();
        $this->duster_config = $this->module->starrApiGetRequest($config_url, 'ddp');
    }

    public function loadDesignConfig () {
      // build and send GET request to config webservice
      $config_url = $this->module->getSystemSetting("starrapi-config-url");
      // add a '/' at the end of the url if it's not there
      $config_url = $config_url .
        ((substr($config_url, -1) === '/') ? "" : "/") . 'design/' . SERVER_NAME . '/' . $this->project_id
        . '?redcap_user=' . $this->module->getUser()->getUserName();
      $this->design_config = $this->module->starrApiGetRequest($config_url, 'ddp');
    }

    public function getDusterConfig()
    {
        if ($this->duster_config == null) {
            $this->loadConfig();
        }
        return $this->duster_config;
    }

    public function getDesignConfig() {
      if ($this->design_config == null) {
        $this->loadDesignConfig();
      }
      return $this->design_config['design_config'];
    }

    public function setDusterConfig($config)
    {
        $this->duster_config = $config;
    }

    /**
     * Fetch researcher provided data.  Returns JsonObject
     * { "redcap_project_id":
     *   "missing_vars":
     *   "missing_data":
     *   "rp_data":
     * }
     * @return Json encoded string
     */
    public function getDusterRequestObject()
    {
        if (empty($this->duster_config)) {
            $this->getDusterConfig();
        }
        if (!isset($this->duster_config['status']) || $this->duster_config['status'] === 200) {
            $rp_data['redcap_project_id'] = intval($this->project_id);
            $rp_data['missing_fields'] = $this->getMissingRedcapFields();

            if (empty($rp_data['missing_fields'])) {
                // add rp_identifiers to request fields
                foreach ($this->duster_config['rp_info']['rp_identifiers'] as $identifier) {
                    $rp_fields[] = $identifier['redcap_field_name'];
                }
                // add rp_dates to request fields
                foreach ($this->duster_config['rp_info']['rp_dates'] as $rp_dates) {
                    $rp_fields[] = $rp_dates['redcap_field_name'];
                }
                //$this->module->emDebug('$request_fields: ' . print_r($rp_fields, true));
                $records = REDCap::getData('array', null, $rp_fields);

                // populate $rp_data with data in $records
                foreach ($records as $record_id => $record) {
                    $has_missing = false;
                    $request_record = [];
                    $request_record['redcap_record_id'] = strval($record_id);
                    $record = $record[$this->module->getEventId()];
                    // add rp_identifiers
                    foreach ($this->duster_config['rp_info']['rp_identifiers'] as $identifier) {
                        $request_record[$identifier['redcap_field_name']] = $record[$identifier['redcap_field_name']];
                        $has_missing = $has_missing || empty($request_record[$identifier['redcap_field_name']]);
                    }
                    //$request_record['dates'] = [];
                    // add rp_dates
                    $date_obj = [];
                    foreach ($this->duster_config['rp_info']['rp_dates'] as $rp_dates) {
                        $date_obj['label'] = $rp_dates['label'];
                        $date_obj['redcap_field_name'] = $rp_dates['redcap_field_name'];
                        $date_obj['value'] = $record[$rp_dates['redcap_field_name']];
                        $date_obj['type'] = $rp_dates['value_type'];
                        $request_record['dates'][] = $date_obj;
                        $has_missing = $has_missing || empty($date_obj['value']);
                    }
                    if ($has_missing) {
                        $rp_data['missing_data'][] = $request_record;
                    }
                    // add everything to rp_data including missing
                    $rp_data['rp_data'][] = $request_record;
                }
            }
            return $rp_data;
        } else {
            $this->module->emDebug("PID ". $this->module->getProjectId() . ' Unable to retrieve DUSTER config from starr-api');
            $return_obj['status'] = 500;
            $return_obj['message'] = 'Unable to retrieve DUSTER config.';
            return $return_obj;
        }

    }

    /*returns list of fields that are in duster config but not in redcap config
     @return array($field_name=>array("label"=>,"redcap_field_name"=>,"form_name"=>,"form_label"=>,"format"=>))
     */
    public function getMissingRedcapFields()
    {
        // rp_info
        $duster_fields = $this->getDusterFields(
            $this->duster_config['rp_info'], $this->rp_info_form);
        // demographics
        $duster_fields = array_merge($duster_fields, $this->getDusterFields(
            $this->duster_config['demographics'], $this->demographics_form));
        // collection windows
        $forms = $this->getDusterForms($this->duster_config['collection_windows']);
        foreach ($this->duster_config['collection_windows'] as $index => $collection_window) {
            $duster_fields = array_merge($duster_fields, $this->getDusterFields($collection_window,
                $forms[$index]));
        }
        //$this->module->emDebug("DUSTER FIELDS: " . print_r($duster_fields, true));

        $missing_fields = [];
        $missing_names = array_diff(array_keys($duster_fields), REDCap::getFieldNames());
        //$this->module->emDebug("MISSING NAMES: " . print_r($missing_names, true));

        foreach ($missing_names as $field_name) {
            $missing_fields[] = $duster_fields[$field_name];
        }
        //$this->module->emDebug("MISSING FIELDS: " . print_r($missing_fields, true));
        return $missing_fields;
    }

    /*convenience function to return all the redcap_instrument names from duster collection windows
    returns array of forms

    @param $json_input
    @return array(array("form_name"=>, "form_label"=>))
    */
    private function getDusterForms($json_input)
    {
        $forms = [];
        $matches = [];
        preg_match_all('/"label":"([^"]+)","form_name":"([\w\d_]+)"/', json_encode($json_input), $matches);
        //$this->module->emDebug("FORMS: " . print_r($matches, true));

        $labels = $matches[1];
        $form_names = $matches[2];

        foreach ($form_names as $index => $form_name) {
            $forms[] = $this->toForm($form_name, $labels[$index]);
        }
        //$this->module->emDebug("FORMS: " . print_r($forms, true));
        return $forms;
    }

    /* returns all the forms in the duster config
    @return array(array("form_name"=>, "form_label"=>))
    */
    public function getForms()
    {
        $forms[] = $this->rp_info_form;
        $forms[] = $this->demographics_form;
        $forms = array_merge($forms, $this->getDusterForms($this->duster_config));
        return $forms;
    }

    /*convenience function to return all the redcap_field_names from a duster_config json object
      returns array keyed by field_name

    @param array $form
    @param jsonObject $json_input
    @return array($field_name=>array("label"=>,"redcap_field_name"=>,"form_name"=>,"form_label"=>,"format"=>))
    */
    private function getDusterFields($json_input, $form)
    {
        //$this->module->emDebug("INPUT: " . json_encode($json_input));

        $fields = [];
        $matches = [];
        preg_match_all('/"label":"([^"]+)"/', json_encode($json_input), $matches);
        //$this->module->emDebug("LABELS: " . print_r($matches, true));

        $labels = $matches[1];
        preg_match_all('/"redcap_field_name":"([^"]+)"/', json_encode($json_input), $matches);
        //$this->module->emDebug("FIELDS: " . print_r($matches, true));

        $field_names = $matches[1];
        preg_match_all('/"format":"([\w]+)"/', json_encode($json_input), $matches);
        //$this->module->emDebug("FORMATS: " . print_r($matches, true));

        $format = $matches[1];
        foreach ($field_names as $index => $fn) {
            $fn = strtolower($fn);
            $fields[$fn] = array(
                "label" => $labels[$index],
                "redcap_field_name" => $fn,
                "form_name" => $form['form_name'],
                "form_label" => $form['form_label'],
                "format" => $format[$index]);
        }
        return $fields;
    }

    /* convenience function to return a form
    @return array("form_name, "form_label")*/
    private function toForm($form_name, $form_label)
    {
        return array('form_name' => $form_name, 'form_label' => $form_label);
    }

    /*used by importMetadata to retrieve duster config in redcap import format
    @return array(redcap_field) redcap metadata*/
    public function getRedcapMetadata()
    {
        //add the redcap_record_id first
        $field = array(
            "redcap_field_name" => "redcap_record_id",
            "label" => "REDCap Record ID",
            "format" => "text",
            "form_name" => "rp_info",
            "form_label" => "");
        $redcap_meta =
            $this->getRedcapFields(array($field), "");
        // Researcher-Provided Information
        if (array_key_exists("rp_info", $this->duster_config)) {
            $redcap_meta = array_merge($redcap_meta,
                $this->getRedcapFields($this->getDusterFields(
                    $this->duster_config['rp_info']['rp_identifiers'], $this->rp_info_form), "Identifiers"),
                $this->getRedcapFields($this->getDusterFields(
                    $this->duster_config['rp_info']['rp_dates'], $this->rp_info_form),
                    "Dates"));
        }

        // Demographics
        if (array_key_exists("demographics", $this->duster_config)) {

            $redcap_meta = array_merge($redcap_meta,
                $this->getRedcapFields($this->getDusterFields(
                    $this->duster_config['demographics'], $this->demographics_form), ""));

        }

        // Clinical Windows
        if (array_key_exists("collection_windows", $this->duster_config)) {
            $forms = $this->getDusterForms($this->duster_config['collection_windows']);

            foreach ($this->duster_config['collection_windows'] as $index => $collection_window) {
                $redcap_meta = array_merge($redcap_meta,
                    $this->getRedcapFields($this->getDusterFields($collection_window['timing'],
                        $forms[$index]), "Timing"),
                    $this->getRedcapFields($this->getDusterFields($collection_window['data']['labs'],
                        $forms[$index]), "Labs"),
                    $this->getRedcapFields($this->getDusterFields($collection_window['data']['vitals'],
                        $forms[$index]), "Vitals"));
            }
        }
        return $redcap_meta;
    }

    /*convenience function, returns redcap metadata from duster fields
    @return array(array("form_name","section_header","field_type","field_label" ...))*/
    private function getRedcapFields($duster_fields, $section_name)
    {
        $rcFields = [];

        foreach ($duster_fields as $index => $field) {
            $rcFields[] = array("field_name" => $field['redcap_field_name'],
                "form_name" => $field['form_name'],
                "section_header" => ($index == array_key_first($duster_fields)) ? $section_name : "",
                "field_type" => "text",
                "field_label" => $field['label'],
                "select_choices_or_calculations" => "",
                "field_note" => "",
                "text_validation_type_or_show_slider_number" => "",
                "text_validation_min" => "",
                "text_validation_max" => "",
                "identifier" => "",
                "branching_logic" => "",
                "required_field" => "",
                "custom_alignment" => "",
                "question_number" => "",
                "matrix_group_name" => "",
                "matrix_ranking" => "",
                "field_annotation" => "");
        }
        return $rcFields;
    }
}

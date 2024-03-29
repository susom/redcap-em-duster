<?php

namespace Stanford\Duster;
/** @var $module Duster */

use ODM;
use RCView;
use Exception;

/**
 * Class OdmXmlString
 * Used to construct a string in ODM-XML format as a parameter for creating a REDCap project via REDCap API
 */
class OdmXmlString
{
  private $project_title;
  private $purpose;
  private $purpose_other;
  private $project_note;

  private $forms;
  private $fields;
  private $repeating_forms;

  /**
   * constructor
   * @param string $project_title
   * @param string $purpose
   * @param string $purpose_other
   * @param string $project_note
   */
  public function __construct(string $project_title, string $purpose, string $purpose_other = "", string $project_note = "")
  {
    $this->project_title = $project_title;
    $this->purpose = $purpose;
    $this->purpose_other = $purpose_other;
    $this->project_note = $project_note;
    $this->forms = array();
    $this->fields = array();
    $this->repeating_forms = array();
  }

  /** adds an instrument form to the project
   * @param string $form_name
   * @param string $label
   * @param bool $repeating
   * @return void
   * @throws Exception
   */
  public function addForm(string $form_name, string $label, bool $repeating = false)
  {
    if (!array_key_exists($form_name, $this->forms)) {
      if($repeating === true) {
        $this->repeating_forms[] = $form_name;
      }
      $this->forms[$form_name] = [
        "form_name" => $form_name,
        "label" => $label,
        "item_groups" => []
      ];
    } else {
      throw new Exception ("Unable to create form.  A form with this name already exists: '$form_name'.");
    }
  }

  /**
   * adds REDCap fields to a form if the given form exists
   * @param $form_name
   * @param null $item_group_name
   * @param null $item_group_label
   * @param string $section_header
   * @param $fields_arr
   * @return void
   */
  public function addFields($form_name, $item_group_name = null, $item_group_label = null, $section_header = "", $fields_arr)
  {
    if (array_key_exists($form_name, $this->forms)) {
      // only add fields that don't already exist in the project
      $fields_curr = $this->fields;
      $fields_arr = array_filter($fields_arr, function ($v, $k) use ($fields_curr) {
        return !in_array($v["redcap_field_name"], $fields_curr);
      }, ARRAY_FILTER_USE_BOTH);

      if (count($fields_arr) > 0) {
        // if $item_group_name is null, use the name of the first index in $fields_arr
        $item_group_name = $item_group_name === null ? $fields_arr[0]["redcap_field_name"] : $item_group_name;

        // if $item_group_label is null, use the form's label
        $item_group_label = $item_group_label === null ? $this->forms[$form_name]["label"] : $item_group_label;

        // create the item group if it doesn't already exist
        if (!array_key_exists($item_group_name, $this->forms[$form_name]["item_groups"])) {
          $this->forms[$form_name]["item_groups"][$item_group_name] = [
            "name" => $item_group_name,
            "label" => $item_group_label,
            "section_header" => $section_header,
            "items" => []
          ];
        }

        // add fields to the item group and its unique field name to the project's list of fields
        foreach ($fields_arr as $field) {
          if (!in_array($field["redcap_field_name"], $this->fields)) {
            $this->forms[$form_name]["item_groups"][$item_group_name]["items"][] = $field;
            $this->fields[] = $field["redcap_field_name"];
          }
        }
      }
    } else {
      throw new Exception("Unable to add fields to form $form_name.  Form does not exist.");
    }
  }

  /**
   * concatenates and returns the completed string in ODM-XML format
   * @return string
   */
  public function getOdmXmlString()
  {
    $repeating_instruments = "";
    if (count($this->repeating_forms) > 0) {
      $repeating_instruments .= "\t<redcap:RepeatingInstrumentsAndEvents>\n"
        . "\t\t<redcap:RepeatingInstruments>\n";
      foreach ($this->repeating_forms as $form_name) {
        $repeating_instruments .= "\t\t\t<redcap:RepeatingInstrument redcap:UniqueEventName=\"event_1_arm_1\" redcap:RepeatInstrument=\"{$form_name}\" redcap:CustomLabel=\"\"/>\n";
      }
      $repeating_instruments .= "\t\t</redcap:RepeatingInstruments>\n"
      . "\t</redcap:RepeatingInstrumentsAndEvents>\n";
    }

    $odm_str = ODM::getOdmOpeningTag($this->project_title)
      . "<Study OID=\"Project." . ODM::getStudyOID($this->project_title) . "\">\n"
      . "<GlobalVariables>\n"
      . "\t<StudyName>" . RCView::escape($this->project_title) . "</StudyName>\n"
      . "\t<StudyDescription>This file contains the metadata, events, and data for REDCap project " . RCView::escape($this->project_title) . ".</StudyDescription>\n"
      . "\t<ProtocolName>" . RCView::escape($this->project_title) . "</ProtocolName>\n"
      . "\t<redcap:RecordAutonumberingEnabled>1</redcap:RecordAutonumberingEnabled>\n"
      . "\t<redcap:CustomRecordLabel></redcap:CustomRecordLabel>\n"
      . "\t<redcap:SecondaryUniqueField></redcap:SecondaryUniqueField>\n"
      . "\t<redcap:SchedulingEnabled>0</redcap:SchedulingEnabled>\n"
      . "\t<redcap:SurveysEnabled>0</redcap:SurveysEnabled>\n"
      . "\t<redcap:SurveyInvitationEmailField></redcap:SurveyInvitationEmailField>\n"
      . "\t<redcap:Purpose>{$this->purpose}</redcap:Purpose>\n"
      . "\t<redcap:PurposeOther>{$this->purpose_other}</redcap:PurposeOther>\n"
      . "\t<redcap:ProjectNotes>{$this->project_note}</redcap:ProjectNotes>\n"
      . $repeating_instruments
      . "</GlobalVariables>\n"
      . "<MetaDataVersion OID=\"" . ODM::getMetadataVersionOID($this->project_title) . "\" Name=\"" . RCView::escape($this->project_title) . "\" redcap:RecordIdField=\"record_id\">\n";

    // forms and fields
    // foreach form in forms
    //      add form to form string
    //      foreach item group in form
    //          add item group to form string
    //          add item group to item group string
    //          foreach field in item group
    //              add field to item def string
    //              add field to item group string
    //          closing tag to item group string
    //      add "form complete" item group to form string
    //      add closing tag to form string
    //      add "form complete" item group to item group string
    //      add "form complete" to item def string
    //      add "form complete" field to code list
    $form_def = "";
    $item_group_def = "";
    $item_def = "";
    $code_list_def = "";
    // foreach form in forms
    foreach ($this->forms as $form) {
      // add form to form string
      $form_def .= "\t<FormDef OID=\"Form.{$form["form_name"]}\" Name=\"{$form["label"]}\" Repeating=\"No\" redcap:FormName=\"{$form["form_name"]}\">\n";
      // foreach item group in form
      foreach ($form["item_groups"] as $item_group) {
        // add item group to form string
        $form_def .= "\t\t<ItemGroupRef ItemGroupOID = \"{$form["form_name"]}.{$item_group["name"]}\" Mandatory=\"No\"/>\n";
        // add item group to item group string
        $item_group_def .= "\t<ItemGroupDef OID=\"{$form["form_name"]}.{$item_group["name"]}\" Name=\"{$item_group["label"]}\" Repeating=\"No\">\n";
        // foreach field in item group
        $section_header = $item_group["section_header"] !== "" ? " redcap:SectionHeader=\"{$item_group["section_header"]}\"" : $item_group["section_header"];
        foreach ($item_group["items"] as $field) {
          $hide_field = $field["hidden"] === true ? " redcap:FieldAnnotation=\"@HIDDEN\"" : "";
          $field_note = htmlentities($field["redcap_field_note"]);

          // add field to item def string
          switch ($field["redcap_field_type"]) {
            case "yesno":
              $item_def .= "\t<ItemDef OID=\"{$field["redcap_field_name"]}\" Name=\"{$field["redcap_field_name"]}\" DataType=\"boolean\" Length=\"1\" redcap:Variable=\"{$field["redcap_field_name"]}\" redcap:FieldType=\"yesno\" redcap:FieldNote=\"{$field_note}\"{$section_header}{$hide_field}>\n"
                . "\t\t<Question><TranslatedText>{$field["label"]}</TranslatedText></Question>\n"
                . "\t\t<CodeListRef CodeListOID=\"{$field["redcap_field_name"]}.choices\"/>\n"
                . "\t</ItemDef>\n";

              // add field to item group string
              $item_group_def .= "\t\t<ItemRef ItemOID=\"{$field["redcap_field_name"]}\" Mandatory=\"No\" redcap:Variable=\"{$field["redcap_field_name"]}\"/>\n";

              // add field to code list
              $code_list_def .= "\t<CodeList OID=\"{$field["redcap_field_name"]}.choices\" Name=\"{$field["redcap_field_name"]}\" DataType=\"boolean\" redcap:Variable=\"{$field["redcap_field_name"]}\">\n"
                . "\t\t<CodeListItem CodedValue=\"1\"><Decode><TranslatedText>Yes</TranslatedText></Decode></CodeListItem>\n"
                . "\t\t<CodeListItem CodedValue=\"0\"><Decode><TranslatedText>No</TranslatedText></Decode></CodeListItem>\n"
                . "\t</CodeList>\n";
              break;

            case "checkbox":
              $options = explode("|", $field["redcap_options"]);
              for ($option_index = 1; $option_index <= count($options); $option_index++) {
                $item_def .= "\t<ItemDef OID=\"{$field["redcap_field_name"]}___{$option_index}\" Name=\"{$field["redcap_field_name"]}___{$option_index}\" DataType=\"boolean\" Length=\"1\" redcap:Variable=\"{$field["redcap_field_name"]}\" redcap:FieldType=\"checkbox\" redcap:FieldNote=\"{$field_note}\"{$section_header}{$hide_field}>\n"
                  . "\t\t<Question><TranslatedText>{$field["label"]}</TranslatedText></Question>\n"
                  . "\t\t<CodeListRef CodeListOID=\"{$field["redcap_field_name"]}___{$option_index}.choices\"/>\n"
                  . "\t</ItemDef>\n";

                // add field to item group string
                $item_group_def .= "\t\t<ItemRef ItemOID=\"{$field["redcap_field_name"]}___{$option_index}\" Mandatory=\"No\" redcap:Variable=\"{$field["redcap_field_name"]}\"/>\n";

                // add field to code list
                $code_list_def .= "\t<CodeList OID=\"{$field["redcap_field_name"]}___{$option_index}.choices\" Name=\"{$field["redcap_field_name"]}___{$option_index}\" DataType=\"boolean\" redcap:Variable=\"{$field["redcap_field_name"]}\" redcap:CheckboxChoices=\"{$field["redcap_options"]}\">\n"
                  . "\t\t<CodeListItem CodedValue=\"1\"><Decode><TranslatedText>Checked</TranslatedText></Decode></CodeListItem>\n"
                  . "\t\t<CodeListItem CodedValue=\"0\"><Decode><TranslatedText>Unchecked</TranslatedText></Decode></CodeListItem>\n"
                  . "\t</CodeList>\n";
              }
              break;

            case "radio":
              // add field to item group string
              $item_def .= "\t<ItemDef OID=\"{$field["redcap_field_name"]}\" Name=\"{$field["redcap_field_name"]}\" DataType=\"text\" Length=\"1\" redcap:Variable=\"{$field["redcap_field_name"]}\" redcap:FieldType=\"radio\" redcap:FieldNote=\"{$field_note}\"{$section_header}{$hide_field}>\n"
                . "\t\t<Question><TranslatedText>{$field["label"]}</TranslatedText></Question>\n"
                . "\t\t<CodeListRef CodeListOID=\"{$field["redcap_field_name"]}.choices\"/>\n"
                . "\t</ItemDef>\n";

              // add field to code list
              $code_list_def .= "\t<CodeList OID=\"{$field["redcap_field_name"]}.choices\" Name=\"{$field["redcap_field_name"]}\" DataType=\"text\" redcap:Variable=\"{$field["redcap_field_name"]}\">\n";
              $field_choices = explode("|", $field["redcap_options"]);
              foreach ($field_choices as $choice) {
                $choice_arr = explode(",", $choice);
                $code_list_def .= "\t\t<CodeListItem CodedValue=\"{$choice_arr[0]}\"><Decode><TranslatedText>{$choice_arr[1]}</TranslatedText></Decode></CodeListItem>\n";
              }
              $code_list_def .= "\t</CodeList>\n";

              // add field to item group string
              $item_group_def .= "\t\t<ItemRef ItemOID=\"{$field["redcap_field_name"]}\" Mandatory=\"No\" redcap:Variable=\"{$field["redcap_field_name"]}\"/>\n";
              break;

            case "calc":
              $calculation = htmlentities($field["redcap_options"]);
              $item_def .= "\t<ItemDef OID=\"{$field["redcap_field_name"]}\" Name=\"{$field["redcap_field_name"]}\" DataType=\"float\" Length=\"999\" redcap:Variable=\"{$field["redcap_field_name"]}\" redcap:FieldType=\"calc\" redcap:FieldNote=\"{$field_note}\" redcap:Calculation=\"{$calculation}\"{$section_header}{$hide_field}>\n"
                . "\t\t<Question><TranslatedText>{$field["label"]}</TranslatedText></Question>\n"
                . "\t</ItemDef>\n";

              // add field to item group string
              $item_group_def .= "\t\t<ItemRef ItemOID=\"{$field["redcap_field_name"]}\" Mandatory=\"No\" redcap:Variable=\"{$field["redcap_field_name"]}\"/>\n";
              break;

            default:
              // text validation type for dates and datetimes
              $text_validation = "";
              switch ($field["value_type"]) {
                case "date":
                  $text_validation .= "redcap:TextValidationType=\"date_ymd\"";
                  break;
                case "datetime":
                  $text_validation .= "redcap:TextValidationType=\"datetime_seconds_ymd\"";
                  break;
              }

              // phi
              $phi = $field["phi"] === 't' ? "redcap:Identifier=\"y\"" : "";

              $item_def .= "\t<ItemDef OID=\"{$field["redcap_field_name"]}\" Name=\"{$field["redcap_field_name"]}\" DataType=\"text\" Length=\"999\" redcap:Variable=\"{$field["redcap_field_name"]}\" redcap:FieldType=\"text\" {$text_validation} {$phi} redcap:FieldNote=\"{$field_note}\"{$section_header}{$hide_field}>\n"
                . "\t\t<Question><TranslatedText>{$field["label"]}</TranslatedText></Question>\n"
                . "\t</ItemDef>\n";

              // add field to item group string
              $item_group_def .= "\t\t<ItemRef ItemOID=\"{$field["redcap_field_name"]}\" Mandatory=\"No\" redcap:Variable=\"{$field["redcap_field_name"]}\"/>\n";
          }
          $section_header = "";
        }
        // closing tag to item group string
        $item_group_def .= "\t</ItemGroupDef>\n";
      }
      // add "form complete" item group to form string
      $form_def .= "\t\t<ItemGroupRef ItemGroupOID = \"{$form["form_name"]}.{$form["form_name"]}_complete\" Mandatory=\"No\"/>\n";
      // add closing tag to form string
      $form_def .= "\t</FormDef>\n";
      // add "form complete" item group to item group string
      $item_group_def .= "\t<ItemGroupDef OID=\"{$form["form_name"]}.{$form["form_name"]}_complete\" Name=\"Form Status\" Repeating=\"No\">\n"
        . "\t\t<ItemRef ItemOID=\"{$form["form_name"]}_complete\" Mandatory=\"No\" redcap:Variable=\"{$form["form_name"]}_complete\"/>\n"
        . "\t</ItemGroupDef>\n";
      // add "form complete" to item def string
      $item_def .= "\t<ItemDef OID=\"{$form["form_name"]}_complete\" Name=\"{$form["form_name"]}_complete\" DataType=\"text\" Length=\"1\" redcap:Variable=\"{$form["form_name"]}_complete\" redcap:FieldType=\"select\" redcap:SectionHeader=\"Form Status\">\n"
        . "\t\t<Question><TranslatedText>Complete?</TranslatedText></Question>\n"
        . "\t\t<CodeListRef CodeListOID=\"{$form["form_name"]}_complete.choices\"/>\n"
        . "\t</ItemDef>\n";
      // add "form complete" field to code list
      $code_list_def .= "\t<CodeList OID=\"{$form["form_name"]}_complete.choices\" Name=\"{$form["form_name"]}_complete\" DataType=\"text\" redcap:Variable=\"{$form["form_name"]}_complete\">\n"
        . "\t\t<CodeListItem CodedValue=\"0\"><Decode><TranslatedText>Incomplete</TranslatedText></Decode></CodeListItem>\n"
        . "\t\t<CodeListItem CodedValue=\"1\"><Decode><TranslatedText>Unverified</TranslatedText></Decode></CodeListItem>\n"
        . "\t\t<CodeListItem CodedValue=\"2\"><Decode><TranslatedText>Complete</TranslatedText></Decode></CodeListItem>\n"
        . "\t</CodeList>\n";
    }

    $odm_str .= $form_def
      . $item_group_def
      . $item_def
      . $code_list_def;

    // closing tags
    $odm_str .= "</MetaDataVersion>\n"
      . "</Study>\n"
      . ODM::getOdmClosingTag();

    return $odm_str;
  }
}

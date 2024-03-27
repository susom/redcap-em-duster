<?php
namespace Stanford\Duster;

use REDCap;
use ExternalModules\ExternalModules;
use Exception;
require_once "emLoggerTrait.php";

/**
 * Class Duster
 * @package Stanford\Duster
 *
 * This external module is an all-in-one solution to design and create clinical datasets as REDCap projects,
 * as well as fetch clinical data for REDCap projects by leveraging STARR-API and REDCap to STARR Link
 *
 */
class Duster extends \ExternalModules\AbstractExternalModule {

  use emLoggerTrait;

  /**
   * Hook to show DUSTER as an option in the 'New Project' page
   * Only shows DUSTER option if:
   *  1) user is allowlisted in the System-level config
   *  2) purpose selected on 'New Project' page is 'Research'
   * @return void
   */
  public function redcap_every_page_top($project_id) {
    // $this->emDebug(" Page is " . PAGE . " action is " . $_GET['action']);
    if ($project_id === null
      && strpos(PAGE, "index.php") !== false
      && isset($_GET['action']) && $_GET['action'] === 'create'
      && $this->isUserAllowed() === true) {
        // $this->emDebug("In Every Page Top Hook project id :" . $this->getProjectId() . " Page is " . PAGE);
        $some = "<script> let dusterUrl = '" . $this->getUrl("pages/newProjectIntro.php", false, true) . "' ; </script>";
        echo $some;
        $script = <<<EOD
                <script>
                    $(document).ready(function() {
                        const dusterLabel = "Create project using DUSTER";
                        const dusterDesc = "DUSTER is a self-service clinical dataset designer/import tool for research studies utilizing STARR data. For more information, visit https://med.stanford.edu/duster.html.";
                        let div = "<div id='duster_option' style='text-indent: -1.5em; margin-left: 1.5em; display: none;'><input name='project_template_radio' id='project_template_duster' type='radio'>" ;
                        div += "<label style='text-indent:3px;margin-top:4px;margin-bottom:0;cursor:pointer;' for='project_template_duster'>" + dusterLabel + "</label>" ;
                        div += "<a href=\"javascript:;\" class=\"help\" onclick=\"simpleDialog('" + dusterDesc + "','" + dusterLabel + "');\">?</a>";
                        div += "</div>" ;
                        div += "<div tabindex=\"-1\" role=\"dialog\" class=\"simpleDialog ui-dialog-content ui-widget-content\">";
                        div += dusterDesc;
                        div += "</div>";

                        $("#project_template_radio0").closest('td').append(div) ;

                        // show DUSTER radio button option if purpose is research
                        $("#purpose").change(function() {
                            $("#purpose").val() == "2" ? $("#duster_option").show() : $("#duster_option").hide() ;
                        }) ;

                        let btn = '<button type="button" class="btn btn-primaryrc" id="dusterSubmitBtn">Create Project</button>' ;
                        let defaultCreateBtn = $("button.btn-primaryrc").hide() ;
                        $("button.btn-primaryrc").closest('td').prepend(btn) ;

                        $("#dusterSubmitBtn").on('click', function() {
                            if ($("#project_template_duster").prop("checked")) {
                                if (checkForm()) {
                                    $("form[name='createdb']").attr('action', dusterUrl) ;
                                    defaultCreateBtn.click() ;
                                }
                            } else {
                                defaultCreateBtn.click() ;
                            }
                        }) ;
                    }) ;
                </script>
                EOD;
        echo $script;
    }
  }

  /**
   * check user is allowed to use DUSTER based on DUSTER's allowlist
   * @return bool
   */
  private function isUserAllowed(): bool {
      $enabled = $this->getSystemSetting('enable-allowlist');
      if ($enabled) {
          $allowlist = $this->getSystemSetting('sunet')[0];
          try {
              $sunet = $this->getUser()->getUsername();
              return in_array($sunet, $allowlist);
          } catch (Exception $e) {
              $username = $this->getUser()->getUsername();
              $this->handleError("ERROR: isUserAllowed Exception",
                  "Checking user $username on allowlist" . print_r($allowlist, true), $e);
              return false;
          }
      }
    return true;
  }

  /**
   * retrieves a valid vertx token via vertx token manager
   * @param $service
   * @return false|mixed
   */
  private function getValidToken($service) {
    $token = false;
    try {
      $tokenMgnt = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
      $token = $tokenMgnt->findValidToken($service);
    } catch (Exception $ex) {
      $this->handleError(
        "ERROR: getValidToken Exception",
          "Could not find a valid token for service $service", $ex);
    }
    return $token;
  }

  /**
   * sends a STARR-API GET request
   * @param $url
   * @param $service
   * @return array|bool|mixed|string
   */
  public function starrApiGetRequest($url, $service) {
    $token = $this->getValidToken($service);
    $response = null;
    $pid = $this->getProjectId() ? : 'No Project ID';

    // attempt STARR-API GET request
    if ($token !== false) {
      $curl = curl_init($url);
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      $headers = array(
        "Accept: application/json",
        "Content-Type: application/json",
        "Authorization: Bearer " . $token
      );
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
      $this->emDebug("PID: $pid. GET request to $url");
      $response = $this->handleStarrApiRequest($curl);
      if (!empty($response['status']) && $response['status'] !== 200) {
        $this->handleError("ERROR: starrApiGetRequest",
          "URL: $url<br>RESPONSE BODY: " . print_r($response['body'], true)
          . "<br>RESPONSE CODE: " . $response['status']
          . "<br>ERROR: " . $response['message']);
      }
      curl_close($curl);
    } else {
      $this->emError("Failed to retrieve a Vertx token.");
    }
    return $response;
  }

  /**
   * sends a STARR-API POST request
   * @param $url
   * @param $service
   * @param $post_fields
   * @return array|bool|mixed|string|null
   */
  public function starrApiPostRequest($url, $service, $post_fields) {
    $token = $this->getValidToken($service);
    $response = null;
    $pid = $this->getProjectId() ? : 'No Project ID';

    $message = json_encode($post_fields);
    $content_type = 'application/json';
    if ($token !== false) {
      $headers = array(
        "Content-Type: " . $content_type,
        "Authorization: Bearer " . $token,
        "Content-Length: " . length($message)
      );
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $message);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      $this->emDebug("PID: $pid. POST request to $url; post_fields $message;");
      $response = $this->handleStarrApiRequest($curl);
      if (!empty($response['status']) && $response['status'] !== 200) {
        $this->handleError("ERROR: starrApiPostRequest",
            "URL: $url;<br>POST_FIELDS: " . $message
            . ";<br>RESPONSE BODY: "
            . print_r($response['body'], true)
            . ";<br>RESPONSE CODE: " . $response['status']
            . ";<br>ERROR: " . $response['message']);
      }
      curl_close($curl);
    } else {
      $this->emError("Failed to retrieve a Vertx token.");
    }
    return $response;
  }

  /**
   * executes a cURL for a STARR-API request
   * if there is an error, returns a JSON object with 'status' field indicating the response code
   * and 'message' field containing the error message
   * else, returns the cURL execution's response (string or JSON-decoded)
   * @param $curl_handle
   * @return array|bool|mixed|string
   */
    // if there is an error, returns a json object with "status" field indicating the response code and "message"
    // field containing the error message
    // otherwise, return json decoded response or just the response string
    public function handleStarrApiRequest($curl_handle) {
      $response = curl_exec($curl_handle);
      $resp_code = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
      $curl_error = curl_error($curl_handle);
      $response = (json_decode($response) === false) ? $response : json_decode($response, true);
      // sometimes the valid response is a string and response code is 0 or missing.
      // Don't want to convert the response to a JSON object unless there's an error.
      /*
      if ($resp_code !== 200 || (!empty($resp_code['status']) && $resp_code['status'] !== 200)
        || !empty($curl_error) || !empty($response['error'])) {
      */
      if ($resp_code !== 200 || (!empty($response['status']) && $response['status'] !== 200)
      || !empty($curl_error) || !empty($response['error'])) {
        $resp_arr['body'] = $response;
        $resp_arr['status'] = (!empty($resp_code['status'])) ? $resp_code['status'] : $resp_code;
        $resp_arr['message'] = implode('; ', array_filter([$response['error'], $curl_error]));
        if (strpos($resp_arr['message'], 'Connection refused') > -1) {
            $resp_arr['status'] = 500;
        }
        $response = $resp_arr;
      }
      curl_close($curl_handle);

      return $response;
    }

  /**
   * sends a STARR-API GET request to retrieve DUSTER metadata
   * @return array|mixed
   */
  public function getMetadata() {
    $metadata_url = $this->getSystemSetting("starrapi-metadata-url");
    $metadata = $this->starrApiGetRequest($metadata_url,'ddp');
    // error handled by starrApiGetRequest
    return $metadata;
  }

  /**
   * @return void
   */
  public function refreshMetadata() {
    // build and send GET request to config webservice
    // add a '/' at the end of the url if it's not there
    $url = $this->getSystemSetting("starrapi-metadata-url");
    $url = $url .
        ((substr($url,-1) ==='/') ? "" : "/") . 'refresh';
    $this->emDebug("PID ". $this->getProjectId() . ":refreshMetadata url = $url for PID " . $this->getProjectId());
    $this->starrApiGetRequest($url,'ddp');
    // error handled by starrApiGetRequest
  }

  /**
   * Fetch a project's IRB number
   * @param String $pid
   * @return mixed
   * @throws Exception
   */
  public function getProjectIrb(String $pid) {
    $sql = 'SELECT project_irb_number FROM redcap_projects WHERE project_id = ?';
    $query = $this->createQuery();
    $query->add($sql, [$pid]);
    $sql_result = $query->execute();
    $num_results = $query->affected_rows;
    if ($num_results === 1) {
      $irb = $sql_result->fetch_assoc()['project_irb_number'];
      return $irb;
    } else {
      $this->handleError("ERROR: Unable to retrieve this project's IRB number.", "Returned $num_results in " .
        __METHOD__ . " from pid '$pid'");
      throw new Exception ("Returned $num_results in " . __METHOD__ . " from pid '$pid'");
    }
  }

  /**
   * Fetch user information from corresponding REDCap API token
   * @param String $token
   * @return String $project_id
   * @throws Exception
   */
  public function getUserProjectFromToken($token)
  {
    $sql = "
            SELECT project_id
            FROM redcap_user_rights
            WHERE api_token = ?";
    $q = $this->createQuery();
    $q->add($sql, [$token]);
    $result = $q->execute();
    $num_results = $q->affected_rows;
    if ($num_results === 1) {
      $row = $result->fetch_assoc();
      return $row['project_id'];
    } else {
        $this->handleError("ERROR: Unable to retrieve user project from token", "Returned $num_results in " .
            __METHOD__ . " from token '$token'");
      throw new Exception ("Returned $num_results in " . __METHOD__ . " from token '$token'");
    }
  }

  /**
   * Mark REDCap project for deletion by 'DeleteProject' REDCap cron
   * @param $pid
   * @return true
   * @throws Exception
   */
  public function deleteRedcapProject($pid)
  {
    $sql = "
            UPDATE redcap_projects
            SET date_deleted = NOW()
            WHERE project_id = ?
            ";
    $q = $this->createQuery();
    $q->add($sql, [$pid]);
    $q->execute();
    $num_results = $q->affected_rows;
    if ($num_results === 1) {
      $this->emLog("REDCap pid $pid marked for deletion.");
      return true;
    } else {
      $this->handleError("ERROR: Unexpected SQL results in deleteRedcapProject()", "Returned $num_results in " .
        __METHOD__ . " from pid '$pid'");
      throw new Exception ("Returned $num_results in " . __METHOD__ . " from project_id '$pid'");
    }
  }

  /**
   * @param $subject
   * @param $message
   * @param $throwable
   * @return string
   */
    // attaches PID to the $subject
    // logs error in duster.log as well as REDCap log
    // sends error to duster configured email
  public function handleError($subject, $message, $throwable=null) {
    $pid = $this->getProjectId() ? : 'No Project ID';
    $user_id = USERID;

    $subject = "User: $user_id | PID: $pid | $subject";

    if (!empty($throwable)) {
      $message .= "<br>Message: ".$throwable->getMessage()."<br>Trace: "
          .$throwable->getTraceAsString();
      $this->emError("User: $user_id | PID: $pid | ERROR: Subject: $subject; Message: $message", $throwable);
    } else {
      $this->emError("User: $user_id | PID: $pid | ERROR: Subject: $subject; Message: $message");
    }
      REDCap::logEvent("DUSTER: ERROR $message");
      $duster_email = $this->getSystemSetting("duster-email");
    if (!empty($duster_email)) {
      $emailStatus = REDCap::email($duster_email,'no-reply@stanford.edu', $subject,
          $message);
      if (!$emailStatus) {
        REDCap::logEvent("DUSTER: Email notification to $duster_email failed");
        $this->emError("User $user_id | PID $pid | ERROR: Email Notification to $duster_email Failed. Subject: $subject; MESSAGE: $message");
        return "Unable to send an error notification email to $duster_email. Please notify your REDCap administrator with the following error message: " . $message;
      } else {
        return "$message  An email regarding this issue has been sent to $duster_email.";
      }
    } else {
      $this->emError("User $user_id | PID $pid | ERROR: No DUSTER email configured as a system-level setting. Unable send message SUBJECT: $subject; MESSAGE: $message");
      return "Unable to send an error notification email to DUSTER's development team. Please notify your REDCap administrator of the following error: " . $message;
    }
  }

}

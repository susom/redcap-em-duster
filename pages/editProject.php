<?php
namespace Stanford\Duster;
/** @var $module Duster */

use REDCap;

require_once $module->getModulePath() . "classes/DusterConfigClass.php";

/**
 * entrypoint to DUSTER's edit project UI (Vue app)
 */
$pid = $module->getProjectId();
$project_status = $module->getProjectStatus($pid);
$user_rights = $module->getUser()->getRights($pid);
$editable = $project_status === "DEV"
    && strlen($user_rights['api_token']) === 32
    && $user_rights['api_import'] === '1'
    && $user_rights['design'] === '1';

if ($editable === true) {
    $irb = $module->getProjectIrb($pid);
    $duster_config_obj = new DusterConfigClass($pid, $module);
    $design_config = json_encode($duster_config_obj->getDesignConfig());
}

?>

<!DOCTYPE html>
<html lang="en">
<h3>
    DUSTER: Edit Project
</h3>
<?php if ($editable === true) {
?>
    <p>Hitting the button below will launch an application where you may perform the following modifications to your project:</p>
    <ol>
      <li>Add new Researcher-Provided Information.</li>
      <li>Select additional demographics.</li>
      <li>Add new data collection windows.</li>
      <li>Add new clinical variables to pre-existing data collection windows.</li>
      <!-- <li>Select additional aggregations to pre-existing clinical variables in data collection windows.</li> -->
    </ol>
    <button
      type="button"
      onclick="window.location = '<?php echo $module->getUrl("pages/js/duster/new-project/dist/index.html"); ?>';"
    >Launch Editor
    </button>
<?php
} else {
?>
    <strong>
        Sorry, you cannot edit this DUSTER project.
    </strong>
    <p>
        In order to edit this DUSTER project, the following conditions are required:
    </p>
    <ol>
        <li>This REDCap project must be in Development mode.</li>
        <li>You must have Project Design and Setup privileges in this REDCap project.</li>
        <li>You must have API Import privileges in this REDCap project.</li>
        <li>You must have an API Token for this REDCap project.</li>
    </ol>
<?php
}
?>
</html>

<!-- axios CDN -->
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

<script>
    setInterval(() => {
    axios.get("<?php echo $module->getUrl("services/refreshSession.php"); ?>")
        .then(function (response) {

        })
        .catch(function (error) {

        });
    },60000);

    const editable = <?php echo $editable; ?> ? true:false;
    if (editable === true) {
        localStorage.removeItem('postObj');
        let postObj = {};
        postObj['redcap_csrf_token'] = "<?php echo $module->getCSRFToken(); ?>";
        postObj['redcap_user'] = "<?php echo $module->getUser()->getUserName(); ?>";
        postObj['edit_mode'] = true;
        postObj['redcap_project_id'] = "<?php echo $pid; ?>";
        postObj['project_irb_number'] = "<?php echo $irb; ?>";
        postObj['initial_design'] = <?php echo $design_config; ?>;

        // store URL for REDCap's 'New Project' page
        postObj['redcap_new_project_url'] = "<?php echo APP_PATH_WEBROOT_FULL . "index.php?action=create"; ?>";
        // store URL for services/reportFatalError.php
        postObj['report_fatal_error_url'] = "<?php echo $module->getUrl("services/reportFatalError.php"); ?>";
        // store URL for services/checkIRB.php
        postObj['check_irb_url'] = "<?php echo $module->getUrl("services/checkIRB.php"); ?>";
        // store URL for services/callMetadata.php
        postObj['metadata_url'] = "<?php echo $module->getUrl("services/callMetadata.php"); ?>";
        // store URL for services/getDatasetDesigns.php
        postObj['get_dataset_designs_url'] = "<?php echo $module->getUrl("services/getDatasetDesigns.php"); ?>";
        // store URL for services/refreshSession.php
        postObj['refresh_session_url'] = "<?php echo $module->getUrl("services/refreshSession.php"); ?>";
        // store URL for services/updateProject.php
        postObj['update_project_url'] = "<?php echo $module->getUrl("services/updateProject.php"); ?>";
        // store DPA URLs
        postObj['new_dpa_url'] = 'https://redcap.stanford.edu/surveys/?s=L3TRTT9EF9';
        postObj['addon_dpa_url'] = 'https://redcap.stanford.edu/surveys/?s=8RWF73YTWA'
        postObj['add_dpa_to_irb_url'] = 'https://med.stanford.edu/starr-tools/data-compliance/modify-existing-protocol.html';
        localStorage.setItem('postObj', JSON.stringify(postObj));
    }
</script>

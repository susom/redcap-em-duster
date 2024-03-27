<template>
  <div class="container">
    <div class="grid">
      <div class="col-offset-1 col-10">
        <nav>
          <div class="grid">
            <div class="col">
              <a class="brand-logo" href="https://med.stanford.edu/">Stanford Medicine</a>
              <div style="display:inline;float:left" class="mt-2">
                <div class="font-bold text-left" style="font-size:1.25em;">DUSTER</div>
                <div class="text-sm font-italic">Research Technology, TDS</div>
              </div>
            </div>
            <div class="col mt-1">
              <p class="text-xl font-italic">Data Upload Service for Translational rEsearch on Redcap</p>
            </div>
          </div>
          <div class="grid text-white text-lg" style="background-color: #53565A;">
            <div class="col-offset-9 col-2">
              <a style='font-size: 1rem' href="https://med.stanford.edu/duster" class="text-white"
                 target="_blank">DUSTER Website</a>
            </div>
          </div>
        </nav>

        <div v-show="!showSummary">
          <!--<p>Define your project.</p>-->
          <div class="grid">
            <div class="col-6">
              <ResearcherProvidedPanel
                  v-model:rp-data="rpData"
                  :reserved-field-names="reservedFieldNames"
              />
            </div>
            <div class="col-6">

              <DemographicsPanel
                  class="flex-1"
                  :demographics-options="demographicsOptions"
                  v-model:demographics-selects="demographicsSelects"
              />
            </div>
          </div>
          <div class="grid">
            <div class="col">
              <CollectionWindowsPanel
                  :lab-options="labOptions"
                  :vital-options="vitalOptions"
                  :score-options="scoreOptions"
                  :outcome-options="outcomeOptions"
                  :clinical-date-options="clinicalDateOptions"
                  :rp-dates="rpDates"
                  v-model:collection-windows="collectionWindows"
                  :initial-windows="initialDesign.collectionWindows"
              />
            </div>
          </div>
          <div class="grid">
            <div class="col">
              <Toast />
              <Toolbar class="col">
                <template #start>
                  <Button
                    :label="projectConfig.edit_mode ? 'Back to REDCap Project' : 'Back to REDCap New Project Page'"
                    icon="pi pi-cross" severity="secondary"
                    class="ml-2"
                    @click="exitFromDuster($event)"
                  />
                </template>
                <template #end>
                  <Button
                    type="submit"
                    :label="projectConfig.edit_mode ? 'Review & Update Project' : 'Review & Create Project'"
                    icon="pi pi-check"
                    class="ml-2"
                    @click="checkValidation"
                  />
                </template>
              </Toolbar>
            </div>
          </div>
        </div>

        <div :style="(showSummary) ?  '': 'display: none !important'">
          <ReviewPanel
              v-model:show-summary="showSummary"
              :dev="dev"
              :rp-data="rpData"
              :rp-identifiers="rpIdentifiers"
              :rp-dates="rpDates"
              :demographics="demographicsSelects"
              :collection-windows="collectionWindows"
              :project-info="projectConfig"
              @delete-auto-save="deleteAutoSaveDesign()"
          />
        </div>
      </div>
    </div>
  </div>
  <Dialog v-model:visible="irbCheckVisible"
          modal header="Checking IRB or DPA"
          :style="{ width: '40vw' }"
          :closable=false>
    <p>
      <span v-html="irbCheckMessage"></span>
    </p>
    <div v-if="!irbValid && irbCheckStatus==='checked'" class="m-2">
      <label>IRB or DPA: </label>
      <InputText v-model="projectIrb"/>
    </div>
    <template #footer>
      <Button v-if="!irbValid && irbCheckStatus==='checked'"
              label="Submit" icon="pi pi-refresh" class="p-button-primary" @click="irbRetry" size="small"/>
      <Button v-if="irbValid && !irbWarningConfirmed"
              label="OK" icon="pi pi-check" class="p-button-primary" @click="irbWarningConfirmed = true" size="small" />
      <Button label="Cancel" icon="pi pi-times" class="p-button-secondary" @click="irbCheckCancel" size="small" />
    </template>
  </Dialog>

  <!-- prompt to ask user if they want to load an auto-saved design from a previous session -->
  <Dialog
    v-model:visible="promptRestoreAutoSave"
    header="Restore last design"
    :modal="true"
    :closable="false"
  >
    <p>
      It looks like you were previously in the middle of designing a dataset.
      <br>
      <br>
      Would you like to restore this design?
    </p>
    <template #footer>
      <Button label="Yes" icon="pi pi-check" class="p-button-primary" @click="restoreAutoSaveDesign()" size="small"/>
      <Button label="No" icon="pi pi-times" class="p-button-secondary" @click="promptRestoreAutoSave=false, deleteAutoSaveDesign()" size="small" />
    </template>
  </Dialog>
  <SystemErrorDialog v-if="systemError"/>
  <ConfirmDialog>
    <template #message="slotProps">
      <div class="flex p-4">
        <i :class="slotProps.message.icon" style="font-size: 1.5rem"></i>
        <p class="pl-2">{{ slotProps.message.message }}</p>
      </div>
    </template>
  </ConfirmDialog>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, watch, provide } from 'vue';
import SystemErrorDialog from '@shared/components/SystemErrorDialog.vue';
import { useConfirm } from "primevue/useconfirm";

import axios from 'axios';
import type FieldMetadata from "@/types/FieldMetadata";
import type {BasicConfig} from "@/types/FieldConfig";
import type CollectionWindow from "@/types/CollectionWindow";
import type DusterMetadata from "@/types/DusterMetadata";

import ResearcherProvidedPanel from "@/components/ResearcherProvidedPanel.vue";
import DemographicsPanel from '@/components/DemographicsPanel.vue';
import CollectionWindowsPanel from '@/components/CollectionWindowsPanel.vue';
import ReviewPanel from '@/components/ReviewPanel.vue';

// for testing
import resp from './dusterTestMetadata.json';
import {useToast} from "primevue/usetoast";
import Toast from 'primevue/toast';
import {useVuelidate} from "@vuelidate/core";

const projectConfig = JSON.parse(localStorage.getItem('postObj') || '{}');
localStorage.removeItem('postObj');

setInterval(() => {
  axios.get(projectConfig.refresh_session_url)
    .then(function (response) {
    })
    .catch(function (error) {
    });
  if (projectConfig.edit_mode === false) {
    saveDatasetDesign('auto-save');
  }
},60000);

const dev = ref<boolean>(false);
const systemError = ref<boolean>(false);

const showSummary = ref<boolean>(false);

const rpData = ref<BasicConfig[]>([
  {
    redcap_field_name: "mrn",
    label:"Medical Record Number (MRN)",
    redcap_field_type:"text",
    value_type:"Identifier", // this needs to be replaced by "text" in review step
    redcap_field_note:"8-digit number (including leading zeros, e.g., '01234567')",
    phi:"t",
    id: "mrn",
    duster_field_name: undefined
  },
  {
    redcap_field_name: "enroll_date",
    redcap_field_type:"text",
    value_type: "date",
    label: "Study Enrollment Date",
    phi: "t",
    id: "enroll_date",
    duster_field_name: undefined
  }]);

// separating out identifiers and dates for review step
const rpIdentifiers = computed(() => {
  return rpData.value.filter((rpi:BasicConfig) => rpi.value_type?.toLowerCase() === 'identifier')
});
const rpDates = computed(() => {
  return rpData.value.filter((rpi:BasicConfig) => rpi.value_type?.toLowerCase() !== 'identifier')
});

const demographicsOptions = ref<FieldMetadata[]>([]);
const labOptions = ref<FieldMetadata[]>([]);
const vitalOptions = ref<FieldMetadata[]>([]);
const outcomeOptions = ref<FieldMetadata[]>([]);
const scoreOptions = ref<FieldMetadata[]>([]);
const clinicalDateOptions = ref<FieldMetadata[]>([]);

const metadataArr = computed<Array<FieldMetadata>>(() => {
  let arr:FieldMetadata[] = [];
  return arr.concat(demographicsOptions.value)
    .concat(labOptions.value)
    .concat(vitalOptions.value)
    .concat(outcomeOptions.value)
    .concat(scoreOptions.value);
});
provide('metadata', metadataArr);

const demographicsSelects = ref<FieldMetadata[]>([]);
const collectionWindows = ref<CollectionWindow[]>([]);

const projectIrb = ref<string>(projectConfig.project_irb_number)
const irbOrDpaStr = (irbOrDpa: string) => {
  return  irbOrDpa.startsWith('DPA') ? irbOrDpa : 'IRB ' + irbOrDpa
}
const irbValid = ref<boolean>(false)
const irbCheckStatus = ref<string>("checking")
const irbCheckMessage = ref<string>("Checking " + irbOrDpaStr(projectIrb.value) + " ...")
const irbCheckVisible = ref<boolean>(false)

// const autoSaveDesign = {rpData: null, demographicsSelects: null, collectionWindows: null};
// const autoSaveDesign = ref<object>();
// const autoSaveDesign: {[key: string]:any, [demographicsSelects: string]:any, [collectionWindows: string]:any} = {}; // = ref<object>();
const autoSaveDesign: {[key: string]:any} = ({}); // = ref<object>();
const initialDesign: {[key: string]:any} = ({
  rpData: [],
  demographicsSelects: [],
  collectionWindows: []
});

// const myObj: {[index: string]:any} = {}

const promptRestoreAutoSave = ref<boolean>(false);

onMounted(() => {
  // check irb
  checkIrb(projectConfig.check_irb_url, projectConfig.redcap_csrf_token, projectConfig.project_irb_number, projectConfig.redcap_user);
})

const irbWarningConfirmed = ref<boolean>(false)
watch(irbWarningConfirmed, (confirmed) => {
  irbCheckMessage.value = "Fetching DUSTER Metadata ..."
  if (confirmed) {
    getDusterMetadata(projectConfig.metadata_url);
  }
})

// check for missing dpa or data attestation items
const checkPrivacyAttestion = () => {
  let message = ""
  const attestationMissing: string[] = []

  if (!complianceSettings.value.dpa) {
    message = "<span style='color:red'>No valid DPA was found for IRB "
        + complianceSettings.value.irb_num
        +".  <a style='font-size: 1rem' href='" +
        projectConfig.add_dpa_to_irb_url
    +"' target='_blank'><u>Please add a DPA to your protocol</u></a> with the necessary attestations.</span> "
      attestationMissing.push("MRNs (required)")
      attestationMissing.push("Dates (required)")
      attestationMissing.push("Names")
      attestationMissing.push("Demographics")
      attestationMissing.push("Lab results")
      attestationMissing.push("Clinical notes (includes flowsheets)")
      attestationMissing.push("Medications")
      attestationMissing.push("Diagnosis codes")
      attestationMissing.push("Procedure codes")
  } else {
    const dpa = complianceSettings.value.dpa

    if (!dpa.approvedForMrn) {
      attestationMissing.push("MRNs (required)")
    }
    if (!dpa.approvedForDates) {
      attestationMissing.push("Dates (required)")
    }
    if (!dpa.approvedForName) {
      attestationMissing.push("Names")
    }
    if (!dpa.approvedForDemographics) {
      attestationMissing.push("Demographics")
    }
    if (!dpa.approvedForLabResult) {
      attestationMissing.push("Lab results")
    }
    if (!dpa.approvedForClinicalNotes) {
      attestationMissing.push("Clinical notes (includes flowsheets)")
    }
    if (!dpa.approvedForMedications) {
      attestationMissing.push("Medications")
    }
    if (!dpa.approvedForDiagnosis) {
      attestationMissing.push("Diagnosis codes")
    }
    if (!dpa.approvedForProcedure) {
      attestationMissing.push("Procedure codes")
    }
  }
  if (attestationMissing.length > 0) {
    if (complianceSettings.value.dpa) {
      message += "<span style='color:red'>"
      const isIRB = !complianceSettings.value.irb_num?.startsWith("DPA-");
      message = irbOrDpaStr(complianceSettings.value.irb_num)
      message +=
          " does not include the following attestations which may be required to retrieve data for this project. If any of the following are included as part of your DUSTER project, you will need to "
      if (isIRB) {
        message +=
            "<a style='font-size: 1rem' href='" +
            projectConfig.add_dpa_to_irb_url
            +"' target='_blank'><u>modify your protocol and file a new DPA</u></a>.<ul>"
      } else {
        message +="<a style='font-size: 1rem' href='" +
            projectConfig.new_dpa_url
            +"' target='_blank'><u>file a new DPA</u></a> with the required attestations.<ul>"
      }
    } else {
      message +=
          "<span style='color:red'>The DPA must include all PHI and data attestations which will be accessed as part of this project. Please include the following items in your DPA as needed.<ul>"
    }
    attestationMissing.forEach(currentValue =>
        message += "<li>" + currentValue + "</li>"
    )
    message += "</ul></span><br>"
  }
  if (complianceSettings.value.dpa && !complianceSettings.value.user_permissions?.signedDpa) {
    message += "<span style='color:red'>User " + projectConfig.redcap_user +
        "  does not have a DPA attestation associated with "
        + irbOrDpaStr(complianceSettings.value.irb_num) +
        " and needs an  <a style='font-size: 1rem' href='" + projectConfig.addon_dpa_url +
        "' target='_blank'><u>add-on DPA</u></a>.</span><br>"
  }
  if (message.length > 0)
    message +=
        "<br>You may continue to configure and create your DUSTER project. However, all IRB and/or DPA issues must be resolved before retrieving data. Click \"OK\" to continue project creation."
  return message;
}

const complianceSettings = ref<any>()

const checkIrb = (checkIrbUrl:string, redcapCsrfToken: string, projectIrbNumber: string,
  user:string) => {
  if (dev.value) {
    irbValid.value = true
  } else if (projectIrbNumber) {
    irbCheckVisible.value = true
    let formData = new FormData();
    formData.append('redcap_csrf_token', redcapCsrfToken);
    formData.append("project_irb_number", projectIrbNumber);
    formData.append("user", user)
    axios.post(checkIrbUrl, formData)
        .then(function (response) {
          // response.data === 1 is valid
          irbCheckStatus.value = 'checked'
          complianceSettings.value = response.data
          //console.log("checkIrbResponse")
          //console.log(response)
          irbCheckMessage.value = ""
          if (response.data.irb_status) {
            // if dpa has a valid irb, use the irb instead
            // do we need to inform the user that we're doing this?
            if (projectIrbNumber.startsWith('DPA') && complianceSettings.value.dpa.protocolNum) {
              irbCheckMessage.value = irbOrDpaStr(complianceSettings.value.dpa.protocolNumStr) +
                  " will be used in place of " + projectIrbNumber + ".  "
              projectIrbNumber = complianceSettings.value.dpa.protocolNumStr
            }
            projectConfig.project_irb_number = projectIrbNumber;
            //console.log('irb = ' + projectConfig.project_irb_number)
            const privacyIssues = checkPrivacyAttestion()
            if (privacyIssues.length > 0) {
              irbCheckMessage.value += irbOrDpaStr(projectIrbNumber) + " is valid.<br><br>"
                  + privacyIssues
            } else {
              irbCheckMessage.value += irbOrDpaStr(projectIrbNumber) + " is valid.<br>Fetching DUSTER Metadata ..."
              irbWarningConfirmed.value = true
            }
            irbValid.value = true
          } else {
            if (typeof response.data == 'string' && response.data.toLowerCase().includes("fatal error")) {
              systemError.value = true;
              let errorFormData = new FormData();
              errorFormData.append('redcap_csrf_token', redcapCsrfToken);
              errorFormData.append('fatal_error', response.data);
              axios.post(projectConfig.report_fatal_error_url, errorFormData)
                  .then(function (response) {

                  })
                  .catch(function (error) {

                  });

            } else {
              irbCheckMessage.value += irbOrDpaStr(projectIrbNumber)
                  + " is invalid. Please enter a different IRB or DPA. (DPAs must start with \"DPA-\")";
            }
            irbValid.value = false
          }

        })
        .catch(function (error) {
          irbValid.value = false;
          irbCheckMessage.value = "IRB Check Error";
          systemError.value = true;
          console.log(error);
        });
  } else {
    irbCheckMessage.value =
        "You must have an IRB or DPA to use DUSTER. Please enter a valid IRB or DPA. (DPAs must start with \"DPA-\")";
    irbValid.value = false
    irbCheckStatus.value = 'checked'
    irbCheckVisible.value = true
  }
}

const irbRetry = () => {
  irbCheckStatus.value = "retry";
  irbCheckMessage.value = "Checking " + irbOrDpaStr(projectIrb.value) + " ...";
  checkIrb(projectConfig.check_irb_url,
      projectConfig.redcap_csrf_token, projectIrb.value,
      projectConfig.redcap_user);
};

const irbCheckCancel = () => {
  irbCheckVisible.value = false;
  // return to project create page for invalid IRBs
  if (!irbValid.value || !irbWarningConfirmed.value) {
    window.location.href = projectConfig.redcap_new_project_url;
  }
};

const getDusterMetadata = (metadataUrl:string) => {
  if (dev.value) {
    demographicsOptions.value = sortDemographics(resp.data.demographics);
    demographicsOptions.value = demographicsOptions.value.map((demographic) => ({...demographic, edit:true}));
    labOptions.value = resp.data.labs;
    vitalOptions.value = resp.data.vitals;
    outcomeOptions.value = resp.data.outcomes;
    scoreOptions.value = resp.data.scores;
    clinicalDateOptions.value = resp.data.clinical_dates;
  } else {
    axios.get(metadataUrl)
      .then(function (response) {
        demographicsOptions.value = sortDemographics(response.data.demographics);
        labOptions.value = response.data.labs;
        vitalOptions.value = response.data.vitals;
        outcomeOptions.value = response.data.outcomes;
        scoreOptions.value = response.data.scores;
        clinicalDateOptions.value = response.data.clinical_dates;
        irbCheckVisible.value = false;

        //  fetch dataset designs
        axios.get(projectConfig.get_dataset_designs_url)
          .then(function (response) {
            const designs = response.data;
            // prompt to restore auto-save
            if (projectConfig.edit_mode === false && designs.hasOwnProperty('auto-save') === true) {
              autoSaveDesign.value = JSON.parse(designs['auto-save']);
              promptRestoreAutoSave.value = true;
            } else if (projectConfig.edit_mode === true) {
              loadEditMode();
            }
          })
          .catch(function (error) {

          });
      }).catch(function (error) {
        irbCheckMessage.value = "Unable to load DUSTER metadata";
        systemError.value = true;
    });
  }
};

const loadEditMode = () => {
  initialDesign.value = JSON.parse(projectConfig.initial_design);
  initialDesign.collectionWindows = initialDesign.value.collectionWindows;
  initialDesign.value = JSON.parse(projectConfig.initial_design);
  // transform and load researcher-provided data
  rpData.value = initialDesign.value.rpData;
  rpData.value.forEach((rp:any) => {
    rp.edit = false;
  });

  // load demographics
  let initialDemographicsSelects = initialDesign.value.demographicsSelects.map((selected:any) => selected.duster_field_name);
  if (Array.isArray(demographicsOptions.value)) {
    demographicsOptions.value.forEach(demographic => {
      if (initialDemographicsSelects.includes(demographic.duster_field_name)) {
        demographic.edit = false;
        demographicsSelects.value.push(demographic);
      }
    });
  }

  // load data collection windows
  collectionWindows.value = initialDesign.value.collectionWindows;
};

const checkForRpDateChanges = () => {
  collectionWindows.value.forEach(cw => {
    // for start and end configurations, check that rp_date is still configured
    if (cw.timing.start.type !== 'interval') {
      cw.timing_valid = (rpDates.value.findIndex(rpDate => rpDate.redcap_field_name ===
          cw.timing.start.rp_date) !== -1)
    }
    if (cw.timing_valid && cw.timing.end.type !== 'interval') {
      cw.timing_valid = (rpDates.value.findIndex(rpDate => rpDate.redcap_field_name ===
          cw.timing.end.rp_date) !== -1)
    }
    // for events, check that rp_date is still configured and also a datetime
    if (cw.event && cw.event[0] && cw.event[0].redcap_field_name) {
      const index = rpDates.value.findIndex(rpDate =>
          // @ts-ignore
          rpDate.redcap_field_name == cw.event[0].redcap_field_name )
      if (index != -1) {
        cw.data.valid = (rpDates.value[index].value_type == 'datetime')
      } else {
        cw.data.valid = false
      }
    }
  })
};

const toast = useToast();

// tracks all redcap field names to ensure uniqueness
const reservedFieldNames = computed(() => {
  // reserve the demographics names?
  if (demographicsOptions.value && demographicsOptions.value.length > 0) {
    return demographicsOptions.value.map(demo => demo.duster_field_name)
  }
  return []
})

const confirm = useConfirm();

const exitFromDuster = (event: any) => {
  //console.log('exit from duster invoked') ;
  confirm.require({
    target: event.currentTarget,
    header: 'Back to REDCap New Project Page',
    message: 'You will exit DUSTER\'s New Project Designer and will lose any changes made here. Are you sure you want to exit?',
    accept: () => {
      window.history.go(-1) ;
    }
  });
};

const v$ = useVuelidate();

const checkValidation = () => {
  checkForRpDateChanges()
  v$.value.$touch()

  toast.removeAllGroups()
  if (!v$.value.$error) {
    showSummary.value = true;

    // auto-save dataset design
    if (projectConfig.edit_mode === false) {
      saveDatasetDesign('auto-save');
    }
  } else {
    //console.log(v$)
    v$.value.$errors.forEach(error => {
      if (typeof error.$message === 'object') {
        // @ts-ignore
        error.$message.forEach(msgs => {
          msgs.forEach((msg: string) => {
            toast.add({
              severity: 'error',
              summary: 'Error',
              detail: msg,
              life: 3000
            });
          });
        });
      } else {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: error.$message,
          life: 3000
        })
      }
    });
  }
  return false;
};

/**
 * Saves current dataset design in STARR-API
 * @param title
 * @param pid
 */
const saveDatasetDesign = (title: string) => {
  let saveDesignForm = new FormData();
  saveDesignForm.append('redcap_csrf_token', projectConfig.redcap_csrf_token);
  saveDesignForm.append('title', title);
  const designObj = {
    rpData: rpData.value,
    demographicsSelects: demographicsSelects.value,
    collectionWindows: collectionWindows.value
  };
  saveDesignForm.append('design', JSON.stringify(designObj));
  axios.post(projectConfig.save_dataset_design_url, saveDesignForm)
    .then(function (response) {
    })
    .catch(function (error) {
    });
}

/**
 * Loads the auto-saved dataset design
 */
const restoreAutoSaveDesign = () => {
  rpData.value = autoSaveDesign.value.rpData;
  demographicsSelects.value = autoSaveDesign.value.demographicsSelects;
  collectionWindows.value = autoSaveDesign.value.collectionWindows;
  promptRestoreAutoSave.value = false;
}

/**
 * Deletes the auto-saved dataset design in STARR-API
 */
const deleteAutoSaveDesign = () => {
  autoSaveDesign.value = null;
  deleteDatasetDesign('auto-save');
}

/**
 * Deletes a dataset design in STARR-API
 * @param title
 */
const deleteDatasetDesign = (title: string) => {
  let deleteDesignForm = new FormData();
  deleteDesignForm.append('redcap_csrf_token', projectConfig.redcap_csrf_token);
  deleteDesignForm.append('title', title);
  axios.post(projectConfig.delete_dataset_design_url, deleteDesignForm)
    .then(function (response) {

    })
    .catch(function (error) {

    });
}

/**
 * sort demographics metadata by subcategories and alphabetical order
 * @param demographics
 */
const sortDemographics = (demographics:any) => {
  let sorted:any[] = JSON.parse(JSON.stringify(demographics));

  // sort name and date options together
  sorted.forEach(option => {
    if (option.label.toLowerCase().indexOf("date") > -1) {
      option['group'] = 1
    } else if (option.label.toLowerCase().indexOf("name") > -1) {
      option['group'] = 2
    } else {
      option['group'] = 3
    }
  })

  // sort group, then alphabetically by label
  sorted.sort(function (a: any, b: any) {
    let x = a.label.toLowerCase()
    let y = b.label.toLowerCase()
    if (a.group < b.group) return -1
    if (a.group > b.group) return 1
    if (x < y) return -1;
    if (x > y) return 1;
    return 0;
  });

  return sorted;
}

</script>

<style scoped lang="scss">
nav {
  padding: 10px;
  text-align: center;
}
.brand-logo {
  position: relative;
  z-index: 10;
  float: left;
  display: block;
  width: 12em;
  height: 3em;
  margin-right: 10px;
  margin-right: .7em;
  text-indent: -9999px;
  background: url(@assets/images/logo_uid_stanfordmedicine.svg) no-repeat;
  background-position: -11px -1px;
  background-position: -0.7857142857142857rem -0.07142857142857142rem;
  background-size: auto 111%;
  border-right: 1px solid;
  border-right: .07142857142857142rem solid;
  border-right-color: #000;
}
</style>

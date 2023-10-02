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
              <a href="https://med.stanford.edu/duster" class="text-white" target="_blank">DUSTER Website</a>
            </div>
          </div>
        </nav>

        <div v-show="!showSummary">
          <!--<p>Define your project.</p>-->
          <div class="grid">
            <div class="col-6">
              <ResearcherProvidedPanel
                  v-model:rp-provided-data="rpProvidedData"
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
              />
            </div>
          </div>
          <div class="grid">
            <div class="col">
              <Toast />
              <Toolbar class="col">
                <template #start>
                  <Button label="Back to REDCap New Project Page" icon="pi pi-cross" severity="secondary" class="ml-2" @click="exitFromDuster($event)"/>
                </template>
                <template #end>
                  <Button type="submit" label="Review & Create Project" icon="pi pi-check"
                          class="ml-2"
                          @click="checkValidation"/>
                </template>
              </Toolbar>
            </div>
          </div>
        </div>

        <div :style="(showSummary) ?  '': 'display: none !important'">
          <ReviewPanel
              v-model:show-summary="showSummary"
              :rp-identifiers="rpIdentifiers"
              :rp-dates="rpDates"
              :demographics="demographicsSelects"
              :collection-windows="collectionWindows"
              :project-info="projectConfig"
              :dev="dev"
          />
        </div>
      </div>
    </div>
  </div>
  <Dialog v-model:visible="irbCheckVisible"
          modal header="Checking IRB"
          :style="{ width: '40vw' }"
          :closable=false>
    <p>
      <span v-html="irbCheckMessage"></span>
    </p>
    <div v-if="!irbValid && irbCheckStatus==='checked'" class="m-2">
      <label>IRB Number: </label>
      <InputText v-model="projectIrb"/>
    </div>
    <template #footer>
      <Button v-if="!irbValid && irbCheckStatus==='checked'"
              label="Submit" icon="pi pi-refresh" class="p-button-primary" @click="irbRetry" size="small"/>
      <Button label="Cancel" icon="pi pi-times" class="p-button-secondary" @click="irbCheckCancel" size="small" />
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
import {computed, ref, onMounted, watch} from 'vue'
import SystemErrorDialog from '@shared/components/SystemErrorDialog.vue'
import { useConfirm } from "primevue/useconfirm";

import axios from 'axios'
import type FieldMetadata from "@/types/FieldMetadata";
import type {BasicConfig} from "@/types/FieldConfig";
import type CollectionWindow from "@/types/CollectionWindow";

import ResearcherProvidedPanel from "@/components/ResearcherProvidedPanel.vue";
import DemographicsPanel from '@/components/DemographicsPanel.vue'
import CollectionWindowsPanel from '@/components/CollectionWindowsPanel.vue'
import ReviewPanel from '@/components/ReviewPanel.vue'

// for testing
import resp from './dusterTestMetadata.json';
import {useToast} from "primevue/usetoast";
import Toast from 'primevue/toast'
import {useVuelidate} from "@vuelidate/core";

const projectConfig = JSON.parse(localStorage.getItem('postObj') || '{}');
//console.log("postObj" + localStorage.getItem('postObj'))
localStorage.removeItem('postObj');

setInterval(() => {
  axios.get(projectConfig.refresh_session_url)
      .then(response => {
        console.log(response);
      }).catch(function (error) {
    console.log(error)
  });
},60000);

const dev = ref<boolean>(false)
const systemError = ref<boolean>(false)

const showSummary = ref<boolean>(false)

const rpProvidedData = ref<BasicConfig[]>([
  {
    redcap_field_name: "mrn",
    label:"Medical Record Number (MRN)",
    redcap_field_type:"text",
    value_type:"Identifier", // this needs to be replace by "text" in review step
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
  }])

// separating out identifiers and dates for review step
const rpIdentifiers = computed(() => {
  return rpProvidedData.value.filter((rpi:BasicConfig) => rpi.value_type?.toLowerCase() === 'identifier')
})
const rpDates = computed(() => {
  return rpProvidedData.value.filter((rpi:BasicConfig) => rpi.value_type?.toLowerCase() !== 'identifier')
})

const demographicsOptions = ref<FieldMetadata[]>([])
const labOptions = ref<FieldMetadata[]>([])
const vitalOptions = ref<FieldMetadata[]>([])
const outcomeOptions = ref<FieldMetadata[]>([])
const scoreOptions = ref<FieldMetadata[]>([])
const clinicalDateOptions = ref<FieldMetadata[]>([])

const demographicsSelects = ref<FieldMetadata[]>([])
const collectionWindows = ref<CollectionWindow[]>([])

const projectIrb = ref<string>(projectConfig.project_irb_number)
const irbValid = ref<boolean>(false)
const irbCheckStatus = ref<string>("checking")
const irbCheckMessage = ref<string>("Checking IRB #" + projectIrb.value + " ...")
const irbCheckVisible = ref<boolean>(false)

onMounted(() => {
  // check irb
  checkIrb(projectConfig.check_irb_url, projectConfig.redcap_csrf_token, projectConfig.project_irb_number)
})

watch(irbValid, (irbValidUpdate) => {
  if (irbValidUpdate) {
    getDusterMetadata(projectConfig.metadata_url)
  }
})

const checkIrb = (checkIrbUrl:string, redcapCsrfToken: string, projectIrbNumber: string) => {
  if (dev.value) {
    irbValid.value = true
  } else {
    irbCheckVisible.value = true
    let formData = new FormData();
    formData.append('redcap_csrf_token', redcapCsrfToken);
    formData.append("project_irb_number", projectIrbNumber);
    axios.post(checkIrbUrl, formData)
        .then(function (response) {
          // response.data === 1 is valid
          irbCheckStatus.value = 'checked'
          if (response.data === 1) {
            irbValid.value = true
            irbCheckMessage.value = "IRB " + projectIrbNumber + " check success.  Fetching DUSTER metadata."
            projectConfig.project_irb_number = projectIrbNumber
          } else {
            irbValid.value = false
            irbCheckMessage.value = "IRB " + projectIrbNumber
                + " is invalid. Please enter a different IRB number."
          }

        })
        .catch(function (error) {
          irbValid.value = false
          irbCheckMessage.value = "IRB Check Error"
          systemError.value = true ;
          console.log(error)
        });
  }
}

const irbRetry = () => {
  irbCheckStatus.value = "retry"
  irbCheckMessage.value = "Checking IRB #" + projectIrb.value + " ..."
  checkIrb(projectConfig.check_irb_url, projectConfig.redcap_csrf_token, projectIrb.value)
}

const irbCheckCancel = () => {
  irbCheckVisible.value = false
  // return to project create page for invalid IRBs
  if (!irbValid.value) {
    window.location.href = projectConfig.redcap_new_project_url
  }
}

const getDusterMetadata = (metadataUrl:string) => {
  if (dev.value) {
    demographicsOptions.value = resp.data.demographics;
    labOptions.value = resp.data.labs;
    vitalOptions.value = resp.data.vitals;
    outcomeOptions.value = resp.data.outcomes;
    scoreOptions.value = resp.data.scores;
    clinicalDateOptions.value = resp.data.clinical_dates
  } else {

    axios.get(metadataUrl)
        .then(response => {
          demographicsOptions.value = response.data.demographics;
          labOptions.value = response.data.labs;
          vitalOptions.value = response.data.vitals;
          outcomeOptions.value = response.data.outcomes;
          scoreOptions.value = response.data.scores;
          clinicalDateOptions.value = response.data.clinical_dates
          irbCheckVisible.value = false
        }).catch(function (error) {
      irbCheckMessage.value = "Unable to load DUSTER metadata"
      systemError.value = true ;
      console.log(error)
    });
  }
}

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
}

/*const deleteRpDate = (rpDate:BasicConfig) => {
  rpProvidedData.value = rpProvidedData.value.filter(item => item.id !== rpDate.id)
}*/
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
  console.log('exit from duster invoked') ;
  confirm.require({
    target: event.currentTarget,
    header: 'Back to REDCap New Project Page',
    message: 'You will exit DUSTER\'s New Project Designer and will lose any changes made here. Are you sure you want to exit?',
    accept: () => {
      window.history.go(-1) ;
    }
  });
}

const v$ = useVuelidate()

const checkValidation = () => {
  checkForRpDateChanges()
  v$.value.$touch()

  toast.removeAllGroups()
  if (!v$.value.$error) {
    showSummary.value = true
  } else {
    console.log(v$)
    v$.value.$errors.forEach(error => {
          if (typeof error.$message === 'object') {
            // @ts-ignore
            error.$message.forEach(msgs =>
                msgs.forEach((msg: string) =>
                    toast.add({
                      severity: 'error',
                      summary: 'Error', detail: msg,
                      life: 3000
                    })
                )
            )
          } else {
            toast.add({
              severity: 'error',
              summary: 'Error', detail: error.$message,
              life: 3000
            })
          }
        }
    )
  }
  return false
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
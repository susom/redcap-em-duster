<template>
  <Panel header="Review Settings">
  <Panel header="Researcher Provided Data">
    <DataTable :value="rpIdentifiers"  tableStyle="min-width: 50rem">
      <template #header>
        <div class="flex flex-wrap align-items-center justify-content-between gap-2">
          <span class="text-sm text-900 font-bold">Identifiers</span>
        </div>
      </template>
      <Column field="label" header="Label"></Column>
      <Column field="redcap_field_name" header="REDCap Field Name"></Column>
      <Column field="redcap_field_note" header="Value Type"></Column>
    </DataTable>

    <DataTable :value="rpDates"  tableStyle="min-width: 50rem mt-2">
      <template #header>
        <div class="flex flex-wrap align-items-center justify-content-between gap-2">
          <span class="text-sm text-900 font-bold">Timing</span>
        </div>
      </template>
      <Column field="label" header="Label"></Column>
      <Column field="redcap_field_name" header="REDCap Field Name"></Column>
      <Column field="value_type" header="Value Type"></Column>
    </DataTable>
  </Panel>

  <Panel v-if="demographicsConfigs.length > 0" header="Demographics" class="mt-2">
    <DataTable :value="demographicsConfigs"  tableStyle="min-width: 50rem">
      <Column field="label" header="Label"></Column>
      <Column field="redcap_field_name" header="REDCap Field Name"></Column>
      <Column field="redcap_field_type" header="REDCap Field Type"></Column>
    </DataTable>
  </Panel>

  <Panel v-for="cw in cwConfigs" :key="cw.form_name" :header="cw.label" class="mt-2">
    <DataTable :value="getTimingCols(cw.timing, cw.event)"  class="mt-2"
               tableStyle="min-width: 50rem">
      <template #header>
        <div class="flex flex-wrap align-items-center justify-content-between gap-2">
          <span class="text-0 text-900 font-bold">Timing</span>
        </div>
      </template>
      <Column field="event" header="Date"></Column>
      <Column field="label" header="Label"></Column>
      <Column field="redcap_field_name" header="REDCap Field Name"></Column>
      <Column field="type" header="Type"></Column>
      <Column field="redcap_field_type" header="REDCap Field Type"></Column>
    </DataTable>

    <DataTable v-if="cw.timing.repeat_interval"
               :value="getRepeatCols(cw.timing)"  class="mt-2"
               tableStyle="min-width: 50rem">
      <template #header>
        <div class="flex flex-wrap align-items-center justify-content-between gap-2">
          <span class="text-0 text-900 font-bold">Repeat Instance</span>
        </div>
      </template>
      <Column field="event" header="Date"></Column>
      <Column field="label" header="Label"></Column>
      <Column field="redcap_field_name" header="REDCap Field Name"></Column>
      <Column field="type" header="Type"></Column>
      <Column field="redcap_field_type" header="REDCap Field Type"></Column>
      <!--Column field="label" header="Label"></Column>
      <Column field="type" header="Type"></Column>
      <Column field="length" header="Length"></Column>
      <Column field="redcap_field_name" header="REDCap Field Name"></Column-->
    </DataTable>

    <DataTable :value="cw.data.labs" class="mt-2"
               tableStyle="min-width: 50rem"
                v-if="cw.data.labs.length > 0"
    >
      <template #header>
        <div class="flex flex-wrap align-items-center justify-content-between gap-2">
          <span class="text-0 text-900 font-bold">Labs</span>
        </div>
      </template>
      <Column field="label" header="Label"></Column>
      <Column field="redcap_field_name" header="REDCap Field Name"></Column>
      <Column field="redcap_field_note" header="REDCap Field Note"></Column>
    </DataTable>

    <DataTable :value="cw.data.vitals" class="mt-2"
               tableStyle="min-width: 50rem"
               v-if="cw.data.vitals.length > 0">
    <template #header>
      <div class="flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="text-0 text-900 font-bold">Vitals</span>
      </div>
    </template>
    <Column field="label" header="Label"></Column>
    <Column field="redcap_field_name" header="REDCap Field Name"></Column>
    <Column field="redcap_field_note" header="REDCap Field Note"></Column>
  </DataTable>

  <DataTable :value="cw.data.outcomes" class="mt-2"
             tableStyle="min-width: 50rem"
             v-if="cw.data.outcomes.length > 0">
    <template #header>
      <div class="flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="text-0 text-900 font-bold">Outcomes</span>
      </div>
    </template>
    <Column field="label" header="Label"></Column>
    <Column field="redcap_field_name" header="REDCap Field Name"></Column>
    <Column field="redcap_field_type" header="REDCap Field Type"></Column>
    <Column field="redcap_options" header="REDCap Options"></Column>
  </DataTable>

    <!-- single table version of outcomes -->
    <!--ScoreSummaryTable
        v-if="cw.data.scores.length > 0"
        v-model:scores="cw.data.scores"
      class="mt-2"
    />
    <hr/-->

<!-- separate tables for each score -->
    <!--this version uses row grouping w/ Jonasel's score fields-->

<div v-for="(score,index) in cw.data.scores" :key="score.duster_field_name">
  <ScoreSummaryTablePerScore
    :score="getScoreFields(score)"
    :score-label="cw.data.scores[index].label"
    class="mt-2"
  />

</div>



  </Panel>
    <Toolbar>
      <template #start>
        <Button label="Back" icon="pi pi-angle-left"  @click="visible=false"/>
      </template>
      <template #end>
      <Button label="Create Project" icon="pi pi-check" severity="success" @click="createProject"/>
      </template>
    </Toolbar>
  </Panel>
  <Dialog v-model:visible="showCreateProjectDialog"
          modal :style="{ width: '50vw' }"
          header="Create Project">
      <div :class="['my-3',{'p-error': createProjectError}]">
      {{createProjectMessage}}
      </div>
    <template #footer>
      <Button label="Close" icon="pi pi-times"  @click="showCreateProjectDialog=false"/>
    </template>
  </Dialog>
  <SystemErrorDialog
    v-model:error-message="systemErrorMessage"
    v-if="systemErrorFlag===true"/>
</template>

<script setup lang="ts">
import {capitalize, computed, ref, watch} from "vue";
import type {PropType} from "vue";
import type CollectionWindow from "@/types/CollectionWindow";
import {INIT_TIMING_CONFIG} from "@/types/TimingConfig";
import type TimingConfig from "@/types/TimingConfig";
import type FieldMetadata from "@/types/FieldMetadata";
import type FieldConfig from "@/types/FieldConfig";
import type TextValuePair from "@/types/TextValuePair";
import type Subscore from "@/types/Subscore";
import type {SubscoreDependency} from "@/types/Subscore";
import {AGGREGATE_OPTIONS} from "@/types/FieldConfig";
import ScoreSummaryTablePerScore from "@/components/ScoreSummaryTablePerScore.vue";
import axios from "axios";
import SystemErrorDialog from "@shared/components/SystemErrorDialog.vue";

const props = defineProps({
  dev: Boolean,
  showSummary: {
    type: Boolean,
    required: true
  },
  rpIdentifiers: {
    type: Array as PropType<Array<FieldConfig>>,
    required: true
  },
  rpDates: {
    type: Array as PropType<Array<FieldConfig>>,
    required: true
  },
  demographics: {
    type: Array as PropType<Array<FieldMetadata>>,
    required: true
  },
  collectionWindows: {
    type: Array as PropType<Array<CollectionWindow>>,
    required: true
  },
  projectInfo: {
    type: Object,
    required: true
  }

})

const emit = defineEmits(['update:showSummary', 'delete-auto-save']);

const visible = computed<boolean>({
  get(){
  return props.showSummary
  },
  set(value){
    emit("update:showSummary", value)
  }
})

// operate on copies of the data so it's not always reacting when the data is being edited
const cwsCopy = ref<CollectionWindow[]>([])
const rpDatesCopy = ref<FieldConfig[]>([])
const demographicsCopy = ref<FieldMetadata[]>([])
watch(visible, (isVisible) => {
  if (isVisible) {
    rpDatesCopy.value = JSON.parse(JSON.stringify(props.rpDates))
    demographicsCopy.value = JSON.parse(JSON.stringify(props.demographics))
    cwsCopy.value = JSON.parse(JSON.stringify(props.collectionWindows))
  }
})

const rpDataConfigs = computed<any> (()=>{
  const rpInfo:any = {rp_identifiers:{}, rp_dates:{}}
  rpInfo['rp_identifiers'] = JSON.parse(JSON.stringify(props.rpIdentifiers))
  for (let rpdate of rpDatesCopy.value) {
    if (rpdate.redcap_field_name) {
      rpInfo.rp_dates[rpdate.redcap_field_name] = {
        label: rpdate.label,
        redcap_field_name: rpdate.redcap_field_name,
        redcap_field_type: rpdate.redcap_field_type,
        value_type: rpdate.value_type,
        phi: rpdate.phi
      }
    }
  }
  return rpInfo
})

const demographicsConfigs = computed<FieldConfig[]>(()=>{
  const demographics: FieldConfig[] = []
  if (demographicsCopy.value) {
    demographicsCopy.value.forEach(demo => demographics.push({
      duster_field_name: demo.duster_field_name,
      redcap_field_name: demo.duster_field_name,
      redcap_field_type: demo.redcap_field_type,
      label: demo.label,
      value_type: demo.value_type,
      phi: demo.phi
    }))
  }
  return demographics
})

const cwConfigs = computed<CollectionWindow[]>(()=>{
  const configs: CollectionWindow[] = []
  if (cwsCopy.value) {
    cwsCopy.value.forEach((cw, index) => {
      let config: any = {
        type: cw.type,
        label: cw.label,
        form_name: getCwLabel(index, cw.label),
        timing: getTiming(cw.timing, index),
        event: (cw.event) ? getEvent(cw.event, index) : []
      }
      const closestTime = (cw.closest_time) ? cw.closest_time + ":00": undefined
      config.data = getData(cw.data, index, cw.aggregate_defaults, config.event, closestTime)
      configs.push(config)
    })
  }
  return configs
})

const getCwLabel=(index: number, label:string)=> {
    // remove whitespace at start and end and convert to lowercase characters only
    let formName =  "cw" + index + "_" + label.trim().toLowerCase()
        .replace(/ +/g, '_') // replace spaces with underscore
        .replace(/[^a-z_0-9]/g, '') // remove illegal characters
        .replace(/[_+]/g, '_') // replace multiple _ with a single one
        .replace(/_$/g, '') // remove trailing _
    // if longer than 50 characters, substring formName to 50 characters
    formName = formName.substring(0, 50);
    // removed check for duplicates since cw prefix should make them unique
  return formName;
}

// for display in table
const getTimingCols = (timingObj:any, events:any) => {
  let cols = []
  cols.push({
    event: "Start",
    label: timingObj.start.label,
    redcap_field_name: timingObj.start.redcap_field_name,
    redcap_field_type: timingObj.start.redcap_field_type,
    value_type: timingObj.start.value_type,
    type: timingObj.start.type

  })
  cols.push({
    event: "End",
    label: timingObj.end.label,
    redcap_field_name: timingObj.end.redcap_field_name,
    redcap_field_type: timingObj.end.redcap_field_type,
    value_type: timingObj.start.value_type,
    type: timingObj.start.type
  })
  if (events) {
    events.forEach((event:any) => {
      cols.push({
        event: "Closest to Event",
        label: event.label,
        redcap_field_name: event.redcap_field_name,
        redcap_field_type: event.redcap_field_type,
        value_type: event.value_type,
        type: event.type
      })
    })
  }
  return cols
}

const getRepeatCols=(timingObj:any)=> {
  if (timingObj.repeat_interval) {
    return [{
      event: "Instance Start",
      label: "Instance Start Datetime - " + timingObj.repeat_interval.label,
      redcap_field_name: getIntervalFieldName(timingObj.start.redcap_field_name),
      redcap_field_type: "text",
      type: "datetime"
    },
    {
      event: "Interval End",
      label: "Interval End Datetime - " + timingObj.repeat_interval.label,
      redcap_field_name: getIntervalFieldName(timingObj.end.redcap_field_name),
      redcap_field_type: "text",
      type: "datetime"
    }]
  }

}

const getTiming=(timing:any, index:number) => {
  let tconfig: any = {
    start: getTimingConfig(timing.start, index, 'start'),
    end: getTimingConfig(timing.end, index, 'end')
  }
  if (timing.repeat_interval && timing.repeat_interval.type) {
    tconfig.repeat_interval = {...timing.repeat_interval}
    tconfig.repeat_interval['start_instance'] = getRepeatInstanceTimingConfig(tconfig, 'start')
    tconfig.repeat_interval['end_instance'] = getRepeatInstanceTimingConfig(tconfig, 'end')
  }
  return tconfig
}

const getEvent = (events:TimingConfig[], index:number) => {
  let eventArr:any[] = []
  if (events) {
    events.forEach((evt, eventIndex) => {
        if (evt.type) {
        eventArr.push(getTimingConfig(evt, index, 'closest_event' + eventIndex))
        }
    })
  }
  return eventArr
}

const getRepeatInstanceTimingConfig = (timing:any, eventType:string) => {
  return {
    label: "Instance " + capitalize(eventType) + " Datetime - " + timing.repeat_interval.label,
    redcap_field_name: getIntervalFieldName(timing[eventType].redcap_field_name),
    phi: "t",
    redcap_field_type: "text",
    value_type: "datetime"
  }
}

const getTimingConfig = (timing:TimingConfig, index: number, eventType:string) => {
  let tconfig: any = {
    type: timing.type,
    label: timing.label,
    redcap_field_name: 'cw' + index + "_" + eventType + "_datetime",
    phi: "t",
    redcap_field_type: "text"
  }

  if (timing.type == 'interval' && timing.interval) {
    tconfig.interval={}
    tconfig.interval.type = timing.interval.type
    tconfig.interval.length = timing.interval.length
    tconfig.value_type = "datetime"
  } else {
    tconfig.value_type= timing.value_type
    if (timing.duster_field_name) {
      tconfig.duster_field_name = timing.duster_field_name
      tconfig.rp_date = timing.rp_date
    } else {
      // get the rp_date label
      let rpIndex = rpDatesCopy.value.findIndex(rpDate => rpDate.redcap_field_name == timing.rp_date)
      if (rpIndex > -1) {
        tconfig.rp_date = timing.redcap_field_name
        tconfig.label = rpDatesCopy.value[rpIndex].label
        tconfig.duster_field_name = null
      }
    }
  }
  return tconfig
}

const getIntervalFieldName = (eventName: string) => {
  if (eventName) {
    const insertIndex = eventName.indexOf("_datetime")
    return eventName.substring(0, insertIndex) + "_interval" + eventName.substring(insertIndex);
  }
  return ""
}

const getData = (data:any, index:number, aggDefaults?: TextValuePair[], event?:TimingConfig[], closestTime?:string) => {
  let dconfig:any = {}
  dconfig.labs = getConfigWithAggregates(data.labs, index, aggDefaults, event, closestTime)
  dconfig.vitals = getConfigWithAggregates(data.vitals, index, aggDefaults, event, closestTime)
  dconfig.outcomes = getConfigNoAggregates(data.outcomes, index)
  dconfig.scores = getScoresConfig(data.scores, index)
  return dconfig
}

const getConfigWithAggregates = (data:FieldMetadata[],
                                 index:number,
                                 aggDefaults?: TextValuePair[],
                                 event?:TimingConfig[],
                                 closestTime?:string) =>{
  let configArray:FieldConfig[] = []
  let evt = (event && event[0]) ? event[0] : INIT_TIMING_CONFIG
  for (let fieldMetadata of data) {
    let aggregates:TextValuePair[] = (fieldMetadata.aggregates && fieldMetadata.aggregates.length > 0)
        ? fieldMetadata.aggregates:
        (aggDefaults) ? aggDefaults: []
    for (let agg of aggregates) {
      let aggName = agg['value'].replace('_agg','')
      let config:any ={
        label: getAggregateLabel(fieldMetadata.label, agg.value, evt, closestTime),
        duster_field_name: fieldMetadata.duster_field_name,
        redcap_field_name: fieldMetadata.duster_field_name + "_" + aggName + "_" + index,
        value_type: fieldMetadata.value_type,
        redcap_field_type: fieldMetadata.redcap_field_type,
        redcap_options: fieldMetadata.redcap_options,
        redcap_field_note: fieldMetadata.redcap_field_note,
        aggregate: agg.value
      }
      if (agg.value == 'closest_event' && evt) {
          config.aggregate_options = {}
          config.aggregate_options.event =evt.redcap_field_name
        }

      if (agg.value == 'closest_time') {
        config.aggregate_options = {}
        config.aggregate_options.time = closestTime
        }
      configArray.push(config)
    }
  }
  return configArray
}

const getConfigNoAggregates = (data:FieldMetadata[], index:number) =>{
  let configArray:FieldConfig[] = []
  for (let fieldMetadata of data) {
      configArray.push({
        label: fieldMetadata.label,
        duster_field_name: fieldMetadata.duster_field_name,
        redcap_field_name: fieldMetadata.duster_field_name + "_" + index,
        value_type: fieldMetadata.value_type,
        redcap_field_type: fieldMetadata.redcap_field_type,
        redcap_options: fieldMetadata.redcap_options,
        redcap_field_note: fieldMetadata.redcap_field_note
      })
    }
  return configArray
}

const getAggregateLabel = (varLabel:string, aggregate: string, event?:TimingConfig, closestTime?: string) => {
  let aggLabelIndex = AGGREGATE_OPTIONS.findIndex(option => option.value == aggregate)
  let aggLabel = AGGREGATE_OPTIONS[aggLabelIndex].text

  if (aggregate == 'closest_event' && event) {
    return varLabel + " " + aggLabel
    /*
add the event label to closest to event label
works but label is long and messy
  let rpIndex = rpDatesCopy.value.findIndex(rpDate => rpDate.redcap_field_name ==
      event.rp_date)
  label += " " + rpDatesCopy.value[rpIndex].label
*/
  }
  if (aggregate == 'closest_time') {
    return varLabel + " " + aggLabel + " " + closestTime
  }
  return aggLabel + " " + varLabel
}

const getScoreFields = (score:any) => {
  let fieldsArr = [];
  if (score.subscores) {
    score.subscores.forEach((subscore:any) => {
      if (subscore.dependencies) {
        subscore.dependencies.forEach((clinicalVar:SubscoreDependency) => {
          fieldsArr.push({
            label: clinicalVar.label,
            redcap_field_name: clinicalVar.redcap_field_name,
            category: subscore.label
          });
        });
      }
      fieldsArr.push({
        label: subscore.label,
        redcap_field_name: subscore.redcap_field_name,
        category: subscore.label
      });
    });
    fieldsArr.push({
      label: score.label,
      redcap_field_name: score.redcap_field_name,
      category: score.label,
    });
  }
  return fieldsArr;
}

const getScoresConfig = (scoresMeta:FieldMetadata[], index:number) => {
  const scoresArr:any = [];
  scoresMeta.forEach((score) => {
    const subscoresArr:Subscore[] = [];
    let scoreCalculation = score.redcap_options;
    if (score.subscores) {
      score.subscores.forEach((subscore) => {

        const clinicalVarArr: any = [];
        let subscoreCalculation = subscore.redcap_options;
        if (subscore.dependencies) {
          subscore.dependencies.forEach((clinicalVar) => {
          if (clinicalVar.aggregates) {
            clinicalVar.aggregates.forEach((agg) => {
              let clinicalVarRCFieldName = subscore.duster_field_name + '_'
                  + clinicalVar.duster_field_name + '_'
                  + agg.replace("_agg", "_")
                  + index;
              let regExp = new RegExp('\\[' +
                  clinicalVar.duster_field_name + '_'
                  + agg.replace("_agg", "") + '\\]','g');  // regex pattern string
              subscoreCalculation = (subscoreCalculation && subscoreCalculation.length > 0)?
                  subscoreCalculation.replace(regExp,
                  '[' + clinicalVarRCFieldName + ']') :
                  subscoreCalculation;

              clinicalVarArr.push({
                duster_field_name: clinicalVar.duster_field_name,
                redcap_field_name: clinicalVarRCFieldName,
                // will need to change this in the future, for now assume subscores not dependent on closest time
                label: getAggregateLabel((clinicalVar.label? clinicalVar.label : ""), agg),
                redcap_field_type: clinicalVar.redcap_field_type,
                redcap_options: clinicalVar.redcap_options,
                value_type: clinicalVar.value_type,
                redcap_field_note: clinicalVar.redcap_field_note,
                aggregate: agg
              });
            });
          } else {
            let clinicalVarRCFieldName = subscore.duster_field_name + '_'
                + clinicalVar.duster_field_name + '_'
                + index;
            let regExp = new RegExp('\\[' + clinicalVar.duster_field_name + '\\]','g');
            subscoreCalculation = (subscoreCalculation && subscoreCalculation.length > 0) ?
                subscoreCalculation.replace(regExp, '[' +
            clinicalVarRCFieldName + ']'): subscoreCalculation;
            clinicalVarArr.push({
              duster_field_name: clinicalVar.duster_field_name,
              redcap_field_name: clinicalVarRCFieldName,
              label: clinicalVar.label,
              redcap_field_type: clinicalVar.redcap_field_type,
              redcap_options: clinicalVar.redcap_options,
              value_type: clinicalVar.value_type,
              redcap_field_note: clinicalVar.redcap_field_note
            });
          }
        });
      }
        let subscoreRCFieldName = subscore.duster_field_name + '_' + index;
        let regExp = new RegExp('\\[' + subscore.duster_field_name + '\\]','g');
        scoreCalculation = (scoreCalculation && scoreCalculation.length > 0) ?
            scoreCalculation.replace(regExp, '[' + subscoreRCFieldName + ']'):
            scoreCalculation;
        subscoresArr.push({
          duster_field_name: subscore.duster_field_name,
          redcap_field_name: subscore.duster_field_name + '_' + index,
          score_duster_field_name: score.duster_field_name,
          label: subscore.label,
          redcap_field_type: subscore.redcap_field_type,
          redcap_field_note: subscore.redcap_field_note,
          redcap_options: subscoreCalculation,
          value_type: subscore.value_type,
          dependencies: clinicalVarArr
        });
      });
    }

    scoresArr.push({
      duster_field_name: score.duster_field_name,
      redcap_field_name: score.duster_field_name + '_' + index,
      label: score.label,
      redcap_field_type: score.redcap_field_type,
      redcap_field_note: score.redcap_field_note,
      redcap_options: scoreCalculation,
      value_type: score.value_type,
      subscores: subscoresArr
    });
  });
  return scoresArr
}

const showCreateProjectDialog = ref<boolean>(false);
const createProjectMessage = ref<string>("");
const createProjectError = ref<boolean>(false);
const systemErrorMessage = ref<string>("");
const systemErrorFlag = ref<boolean>(false);

const getDusterConfig = () =>{
  return JSON.parse(JSON.stringify({
    rp_info: rpDataConfigs.value,
    demographics: demographicsConfigs.value,
    collection_windows: cwConfigs.value
  }))
}

const showSystemError = (message:string) => {
  showCreateProjectDialog.value = false;
  systemErrorMessage.value = message;
  systemErrorFlag.value = true;
}

const createProject = () => {
  createProjectMessage.value = "Creating REDCap Project. Please wait.";
  showCreateProjectDialog.value = true;
  if (!props.dev) {
    const data = {
      surveys_enabled: props.projectInfo.surveys_enabled,
      repeatforms: props.projectInfo.repeatforms,
      scheduling: props.projectInfo.scheduling,
      randomization: props.projectInfo.randomization,
      app_title: props.projectInfo.app_title,
      purpose: props.projectInfo.purpose,
      project_pi_firstname: props.projectInfo.project_pi_firstname,
      project_pi_mi: props.projectInfo.project_pi_mi,
      project_pi_lastname: props.projectInfo.project_pi_lastname,
      project_pi_email: props.projectInfo.project_pi_email,
      project_pi_alias: props.projectInfo.project_pi_alias,
      project_irb_number: props.projectInfo.project_irb_number,
      purpose_other: props.projectInfo.purpose_other,
      project_note: props.projectInfo.project_note,
      projecttype: props.projectInfo.projecttype,
      repeatforms_chk: props.projectInfo.repeatforms_chk,
      project_template_radio: props.projectInfo.project_template_radio,
      config: getDusterConfig()
    }
    let formData = new FormData()
    formData.append('redcap_csrf_token', props.projectInfo.redcap_csrf_token);
    formData.append('data', JSON.stringify(data));

    axios.post(props.projectInfo.create_project_url, formData)
      .then(function (response) {
        if (response.data.toLowerCase().includes("fatal error")) {
          /*
          createProjectMessage.value = "Oops";
          createProjectError.value = true;
          showCreateProjectDialog.value = true;
          */
          showSystemError("A project was not properly created and configured.");

          // send a report of the fatal error
          let errorFormData = new FormData();
          errorFormData.append('fatal_error', response.data);
          errorFormData.append('redcap_csrf_token', props.projectInfo.redcap_csrf_token);
          errorFormData.append('fatal_error', response.data);
          axios.post(props.projectInfo.report_fatal_error_url, errorFormData)
            .then(function (response) {

            })
            .catch(function (error) {

            });
        } else if (response.data.toLowerCase().indexOf('error') > -1) {
          showSystemError("A project was not properly created and configured.");
          /*
          console.log("Found Error");
          createProjectError.value = true;
          createProjectMessage.value = response.data;
          showCreateProjectDialog.value = true;
          */
        } else { // success
          emit('delete-auto-save');
          window.location.href = response.data;
        }
      })
      .catch(function (error) {
        if (error.response.status == 400 || error.response.status == 500) {
          switch (error.response.data) {
            // a project was created, but something went wrong during configuration
            case 'fail_project_post':
              showSystemError("A project was created, but it was not properly configured.");
              break;
            // a project could not be created
            case 'fail_project':
              showSystemError("A project was not created.");
              break;
            // something wrong happened
            default:
              showSystemError("A project was not properly created and configured.");
          }
        } else {
          showSystemError("A project was not properly created and configured.");
        }
        /*
        createProjectMessage.value = error.message;
        createProjectError.value = true;
        showCreateProjectDialog.value = true;
        console.log("Catch: " + error);

        console.log(error.toJSON());
        console.log(error.response);
        console.log(error.response.data);
        */
      });
  }
}

</script>

<style scoped>

</style>

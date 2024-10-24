<template>
  <Panel>
    <template #header>
      <span class="p-panel-title">Data Collection Windows
        <Button
          icon="pi pi-info-circle"
          text rounded
          aria-label="Info"
          class="ml-2 pt-0 pb-0 mt-0 mb-0"
          style="height:1.3em"
          @click="showDataCollectionInfo = true"
        />
        </span>
    </template>
    <DataTable
        editMode="row"
        class="p-datatable-sm"
        :value="localCollectionWindows"
        dataKey="id"
    >
      <Column
          key="timing_config"
          header="Timing"
          style="width: 5%"
      >
        <template #body="{ data }">
          <Button
              icon="pi pi-pencil"
              class="ml-2 p-1"
              size="small"
              :severity="(!v$.$dirty || data.timing_valid) ? 'primary':'danger'"
              :disabled="initialWindowIds.includes(data.id)"
              @click="showTiming(data)"
              v-tooltip.top="'Configure Timing'"
          />
        </template>
      </Column>
      <Column
          key="timing_display"
          header="Period"
          style="width: 20%"
      >
        <template #body="{ data }">
          <div
              v-if="data['timing']['start']['label']"
              :class="{'p-invalid': !data['timing_valid']}"
          >
            <strong>From: </strong>{{ data['timing']['start']['label'] }}<br>
            <strong>To: </strong>{{ data['timing']['end']['label'] }}<br>
              <span v-if="data['timing']['repeat_interval']
              && data['timing']['repeat_interval']['label']">
                <strong>Repeat:</strong> {{
                  data['timing']['repeat_interval']['label'] }}</span>
          </div>
          <div v-else>
              &lt;Not configured yet&gt;
          </div>
        </template>
      </Column>
      <Column
          key="label"
          field="label"
          header="Label"
          style="width: 25%"
      >
        <template #body="slotProps">
          <div>
            <InputText
              v-model="slotProps.data[slotProps.field]"
              :class="['p-inputtext-sm', 'w-11',{'p-invalid': labelInvalid(slotProps.index)}]"
              @change="v$.value.$reset()"
              :disabled="initialWindowIds.includes(slotProps.data.id)"
            >
            </InputText>
            <small v-if="labelInvalid(slotProps.index)"
                   class="flex p-error mb-3">
              {{ v$.localCollectionWindows.$each.$response.$errors[slotProps.index].label[0].$message }}
            </small>
          </div>
        </template>
      </Column>

      <Column
        key="data"
        field="data"
        header="Clinical Data"
        style="width: 40%"
      >
        <template #body="slotProps">
          <Button
              @click="showClinicalData('labs', slotProps.data)"
              size="small"
              class="ml-1 p-1 pr-2 pl-2"
              rounded
              :severity="(v$.$dirty
                && !slotProps.data[slotProps.field].valid
                && slotProps.data.data.errors?.findIndex((cd: any) => (cd.$property === 'aggregateDefaults')) > -1)
                ? 'danger':'primary'"
          >
            Labs
            <Badge class="p-badge-no-gutter">{{ slotProps.data[slotProps.field].labs.length }}</Badge>
          </Button>
          <Button
              @click="showClinicalData('ud_labs', slotProps.data)"
              size="small"
              class="ml-1 p-1 pr-2 pl-2"
              rounded
              :severity="(v$.$dirty && !slotProps.data[slotProps.field].valid
                 && slotProps.data.data.errors?.findIndex((cd: any) => (cd.$property === 'udLabsMissingAggregates')) > -1)
                 ? 'danger':'primary'"
          >
            User-Defined Labs
            <Badge class="p-badge-no-gutter">{{ slotProps.data[slotProps.field].ud_labs.length }}</Badge>
          </Button>
          <Button
              @click="showClinicalData('vitals', slotProps.data)"
              size="small"
              class="ml-1 p-1 pr-2 pl-2"
              rounded
              :severity="(v$.$dirty
                && !slotProps.data[slotProps.field].valid
                && slotProps.data.data.errors?.findIndex((cd: any) => (cd.$property === 'aggregateDefaults')) > -1)
                ? 'danger':'primary'"
          >
            Vitals
            <Badge class="p-badge-no-gutter">{{ slotProps.data[slotProps.field].vitals.length }}</Badge>
          </Button>
          <!-- TODO Medications
          <Button @click="showClinicalData('medications', slotProps.data)" size="small" class="ml-1 p-1 pr-2 pl-2" rounded :severity="(v$.$dirty && !slotProps.data[slotProps.field].valid) ? 'danger':'primary'">
            Medications<Badge class="p-badge-no-gutter">{{ slotProps.data[slotProps.field].vitals.length }}</Badge>
          </Button>
          -->
          <Button @click="showClinicalData('outcomes', slotProps.data)" size="small" class="ml-1 p-1 pr-2 pl-2" rounded>
              Outcomes<Badge class="p-badge-no-gutter">{{ slotProps.data[slotProps.field].outcomes.length }}</Badge>
          </Button>
          <Button @click="showClinicalData('scores', slotProps.data)" size="small" class="ml-1 p-1 pr-2 pl-2" rounded>
              Scores<Badge class="p-badge-no-gutter">{{ slotProps.data[slotProps.field].scores.length }}</Badge>
          </Button>
          <small v-if="(v$.$dirty && !slotProps.data[slotProps.field].valid)"
                 class="flex p-error mb-3">
            {{ slotProps.data[slotProps.field].errors[0].$message }}

          </small>
        </template>
      </Column>
      <Column
        key="id"
        field="id"
        header="Actions"
        style="width: 10%"
      >
        <template #body="{ data, field }">
          <Button
            icon="pi pi-copy"
            outlined
            rounded
            severity="success"
            class="ml-2 p-1 small-icon"
            size="small"
            @click="duplicateCw(data[field])"
            v-tooltip.top="'Duplicate Data Collection Window'"
          />
          <Button
            icon="pi pi-trash"
            outlined rounded
            severity="danger"
            class="ml-2 p-1 small-icon"
            size="small"
            @click="deleteCw(data[field])"
            v-tooltip.top="'Delete Data Collection Window'"
            v-if="!initialWindowIds.includes(data.id)"
          />
        </template>
      </Column>
      <template #footer>
        <div class="text-right">
          <Button
            label="Add Data Collection Window"
            icon="pi pi-plus"
            severity="success"
            class="mr-2"
            @click="addNew"
          />
        </div>
      </template>
      <template #empty>
          <p
            class="w-full"
            style="text-align: center;"
          >
            No Data Collection Windows have been added.
          </p>
      </template>
    </DataTable>

  </Panel>

  <TimingDialog
    v-model:show-timing-dialog = "showTimingDialog"
    :collection-window = "currentCollectionWindow"
    :event-options="eventDts"
    :rp-dates="rpDates"
    :presets="presets.cw_presets"
    @save-timing-update="saveTiming"
    @cancel-timing-update="showTimingDialog = false"
    @update:visible="showTimingDialog = false"
  />

  <ClinicalDataDialog
    v-model:show-clinical-data-dialog = "showClinicalDataDialog"
    v-model:clinical-data= "currentCollectionWindow.data"
    v-model:aggregate-defaults= "currentCollectionWindow.aggregate_defaults"
    :initial-window="getInitialWindow(currentCollectionWindow.id)"
    :clinical-data-category = "clinicalDataCategory"
    :timing = "currentCollectionWindow.timing"
    :lab-options="labOptions"
    :vital-options="vitalOptions"
    :score-options="scoreOptions"
    :outcome-options="outcomeOptions"
    v-model:active-options="activeClinicalOptions"
    v-model:closest-to-event = "currentCollectionWindow.event"
    v-model:closest-to-time = "currentCollectionWindow.closest_time"
    :event-options="eventDts"
    :rp-dates="rpDates"
    @update:visible="restoreInitialStates"
    @save-clinical-data-update="saveUpdate"
    @cancel-clinical-data-update="restoreInitialStates"
  />

  <Dialog v-model:visible="showDataCollectionInfo" modal header="Data Collection Windows" :style="{ width: '50vw' }">
    <p>
      Clinical data is partly defined by relative windows of time.
    </p>
    <p>
      DUSTER uses Data Collection Windows to apply this concept of creating windows of time in which you'd like to gather clinical data.
      <br>
      Each Data Collection Window will appear in the form of REDCap Instruments in your project.
      <br>
      Within each window, you may add your desired clinical data.
    </p>
    <p>
      You may create Data Collection Windows below with the options to choose among preset configurations or to configure from scratch.
    </p>
    <template #footer>
      <Button @click="showDataCollectionInfo=false">Close</Button>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import {computed, ref, onMounted} from "vue";
import type {PropType} from "vue";
import presets from '../types/CollectionWindowPresets.json';
import type CollectionWindow from "@/types/CollectionWindow";
import ClinicalDataDialog from "./ClinicalDataDialog.vue"
import TimingDialog from "./TimingDialog.vue"
import type {TIMING_TYPE} from "@/types/TimingConfig";

import {INIT_COLLECTION_WINDOW} from "@/types/CollectionWindow";
import type FieldMetadata from "@/types/FieldMetadata";
import type FieldConfig from "@/types/FieldConfig";
import type TimingConfig from "@/types/TimingConfig";
import {helpers, required, sameAs} from "@vuelidate/validators";
import {useVuelidate} from "@vuelidate/core";

const props = defineProps({
  labOptions: {
    type: Array as PropType<Array<FieldMetadata>>,
    required: true
  },
  vitalOptions: {
    type: Array as PropType<Array<FieldMetadata>>,
    required: true
  },
  outcomeOptions: {
    type: Array as PropType<Array<FieldMetadata>>,
    required: true
  },
  scoreOptions: {
    type: Array as PropType<Array<FieldMetadata>>,
    required: true
  },
  clinicalDateOptions: {
    type: Array as PropType<Array<FieldMetadata>>,
    required: true
  },
  rpDates: {
    type: Array as PropType<Array<FieldConfig>>,
    required: true
  },
  collectionWindows: {
    type: Array as PropType<Array<CollectionWindow>>,
    required: true
  },
  initialWindows: {
    type: Array as PropType<Array<CollectionWindow>>,
    required: true
  }
})

const emit = defineEmits(['update:collectionWindows'])

const currentCollectionWindow = ref<CollectionWindow>(JSON.parse(JSON.stringify(INIT_COLLECTION_WINDOW)));
const savedCollectionWindow = ref<CollectionWindow>()
const showTimingDialog = ref(false)
const showClinicalDataDialog = ref(false)
const showDataCollectionInfo = ref(false)

const localCollectionWindows = computed<CollectionWindow[]>({
get() {
  return props.collectionWindows;
},
set(value) {
  emit('update:collectionWindows', value)
}
});

const initialWindowIds = computed(() => {
  if (Array.isArray(props.initialWindows)) {
    return props.initialWindows.map(window => window.id);
  }
  return [];
});

/*assume we are starting with no collection windows defined*/
onMounted(()=> {
  // delete the inital cw used to initialize the table
  if (localCollectionWindows.value) {
    let index = getRowIndex("Undefined", localCollectionWindows.value)
    localCollectionWindows.value.splice(index, 1)
    /*if (localCollectionWindows.value.length == 0) {
      addNew()
    }*/
  }
  v$.value.$reset()
})

const addNew = () => {
  currentCollectionWindow.value = JSON.parse(JSON.stringify(INIT_COLLECTION_WINDOW))
  currentCollectionWindow.value.id = "cw" + new Date().getTime()
  if (!localCollectionWindows.value) {
    localCollectionWindows.value = []
  }
  localCollectionWindows.value.push(currentCollectionWindow.value)
  showTiming(localCollectionWindows.value[localCollectionWindows.value.length - 1])
}

// to restore after cancel
const saveInitialState = (cw: CollectionWindow) => {
  currentCollectionWindow.value = cw
  savedCollectionWindow.value = JSON.parse(JSON.stringify(cw))
}

const showTiming = (cw:CollectionWindow) => {
  currentCollectionWindow.value = cw
  showTimingDialog.value = true
}

const activeClinicalOptions = ref<number[]>([])
const showClinicalData = (category:string, cw: CollectionWindow) => {
  saveInitialState(cw)
  clinicalDataCategory.value = category
  activeClinicalOptions.value.length = 0
  switch (category) {
    case 'labs' :
      activeClinicalOptions.value.push(0);
      break;
    case 'ud_labs':
      activeClinicalOptions.value.push(1);
      break;
    case 'vitals' :
      activeClinicalOptions.value.push(2);
      break;
    case 'outcomes' :
      activeClinicalOptions.value.push(3);
      break;
    case 'scores' :
      activeClinicalOptions.value.push(4);
    /* TODO Medications
    case 'labs' :
      activeClinicalOptions.value.push(0);
      break;
    case 'ud_labs':
      activeClinicalOptions.value.push(1);
      break;
    case 'vitals' :
      activeClinicalOptions.value.push(2);
      break;
    case 'medications' :
      activeClinicalOptions.value.push(3);
      break;
    case 'outcomes' :
      activeClinicalOptions.value.push(4);
      break;
    case 'scores' :
      activeClinicalOptions.value.push(5);
     */
  }
  showClinicalDataDialog.value = true
}

const eventDts = computed<TimingConfig[]>(
    () => {
      let events: TimingConfig[] = []
      if (props.clinicalDateOptions) {
        props.clinicalDateOptions.forEach(opt => {
          let event:TimingConfig = {
            type: "datetime",
            label: opt.label,
            redcap_field_name: undefined,
            redcap_field_type: opt.redcap_field_type,
            duster_field_name: opt.duster_field_name,
            value_type: opt.value_type,
            preposition: "",
            phi: "t",
            rp_date: undefined,
            interval: undefined
          }
          /*if (opt.duster_field_name.indexOf('adm') > -1) {
            event.preposition = 'before'
          } else {
            event.preposition = 'after'
          }*/
          events.push(event)
        })
      }
      if (props.rpDates) {
        props.rpDates.forEach(opt => {
          let rpdateType: TIMING_TYPE = (opt.value_type == 'date') ? 'date' : 'datetime'
          events.push({
            type: rpdateType,
            label: opt.label,
            redcap_field_name: opt.redcap_field_name,
            interval: undefined,
            rp_date: opt.redcap_field_name,
            redcap_field_type: opt.redcap_field_type,
            duster_field_name: undefined,
            value_type: opt.value_type,
            phi: "t"
          })
        })
      }
      return events;
    });

const clinicalDataCategory = ref<string>()

/*** vuelidate ***/
const labelInvalid = (index: number) =>{
  if (v$.value.localCollectionWindows.$each.$response
    && v$.value.localCollectionWindows.$each.$response['$errors']
  && v$.value.localCollectionWindows.$each.$response.$errors[index]
  && v$.value.localCollectionWindows.$each.$response.$errors[index].label.length) {
    return true
  }
  return false
}

const uniqueLabel = (value:String, siblings:any, vm:any) => {
  return (localCollectionWindows.value.findIndex(cw => cw.id != siblings.id && cw.label == value) == -1)
}
const validationState = computed(() => {
  return {
    localCollectionWindows: localCollectionWindows.value
  }
})
const rules = {
  localCollectionWindows: {
    $each: helpers.forEach({
          label: {
            required: helpers.withMessage('Labels are required', required),
            uniqueLabel: helpers.withMessage('Labels must be unique', uniqueLabel)
          },
          timing_valid: {
              sameAs: helpers.withMessage("Timing Configuration is invalid.", sameAs(true))
          }
        }
    )
  }
}

const v$ = useVuelidate(rules, validationState, {$lazy: true})
/****/

const saveTiming = (cwCopy:CollectionWindow) => {
  //console.log("saveTiming")
  //console.log(cwCopy)
  if (cwCopy && cwCopy.id) {
    let index = getRowIndex(cwCopy.id, localCollectionWindows.value)
    if (localCollectionWindows.value && index > -1) {
      localCollectionWindows.value[index].type = cwCopy.type
      localCollectionWindows.value[index].timing_valid = true
      localCollectionWindows.value[index].label = cwCopy.label
      localCollectionWindows.value[index].timing.start = cwCopy.timing.start
      localCollectionWindows.value[index].timing.end = cwCopy.timing.end
      localCollectionWindows.value[index].timing.repeat_interval = cwCopy.timing.repeat_interval
    }
  }
  v$.value.$reset()
}

const saveUpdate = () => {
  v$.value.$reset()

}

const restoreInitialStates = () => {
  if (localCollectionWindows.value && currentCollectionWindow.value && currentCollectionWindow.value.id) {
    let cwIndex = getRowIndex(currentCollectionWindow.value.id, localCollectionWindows.value)
    if (savedCollectionWindow.value && cwIndex > -1) {
      localCollectionWindows.value[cwIndex] = savedCollectionWindow.value
      currentCollectionWindow.value = localCollectionWindows.value[cwIndex]
    }
  }
  saveUpdate()
}

const deleteCw = (id:string) => {
  if (localCollectionWindows.value) {
    let index = getRowIndex(id, localCollectionWindows.value)
    localCollectionWindows.value.splice(index, 1)
  }
}

const duplicateCw = (id:string) => {
  if (localCollectionWindows.value) {
    const index = getRowIndex(id, localCollectionWindows.value)
    const duplicate = JSON.parse(JSON.stringify(localCollectionWindows.value[index]));
    duplicate.id = "cw" + new Date().getTime()
    localCollectionWindows.value.push(duplicate)
  }
}

const getRowIndex = (id:string, haystack:any[]) => {
  //console.log(id)
  return haystack.findIndex(
      (cw) => cw.id === id)
}

const getInitialWindow = (id:any) => {
  let window = props.initialWindows.find(window => {
    return window.id === id;
  });
  return window;
}

</script>

<style scoped>
    :deep(.p-datatable-header) {
        background: blue
    }
</style>

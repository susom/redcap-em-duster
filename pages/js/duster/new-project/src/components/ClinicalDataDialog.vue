<template>
    <Dialog
      v-model:visible="visible"
      :modal="true"
      :close-on-escape="false"
      :style="{ width: '75vw' }"
      header="Select Clinical Values"
    >

      <!-- add search -->
      <div class="grid">
        <div class="col-6 mt-2">
          <div class="p-inputgroup flex">
            <InputText
              placeholder="Search Clinical Value"
              v-model="filters['global'].value"
              @update:model-value="expandAll"
            />
            <span class="p-inputgroup-addon"><i class="pi pi-search"/></span>
          </div>
        </div>

        <div class="col-6 mt-2">
          <!-- expand category panels -->
          <!--div class="form grid">
            <div class="col-fixed"><label class="flex align-items-center">Display: </label>
            </div>
            <div class="col">
              <div class="flex flex-wrap gap-3">
                <div class="flex align-items-center">
                  <InputSwitch v-model="expandLabs"/>
                  <label class="flex align-items-center"> Labs</label></div>
                <div class="flex align-items-center">
                  <InputSwitch v-model="expandVitals"/>
                  <label class="flex align-items-center"> Vitals</label></div>
                <div class="flex align-items-center">
                  <InputSwitch v-model="expandOutcomes"/>
                  Outcomes
                </div>
                <div class="flex align-items-center">
                  <InputSwitch v-model="expandScores"/>
                  Scores
                </div>
              </div>
            </div>
          </div-->
          <!-- select/unselect filters -->
          <div class="flex flex-wrap gap-3">
            <label class="flex align-items-center">Show: </label>
            <div v-for="(value, index) in selectOptions" :key="index" class="flex align-items-center">
              <RadioButton v-model="selectFilter"
                           name="filterSelected"
                           :input-id="value"
                           :value="value"
              />
              <label :for="value" class="flex align-items-center ml-2">{{ value }}</label>
            </div>
          </div>
        </div>
      </div>
      <hr/>
      <!-- default aggregates-->
      <Panel
        header="Default Aggregates"
        toggleable
      >
          <div>
            <p>
              Clinical variables that are added and require aggregation (i.e., any clinical variables under the category of "Labs" or "Vitals") will default to the settings here for convenience.
              <br>
              Such variables may have their settings individually changed after being added.
              <br>
              NOTE: The "Closest to Event" and "Closest to Time" aggregations are only available when applicable according to the Data Collection Window's timing.
            </p>
          </div>
          <div class="card flex flex-wrap gap-4 mt-3">
              <div v-for="(option) in filteredAggregates" :key="option.value" class="flex align-items-center">
                  <Checkbox
                    name="defaultAggregate"
                    v-model="localAggregateDefaults"
                    :value="option"
                    :input-id="option.value"
                    :class="['mr-2', { 'p-invalid': v$.aggregateDefaults.$error }]"
                    :disabled="initialAggregates.includes(option.value)"
                  />
                  <label
                    :for="option.value"
                  >
                    {{ option.text }}
                  </label>
              </div>
              <!-- closest time-->
              <div
                v-if="hasClosestTime"
                class="flex align-items-center"
              >
                <Checkbox
                  v-model="localAggregateDefaults"
                  name="defaultAggregate"
                  :input-id="closestTimeOption.value"
                  :value="closestTimeOption"
                  :class="{ 'p-invalid': v$.aggregateDefaults.$error }"
                  :disabled="initialAggregates.includes(closestTimeOption.value)"
                />
                <label
                  :for="closestTimeOption.value"
                  class="ml-2 mr-2"
                >
                  {{ closestTimeOption.text }}
                </label>
                <div v-if="showClosestTime">
                  <Calendar
                    id="calendar-timeonly"
                    v-model="closestCalendarTime"
                    timeOnly
                    v-tooltip="'Closest Time value applies to both default and custom aggregates'"
                    :disabled="initialContainsClosest"
                  />
                  <small
                    v-if="v$.closestTime.$error"
                    class="flex p-error mb-3"
                  >
                      {{ v$.closestTime.$errors[0].$message }}
                  </small>
                </div>
              </div>
              <!-- closest event -->
              <div v-if="hasClosestEvent" class="flex align-items-center">
                  <Checkbox v-model="localAggregateDefaults"
                      name="defaultAggregate"
                      :input-id="closestEventOption.value"
                      :value="closestEventOption"
                      :class="{ 'p-invalid': v$.aggregateDefaults.$error }"
                      :disabled="initialAggregates.includes(closestEventOption.value)"
                  />
                  <label :for="closestEventOption.value" class="ml-2 mr-2"
                         >
                    {{ closestEventOption.text }}
                  </label>
                  <span
                      v-tooltip="'Closest Event value applies to both default and custom aggregates'">

                  <Dropdown v-model="localClosestEvent"
                      :options="datetimeEventOptions"
                      optionLabel="label"
                      placeholder="Choose an event"
                      v-if="showClosestEvent"
                      :class="[{ 'p-invalid': v$.closestEvent.$error }]"
                      :disabled="initialContainsClosest"
                      />
                    </span>
                      <small v-if="v$.closestEvent.$error"
                          class="flex p-error ml-2">
                          {{ v$.closestEvent.$errors[0].$message }}
                      </small>
                      <small
                          v-if="v$.aggregateDefaults.$error"
                          id="aggOption-help"
                          class="flex p-error ml-2">
                          {{ v$.aggregateDefaults.$errors[0].$message }}
                      </small>
              </div>
          </div>
      </Panel>

      <Accordion :multiple="true" :activeIndex="activeClinicalOptions" class="mt-2">
      <AccordionTab header="Labs">
        <ClinicalDataOptions
            category="labs"
            :options="labOptions"
            :initial-data="initialData"
            :has-aggregates=true
            :has-closest-time="hasClosestTime"
            :has-closest-event="hasClosestEvent"
            :closest-time="closestToTime"
            :closest-event="localClosestEvent.label"
            :search-text="searchText"
            :select-filter="selectFilter"
            v-model:selected-options="localClinicalData.labs"
        />
      </AccordionTab>
      <AccordionTab header="Vitals">
        <ClinicalDataOptions
            category="vitals"
            :options="vitalOptions"
            :initial-data="initialData"
            :has-aggregates=true
            :has-closest-time="hasClosestTime"
            :has-closest-event="hasClosestEvent"
            :closest-time="closestToTime"
            :closest-event="localClosestEvent.label"
            :search-text="searchText"
            :select-filter="selectFilter"
            v-model:selected-options="localClinicalData.vitals"
        />
      </AccordionTab>
      <AccordionTab header="Outcomes">
        <ClinicalDataOptions
            category="outcomes"
            :options="outcomeOptions"
            :initial-data="initialData"
            :has-aggregates=false
            :has-closest-time=false
            :has-closest-event=false
            :search-text="searchText"
            :select-filter="selectFilter"
            v-model:selected-options="localClinicalData.outcomes"
        />
      </AccordionTab>
      <AccordionTab header="Scores">
        <ClinicalDataOptions
            category="scores"
            :options="scoreOptions"
            :initial-data="initialData"
            :has-aggregates=false
            :has-closest-time=false
            :has-closest-event=false
            :search-text="searchText"
            :select-filter="selectFilter"
            v-model:selected-options="localClinicalData.scores"
        />
      </AccordionTab>
  </Accordion>
      <Toast />
      <template #footer>
          <Button label="Save" class="p-button-primary" size="small" icon="pi pi-check" @click="saveClinicalData"/>
          <Button label="Cancel" class="p-button-secondary" size="small" icon="pi pi-times" @click="cancelClinicalData"/>
      </template>
    </Dialog>
</template>

<script setup lang="ts">
import {computed, ref, watch, watchEffect} from "vue";
import type {PropType} from "vue";
import {FilterMatchMode} from "primevue/api";
import {AGGREGATE_OPTIONS} from "@/types/FieldConfig";
import type CollectionWindow from "@/types/CollectionWindow";
import type FieldMetadata from "@/types/FieldMetadata";
import type FieldConfig from "@/types/FieldConfig";
import type TimingConfig from "@/types/TimingConfig";
import type TextValuePair from "@/types/TextValuePair";
import ClinicalDataOptions from "./ClinicalDataOptions.vue";
import { useToast } from "primevue/usetoast";
import Toast from 'primevue/toast'
import {INIT_TIMING_CONFIG} from "@/types/TimingConfig";
import {helpers, requiredIf, minLength} from "@vuelidate/validators";
import {useVuelidate} from "@vuelidate/core";

const props = defineProps({
  showClinicalDataDialog: Boolean,
  activeOptions: Array as PropType<Array<number>>,
  initialWindow: {
    type: Object as PropType<CollectionWindow>,
    required: false
  },
  timing: {
    type: Object,
    required: true
  },
  clinicalData: {
    type: Object,
    required: true
  },
  aggregateDefaults: {
    type: Array as PropType<Array<TextValuePair>>
  },
  closestToEvent: {
    type: Array as PropType<Array<TimingConfig>>
  },
  closestToTime: {
    type: String
  },
  eventOptions: {
    type: Array as PropType<Array<TimingConfig>>,
    required: true
  },
  rpDates: {
    type: Array as PropType<Array<FieldConfig>>,
    required: true
  },
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
  }
})

const emit = defineEmits(
  ['saveClinicalDataUpdate', 'cancelClinicalDataUpdate',
    'update:clinicalData', 'update:aggregateDefaults', 'update:showClinicalDataDialog',
    'update:activeOptions',
    'update:closestToEvent', 'update:closestToTime']
)

const filters = ref<any>({
  global: {value: null, matchMode: FilterMatchMode.CONTAINS}
});

const searchText = computed(
    () => {
      return filters.value.global.value
    }
)

const visible = computed({
  get() {
    return props.showClinicalDataDialog;
  },
  set(value) {
    emit('update:showClinicalDataDialog', value)
  }
});

const localClinicalData = computed({
  get() {
    return props.clinicalData;
  },
  set(value:Object) {
    emit('update:clinicalData', value)
  }
});

const localAggregateDefaults = computed({
  get() {
    return props.aggregateDefaults;
  },
  set(value:(Array<TextValuePair>|undefined)){
    emit('update:aggregateDefaults', value)
  }
});

const initialAggregates = computed(() => {
  if (props.initialWindow?.aggregate_defaults !== undefined) {
    return props.initialWindow?.aggregate_defaults.map(agg => agg.value);
  }
  return [];
});

const initialData = computed(() => {
  return props.initialWindow?.data !== undefined
    ? props.initialWindow?.data : {};
});

// TODO test this works
const initialContainsClosest = computed(() => {
  if (initialAggregates.value.includes('closest_time') || initialAggregates.value.includes('closest_event')) {
    return true;
  }
  let dataArr:any[] = [];
  if (initialData.value.hasOwnProperty('labs')) {
    dataArr.concat((initialData.value as any).labs);
  }
  if (initialData.value.hasOwnProperty('vitals')) {
    dataArr.concat((initialData.value as any).vitals);
  }
  dataArr.forEach((clinicalVar:any) => {
    const aggs = clinicalVar.aggregates.map((agg:any) => agg.value);
    if (aggs.includes('closest_time') || initialAggregates.value.includes('closest_event')) {
      return true;
    }
  });
  return false;
});

// remove the "closest to" options to display them on a separate line
const filteredAggregates = computed(() => {
  return AGGREGATE_OPTIONS.filter(option => option.value.indexOf('closest') === -1);
})

/*** closest event ***/

const closestEvent = computed({
  get() {
    return props.closestToEvent;
  },
  set(value) {
    // only used when reset?
    emit('update:closestToEvent', value)
  }
})

/* closestEvent is an array with only one element
so this makes accessing it more convenient*/
const localClosestEvent = computed<TimingConfig>({
      get() {
        if (props.closestToEvent && props.closestToEvent[0] && props.closestToEvent[0].label) {
          //Find the matching event in the event options to make sure it displays in the dropdown
          //@ts-ignore
          return (datetimeEventOptions.value.find(opt => opt.label === props.closestToEvent[0].label) ??
              JSON.parse(JSON.stringify(INIT_TIMING_CONFIG)))
          //return props.closestToEvent[0]
        } else {
          return JSON.parse(JSON.stringify(INIT_TIMING_CONFIG))
        }
      },
      set(value) {
        // if localClosestEvent is reset to INIT_TIMING_CONFIG, don't update
        if (value.label !== "")
          emit('update:closestToEvent', [value])
      }
    })

/** separate out closest event checkbox option to be displayed separately **/
const closestEventOption = computed(() => {
  return (AGGREGATE_OPTIONS.find(option => option.value === 'closest_event')
      ?? {text:"Closest Event", value:"closest_event"})
})


/* whether to show closest event as default aggregate*/
/* don't show if there's a repeat interval defined*/
const hasClosestEvent = computed(() => {
  if (props.timing) {
    if (props.timing.repeat_interval
        && props.timing.repeat_interval.length > 0) {
      return false
    }
  }
  return true
})

const showClosestEvent = computed(() => {
  let show = false
  if (hasClosestEvent.value) {
    // show closest event if it's selected as a default
    if (localAggregateDefaults.value) {
      show = (localAggregateDefaults.value.findIndex(agg => agg.value === 'closest_event') > -1)
    }
  if (!show) {
      // show closest event if it's selected as a custom aggregate
      show = localClinicalData.value.labs.findIndex((cd: any) =>
          (cd.selected && cd.aggregate_type === 'custom' &&
              (JSON.stringify(cd.aggregates).indexOf("closest_event") > -1))) > -1
    }
  if (!show) {
      // show closest event if it's selected as a custom aggregate
      show = localClinicalData.value.vitals.findIndex((cd: any) =>
          (cd.selected && cd.aggregate_type === 'custom' &&
              (JSON.stringify(cd.aggregates).indexOf("closest_event") > -1))) > -1
    }
  }
  return show
})

watch(showClosestEvent, (show) => {
  if (!show) {
    closestEvent.value = []
    localClosestEvent.value = JSON.parse(JSON.stringify(INIT_TIMING_CONFIG))
    removeAggregate('closest_event')
  }
})

const removeAggregate=(aggregate: string) => {
  // remove default aggregate
  if (localAggregateDefaults.value) {
    // doesn't work if you try to do this all in one line
    const removed = localAggregateDefaults.value.filter(agg => agg.value !== aggregate)
    localAggregateDefaults.value = removed
  }
  // remove custom aggregates from labs
  localClinicalData.value.labs = removeCustomAggregates(aggregate, localClinicalData.value.labs)
  // remove custom aggregates from vitals
  localClinicalData.value.vitals = removeCustomAggregates(aggregate, localClinicalData.value.vitals)
}

const removeCustomAggregates=(aggregate: string, clinicalOptions: any) => {
  const mapped = clinicalOptions.map((cd:any) => {
    if (cd.selected && cd.aggregate_type === 'custom' &&
        (JSON.stringify(cd.aggregates).indexOf(aggregate) > -1)) {
      const removed = cd.aggregates.filter((agg:any) => agg.value != aggregate)
      cd.aggregates = removed
      // if there are no more custom aggregates after removing, then set the aggregate type to default
      if (removed.length === 0) {
        cd.aggregate_type = 'default'
      }
    }
    return cd
  })
  return mapped
}

/** closest event selector should only show datetime options **/
const datetimeEventOptions = computed(() => {
  return props.eventOptions.filter(option => option.value_type === 'datetime')
})


/*** closest time ***/
const closestTime = computed({
  get() {
    return props.closestToTime;
  },
  set(value) {
    emit('update:closestToTime', value)
  }
})

// default to 8 AM, date portion will be ignored
// this is for the PrimeVue Calendar component
const closestCalendarTime = ref(new Date('2024T08:00'))

/*assign time portion of closestCalendarTime to collection window closest time*/
watchEffect(() => {
  if (closestCalendarTime.value) {
    closestTime.value = ("0" + closestCalendarTime.value.getHours()).slice(-2)
        + ":" + ("0" + closestCalendarTime.value.getMinutes()).slice(-2)
        + ":00"
  } else {
    closestTime.value = "08:00:00"
  }
})

/** closest time checkbox option **/
const closestTimeOption = computed(() => {
  return (AGGREGATE_OPTIONS.find(option => option.value === 'closest_time')
      ?? {text:"Closest Time", value:"closest_time"})
})

/* whether to show closest time as default aggregate*/
/* only when there is a time interval of 1 calendar day*/
const hasClosestTime = computed(() => {
  if (props.timing) {
    if (props.timing.start.interval.type == "day" && props.timing.start.interval.length == 1) {
      return true
    }
    if (props.timing.end.interval.type == "day" && props.timing.end.interval.length == 1) {
      return true
    }
    if (props.timing.repeat_interval &&
        props.timing.repeat_interval.type == "day" &&
        props.timing.repeat_interval.length == 1) {
      return true
    }
    // if start and end are the same day
    if (props.timing.start.type ==='date' && props.timing.end.type ==='date' &&
        ((props.timing.start.duster_field_name && props.timing.start.duster_field_name.length > 0 &&
            (props.timing.start.duster_field_name === props.timing.end.duster_field_name)) ||
         (props.timing.start.redcap_field_name && props.timing.start.redcap_field_name.length > 0 &&
             (props.timing.start.redcap_field_name === props.timing.end.redcap_field_name))
      )) {
      return true
    }
    return false
  }
  return false
})

const showClosestTime = computed(() => {
  // show closest time if it's selected as a default
  let show = false
  if (hasClosestTime.value) {
    if (localAggregateDefaults.value) {
      show = (localAggregateDefaults.value.findIndex(agg => agg.value === 'closest_time') > -1)
    }
    // show closest time if it's selected as a custom aggregate in labs
    if (!show) {
      show = localClinicalData.value.labs.findIndex((cd: any) =>
          (cd.selected && cd.aggregate_type === 'custom' &&
              (JSON.stringify(cd.aggregates).indexOf("closest_time") > -1))) > -1
    }
    // show closest time if it's selected as a custom aggregate in vitals
    if (!show) {
      show = localClinicalData.value.vitals.findIndex((cd: any) =>
          (cd.selected && cd.aggregate_type === 'custom' &&
              (JSON.stringify(cd.aggregates).indexOf("closest_time") > -1))) > -1
    }
  }
  return show
})

watch(showClosestTime,(show) => {
  if (!show) {
    closestCalendarTime.value = new Date('2024T08:00')
    closestTime.value = undefined
    removeAggregate('closest_time')
  }
})

const defaultAggregatesRequired = computed(() => {
  let hasDefaults = (localClinicalData.value.labs) ?
      (localClinicalData.value.labs.findIndex((cd: any) =>
      (cd.selected && cd.aggregate_type == 'default')) > -1)
      : false

  if (!hasDefaults) {
    hasDefaults = (localClinicalData.value.vitals) ? (localClinicalData.value.vitals.findIndex((cd: any) =>
        (cd.selected && cd.aggregate_type === 'default')) > -1) : false
  }
  return hasDefaults
  }
)

const timeFormat = helpers.regex(/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/)

const validationFields = computed(() => {
  return {
    aggregateDefaults: localAggregateDefaults.value,
    closestEvent: (localClosestEvent.value.duster_field_name)
        ? localClosestEvent.value.duster_field_name :
        localClosestEvent.value.redcap_field_name,
    closestTime: closestTime.value
  }
})

const rules = computed(() =>({
      aggregateDefaults: {
    requiredIf: helpers.withMessage(
        "At least one default aggregate must be selected.",
        requiredIf(defaultAggregatesRequired.value)
    ),
    minLength: minLength(1)
  },
  closestEvent: {
      requiredIf: helpers.withMessage(
          "Closest event is required", requiredIf(showClosestEvent.value))
    },
  closestTime: {
      requiredIf: helpers.withMessage("Closest time is required",
        requiredIf(showClosestTime.value)),
      timeFormat: helpers.withMessage("Incorrect time format",
          timeFormat)
    }
})
)
const v$ = useVuelidate(rules, validationFields)
watchEffect(() => {
  if (localClinicalData.value) {
    localClinicalData.value['valid'] = !v$.value.$error
    localClinicalData.value['errors'] = v$.value.$errors
  }
  /*    // default aggregate is missing
  !((defaultAggregatesRequired.value
      && (!localAggregateDefaults.value || !localAggregateDefaults.value.length))
      // closest event is missing
  || (showClosestEvent.value && (!localClosestEvent.value
          || (!localClosestEvent.value.duster_field_name &&
              !localClosestEvent.value.redcap_field_name)))
      // closest time is missing
  || (showClosestTime.value && !closestTime.value))*/
})
const toast = useToast();

const saveClinicalData = () => {
  v$.value.$touch() ;
  if (!v$.value.$error) {
    visible.value = false
    emit('saveClinicalDataUpdate')
  } else {
    v$.value.$errors.forEach(error =>
      toast.add({
        severity: 'error',
        summary: 'Missing values', detail: error.$message,
        life: 3000
      })
    )
  }
}

const cancelClinicalData = () => {
  visible.value = false
  emit('cancelClinicalDataUpdate')
}

/**** validation rules and messages *****/

/**** selected/unselected options****/
const selectOptions = ref<string[]>(['Selected', 'Unselected', 'All'])
const selectFilter = ref<string>("All")
/****/

/* expand or collapse different sections */
/* these controls are currently commented out*/
const activeClinicalOptions = computed({
  get() {
    return props.activeOptions
  },
  set(value) {
    emit('update:activeOptions', value)
  }
})

const expandAll = () => {
  activeClinicalOptions.value = [0,1,2,3]
}
/****/

</script>

<style scoped>

</style>

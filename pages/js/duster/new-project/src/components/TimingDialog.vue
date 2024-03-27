<template>
  <Dialog
    v-model:visible="visible"
    :modal="true"
    :style="{ width: '75vw' }"
    header="Data Collection Timing"
    class="my-2"
  >
    <div class="field grid mt-2">
      <label class="col-2" for="presets">Presets:
        <i class="ml-2 pi pi-info-circle" style="color:blue"
           v-tooltip="'Common preset configurations'"></i></label>
      <Dropdown
          class="col-6"
          :options="presets"
          id="presets"
          v-model="selectedPreset"
          optionLabel="label"/>
    </div>
    <Divider/>
    <div class="card">
      <TimingEvent
          v-model:timing-object="cwCopy.timing.start"
          event-type="start"
          :time-type-options="START_TIME_TYPE_OPTIONS"
          :event-options="eventOptions"
          :rp-dates="rpDates"
          :other-timing-event="cwCopy.timing.end"
          :instigator="eventInstigator"
          @instigate="(instigator) => eventInstigator = instigator"
          @clear-preset="clearPreset"
      />
    </div>
    <Divider/>
    <div class="card">
      <TimingEvent
          v-model:timing-object="cwCopy.timing.end"
          event-type="end"
          :time-type-options="END_TIME_TYPE_OPTIONS"
          :event-options="eventOptions"
          :rp-dates="rpDates"
          :other-timing-event="cwCopy.timing.start"
          :instigator="eventInstigator"
          @clear-preset="clearPreset"
          @instigate="(instigator) => eventInstigator = instigator"
      />
    </div>
    <Divider/>
    <div class="card">
      <div class="field grid">
        <div class="col-offset-2 col-12 md:col-10">

          <Checkbox
              input-id="hasRepeatIntervals"
              v-model="hasRepeatIntervals"
              :binary="true"
              :disabled="repeatIntervalDisabled"
              v-tooltip="'Start and end points must be defined before configuring repeat intervals.'"
          />
          <label
              class="ml-2"
              for="hasRepeatIntervals"
          >
            Repeat Data Collection at defined intervals between Start & End?
            <i class="ml-2 pi pi-info-circle" style="color:blue"
               v-tooltip="'Collect data multiple times in the collection window at the defined intervals.'"></i>
          </label>

        </div>
      </div>
      <div v-if="hasRepeatIntervals" class="field grid">

        <div class="col-12 mt-2 md:col-2 md:mb-0">
          <label>Collect Data Every: </label>
        </div>
        <div class="col-12 md:col-10">
          <div class="formgroup-inline">
            <div class="field ">

              <InputNumber
                  v-model="repeatIntervalLength"
                  id="repeatIntervalLength"
                  input-id="integeronly"
                  :min="1"
                  :class="{ 'p-invalid': v$.timingRepeatIntervalLength.$error }"
                  :input-style="{'width': '3rem'}"
                  placeholder="# of"/>
              <small v-if="v$.timingRepeatIntervalLength.$error"
                     class="flex p-error mb-3">
                {{ v$.timingRepeatIntervalLength.$errors[0].$message }}
              </small>
            </div>
            <div class="field ">
              <!--Dropdown v-model="repeatIntervalType"
                        :options="filteredIntervalOptions"
                        optionLabel="text"
                        optionValue="value"
                        placeholder="Hours/Days"
                        :class="['ml-1 mr-2', { 'p-invalid': v$.repeatIntervalType.$error }]"
                        /-->
              <label class="mt-2">{{ repeatIntervalType }}(s) between Start and End Date/Datetimes</label>
              <small v-if="v$.timingRepeatIntervalType.$error"
                     class="flex p-error mb-3">
                {{ v$.timingRepeatIntervalType.$errors[0].$message }}
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <Toast />
    <template #footer>
      <Button label="Save" class="p-button-primary" size="small" icon="pi pi-check" @click="saveTiming"/>
      <Button label="Cancel" class="p-button-secondary" size="small" icon="pi pi-times" @click="cancelTiming"/>
      <Button label="Reset" class="p-button p-button-secondary" size="small" @click="resetTiming"/>
    </template>
  </Dialog>
</template>

<script setup lang="ts">
import {computed, watchEffect, watch,  ref} from "vue";
import type {PropType} from "vue";
import type CollectionWindow from "@/types/CollectionWindow";
import type FieldConfig from "@/types/FieldConfig";
import type TimingConfig from "@/types/TimingConfig";
import type {INTERVAL_TYPE} from "@/types/TimingConfig";
import {START_TIME_TYPE_OPTIONS, END_TIME_TYPE_OPTIONS, INTERVAL_OPTIONS, INIT_TIMING_INTERVAL, INIT_TIMING_CONFIG} from
      "@/types/TimingConfig";
import TimingEvent from "./TimingEvent.vue"
import { useToast } from "primevue/usetoast";
import Toast from 'primevue/toast'
import { useVuelidate } from '@vuelidate/core'
import {requiredIf, helpers} from '@vuelidate/validators'
import {INIT_COLLECTION_WINDOW} from "@/types/CollectionWindow";


const props = defineProps({
  collectionWindow: {
    type: Object as PropType<CollectionWindow>,
    required: true
  },
  presets: {
    type: Array as PropType<Array<Object>>,
    required: true
  },
  eventOptions: {
    type: Array as PropType<Array<TimingConfig>>,
    required: true
  },
  rpDates: {
    type: Array as PropType<Array<FieldConfig>>,
    required: true
  },
  showTimingDialog: {
    type: Boolean,
    required: true
  }
})

const emit = defineEmits(
    ['update:showTimingDialog', 'saveTimingUpdate',]
)

const visible = computed({
  get() {
    return props.showTimingDialog;
  },
  set(value) {
    emit('update:showTimingDialog', value)
  }
});

// make a copy of the collection window and actual collection window only on save
const cwCopy = ref<CollectionWindow>(JSON.parse(JSON.stringify(INIT_COLLECTION_WINDOW)))
watch(visible, (isVisible) => {
  if (isVisible) {
    cwCopy.value = JSON.parse(JSON.stringify(props.collectionWindow))
  }
})

/*****  repeat interval *****/

/* no longer needed?*/
const filteredIntervalOptions = computed(() => {
      if (cwCopy.value.timing.start.type === 'datetime' || cwCopy.value.timing.end.type === 'datetime') {
        return INTERVAL_OPTIONS.filter(opt => opt.value === 'hour')
      } else if (cwCopy.value.timing.start.type  === 'date' || cwCopy.value.timing.end.type === 'date') {
        return INTERVAL_OPTIONS.filter(opt => opt.value === 'day')
      }
      return INTERVAL_OPTIONS
    }
)

const repeatIntervalType = computed({
  get() {
    return cwCopy.value.timing.repeat_interval?.type ?? undefined
  },
  set(value:INTERVAL_TYPE) {
    if (!cwCopy.value.timing.repeat_interval) {
      cwCopy.value.timing.repeat_interval = {...INIT_TIMING_INTERVAL}
    }
    cwCopy.value.timing.repeat_interval.type = value
  }
})

const repeatIntervalLength = computed({
  get() {
    return cwCopy.value.timing.repeat_interval?.length ?? undefined
  },
  set(value:number|undefined) {
    if (!cwCopy.value.timing.repeat_interval) {
      cwCopy.value.timing.repeat_interval = {...INIT_TIMING_INTERVAL}
    }
    cwCopy.value.timing.repeat_interval.length = value
  }
})

/* set the repeat interval label.*/
watchEffect(() => {
  if (cwCopy.value.timing.repeat_interval &&
      repeatIntervalLength.value && repeatIntervalLength.value >= 0
      && repeatIntervalType.value)
    cwCopy.value.timing.repeat_interval.label = "Every " + repeatIntervalLength.value
        + " " + repeatIntervalType.value + "(s) "
})

const hasRepeatIntervals = computed({
  get() {
    return (cwCopy.value.type === 'repeating')
  },
  set(value:boolean) {
    cwCopy.value.type = (value) ? 'repeating':'nonrepeating'
  }
})

const repeatIntervalDisabled = computed(() => {
  return !(cwCopy.value.timing &&
      cwCopy.value.timing.start &&
      cwCopy.value.timing.end &&
      cwCopy.value.timing.start.type &&
      cwCopy.value.timing.end.type)
})

/* set the repeat interval type based on start/end types*/
watchEffect(() => {
  if (hasRepeatIntervals.value) {
    if (!cwCopy.value.timing.repeat_interval) {
      cwCopy.value.timing.repeat_interval = {...INIT_TIMING_INTERVAL}
    }
    if (cwCopy.value.timing.start.type === 'datetime' || cwCopy.value.timing.end.type === 'datetime') {
      cwCopy.value.timing.repeat_interval.type = 'hour'
    } else if (cwCopy.value.timing.start.type  === 'date' || cwCopy.value.timing.end.type === 'date') {
      cwCopy.value.timing.repeat_interval.type = 'day'
    }
  } else {
    cwCopy.value.timing.repeat_interval = undefined
    repeatIntervalLength.value = undefined
    repeatIntervalType.value = undefined
  }
})

const selectedPreset = ref<CollectionWindow>()

// set start and end values based on presets
watch(selectedPreset, (newPreset) => {
  if (newPreset) {
    const priorStartRpDate = cwCopy.value.timing.start.rp_date
    const priorEndRpDate = cwCopy.value.timing.end.rp_date
    cwCopy.value.timing.start = JSON.parse(JSON.stringify(newPreset.timing.start))
    cwCopy.value.timing.end = JSON.parse(JSON.stringify(newPreset.timing.end))
    cwCopy.value.label = newPreset.label
    // select first rp_date for presets by default unless it has already been set to something else
    // this is okay because presets all have duster metadata
    cwCopy.value.timing.start.rp_date =
        (priorStartRpDate && newPreset.timing.start.type !== 'interval') ?
        priorStartRpDate : props.rpDates[0].redcap_field_name
    cwCopy.value.timing.end.rp_date =
        (priorEndRpDate && newPreset.timing.end.type !== 'interval') ?
            priorEndRpDate : props.rpDates[0].redcap_field_name
    cwCopy.value.type = newPreset.type
    cwCopy.value.timing.repeat_interval = JSON.parse(JSON.stringify(newPreset.timing.repeat_interval))
  }
})

const eventInstigator = ref<string>()

/*** validation **/

const positiveInteger = helpers.regex(/^[1-9][0-9]*$/)

const validationValues = computed(()=> {
  return {
    timingRepeatIntervalLength: repeatIntervalLength.value,
    timingRepeatIntervalType: repeatIntervalType.value,
  }
})
const rules = computed(() => ({
  timingRepeatIntervalLength: {
    requiredIf: helpers.withMessage('Repeat interval length required',
        requiredIf(hasRepeatIntervals.value)),
    positiveInteger: helpers.withMessage('Value must be a positive integer',
        positiveInteger)
  },
  timingRepeatIntervalType: {
    requiredIf: helpers.withMessage('Repeat interval type required',
        requiredIf(hasRepeatIntervals.value))
  }
}))

const v$ = useVuelidate(rules, validationValues)

const toast = useToast();

const saveTiming = () => {
  //emit('timingValidationUpdate', validationState.value)
  v$.value.$touch() ;
  console.log("Validation errors :" + v$.value.$error) ;
  console.log(v$.value) ;
  clearPreset()
  if (!v$.value.$error) {
    //cw.value = JSON.parse(JSON.stringify(cwCopy.value))
    visible.value = false
    emit('saveTimingUpdate', cwCopy.value)
    v$.value.$reset() ;
  } else {
    v$.value.$errors.forEach(error =>
        toast.add({ severity: 'error', summary: 'Unable To Save', detail: error.$message, life: 3000
        })
    )
  }
}

const cancelTiming = () => {
  clearPreset()
  visible.value = false
  v$.value.$reset()
}

const clearPreset = () => {
  selectedPreset.value = undefined
}

const resetTiming = () => {
  clearPreset()
  cwCopy.value.label = ""
  cwCopy.value.type = "nonrepeating"
  cwCopy.value.timing.start = JSON.parse(JSON.stringify(INIT_TIMING_CONFIG))
  cwCopy.value.timing.end = JSON.parse(JSON.stringify(INIT_TIMING_CONFIG))
  cwCopy.value.timing_valid = false
  repeatIntervalLength.value = undefined
  repeatIntervalType.value = undefined
  cwCopy.value.timing.repeat_interval = {...INIT_TIMING_INTERVAL}
}

</script>

<style scoped>


</style>

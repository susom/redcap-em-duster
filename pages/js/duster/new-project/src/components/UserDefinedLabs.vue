<script setup lang="ts">
  import {ref, inject, computed} from "vue";
  import type {ComputedRef} from "vue";
  import InputGroup from "primevue/inputgroup";
  import InputText from 'primevue/inputtext';
  import AutoComplete from 'primevue/autocomplete';
  import { AGGREGATE_OPTIONS, TEXT_AGGREGATE_OPTIONS} from "@/types/FieldConfig";
  import {required, helpers} from "@vuelidate/validators";
  import { useVuelidate } from "@vuelidate/core";

  const labResults = inject('labResults') as ComputedRef<any>;

  const emit = defineEmits(['updateLabs']);

  const props = defineProps({
    selectedLabs: {
      type: Array,
      required: true,
      default: () => []
    },
    initialLabs: {
      type: Array,
      required: true,
      default: () => []
    },
    hasClosestTime: {
      type: Boolean,
      required: true
    },
    hasClosestEvent: {
      type: Boolean,
      required: true
    }
  });

  const aggOptions = computed(() => {
    let filteredOptions = valueType.value === 'text' ? TEXT_AGGREGATE_OPTIONS : AGGREGATE_OPTIONS;

    if (!props.hasClosestTime) {
      filteredOptions = filteredOptions.filter((option) => option.value !== 'closest_time');
    }
    if (!props.hasClosestEvent) {
      filteredOptions = filteredOptions.filter((option) => option.value !== 'closest_event');
    }
    return filteredOptions;
  });

  const visibleLabForm = ref(false);
  const valueTypes = ["numeric", "text"];

  const searchingLabResults = ref(false);
  const filteredLabResults = ref<object[]>([]);

  const editingLab = ref(false);
  const id = ref("");
  const label = ref();
  const notes = ref("");
  const selectedLabResults = ref([]);
  const valueType = ref();
  const aggSelections = ref<string[]>([]);
  const minThreshold = ref();
  const maxThreshold = ref();

  const localSelectedLabs = computed({
    get () {
      return props.selectedLabs;
    },
    set(newValue) {
      emit('updateLabs', newValue);
    }
  });

  const getNewId = () => {
    return Date.now() + "";
  };


  const labToDelete = ref();
  const visibleConfirmDeleteLab = ref(false);

  const labForm = computed(() => {
    return {
      id: id.value,
      lab_results: selectedLabResults.value,
      label: label.value,
      notes: notes.value,
      value_type: valueType.value,
      aggregation_options: aggSelections.value,
      min_threshold: minThreshold.value,
      max_threshold: maxThreshold.value
    };
  });

  const validationState = computed(() => {
    return {
      selectedLabResults: selectedLabResults.value,
      label: label.value,
      valueType: valueType.value,
      aggSelections: aggSelections.value,
      minThreshold: minThreshold.value,
      maxThreshold: maxThreshold.value
    }
  });

  const rules = computed(() => ({
    selectedLabResults: {
      required
    },
    label: {
      required,
      customUnique: helpers.withMessage('Label must be unique.', () => {
        const otherLabels = localSelectedLabs.value.filter((result:any) => result.id !== id.value);
        return !otherLabels.map((result:any) => result.label).includes(label.value);
      }),
      customLegal: helpers.withMessage('Label must be alphanumeric with spaces.', () => {
        return /^[a-zA-Z0-9 ]+$/.test(label.value);
      })
    },
    valueType: {
      required
    },
    aggSelections: {
      required: helpers.withMessage('You must select at least one option.', required)
    },
    minThreshold: {
      customMax: helpers.withMessage('Minimum must be less than or equal to maximum.', () => {
        if (minThreshold.value && maxThreshold.value) {
          return minThreshold.value <= maxThreshold.value;
        }
        return true;
      }),
      customValidNum: helpers.withMessage('Minimum must be less than 9999999.', () => {
        if (minThreshold.value) {
          return minThreshold.value < 9999999;
        }
        return true;
      })
    },
    maxThreshold: {
      custom: helpers.withMessage('Maximum must be less than 9999999.', () => {
        if (maxThreshold.value) {
          return maxThreshold.value < 9999999;
        }
        return true;
      })
    }
  }));

  const v$ = useVuelidate(rules, validationState, { $scope: false });

  // TODO update how this filters, not very robust/effective for end user
  const searchLabResults = (event:any) => {
    searchingLabResults.value = true;
    const query = event.query.trim().toLowerCase();
    //  include results that match query and also filter out results that already exist in selectedLabResults.value
    filteredLabResults.value = labResults.value.filter((result:any) => result.label.toLowerCase().includes(query)
        && !selectedLabResults.value.map((result:any) => result.label).includes(result.label));
    searchingLabResults.value = false;
  }

  const getAggregateLabel = (aggregation:string) => {
    return aggOptions.value.find((option) => option.value === aggregation)?.text;
  }

  const canEditOrDelete = (lab:any) => {
    return props.initialLabs.findIndex((obj:any) => obj.id === lab.id) === -1;
  }

  const submitForm = () => {
    v$.value.$touch();
    if (!v$.value.$error) {
      if (editingLab.value) {
        const index = localSelectedLabs.value.findIndex((result:any) => result.id === labForm.value.id);
        localSelectedLabs.value[index] = labForm.value;
      }
      else {
        id.value = getNewId();
        localSelectedLabs.value.push(labForm.value);
      }
      closeForm();
    }
  }

  const editLab = (labObj:any) => {
    const lab:any = localSelectedLabs.value.find((result:any) => result.id === labObj.id);
    if (lab) {
      editingLab.value = true;
      id.value = labObj.id;
      label.value = labObj.label;
      notes.value = labObj.notes;
      selectedLabResults.value = labObj.lab_results;
      valueType.value = labObj.value_type;
      aggSelections.value = labObj.aggregation_options;
      minThreshold.value = labObj.min_threshold;
      maxThreshold.value = labObj.max_threshold;
      visibleLabForm.value = true;
    }
  }

  const confirmDeleteLab = (labObj:any) => {
    labToDelete.value = labObj;
    visibleConfirmDeleteLab.value = true;
  }

  const closeConfirmDeleteLab = () => {
    visibleConfirmDeleteLab.value = false;
    labToDelete.value = null;
  }

  const deleteLab = () => {
    localSelectedLabs.value = localSelectedLabs.value.filter((result:any) => result.id !== labToDelete.value.id);
  }

  const closeForm = () => {
    visibleLabForm.value = false;
    editingLab.value = false;
    id.value = "";
    label.value = "";
    notes.value = "";
    selectedLabResults.value = [];
    valueType.value = null;
    aggSelections.value = [];
    minThreshold.value = null;
    maxThreshold.value = null;
    v$.value.$reset();
  }

</script>

<template>
  <div class="mb-2">
    <p>
      You can define your own labs here. This is especially helpful if the current library of clinical variables that DUSTER provides doesn't contain a particular lab that you want.
    </p>
    <p>
      Each lab may be defined by multiple lab results. Each individual lab result is a unique combination of lab name and base name. At least one lab result is required to define a lab.
    </p>
  </div>
  <div>
  <DataTable
      class="mb-2"
      :value="localSelectedLabs"
      removableSort
  >
    <Column
        field="lab_results"
        header="Lab Results"
    >
      <template #body="slotProps">
        <Chip
            v-for="(result,index) in slotProps.data.lab_results"
            :key="index"
            :label="result.label"
        >
        </Chip>
      </template>
    </Column>
    <Column
        field="label"
        header="Label"
        sortable
    />
    <Column
        field="notes"
        header="Notes"
        sortable
    />
    <Column
        field="value_type"
        header="Value Type"
        sortable
    />
    <Column
        field="aggregation_options"
        header="Aggregations"
    >
      <template #body="slotProps">
        <!-- <span v-if="slotProps.data.aggregation_type === 'default'">Default</span> -->
        <Chip
            v-for="(aggregation) in slotProps.data.aggregation_options"
            :key="aggregation"
            :label="getAggregateLabel(aggregation)"
        >
        </Chip>
      </template>
    </Column>
    <Column
        field="min_threshold"
        header="Minimum Threshold"
    >
      <template #body="slotProps">
        {{slotProps.data.value_type === 'numeric' ? slotProps.data.min_threshold : 'N/A' }}
      </template>
    </Column>
    <Column
        field="max_threshold"
        header="Maximum Threshold"
    >
      <template #body="slotProps">
        {{slotProps.data.value_type === 'numeric' ? slotProps.data.max_threshold : 'N/A' }}
      </template>
    </Column>
    <Column header="Actions">
      <template #body="slotProps">
        <Button
            v-show="canEditOrDelete(slotProps.data)"
            icon="pi pi-pencil"
            outlined
            rounded
            class="ml-2 p-1 small-icon"
            severity="secondary"
            v-tooltip.top="'Edit'"
            @click="editLab(slotProps.data)"
        >
        </Button>
        <Button
            v-show="canEditOrDelete(slotProps.data)"
            icon="pi pi-trash"
            outlined
            rounded
            class="ml-2 p-1 small-icon"
            severity="danger"
            v-tooltip.top="'Delete'"
            @click="confirmDeleteLab(slotProps.data)"
        >
        </Button>

      </template>
    </Column>
    
    <template #empty>No labs added.</template>

  </DataTable>
  <Button
      label="Add Lab"
      icon="pi pi-plus"
      severity="success"
      class="mr-2"
      @click="visibleLabForm = true"
  >
  </Button>
  <Dialog
    :visible="visibleLabForm"
    modal
    :header="editingLab ? 'Edit Lab' : 'Add New Lab'"
    :closable="false"
    :style="{width:'60rem'}"
  >
    <div class="formgrid grid">
      <div>
        <label
            for="lab-result-input"
            class="font-bold block"
        >
          Lab Results
        </label>
        <small>
          Multiple may be added. Results will be in the format 'Lab Name [Base Name] (Number of unique patients in STARR) Years resulted'
        </small>
        <InputGroup class="mb-2">
          <AutoComplete
              id="lab-result-input"
              :class="{ 'p-invalid': v$.selectedLabResults.$error }"
              v-model="selectedLabResults"
              :multiple="true"
              :suggestions="filteredLabResults"
              :virtualScrollerOptions="{ itemSize:38, showLoader:true, lazy:true }"
              :loading="searchingLabResults"
              @blur="v$.selectedLabResults.$touch"
              @complete="searchLabResults($event)"
              optionLabel="label"
              placeholder="Search to add"
          >
          </AutoComplete>
        </InputGroup>
        <small
            v-if="v$.selectedLabResults.$error"
            class="flex p-error mb-3"
        >
          {{ v$.selectedLabResults.$errors[0].$message }}
        </small>

        <div class="formgroup-inline">
          <label
              for=label
              class="font-bold block mb-2"
          >
            Label
          </label>
          <Button
              icon="pi pi-info-circle"
              text rounded
              class="ml-2 pt-0 pb-0 mt-0 mb-0"
              style="height:1.3em"
              v-tooltip.right="{
                value: 'This label is simply the name you want to give your user-defined lab. It does not affect the query to fetch results. The REDCap field(s) created for this lab will be based on this label. This label must be unique from other user-defined labs in the same data collection window.',
                pt: {
                    text: {
                        style: {
                            width: '400px'
                        }
                    }
                }
            }"
          />
        </div>

        <InputText
            maxlength="26"
            id="label"
            v-model="label"
            autocomplete="off"
            :class="{ 'p-invalid': v$.label.$error }"
            @blur="v$.label.$touch()"
        />
        <br/>
        <small>
          {{label?.length ? label.length : 0}}/26 characters
        </small>
        <small
            v-if="v$.label.$error"
            class="flex p-error mb-3"
        >
          {{ v$.label.$errors[0].$message }}
        </small>

        <div class="my-2">
          <div class="formgroup-inline">
            <label for="notes" class="font-bold block mb-2">Notes (optional)</label>
            <Button
                icon="pi pi-info-circle"
                text rounded
                class="ml-2 pt-0 pb-0 mt-0 mb-0"
                style="height:1.3em"
                v-tooltip.right="{
                  value: 'Anything entered in this input field will not affect the query to fetch results. It is more of a reference for yourself and will appear as part of the REDCap field note for this lab\'s REDCap field(s). Among other things, you could use this to indicate the unit of measurement you expect for your lab\'s results.',
                  pt: {
                      text: {
                          style: {
                              width: '400px'
                          }
                      }
                  }
              }"
            />
          </div>

          <InputText
              maxlength="80"
              id="notes"
              v-model="notes"
              autocomplete="off"
              class="w-7"
          />
          <br/>
          <small>
            {{notes?.length ? notes.length : 0}}/80 characters
          </small>
        </div>
        <div class="formgroup-inline flex align-items-center">
          <p class="font-bold block mb-2">Value Type</p>
          <Button
              icon="pi pi-info-circle"
              text rounded
              class="ml-2 pt-0 pb-0 mt-0 mb-0"
              style="height:1.3em"
              v-tooltip.right="{
                  value: 'You can choose your lab results to either be \'numeric\' or \'text\'. If you select \'numeric\', then only lab results with numeric values will be aggregated. This means that lab results are filtered out if the result does not directly represent a number. For example, if a lab result was \'2.3 (see note)\', then that result will get filtered out. If you select \'text\', then lab results will be aggregated without filtering. For example, a lab result with a value of \'2.3 (see note)\' and a result with a value of \'2.3\' would both be included in the aggregation of a user-defined lab for \'text\' values.',
                  pt: {
                      text: {
                          style: {
                              width: '400px'
                          }
                      }
                  }
              }"
          />
        </div>

        <div class="formgroup-inline">
          <div
              v-for="type in valueTypes"
              :key="type"
          >
          <div class="field-radiobutton">
            <RadioButton
                v-model="valueType"
                :inputId="type"
                :value="type"
                :class="{ 'p-invalid': v$.valueType.$error }"
                @change="aggSelections = [], v$.aggSelections.$reset(), minThreshold = null, maxThreshold = null"
                @blur="v$.valueType.$touch()"
            />
            <label
                :for="type"
                class="ml-2"
            >
              {{ type }}
            </label>
          </div>
        </div>
        <small
            v-if="v$.valueType.$error"
            class="flex p-error mb-3"
        >
          {{ v$.valueType.$errors[0].$message }}
        </small>
        </div>

        <div v-if="valueType">
          <div class="formgroup-inline flex align-items-center">
            <p class="font-bold block mb-2">Aggregations</p>
            <Button
                icon="pi pi-info-circle"
                text rounded
                class="ml-2 pt-0 pb-0 mt-0 mb-0"
                style="height:1.3em"
                v-tooltip.right="{
                  value: 'If you select \'numeric\', then only lab results with numeric values will be aggregated. This means that lab results are filtered out if the result does not directly represent a number. For example, if a lab result was \'2.3 (see note)\', then that result will get filtered out. If you select \'text\', then lab results will be aggregated without filtering. For example, a lab result with a value of \'2.3 (see note)\' and a result with a value of \'2.3\' would both be included in the aggregation of a user-defined lab for \'text\' values.',
                  pt: {
                      text: {
                          style: {
                              width: '400px'
                          }
                      }
                  }
              }"
            />
          </div>
          <!--
          <div
            class="flex align-items-center gap-3 mb-2"
            v-if="['numeric', 'text'].includes(valueType)"
          >
            <div class="formgroup-inline">
              <div class="field-radiobutton">
                <RadioButton
                  v-model="aggType"
                  inputId="st-default"
                  value="default"
                />
                <label
                  for="st-default"
                  class="ml-2"
                >
                  Default
                </label>
              </div>
              <div class="field-radiobutton">
                <RadioButton
                  v-model="aggType"
                  inputId="st-custom"
                  value="custom"
                />
                <label
                  for="st-custom"
                  class="ml-2"
                >
                  Custom
                </label>
              </div>
            </div>
          </div>
          -->

          <div class="mb-4">
            <div class="formgroup-inline">
              <div
                  class="mr-4"
                  v-for="(option, index) in aggOptions"
                  :key="index"
              >
                <Checkbox
                    class="mr-2"
                    v-model="aggSelections"
                    :value="option.value"
                    :inputId="'aggOptions_index_' + index"
                    @blur="v$.aggSelections.$touch()"
                />

                <label
                    :for="'aggOptions_index_' + index"
                >
                  {{option.text}}
                </label>
              </div>
            </div>
            <small
                v-if="v$.aggSelections.$error"
                class="flex p-error mb-3"
            >
              {{ v$.aggSelections.$errors[0].$message }}
            </small>
          </div>
        </div>
        <div
            class="mb-4"
            v-if="valueType==='numeric'"
        >
          <div>
            <div class="flex align-items-center formgroup-inline">
              <p class="flex align-items-center font-bold block mb-2">Thresholds (optional)</p>
                <Button
                    icon="pi pi-info-circle"
                    text rounded
                    class="flex align-items-center ml-2 pt-0 pb-0 mt-0 mb-0"
                    style="height:1.3em"
                    v-tooltip.right="{
                      value: 'These are optional parameters that can be used to establish cutoff values for numeric lab results (minimum threshold <= lab result value <= maximum threshold).',
                      pt: {
                          text: {
                              style: {
                                  width: '400px'
                              }
                          }
                      }
                  }"
                />
            </div>
          </div>
          <div class="flex align-items-center gap-3 mb-2">
            <label
                for="min-threshold"
                class="w-6rem"
            >
              Minimum
            </label>
            <InputNumber
                id="min-threshold"
                v-model="minThreshold"
                @blur="v$.minThreshold.$touch()"
                :class="{ 'p-invalid': v$.minThreshold.$error }"
                :useGrouping="false"
                :minFractionDigits="0"
                :maxFractionDigits="2"
                autocomplete="off"
            />
          </div>
          <div class="flex align-items-center gap-3 mb-2">
            <label
                for="max-threshold"
                class="w-6rem"
            >
              Maximum
            </label>
            <InputNumber
                id="max-threshold"
                v-model="maxThreshold"
                @blur="v$.minThreshold.$touch(), v$.maxThreshold.$touch()"
                :class="{ 'p-invalid': v$.minThreshold.$error || v$.maxThreshold.$error }"
                :useGrouping="false"
                :minFractionDigits="0"
                :maxFractionDigits="2"
                autocomplete="off"
            />
          </div>
          <small
              v-if="v$.minThreshold.$error"
              class="flex p-error mb-3"
          >
            {{ v$.minThreshold.$errors[0].$message }}
          </small>
          <small
              v-if="v$.maxThreshold.$error"
              class="flex p-error mb-3"
          >
            {{ v$.maxThreshold.$errors[0].$message }}
          </small>
        </div>
      </div>
    </div>
    <template #footer>
      <Button label="Submit" severity="primary" @click="submitForm()" />
      <Button label="Cancel" severity="secondary" @click="closeForm()" />
    </template>
  </Dialog>
  <Dialog
    :visible="visibleConfirmDeleteLab"
    modal
    header="Delete Lab"
    :closable="false"
  >
    <div>
      <p>
        Are you sure you want to delete {{labToDelete.label}}?
      </p>
    </div>
    <template #footer>
      <Button label="No" severity="secondary" @click="closeConfirmDeleteLab()" />
      <Button label="Yes" severity="primary" @click="deleteLab(), closeConfirmDeleteLab()" />
    </template>
  </Dialog>
  </div>
</template>

<style scoped>

</style>
<script setup lang="ts">
  import { reactive, ref, inject, computed } from "vue";
  import type { ComputedRef } from "vue";
  import { useVuelidate } from '@vuelidate/core';
  import {helpers, required} from '@vuelidate/validators';
  import AutoComplete from 'primevue/autocomplete';
  import InputText from "primevue/inputtext";
  import InputGroup from "primevue/inputgroup";

  const medicationsMetadata = inject('medicationsMetadata') as ComputedRef<any>;

  const props = defineProps({
    initialMedications: {
      type: Array,
      required: true
    }
  });

  const medications = defineModel({
    type: Array,
    required: true
  });

  const editingMedication = ref(false);
  const id = ref("");
  const label = ref();
  const notes = ref("");
  const selectedTherapeutics = ref([]);
  const selectedPharmacologics = ref([]);

  const visibleMedForm = ref(false);

  const searchingTherapeutics = ref(false);
  const filteredTherapeutics = ref<object[]>([]);
  const searchingPharmacologics = ref(false);
  const filteredPharmacologics = ref<object[]>([]);

  const getNewId = () => {
    return Date.now() + "";
  };

  const medicationForm = computed(() => {
    return {
      id: id.value,
      label: label.value,
      notes: notes.value,
      therapeutics: selectedTherapeutics.value,
      pharmacologics: selectedPharmacologics.value
    };
  });

  const canEditOrDelete = (medication:any) => {
    return props.initialMedications.findIndex((obj:any) => obj.id === medication.id) === -1;
  }

  const editMedication = (medication:any) => {
    const medFound:any = medications.value.find((result:any) => result.id === medication.id);
    if (medFound) {
      editingMedication.value = true;
      id.value = medication.id;
      label.value = medication.label;
      notes.value = medication.notes;
      selectedPharmacologics.value = medication.pharmacologics;
      selectedTherapeutics.value = medication.therapeutics;
      visibleMedForm.value = true;
    }
  }

  const confirmDeleteMedication = (medication:any) => {
    if (confirm('Are you sure you want to delete this medication?')) {
      medications.value = medications.value.filter((result:any) => result.id !== medication.id);
    }
  }

  const validationState = computed(() => {
    return {
      label: label.value,
      selectedClasses: selectedTherapeutics.value.concat(selectedPharmacologics.value)
    };
  });

  const rules = {
    label: {
      required,
      customUnique: helpers.withMessage('Label must be unique.', () => {
        const otherLabels = medications.value.filter((result:any) => result.id !== id.value);
        return !otherLabels.map((result:any) => result.label).includes(label.value);
      }),
      customLegal: helpers.withMessage('Label must be alphanumeric with spaces.', () => {
        return /^[a-zA-Z0-9 ]+$/.test(label.value);
      })
    },
    selectedClasses: {
      custom: helpers.withMessage('At least one therapeutic class or pharmacologic class must be selected.', () => {
        return validationState.value.selectedClasses.length > 0;
      })
    }
  }

  const v$ = useVuelidate(rules, validationState, { $scope: false });

  // TODO update how this filters, not very robust/effective for end user
  const searchTherapeutics = (event:any) => {
    searchingTherapeutics.value = true;
    const query = event.query.trim().toLowerCase();
    //  include results that match query and also filter out results that already exist in selectedTherapeutics.value
    filteredTherapeutics.value = medicationsMetadata.value.therapeutics.filter((result:any) => result.label.toLowerCase().includes(query)
        && !selectedTherapeutics.value.map((result:any) => result.label).includes(result.label));
    searchingTherapeutics.value = false;
  }

  // TODO update how this filters, not very robust/effective for end user
  const searchPharmacologics = (event:any) => {
    searchingPharmacologics.value = true;
    const query = event.query.trim().toLowerCase();
    //  include results that match query and also filter out results that already exist in selectedPharmacologics.value
    filteredPharmacologics.value = medicationsMetadata.value.pharmacologics.filter((result:any) => result.label.toLowerCase().includes(query)
        && !selectedPharmacologics.value.map((result:any) => result.label).includes(result.label));
    searchingPharmacologics.value = false;
  }

  const submitForm = () => {
    v$.value.$touch();
    if (!v$.value.$error) {
      if(editingMedication.value) {
        const index = medications.value.findIndex((result:any) => result.id === id.value);
        medications.value[index] = medicationForm.value;
      } else {
        id.value = getNewId();
        medications.value.push(medicationForm.value);
      }
      closeForm();
    }
  }
  const closeForm = () => {
    visibleMedForm.value = false;
    editingMedication.value = false;
    id.value = "";
    label.value = "";
    notes.value = "";
    selectedTherapeutics.value = [];
    selectedPharmacologics.value = [];
    v$.value.$reset();
  }

</script>

<template>
  <div class="mb-2">
    <p>
      Each medication defined and added here creates a boolean clinical variable that answers this question - "In this data collection window, was this medication ordered for the patient (yes/no)?"
    </p>
    <p>
      Please note that this only attempts to capture if a medication order was placed during the data collection window, not the actual administration of the medication.
    </p>
    <p>
      Each medication may be defined by multiple therapeutic and pharmacologic class. At least one therapeutic or pharmacologic class is required to define a medication.
    </p>
  </div>
  <DataTable
    class="mb-2"
    :value="medications"
    removable-sort
  >

    <Column
      field="label"
      header="Label"
    >
    </Column>

    <Column
        field="therapeutics"
        header="Therapeutic Classes"
    >
      <template #body="slotProps">
        <Chip
            v-for="(therapeutic,index) in slotProps.data.therapeutics"
            :key="index"
            :label="therapeutic.label"
        >
        </Chip>
      </template>
    </Column>

    <Column
      field="pharmacologics"
      header="Pharmacologic Classes"
    >
      <template #body="slotProps">
        <Chip
            v-for="(pharmacologic,index) in slotProps.data.pharmacologics"
            :key="index"
            :label="pharmacologic.label"
        >
        </Chip>
      </template>
    </Column>

    <Column
      field="notes"
      header="Notes"
    >
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
            @click="editMedication(slotProps.data)"
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
            @click="confirmDeleteMedication(slotProps.data)"
        >
        </Button>
      </template>
    </Column>

  </DataTable>

  <Button
    label="Add Medication"
    icon="pi pi-plus"
    severity="success"
    class="mr-2"
    @click="visibleMedForm=true"
  >
  </Button>

  <Dialog
    :visible="visibleMedForm"
    modal
    header="Add New Medication"
    :closable="false"
    :style="{width:'60rem'}"
  >
    <div>
      <div class="my-2">
        <label
            for="medLabel"
            class="font-bold block mb-2"
        >
          Label
        </label>
        <InputText
            id="medLabel"
            maxlength="26"
            v-model="label"
            autocomplete="off"
            :class="{ 'p-invalid': v$.label.$error }"
            @blur="v$.label.$touch()"
        />
        <br/>
        <small>
          {{label?.length ? label.length : 0}}/26 characters
        </small>
        <br/>
        <small
          v-if="v$.label.$error"
          class="flex p-error"
        >
          {{ v$.label.$errors[0].$message }}
        </small>
      </div>

      <div class="mb-2">
        <label for="notes" class="font-bold block mb-2">Notes (optional)</label>
        <InputText
            maxlength="80"
            id="medNotes"
            v-model="notes"
            autocomplete="off"
            class="w-7"
        />
        <br/>
        <small>
          {{notes?.length ? notes.length : 0}}/80 characters
        </small>
      </div>

      <div>
        <label
            for="therapeutics-input"
            class="font-bold block"
        >
          Therapeutic Classes
        </label>
        <small>Multiple may be added. Search results will be in the format 'Therapeutic Class (Number of unique patients in STARR) Years ordered'</small>
        <InputGroup class="mb-2">
          <AutoComplete
              id="therapeutics-input"
              :class="{  'p-invalid': v$.selectedClasses.$error }"
              v-model="selectedTherapeutics"
              :multiple="true"
              :suggestions="filteredTherapeutics"
              :virtualScrollerOptions="{ itemSize:38, showLoader:true, lazy:true }"
              :loading="searchingTherapeutics"
              @blur="v$.selectedClasses.$touch"
              @complete="searchTherapeutics($event)"
              optionLabel="label"
              placeholder="Search to add"
          >
          </AutoComplete>
        </InputGroup>
      </div>

      <div>
        <label
            for="pharmacologics-input"
            class="font-bold block"
        >
          Pharmacologic Classes
        </label>
        <small>Multiple may be added. Search results will be in the format 'Pharmacologic Class (Number of unique patients in STARR) Years ordered'</small>
        <InputGroup class="mb-2">
          <AutoComplete
              id="pharmacologics-input"
              :class="{ 'p-invalid': v$.selectedClasses.$error }"
              v-model="selectedPharmacologics"
              :multiple="true"
              :suggestions="filteredPharmacologics"
              :virtualScrollerOptions="{ itemSize:38, showLoader:true, lazy:true }"
              :loading="searchingPharmacologics"
              @blur="v$.selectedClasses.$touch"
              @complete="searchPharmacologics($event)"
              optionLabel="label"
              placeholder="Search to add"
          >
          </AutoComplete>
        </InputGroup>
      </div>

      <div>
        <small
            v-if="v$.selectedClasses.$error"
            class="flex p-error mb-3"
        >
          {{ v$.selectedClasses.$errors[0].$message }}
        </small>
      </div>
    </div>
    <template #footer>
      <Button label="Submit" severity="primary" @click="submitForm()" />
      <Button label="Cancel" severity="secondary" @click="closeForm()" />
    </template>
  </Dialog>
</template>

<style scoped>

</style>
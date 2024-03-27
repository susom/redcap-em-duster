<template>
  <Panel>
    <template #header>
      <span class="p-panel-title">Researcher-Provided Info
        <Button icon="pi pi-info-circle"
                text rounded
                aria-label="Info"
                class="ml-2 pt-0 pb-0 mt-0 mb-0"
                style="height:1.3em"
                @click="showRPInfoHelp = true"/>
      </span>
    </template>
    <div class="col-12">
      <DataTable
        :value="localRpData"
        class="p-datatable-sm"
        data-key="id"
      >
        <Column
            key="value_type"
            field="value_type"
            header="Type"
        >
          <template #body="slotProps">
            <div
              v-if="localRpData[slotProps.index].value_type != 'Identifier'"
            >
              <Dropdown
                v-model="slotProps.data[slotProps.field]"
                :options="dateTypes"
                :class="['w-full md:w-8rem',{'p-invalid':rpFieldInvalid('value_type', slotProps.index)}]"
                optionLabel="text"
                optionValue="dtValue"
                placeholder="Select a type"
                :disabled="rpData[slotProps.index].edit === false"
              >
              </Dropdown>
              <small
                v-if="rpFieldInvalid('value_type', slotProps.index)"
                class="flex p-error mb-3"
              >
                {{ v$.rpData.$each.$response.$errors[slotProps.index].value_type[0].$message }}
              </small>
            </div>
            <span v-else>{{slotProps.data.value_type}}</span>
          </template>
        </Column>
        <Column
          key="label"
          field="label"
          header="Label"
        >
          <template
              #body="slotProps">
            <div
              v-if="localRpData[slotProps.index].value_type != 'Identifier'">
              <InputText
                v-model="slotProps.data[slotProps.field]"
                :class="['w-full', {'p-invalid': rpFieldInvalid('label', slotProps.index)}]"
                :disabled="rpData[slotProps.index].edit === false"
              >
            </InputText>
            <small v-if="rpFieldInvalid('label', slotProps.index)"
                   class="flex p-error mb-3">
              {{ v$.rpData.$each.$response.$errors[slotProps.index].label[0].$message }}
            </small>
            </div>
            <span v-else>{{slotProps.data.label}}</span>
          </template>
        </Column>
        <Column
            key="redcap_field_name"
            field="redcap_field_name"
            header="REDCap Field Name"
            >
          <template
              #body="slotProps">
            <div
                 v-if="localRpData[slotProps.index].value_type != 'Identifier'">
              <InputText
                v-model="slotProps.data[slotProps.field]"
                :class="['w-full', {'p-invalid': rpFieldInvalid('redcap_field_name', slotProps.index)}]"
                :disabled="rpData[slotProps.index].edit === false"
              >
              </InputText>
              <small
                v-if="rpFieldInvalid('redcap_field_name', slotProps.index)"
                class="flex p-error mb-3"
              >
              {{ v$.rpData.$each.$response.$errors[slotProps.index].redcap_field_name[0].$message }}
              </small>
            </div>
            <span v-else>{{slotProps.data.redcap_field_name}}</span>
          </template>
        </Column>
        <Column
          :exportable="false"
          header="Actions"
          :class="['w-6rem']">
          <template
            #body="slotProps"
          >
            <Button
              icon="pi pi-trash"
              outlined
              rounded
              size="small"
              severity="danger"
              :class="(slotProps.index < 2 || rpData[slotProps.index].edit === false)? 'hidden' : 'mr-2'"
              @click="confirmDeleteRpDate(slotProps.data)"
              :disabled="rpData[slotProps.index].edit === false"
            />
            <Button
              icon="pi pi-plus"
              outlined
              rounded
              size="small"
              severity="success"
              :class="((slotProps.index == (localRpData.length -1)) && slotProps.index < 5  )? '': 'hidden'"
              @click="addRpDate"
            >
            </Button>
          </template>
        </Column>
      </DataTable>

    </div>
  </Panel>
  <Dialog
      v-model:visible="deleteRpDateDialog"
      :style="{width: '450px'}"
      header="Confirm"
      :modal="true">
    <div class="confirmation-content mt-2 mb-4">
      <i class="pi pi-exclamation-triangle mr-3"
         style="font-size: 2rem" />
      <span
          v-if="rpDate">
        Are you sure you want to delete <b>{{rpDate.label}}</b>?
      </span>
    </div>
    <template #footer>
      <Button
          label="No"
          icon="pi pi-times"
          text
          @click="deleteRpDateDialog = false"/>
      <Button
          label="Yes"
          icon="pi pi-check"
          text
          @click="deleteRpDate" />
    </template>
  </Dialog>
  <Dialog v-model:visible="showRPInfoHelp" modal header="Data Collection Windows" :style="{ width: '50vw' }">
    <div class="my-2">
      There are identifiers and dates/datetimes for your study cohort that you will provide for your REDCap project.
      <br>
      <br>
      The minimum required information for each record is an MRN and a study enrollment date, which DUSTER will use to query STARR.
      <br>
      Optionally, you may also add other dates/datetimes of interest.
      <br>
      <br>
      After DUSTER creates the project, you may perform a bulk upload of the Researcher-Provided Info you define here using the Data Import Tool.
    </div>
    <template #footer>
      <Button @click="showRPInfoHelp=false">Close</Button>
    </template>
  </Dialog>
</template>


<script setup lang="ts">
import {computed, ref} from 'vue'
import type {PropType} from 'vue'
import type {BasicConfig} from "@/types/FieldConfig";
import {INIT_BASIC_CONFIG} from "@/types/FieldConfig";
import {helpers, required} from "@vuelidate/validators";
import {useVuelidate} from "@vuelidate/core";


const props = defineProps({
  rpData: {
    type: Object as PropType<BasicConfig[]>,
    required: true
  },
  reservedFieldNames : {
      type: Array as PropType<string[]>,
      required:true
    }
});

const emit = defineEmits(
    ['update:rpData']
);

const dateTypes = ref([
  {text: 'Date', dtValue: 'date'},
  {text: 'Datetime', dtValue: 'datetime'}
]);

const localRpData = computed({
  get(){
    return props.rpData;
  },
  set(value) {
    emit('update:rpData', value)
  }
});
const localRpDatesEditing = ref<BasicConfig[]>([]);

const deleteRpDateDialog = ref(false);

const newRpDate = () => {
  return {...INIT_BASIC_CONFIG}
}
const rpDate = ref<BasicConfig>(newRpDate())

const addRpDate = () => {
  rpDate.value = newRpDate()
  rpDate.value.redcap_field_type = 'text';
  rpDate.value.phi = 't';
  rpDate.value.id = (rpDate.value.redcap_field_name || "") + new Date().getTime()
  console.log(rpDate.value);

  if (localRpData.value)
    localRpData.value.push(rpDate.value)
}

const confirmDeleteRpDate = (rpDateToDelete:BasicConfig) => {
  rpDate.value = rpDateToDelete;
  deleteRpDateDialog.value = true;
};

const deleteRpDate = () => {
  if (localRpData.value) {
    localRpData.value = localRpData.value.filter((val: BasicConfig) => val.id !==
        rpDate.value.id);
  }
  deleteRpDateDialog.value = false;
  rpDate.value = newRpDate();
}

// returns array of field names not including current field
// includes reserved redcap_field_names of demographics
const otherFieldNames = (id:string) => {
  return localRpData.value
      .filter((data) => data.id != id)
      .map(data => data.redcap_field_name)
      .concat(props.reservedFieldNames)
}

const showRPInfoHelp = ref(false);

/**** vuelidate ***/
/* this is needed b/c we have set initialization to lazy **/
const rpFieldInvalid = (field:string, index: number) =>{
  if (v$.value.rpData.$each.$response
      && v$.value.rpData.$each.$response['$errors']
      && v$.value.rpData.$each.$response.$errors[index]
      && v$.value.rpData.$each.$response.$errors[index][field].length) {
    return true
  }
  return false
}

const uniqueLabel = (value:string, siblings:any, vm: any) => {
  return (localRpData.value.findIndex(rp => rp.id != siblings.id && rp.label == value) == -1)
}

const uniqueRedcapFieldName = (value:string, siblings:any, vm: any) => {
  return (otherFieldNames(siblings.id ?? "").indexOf(value) == -1)
}

const isRedcapFieldName = helpers.regex(/^[a-z][a-z0-9_]*$/)

const state = computed(() => {
  return {
    rpData: localRpData.value
  }
})
const rules = {
  rpData: {
    $each: helpers.forEach({
          value_type: {
            required: helpers.withMessage('Date types are required', required)
          },
          label: {
            required: helpers.withMessage('Labels are required', required),
            uniqueLabel: helpers.withMessage('Labels must be unique', uniqueLabel)
          },
      redcap_field_name: {
            required: helpers.withMessage('Redcap field names are required', required),
            isRedcapFieldName: helpers.withMessage('Only lowercase letters, numbers and underscores allowed',
                isRedcapFieldName),
            uniqueRedcapFieldName: helpers.withMessage('Must be unique',
                uniqueRedcapFieldName)
      }
        }
    )
  }
}

const v$ = useVuelidate(rules, state, {$lazy: true})

</script>



<style scoped>

</style>

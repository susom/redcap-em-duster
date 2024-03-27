<template>
  <div class="container">
    <Panel header="Demographics">
      <div class="mb-2">
        Select demographics below that you'd like to collect on your cohort.
          <br>
          <em>Please bear in mind HIPAA Minimum Necessary when selecting identifying information.</em>
      </div>

      <div class="formgrid grid">
          <div
            v-for="field in demographicsOptions"
            :key="field.duster_field_name"
            class="my-2 col-6"
          >
            <div>
              <Checkbox
                v-model="selected"
                :input-id="field.duster_field_name"
                :value="field"
                :disabled="field.edit === false"
              />
              <label
                :for="field.duster_field_name"
                class="ml-2"
              >
                {{ field.label }}
              </label>
            </div>
          </div>
      </div>
      <div class="formgrid grid">
        <div class="col-offset-6 col-6">
        <Button
          v-if="canEditAll()"
          :label="selectButtonLabel"
          size="small"
          @click="selectAll()" />
  <!--Checkbox v-model="selectAll"
                    id="selectAll"
                    :binary="true"
          />
          <label for="selectAll" class="ml-2">Select All</label-->
        </div>
      </div>
    </Panel>
  </div>
</template>

<script setup lang="ts">
import {computed} from "vue"
import type {PropType} from 'vue'
import type FieldMetadata from "@/types/FieldMetadata"

const props = defineProps({
  demographicsOptions: {
    type: Array as PropType<Array<FieldMetadata>>,
    required: true
  },
  demographicsSelects: {
    type:  Array as PropType<Array<FieldMetadata>>,
    required: true
  }
})
const emit = defineEmits(['update:demographicsSelects'])

const selected = computed({
  get() {
    return props.demographicsSelects
  },
  set(value) {
    emit('update:demographicsSelects', value)
  }
})

const selectButtonLabel = computed(()=> {
    return (selected.value.length < props.demographicsOptions.length)
        ? "Select All"
        : "Unselect All"
})

const selectAll = () => {
  // selectButtonLabel is computed after this invoked so can't use it here.
  if (selected.value.length < props.demographicsOptions.length) {
    selected.value = [...props.demographicsOptions]
  } else {
    selected.value.length = 0
  }
}


const canEditAll = () => {
  for (const demographic of props.demographicsSelects) {
    if (demographic.edit === false) {
      return false;
    }
  }
  return true;
}

/*const selectAll = ref<Boolean>(false)
watch(selectAll, (newSelectAll: any) => {
  if (selected.value) {
    if (newSelectAll) {
        selected.value.length = 0
        if (sorted.value) {
          selected.value = [...sorted.value]
      }
    } else {
      selected.value.length = 0
    }
  }
})*/

</script>

<style scoped>
</style>

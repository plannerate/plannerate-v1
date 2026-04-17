<!--
 * FormFieldSection - Section container for organizing form fields with collapsible accordion
 *
 * Groups related fields together with an optional collapsible container
 -->
 <template>
  <div class="col-span-12">
    <Collapsible
      v-if="column.collapsible"
      :default-open="column.defaultOpen !== false"
      class="w-full space-y-2"
    >
      <div class="flex items-center justify-between space-x-4">
        <div class="flex-1">
          <h4 v-if="column.label" class="text-sm font-semibold">
            {{ column.label }}
            <span v-if="column.required" class="text-destructive">*</span>
          </h4>
          <p
            v-if="column.helpText || column.hint || column.tooltip"
            class="text-sm text-muted-foreground"
          >
            {{ column.helpText || column.hint || column.tooltip }}
          </p>
        </div>
        <CollapsibleTrigger as-child>
          <Button variant="ghost" size="sm" class="w-9 p-0">
            <ChevronsUpDown class="h-4 w-4" />
            <span class="sr-only">Toggle</span>
          </Button>
        </CollapsibleTrigger>
      </div>

      <CollapsibleContent class="space-y-2">
        <div class="rounded-md border px-4 py-3">
          <div class="grid grid-cols-12 gap-4">
            <div
              v-for="(field, index) in sectionFields"
              :key="field.name"
              :class="getColumnClasses(field)"
            >
              <FieldRenderer
                :column="field"
                :index="index"
                :error="props.error?.[field.name]"
                :modelValue="fieldValues[field.name]"
                @update:modelValue="(value) => handleFieldUpdate(field.name, value)"
              />
            </div>
          </div>
        </div>
      </CollapsibleContent>
    </Collapsible>

    <div v-else class="space-y-4">
      <div v-if="column.label || column.helpText || column.hint || column.tooltip">
        <h4 v-if="column.label" class="text-sm font-semibold">
          {{ column.label }}
          <span v-if="column.required" class="text-destructive">*</span>
        </h4>
        <p
          v-if="column.helpText || column.hint || column.tooltip"
          class="text-sm text-muted-foreground"
        >
          {{ column.helpText || column.hint || column.tooltip }}
        </p>
      </div>

      <div class="grid grid-cols-12 gap-4">
        <div
          v-for="(field, index) in sectionFields"
          :key="field.name"
          :class="getColumnClasses(field)"
        >
          <FieldRenderer
            :column="field"
            :index="index"
            :error="props.error?.[field.name]"
            :modelValue="fieldValues[field.name]"
            @update:modelValue="(value) => handleFieldUpdate(field.name, value)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Button } from "@/components/ui/button";
import { ChevronsUpDown } from "lucide-vue-next";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import FieldRenderer from "~/components/form/FieldRenderer.vue";
import { useGridLayout } from "~/composables/useGridLayout";
import { createMultiFieldUpdate, isMultiFieldUpdate } from "~/types/form";
import type { FieldEmitValue } from "~/types/form";

interface SectionField {
  name: string;
  label: string;
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
  readonly?: boolean;
  helpText?: string;
  columnSpan?: string;
  [key: string]: any;
}

interface FormColumn {
  name: string;
  label?: string;
  required?: boolean;
  tooltip?: string;
  helpText?: string;
  hint?: string;
  fields?: SectionField[];
  collapsible?: boolean;
  defaultOpen?: boolean;
}

interface Props {
  column: FormColumn;
  modelValue?: Record<string, any> | string | null;
  error?: Record<string, string | string[]>;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => ({}),
  error: () => ({}),
});

const emit = defineEmits<{
  (e: "update:modelValue", value: FieldEmitValue): void;
}>();

// Grid layout composable
const { getColumnClasses } = useGridLayout();

const fieldValues = ref<Record<string, any>>({});

// Campos da seção do backend
const sectionFields = computed(() => props.column.fields || []);

// Inicializa valores dos campos
watch(
  () => props.modelValue,
  (newValue) => {
    // Normaliza o modelValue para sempre ser um objeto
    const normalizedValue = typeof newValue === 'object' && newValue !== null ? newValue : {};
    
    sectionFields.value.forEach((field) => {
      // Se o campo usa dot notation, busca o valor aninhado
      if (field.name.includes(".")) {
        fieldValues.value[field.name] = getNestedValue(normalizedValue, field.name) || "";
      } else {
        fieldValues.value[field.name] = normalizedValue[field.name] || "";
      }
    }); 
  },
  { immediate: true }
);

// Obtém valor de objeto aninhado usando dot notation
// Ex: getNestedValue({settings: {theme: {color: "orange"}}}, "settings.theme.color") -> "orange"
function getNestedValue(obj: Record<string, any>, path: string): any {
  const keys = path.split(".");
  let current = obj;

  for (const key of keys) {
    if (
      current &&
      typeof current === "object" &&
      !Array.isArray(current) &&
      key in current
    ) {
      current = current[key];
    } else {
      return undefined;
    }
  }

  return current;
}

function handleFieldUpdate(fieldName: string, value: FieldEmitValue) {
  if (isMultiFieldUpdate(value)) {
    Object.entries(value.fields).forEach(([key, val]) => {
      fieldValues.value[key] = val;
    });
  } else {
    fieldValues.value[fieldName] = value;
  }
  // Emite o objeto da seção sob column.name para o FormRenderer gravar em formData[column.name]
  const sectionData = { ...fieldValues.value }; 
  emit(
    "update:modelValue",
    createMultiFieldUpdate({ [props.column.name]: sectionData })
  );
}
</script>

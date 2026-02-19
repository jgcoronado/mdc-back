<script setup>
import { ref, watch } from 'vue';
import AutocompleteMulti from '@/components/admin/AutocompleteMulti.vue';
import AutocompleteSingle from '@/components/admin/AutocompleteSingle.vue';
import { useAutocompleteSelect } from '@/composables/useAutocompleteSelect';
import {
  buildMarchaInsertPayload,
  executeMarchaInsert,
} from '@/services/edits';

const draftData = ref({
  TITULO: '',
  FECHA: '',
  DEDICATORIA: '',
  LOCALIDAD: '',
  PROVINCIA: '',
  BANDA_ESTRENO: null,
  DETALLES_MARCHA: '',
  AUTORES_IDS: '',
});

const authorSelector = useAutocompleteSelect({
  endpoint: '/autor/fastSearch',
  idKey: 'ID_AUTOR',
  minChars: 6,
  limit: 8,
  multiple: true,
});

const bandSelector = useAutocompleteSelect({
  endpoint: '/banda/fastSearch',
  idKey: 'ID_BANDA',
  minChars: 6,
  limit: 5,
  multiple: false,
});

const pendingInsert = ref(buildMarchaInsertPayload(draftData.value));
const requestState = ref({
  status: 'idle',
  code: '',
  msg: '',
});

watch(
  () => draftData.value,
  () => {
    pendingInsert.value = buildMarchaInsertPayload(draftData.value);
    if (requestState.value.status !== 'saving') {
      requestState.value = { status: 'idle', code: '', msg: '' };
    }
  },
  { deep: true }
);

watch(
  () => authorSelector.selected.value,
  () => {
    draftData.value.AUTORES_IDS = (authorSelector.selected.value || [])
      .map((author) => author.ID_AUTOR)
      .join(',');
  },
  { deep: true }
);

watch(
  () => bandSelector.selected.value,
  () => {
    draftData.value.BANDA_ESTRENO = bandSelector.selected.value?.ID_BANDA ?? null;
  },
  { deep: true }
);

async function sendDataToAddMarcha() {
  const payload = buildMarchaInsertPayload(draftData.value);
  pendingInsert.value = payload;
  requestState.value = { status: 'saving', code: '', msg: '' };

  try {
    const result = await executeMarchaInsert(payload);
    requestState.value = {
      status: result.code === 'CREATED' ? 'success' : 'error',
      code: result.code || 'UNKNOWN',
      msg: result.msg || 'Respuesta sin mensaje',
    };
  } catch (error) {
    requestState.value = {
      status: 'error',
      code: error?.response?.data?.code || 'REQUEST_ERROR',
      msg: error?.response?.data?.msg || 'No se pudo crear la marcha.',
    };
  }
}

function resetDraft() {
  draftData.value = {
    TITULO: '',
    FECHA: '',
    DEDICATORIA: '',
    LOCALIDAD: '',
    PROVINCIA: '',
    BANDA_ESTRENO: null,
    DETALLES_MARCHA: '',
    AUTORES_IDS: '',
  };
  authorSelector.reset();
  bandSelector.reset();
  pendingInsert.value = buildMarchaInsertPayload(draftData.value);
  requestState.value = { status: 'idle', code: '', msg: '' };
}

function getAuthorLabel(author) {
  const apellidos = (author?.APELLIDOS || '').trim();
  const nombre = (author?.NOMBRE || '').trim();
  const full = `${apellidos} ${nombre}`.trim();
  return full || author?.NOMBRE_COMPLETO || '';
}

function getBandLabel(band) {
  const shortName = (band?.NOMBRE_BREVE || '').trim();
  const fullName = (band?.NOMBRE_COMPLETO || '').trim();
  const localidad = (band?.LOCALIDAD || '').trim();
  const baseName = (fullName || shortName)
    .replace(/agrupaci[oó]n musical/gi, 'AM')
    .replace(/banda de cornetas y tambores/gi, 'CCTT')
    .replace(/\s{2,}/g, ' ')
    .trim();
  return localidad ? `${baseName} (${localidad})` : baseName;
}

function formatPreviewValue(value) {
  return value === null || value === undefined || value === '' ? '(vacio)' : value;
}
</script>

<template>
  <div class="md:min-w-4xl">
    <div class="headDetail">Alta de marcha</div>
    <table class="table table-zebra">
      <tbody>
        <tr>
          <th>Titulo</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.TITULO"
              placeholder="Titulo"
            />
          </td>
        </tr>
        <tr>
          <th>Fecha</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.FECHA"
              placeholder="Fecha (ej: 1998)"
            />
          </td>
        </tr>
        <tr>
          <th>Dedicatoria</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.DEDICATORIA"
              placeholder="Dedicatoria"
            />
          </td>
        </tr>
        <tr>
          <th>Localidad</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.LOCALIDAD"
              placeholder="Localidad"
            />
          </td>
        </tr>
        <tr>
          <th>Provincia</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.PROVINCIA"
              placeholder="Provincia"
            />
          </td>
        </tr>
        <tr>
          <th>ID banda estreno</th>
          <td>
            <AutocompleteSingle
              :selected-item="bandSelector.selected.value"
              :query="bandSelector.query.value"
              :suggestions="bandSelector.suggestions.value"
              :loading="bandSelector.loading.value"
              :min-chars="6"
              id-key="ID_BANDA"
              placeholder="Escribe nombre de banda (min. 6 caracteres)"
              loading-text="Buscando bandas..."
              :label-builder="getBandLabel"
              @update:query="bandSelector.query.value = $event"
              @select="bandSelector.selectItem"
              @remove="bandSelector.removeItem"
            />
          </td>
        </tr>
        <tr>
          <th>Detalles</th>
          <td>
            <textarea
              class="textarea w-full min-h-28"
              v-model="draftData.DETALLES_MARCHA"
              placeholder="Informacion adicional"
            />
          </td>
        </tr>
        <tr>
          <th>Autor(es) ID</th>
          <td>
            <AutocompleteMulti
              :selected-items="authorSelector.selected.value"
              :query="authorSelector.query.value"
              :suggestions="authorSelector.suggestions.value"
              :loading="authorSelector.loading.value"
              :min-chars="6"
              id-key="ID_AUTOR"
              placeholder="Escribe apellido/nombre (min. 6 caracteres)"
              loading-text="Buscando autores..."
              :label-builder="getAuthorLabel"
              @update:query="authorSelector.query.value = $event"
              @select="authorSelector.selectItem"
              @remove="authorSelector.removeItem"
            />
          </td>
        </tr>
      </tbody>
    </table>

    <div class="divider py-2 my-2">Previsualizacion</div>
    <div class="overflow-x-auto">
      <table class="table table-zebra">
        <thead class="bg-neutral-content text-neutral">
          <tr>
            <td>Campo</td>
            <td>Valor nuevo</td>
          </tr>
        </thead>
        <tbody>
          <tr v-for="field in pendingInsert.previewFields" :key="field.key">
            <td>{{ field.key }}</td>
            <td>{{ formatPreviewValue(field.newValue) }}</td>
          </tr>
          <tr>
            <td>AUTORES_IDS</td>
            <td>{{ formatPreviewValue(pendingInsert.autoresIds) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      <p class="font-semibold">SQL preparada:</p>
      <pre class="bg-base-200 p-3 rounded-box overflow-x-auto">{{ pendingInsert.sqlPreview }}</pre>
      <p class="font-semibold mt-2">Parametros:</p>
      <pre class="bg-base-200 p-3 rounded-box overflow-x-auto">{{ pendingInsert.valuesToInsert }}</pre>
    </div>

    <div v-if="requestState.status !== 'idle'" class="mt-3">
      <div class="alert" :class="requestState.status === 'success' ? 'alert-success' : requestState.status === 'saving' ? 'alert-info' : 'alert-error'">
        <span>{{ requestState.code }} - {{ requestState.msg }}</span>
      </div>
    </div>

    <div class="flex gap-2 mt-4">
      <button
        class="btn btn-neutral"
        :disabled="requestState.status === 'saving'"
        @click="sendDataToAddMarcha()"
      >
        Crear marcha
      </button>
      <button
        class="btn"
        :disabled="requestState.status === 'saving'"
        @click="resetDraft()"
      >
        Limpiar formulario
      </button>
    </div>
  </div>
</template>

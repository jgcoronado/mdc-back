<script setup>
import { ref, watch } from 'vue';
import {
  buildAutorInsertPayload,
  executeAutorInsert,
} from '@/services/autor';

const initialDraft = {
  NOMBRE: '',
  APELLIDOS: '',
  F_NAC: '',
  LUGAR_NAC: '',
  F_DEF: '',
  BIO: '',
};

const draftData = ref({ ...initialDraft });
const pendingInsert = ref(buildAutorInsertPayload(draftData.value));
const requestState = ref({ status: 'idle', code: '', msg: '' });

watch(
  () => draftData.value,
  () => {
    pendingInsert.value = buildAutorInsertPayload(draftData.value);
    if (requestState.value.status !== 'saving') {
      requestState.value = { status: 'idle', code: '', msg: '' };
    }
  },
  { deep: true }
);

async function sendDataToAddAutor() {
  const payload = buildAutorInsertPayload(draftData.value);
  pendingInsert.value = payload;
  requestState.value = { status: 'saving', code: '', msg: '' };

  try {
    const result = await executeAutorInsert(payload);
    requestState.value = {
      status: result.code === 'CREATED' ? 'success' : 'error',
      code: result.code || 'UNKNOWN',
      msg: result.msg || 'Respuesta sin mensaje',
    };
  } catch (error) {
    requestState.value = {
      status: 'error',
      code: error?.response?.data?.code || 'REQUEST_ERROR',
      msg: error?.response?.data?.msg || 'No se pudo crear el autor.',
    };
  }
}

function resetDraft() {
  draftData.value = { ...initialDraft };
  pendingInsert.value = buildAutorInsertPayload(draftData.value);
  requestState.value = { status: 'idle', code: '', msg: '' };
}

function formatPreviewValue(value) {
  return value === null || value === undefined || value === '' ? '(vacio)' : value;
}
</script>

<template>
  <div class="md:min-w-4xl">
    <div class="headDetail">Alta de autor</div>
    <table class="table table-zebra">
      <tbody>
        <tr>
          <th>Nombre</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.NOMBRE"
              placeholder="Nombre"
            />
          </td>
        </tr>
        <tr>
          <th>Apellidos</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.APELLIDOS"
              placeholder="Apellidos"
            />
          </td>
        </tr>
        <tr>
          <th>Fecha de nacimiento</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.F_NAC"
              placeholder="Ej: 05/12/1982"
            />
          </td>
        </tr>
        <tr>
          <th>Lugar de nacimiento</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.LUGAR_NAC"
              placeholder="Localidad o ciudad"
            />
          </td>
        </tr>
        <tr>
          <th>Fecha de defunción</th>
          <td>
            <input
              class="input w-full"
              type="text"
              v-model="draftData.F_DEF"
              placeholder="Opcional"
            />
          </td>
        </tr>
        <tr>
          <th>Bio</th>
          <td>
            <textarea
              class="textarea w-full min-h-28"
              v-model="draftData.BIO"
              placeholder="Resumen breve"
            ></textarea>
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
        @click="sendDataToAddAutor()"
      >
        Crear autor
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

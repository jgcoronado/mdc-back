<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { goToDetail } from '@/services/goTo';
import { getDetailData } from '@/services/getData';
import { buildMarchaUpdatePayload } from '@/services/admin';

const BASE_URL = import.meta.env.VITE_BASE_URL;
const router = useRouter();
const route = useRoute();

const apiData = ref(null);
const oldData = ref(null);
const pendingUpdate = ref({
  marchaId: null,
  keysToUpdate: [],
  valuesToUpdate: [],
  params: [],
  sqlPreview: '',
  changedFields: [],
});

const AUTOR = 'autor';
const MARCHA = 'marcha';

onMounted(async () => {
  const data = await getDetailData(MARCHA, route);
  apiData.value = { ...data };
  oldData.value = { ...data };
  pendingUpdate.value = buildMarchaUpdatePayload(oldData.value, apiData.value);
});

watch(
  () => apiData.value,
  () => {
    if (!apiData.value || !oldData.value) {
      return;
    }
    pendingUpdate.value = buildMarchaUpdatePayload(oldData.value, apiData.value);
  },
  { deep: true }
);

function sendDataToEditMarcha() {
  const payload = buildMarchaUpdatePayload(oldData.value, apiData.value);
  pendingUpdate.value = payload;
  const apiUrl = `${BASE_URL}/admin/editMarcha`;
  console.log('Prepared marcha update payload:', { apiUrl, ...payload });
}

function resetChanges() {
  apiData.value = { ...oldData.value };
  pendingUpdate.value = buildMarchaUpdatePayload(oldData.value, apiData.value);
}

function formatPreviewValue(value) {
  return value === null || value === undefined || value === '' ? '(vacío)' : value;
}
</script>

<template>
  <div v-if="apiData">
    <div class="md:min-w-4xl">
      <div class="headDetail">Edición de marcha #{{ apiData.ID_MARCHA }}</div>
      <table class="table table-zebra">
        <tbody>
          <tr>
            <th>Título</th>
            <td>
              <input
                class="input w-full"
                type="text"
                v-model="apiData.TITULO"
                placeholder="Título"
              />
            </td>
          </tr>
          <tr>
            <th>Fecha</th>
            <td>
              <input
                class="input w-full"
                type="text"
                v-model="apiData.FECHA"
                placeholder="Fecha (ej: 1998)"
              />
            </td>
          </tr>
          <tr>
            <th>Autor</th>
            <td>
              <div v-for="a in apiData.AUTOR" :key="a.autorId">
                <a class="hover:underline cursor-pointer" @click="goToDetail(router, AUTOR, a.autorId)">
                  {{ a.nombre }}
                </a>
              </div>
            </td>
          </tr>
          <tr>
            <th>Dedicatoria</th>
            <td>
              <input
                class="input w-full"
                type="text"
                v-model="apiData.DEDICATORIA"
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
                v-model="apiData.LOCALIDAD"
                placeholder="Localidad"
              />
            </td>
          </tr>
          <tr>
            <th>Audio</th>
            <td>
              <input
                class="input w-full"
                type="text"
                v-model="apiData.AUDIO"
                placeholder="URL audio"
              />
            </td>
          </tr>
          <tr>
            <th>ID banda estreno</th>
            <td>
              <input
                class="input w-full"
                type="number"
                v-model.number="apiData.BANDA_ESTRENO"
                placeholder="ID de banda"
              />
            </td>
          </tr>
          <tr>
            <th>Detalles</th>
            <td>
              <textarea
                class="textarea w-full min-h-28"
                v-model="apiData.DETALLES_MARCHA"
                placeholder="Información adicional"
              />
            </td>
          </tr>
        </tbody>
      </table>

      <div class="divider py-2 my-2">Previsualización</div>
      <div v-if="pendingUpdate.changedFields.length > 0" class="overflow-x-auto">
        <table class="table table-zebra">
          <thead class="bg-neutral-content text-neutral">
            <tr>
              <td>Campo</td>
              <td>Valor actual</td>
              <td>Nuevo valor</td>
            </tr>
          </thead>
          <tbody>
            <tr v-for="field in pendingUpdate.changedFields" :key="field.key">
              <td>{{ field.key }}</td>
              <td>{{ formatPreviewValue(field.previousValue) }}</td>
              <td>{{ formatPreviewValue(field.newValue) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="alert">No hay cambios pendientes.</div>

      <div v-if="pendingUpdate.sqlPreview" class="mt-4">
        <p class="font-semibold">SQL preparada:</p>
        <pre class="bg-base-200 p-3 rounded-box overflow-x-auto">{{ pendingUpdate.sqlPreview }}</pre>
        <p class="font-semibold mt-2">Parámetros:</p>
        <pre class="bg-base-200 p-3 rounded-box overflow-x-auto">{{ pendingUpdate.params }}</pre>
      </div>

      <div class="flex gap-2 mt-4">
        <button
          class="btn btn-neutral"
          @click="sendDataToEditMarcha()"
        >
          Preparar update
        </button>
        <button
          class="btn"
          @click="resetChanges()"
        >
          Revertir cambios
        </button>
      </div>
    </div>
  </div>
  <p v-else>Loading...</p>
</template>

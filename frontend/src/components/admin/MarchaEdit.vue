<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router';
import { goToDetail } from '@/services/goTo';
import { getDetailData } from '@/services/getData';
import { paramsToUpdate } from '@/services/admin';
import axios from 'axios';

const BASE_URL = import.meta.env.VITE_BASE_URL;
const router = useRouter()
const route = useRoute()
const apiData = ref({});
let oldData = {};

const AUTOR = 'autor';
const MARCHA = 'marcha'

onMounted(async () => {
  apiData.value = await getDetailData(MARCHA,route);
  oldData.value = {...apiData.value};
});

watch(
  () => apiData,
  (newValue, oldValue) => {
    console.log("🚀 ~ newValue, oldValue:", newValue, oldValue)
  },
  { deep: true }
)

async function sendDataToEditMarcha() {
  const { ID_MARCHA } = apiData.value;
  const newData = {...apiData.value};
  const keysToUpdate = paramsToUpdate(oldData.value, newData);
  const valuesToUpdate = [];
  keysToUpdate.forEach(k => {
    valuesToUpdate.push(newData[`${k}`]);
  });
  const apiUrl = `${BASE_URL}/admin/editMarha`;
  // const res = await axios.post(apiUrl, {ID_MARCHA, keysToUpdate, valuesToUpdate});
  console.log("🚀 ~ sendDataToEditMarcha ~ ID_MARCHA, keysToUpdate, valuesToUpdate:", ID_MARCHA, keysToUpdate, valuesToUpdate)
}
</script>

<template>
  <div v-if="apiData">
    <div class="md:min-w-4xl">
      <table class="table table-zebra">
        <tbody>
          <tr>
            <th>Título</th>
            <td>
                <input
                  class="input"
                  type="text"
                  v-model="apiData.TITULO"
                  placeholder="apiData.TITULO"
              />
              {{ oldData.value?.TITULO }}
            </td>
          </tr>
          <tr>
            <th>Fecha</th>
            <td>
              <input
                class="input"
                type="text"
                v-model="apiData.FECHA"
                placeholder="apiData.FECHA"
            />
              {{ oldData.value?.FECHA }}
            </td>
          </tr>
          <tr>
            <th>Autor</th>
            <td>
              <div v-for="a in apiData.AUTOR">
                <a @click="goToDetail(router, AUTOR, a.autorId)">
                  {{ a.nombre }}
                </a>
              </div>
            </td>
          </tr>
          <tr>
            <th>Dedicatoria</th>
            <td>
              <input
                class="input"
                type="text"
                v-model="apiData.DEDICATORIA"
                placeholder="apiData.DEDICATORIA"
              />
              {{ oldData.value?.DEDICATORIA }}
            </td>
          </tr>
          <tr>
            <th>Estrenada por</th>
            <td>
              <input
                class="input"
                type="text"
                v-model="apiData.BANDA_ESTRENO"
                placeholder="apiData.BANDA_ESTRENO"
              />
                {{ oldData.value?.BANDA }}
            </td>
          </tr>
        </tbody>
      </table>
      <button
        class="btn btn-neutral mt-4"
        @keyup.enter="sendDataToEditMarcha()"
        @click="sendDataToEditMarcha()"
      >
        Buscar
      </button>
    </div>
  </div>
  <p v-else>Loading...</p>   
</template>
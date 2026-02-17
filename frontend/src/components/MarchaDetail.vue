<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import CdList from './molecules/CdList.vue';
import { canonicalizeDetailRoute, goToDetail } from '@/services/goTo';
import { getDetailData } from '@/services/getData';

const router = useRouter()
const route = useRoute()
const apiData = ref('');

onMounted(async () => {
  apiData.value = await getDetailData('marcha',route);
  canonicalizeDetailRoute(router, route, 'marcha', apiData.value.ID_MARCHA, apiData.value.TITULO);
});

function getDedicatoria(ded, loc) {
  const isDed = ded != 0;
  const isLoc = loc != 0 && loc !== undefined;
  if(isDed && isLoc) return `${ded} (${loc})`;
  else if (isDed) return `${ded}`;
  else return '';
};
</script>

<template>
  <div v-if="apiData">
    <div class="headDetail">{{ apiData.TITULO }}</div>
    <div class="tableList">
      <table class="table table-zebra">
        <tbody>
          <tr v-if="apiData.FECHA">
            <th>Fecha</th>
            <td>{{ apiData.FECHA }}</td>
          </tr>
          <tr>
            <th>Autor</th>
            <td>
              <div v-for="a in apiData.AUTOR">
                <a class="hover:underline cursor-pointer" @click="goToDetail(router,'autor',a.autorId,a.nombre)">
                  {{ a.nombre }}
                </a>
              </div>
            </td>
          </tr>
          <tr v-if="apiData.DEDICATORIA">
            <th>Dedicatoria</th>
            <td>
              {{ getDedicatoria(apiData.DEDICATORIA, apiData.LOCALIDAD) }}
            </td>
          </tr>
          <tr v-if="apiData.BANDA_ESTRENO">
            <th>Estrenada por</th>
            <td>
              <a class="hover:underline cursor-pointer" @click="goToDetail(router,'banda',apiData.BANDA_ESTRENO, apiData.BANDA)">
                {{ apiData.BANDA }}
              </a>
            </td>
          </tr>
          <tr v-if="apiData.DETALLES_MARCHA">
            <th>Información adicional</th>
            <td>{{ apiData.DETALLES_MARCHA }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="apiData.discosLength !== 0" class="divider py-10 my-0">Esta marcha se ha grabado en {{ apiData.discosLength }} discos:</div>
      <CdList
        class="m-3"
        v-for="d in apiData.discos"
        :disco="d"
      />
    <div v-if="apiData.discosLength === 0" class="divider py-10 my-0">Esta marcha aún no ha sido grabada en disco.</div>
  </div>
  <p v-else>Loading...</p>   
</template>

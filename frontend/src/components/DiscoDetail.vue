<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { canonicalizeDetailRoute, goToDetail } from '@/services/goTo';
import { getDetailData } from '@/services/getData';

const router = useRouter()
const route = useRoute()
const apiData = ref('');

onMounted(async () => {
  apiData.value = await getDetailData('disco',route);
  canonicalizeDetailRoute(router, route, 'disco', apiData.value.ID_DISCO, apiData.value.NOMBRE_CD);
});

function getCover(ID_DISCO) {
  return `/cover/${ID_DISCO}.png`;
}
function getAlt(NOMBRE_CD) {
  return `Portada del disco '${NOMBRE_CD}'`;
}

function onCoverError(event) {
  event.target.src = '/cover/default.png';
}
</script>

<template>
  <div v-if="apiData" class="grid place-items-center">
    <div class="grid place-items-center xl:join md:join join-vertically">
      <figure class="m-1">
        <img
          class="shadow-sm"
          :src="getCover(apiData.ID_DISCO)"
          :alt="getAlt(apiData.NOMBRE_CD)"
          @error="onCoverError"
        />
      </figure>
    <div class="justify-items-center">
      <div
        class="headDetail"
      >
        {{ apiData.NOMBRE_CD }}
      </div>
      <div class="tableList">
        <table class="table table-zebra">
          <tbody>
            <tr>
              <th>Fecha</th>
              <td>{{ apiData.FECHA_CD }}</td>
            </tr>
            <tr>
              <th>Banda</th>
              <td>
                <a
                  class="hover:underline cursor-pointer"
                  @click="goToDetail(router, 'banda', apiData.ID_BANDA, apiData.BANDA)"
                >
                  {{ apiData.BANDA }}
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
    <div class="divider py-10 my-0">Este disco contiene {{ apiData.marchasLength }} marchas:</div>
    <div class="tableList">
      <table class="table table-zebra">
           <thead class="bg-neutral-content text-neutral">
            <tr>
              <td v-if="apiData.DISCOS > 1">Disco</td>
              <td>#</td>
              <td>Marcha</td>
              <td>Autor</td>
              <td>Fecha</td>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in apiData.marchas">
              <td v-if="apiData.DISCOS > 1">
                {{ m.N_DISCO }}
              </td>
              <td>{{ m.NUMEROMARCHA }}</td>
              <td>
                <a 
                  class="hover:underline cursor-pointer"
                  @click="goToDetail(router, 'marcha', m.ID_MARCHA, m.TITULO)"
                >
                  {{ m.TITULO }}
                </a>
              </td>
            <td>
              <div v-for="a in m.AUTOR">
                <a
                  class="hover:underline cursor-pointer"
                  @click="goToDetail(router, 'autor', a.autorId, a.nombre)"
                >
                  {{ a.nombre }}
                </a>
              </div>
            </td>
              <td>{{ m.FECHA }}</td>
            </tr>
          </tbody>
        </table>
    </div>  
  </div>
  <p v-else>Loading...</p>   
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router';
import { goToDetail } from '@/services/goTo';
import { getListData } from '@/services/getData';

const router = useRouter()
const route = useRoute()
const apiData = ref('');

const BANDA = 'banda';

onMounted( async () => {
  apiData.value = await getListData(BANDA,route);
});

function showDate(fund,ext) {
  const funRes = (fund > 1800) ? fund : 's/f';
  const extRes = (ext == null) || (ext === 0) ? '' : ` - ${ext}`;
  return `${funRes}${extRes}`;
}
function showLocalidad(loc, prov) {
  const isLoc = loc != 0;
  const isProv = prov != 0 && prov !== undefined && prov != null;
  if(isLoc && isProv) return `${loc} (${prov})`;
  else if (isLoc) return `${loc}`;
  else return '';
};
</script>

<template>
  <div v-if="apiData.rowsReturned < 1" class="divider py-10 my-0"> Lo sentimos, no se ha encontrado ninguna banda.</div>
  <div v-else-if="apiData.rowsReturned == 1" class="divider py-10 my-0"> Se ha encontrado una banda:</div>
  <div v-else class="divider py-10 my-0"> Se han encontrado {{ apiData.rowsReturned }} bandas:</div>
  <div class="tableList">
    <table class="table table-zebra">
      <thead  class="bg-neutral-content text-neutral">
        <tr>
          <td>Nombre</td>
          <td>Localidad</td>
          <td>Fundación</td>
        </tr>
      </thead>
      <tbody>
        <tr  v-for="b in apiData.data">
          <td>
            <a class="hover:underline cursor-pointer" @click="goToDetail(router, BANDA, b.ID_BANDA)">
              {{ b.NOMBRE_COMPLETO }}
            </a>
          </td>
          <td>
            {{ showLocalidad(b.LOCALIDAD, b.PROVINCIA) }}
          </td>
          <td>
            {{ showDate(b.FECHA_FUND,b.FECHA_EXT) }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
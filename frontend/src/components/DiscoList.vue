<script setup>
import { ref, onMounted } from 'vue'
import CdList from './molecules/CdList.vue';
import { useRouter, useRoute } from 'vue-router';
import { goToDetail } from '@/services/goTo';
import { getListData } from '@/services/getData';

const router = useRouter()
const route = useRoute()
const apiData = ref('');

const DISCO = 'disco';

onMounted( async () => {
  apiData.value = await getListData(DISCO,route);
});


function showDate(fund,ext) {
  const funRes = (fund > 1800) ? fund : 's/f';
  const extRes = (ext == null) || (ext === 0) ? '' : ` - ${ext}`;
  return `${funRes}${extRes}`;
}
</script>

<template>
  <div v-if="apiData.rowsReturned < 1" class="divider py-10 my-0"> Lo sentimos, no se ha encontrado ningún disco.</div>
  <div v-else-if="apiData.rowsReturned == 1" class="divider py-10 my-0"> Se ha encontrado un disco:</div>
  <div v-else class="divider py-10 my-0"> Se han encontrado {{ apiData.rowsReturned }} discos:</div>
    <CdList
    class="m-3"
    v-for="d in apiData.data"
    :disco="d"
  />
</template>
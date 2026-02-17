<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios';
import { useRouter, useRoute } from 'vue-router';
import { goToDetail } from '@/services/goTo';
import { getListData } from '@/services/getData';

const router = useRouter()
const route = useRoute()
const apiData = ref('');

const AUTOR = 'autor';

onMounted( async () => {
apiData.value = await getListData(AUTOR,route);
});
</script>

<template>
  <div v-if="apiData.rowsReturned < 1" class="divider py-10 my-0"> Lo sentimos, no se ha encontrado ninguna autor.</div>
  <div v-else-if="apiData.rowsReturned == 1" class="divider py-10 my-0"> Se ha encontrado un autor:</div>
  <div v-else class="divider py-10 my-0"> Se han encontrado {{ apiData.rowsReturned }} autores:</div>
  <div v-if="apiData.rowsReturned > 0" class="tableList">
    <table class="table table-zebra">
      <thead class="bg-neutral-content text-neutral">
        <tr>
          <td>Nombre</td>
          <td>Marchas compuestas</td>
        </tr>
      </thead>
      <tbody>
        <tr v-for="autor in apiData.data">
          <td>
            <a class="hover:underline cursor-pointer" @click="goToDetail(router, 'autor', autor.ID_AUTOR, autor.NOMBRE_COMPLETO)">
              {{ autor.NOMBRE_COMPLETO }}
            </a>
          </td>
          <td> {{ autor.MARCHAS }} </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

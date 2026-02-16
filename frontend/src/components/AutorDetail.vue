<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { getDetailData } from '@/services/getData';
import { goToDetail } from '@/services/goTo';

const router = useRouter();
const route = useRoute()
const apiData = ref('');

const AUTOR = 'autor';
const MARCHA = 'marcha';

onMounted(async () => {
  apiData.value = await getDetailData(AUTOR, route);
});
</script>

<template>
    <div class="headDetail">{{ apiData.NOMBRE }} {{ apiData.APELLIDOS }}</div>
    <div class="tableList">
      <table class="table table-zebra">
        <tbody>
          <tr>
            <th>Fecha de nacimiento</th>
            <td>{{ apiData.F_NAC }}</td>
          </tr>
          <tr>
            <th>Lugar de nacimiento</th>
            <td>{{ apiData.LUGAR_NAC }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="divider py-10 my-0">Ha compuesto {{ apiData.marchasLength }} marchas:</div>
    <div class="tableList">
      <table class="table table-zebra">
        <thead class="bg-neutral-content text-neutral">
          <tr class="">
            <td>Marcha</td>
            <td>Fecha</td>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in apiData.marchas">
            <td>
              <a class="hover:underline cursor-pointer" @click="goToDetail(router, MARCHA, m.ID_MARCHA)">
                {{ m.TITULO }}
              </a>
            </td>
            <td>{{ m.FECHA }}</td>
          </tr>
          <tr></tr>
        </tbody>
      </table>
    </div>
</template>
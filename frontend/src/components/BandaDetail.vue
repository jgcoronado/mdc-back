<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios';
import { useRouter, useRoute } from 'vue-router'
import CdList from './molecules/CdList.vue';
import Timeline from './molecules/Timeline.vue';
import { goToDetail } from '@/services/goTo';
import { getDetailData } from '@/services/getData';

const router = useRouter();
const route = useRoute()
const apiData = ref('');

onMounted(async () => {
  apiData.value = await getDetailData('banda',route);
});
</script>

<template>
    <div class="headDetail">
      {{ apiData.NOMBRE_BREVE }}
    </div>
    <div class="tableList">
      <table class="table table-zebra">
        <tbody>
          <tr>
            <th>Nombre completo</th>
            <td>{{ apiData.NOMBRE_COMPLETO }}</td>
          </tr>
          <tr>
            <th>Localidad</th>
            <td>{{ apiData.LOCALIDAD }}</td>
          </tr>
          <tr>
            <th>Fecha de fundación</th>
            <td>{{ apiData.FECHA_FUND }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="grid place-items-center">  
      <Timeline v-bind:apiData/>
    </div>

    <div v-if="apiData.discosLength" class="divider py-10 my-0">Esta banda ha grabado {{ apiData.discosLength }} discos:</div>
      <CdList
        class="m-3"
        v-for="d in apiData.discos"
        :disco="d"
      />
    <div v-if="apiData.marchasLength" class="divider py-10 my-0">Esta banda ha estrenado {{ apiData.marchasLength }} marchas:</div>
    <div class="tableList">
      <table class="table table-zebra">
        <thead class="bg-neutral-content text-neutral">
          <tr>
            <td>Marcha</td>
            <td>Fecha</td>
            <td>Compositor/es</td>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in apiData.marchas">
            <td>{{ m.FECHA }}</td>
            <td>
              <a
                class="hover:underline cursor-pointer"
                @click="goToDetail(router, 'marcha', m.ID_MARCHA)"
              >
                {{ m.TITULO }}
              </a>
            </td>
            <td>
              <div v-for="a in m.AUTOR">
                <a
                  class="hover:underline cursor-pointer"
                  @click="goToDetail(router, 'autor', a.autorId)"
                >
                  {{ a.nombre }}
                </a>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <br />
</template>
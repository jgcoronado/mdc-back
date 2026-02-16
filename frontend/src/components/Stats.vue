<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { goToDetail } from '@/services/goTo';
import axios from 'axios';

const router = useRouter()

const BASE_URL = (import.meta.env.VITE_BASE_URL || '/api').replace(/\/$/, '');
const masAutor = ref('');
const masDedica = ref('');
const masEstreno = ref('');
const masGrabada = ref('');

const getMasAutor = computed(async () => {
  const apiUrl = `${BASE_URL}/stats/masAutor`;
  const res = await axios.get(apiUrl);
  masAutor.value = res.data;
  return masAutor;
});

const getMasDedica = computed(async () => {
  const apiUrl = `${BASE_URL}/stats/masDedica`;
  const res = await axios.get(apiUrl);
  masDedica.value = res.data;
  return masDedica;
});

const getMasEstreno = computed(async () => {
  const apiUrl = `${BASE_URL}/stats/masEstreno`;
  const res = await axios.get(apiUrl);
  masEstreno.value = res.data;
  return masEstreno;
});

const getMasGrabada = computed(async () => {
  const apiUrl = `${BASE_URL}/stats/masGrabada`;
  const res = await axios.get(apiUrl);
  masGrabada.value = res.data;
  return masGrabada;
});
</script>

<template>
  <details class="collapse collapse-arrow bg-base-100 border border-base-300">
    <input type="radio" name="my-accordion-2"/>
    <summary class="collapse-title font-semibold">Autores que más marchas han compuesto</summary>
    <div class="collapse-content text-sm" v-if="getMasAutor">
      <div class="tableList">
        <table class="table table-zebra">
          <thead class="bg-neutral-content text-neutral">
            <tr>
              <td>Nombre</td>
              <td>Marchas compuestas</td>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in masAutor">
              <td>
                <a class="hover:underline cursor-pointer" @click="goToDetail(router, 'autor', autor.ID_AUTOR)">
                  {{ a.AUTOR }}
                </a>
              </td>
              <td> {{ a.MARCHAS }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </details>
  <details class="collapse collapse-arrow bg-base-100 border border-base-300">
    <input type="radio" name="my-accordion-2" />
    <summary class="collapse-title font-semibold">Hermandades con más marchas dedicadas</summary>
    <div class="collapse-content text-sm" v-if="getMasDedica">
      <div class="tableList">
        <table class="table table-zebra">
          <thead class="bg-neutral-content text-neutral">
            <tr>
              <td>Nombre</td>
              <td>Marchas compuestas</td>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in masDedica">
              <td> {{ d.LUGAR }} </td>
              <td> {{ d.CUENTA }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </details>
  <details class="collapse collapse-arrow bg-base-100 border border-base-300">
    <input type="radio" name="my-accordion-2" />
    <summary class="collapse-title font-semibold">Bandas que más marchas han estrenado</summary>
    <div class="collapse-content text-sm" v-if="getMasEstreno">
      <div class="tableList">
        <table class="table table-zebra">
          <thead class="bg-neutral-content text-neutral">
            <tr>
              <td>Banda</td>
              <td>Marchas estrenadas</td>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in masEstreno">
              <td>
                <a class="hover:underline cursor-pointer" @click="goToDetail(router, 'banda', e.ID_BANDA)">
                  {{ e.BANDA }}
                </a>
              </td>
              <td> {{ e.MARCHAS }} </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </details>
  <details class="collapse collapse-arrow bg-base-100 border border-base-300">
    <input type="radio" name="my-accordion-2" />
    <summary class="collapse-title font-semibold">Marchas que más veces han sido grabadas</summary>
    <div class="collapse-content text-sm">
      <div class="tableList" v-if="getMasGrabada">
        <table class="table table-zebra">
          <thead class="bg-neutral-content text-neutral">
            <tr>
              <td>Marcha</td>
              <td>Autor/es</td>
              <td>Grabaciones</td>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in masGrabada">
              <td>
                <a class="hover:underline cursor-pointer" @click="goToDetail(router, 'marcha', g.ID_MARCHA)">
                  {{ g.TITULO }}
                </a>
              </td>
              <td>
                <div v-for="a in g.AUTOR">
                  <a class="hover:underline cursor-pointer" @click="goToDetail(router,'autor',a.autorId)">
                    {{ a.nombre }}
                  </a>
                </div>
              </td>
              <td> {{ g.GRABACIONES }} </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </details>
</template>
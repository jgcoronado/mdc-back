<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router'

const router = useRouter()

const titulo = ref('');
const localidad= ref('');
const provincia= ref('');
let urlJoin = ref({});

function goToBandaList(text) {
  const url = [];
  if(titulo.value) url.push(`titulo=${titulo.value}`);
  if(localidad.value) url.push(`localidad=${localidad.value}`);
  if(provincia.value) url.push(`provincia=${provincia.value}`);

  urlJoin = url.join('&');
  router.push({
    name: 'bandaList',
    params: {
      query: urlJoin,
    },
  });
};
</script>

<template>

  <fieldset
    class="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
    @keyup.enter="goToBandaList()"
  >
    <legend class="fieldset-legend">Buscador de bandas</legend>

    <label class="label">Nombre</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="titulo"
      placeholder="Ejemplo: Sagrada Columna y Azotes"
    />
    <label class="label">Localidad</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="localidad"
      placeholder="Ejemplo: Palma del Río"
    />
    <label class="label">Provincia</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="provincia"
      placeholder="Ejemplo: Huelva"
    />
    <button
      class="btn btn-neutral mt-4"
      @keyup.enter="goToBandaList()"
      @click="goToBandaList()"
    >
      Buscar
    </button>
  </fieldset>





  <!-- <input
    class="input"
    type="text"
    v-model="text"
    @keyup.enter="goToBandaList(text)"
    placeholder="Buscar banda por nombre"
  />
  <button
    class="btn"
    @click="goToBandaList(text)"
  >
  Buscar
  </button> -->
</template>


<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router'

const router = useRouter()

const titulo = ref('');
const fechaIni = ref('');
const fechaFin = ref('');
const dedicatoria= ref('');
const localidad= ref('');
const provincia= ref('');
let urlJoin = ref({});

function goToMarchaList() {
  const url = [];
  if(titulo.value) url.push(`titulo=${titulo.value}`);
  if(fechaIni.value) url.push(`fechaDesde=${fechaIni.value}`);
  if(fechaFin.value) url.push(`fechaHasta=${fechaFin.value}`);
  if(dedicatoria.value) url.push(`dedicatoria=${dedicatoria.value}`);
  if(localidad.value) url.push(`localidad=${localidad.value}`);
  if(provincia.value) url.push(`provincia=${provincia.value}`);

  urlJoin = url.join('&');
  router.push({
    name: 'marchaList',
    params: {
      query: urlJoin,
    }
  });
};
</script>

<template>
  <fieldset
    class="fieldset bg-base-200 border-base-300 rounded-box w-ms border p-4 md:min-w-xl place-items-center"
    @keyup.enter="goToMarchaList()"
  >
    <legend class="fieldset-legend">Buscador de marchas procesionales</legend>

    <label class="label">Título</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="titulo"
      placeholder="Ejemplo: Consuelo Gitano"
    />
    <label class="label">Fecha</label>
    <div class="join">
      <input
        class="input text-base"
        type="text"
        maxlength="4"
        size="4"
        v-model="fechaIni"
        placeholder="Desde: 1993"
      />
      <input
        class="input text-base"
        type="text"
        maxlength="4"
        size="4"
        v-model="fechaFin"
        placeholder="Hasta: 2021"
      />
    </div>
    <label class="label">Dedicatoria</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="dedicatoria"
      placeholder="Ejemplo: Hdad Cristo de la Corona"
    />
    <label class="label">Localidad</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="localidad"
      placeholder="Ejemplo: Osuna"
    />
    <label class="label">Provincia</label>
    <input
      class="input w-full text-base"
      type="text"
      v-model="provincia"
      placeholder="Ejemplo: Almería"
    />
    <button
      class="btn btn-neutral mt-4"
      @keyup.enter="goToMarchaList()"
      @click="goToMarchaList()"
    >
      Buscar
    </button>
  </fieldset>
</template>


<template>
  <ul class="list bg-base-200 hover:bg-base-300 rounded-box shadow-md">  
  <li class="list-row">
    <div class="cursor-pointer">
      <a @click="goToDetail($router, 'disco',disco.ID_DISCO, disco.NOMBRE_CD)">
        <img class="size-15 rounded-box" :src="srcImg" @error="onCoverError" />
      </a>
    </div>
    <div class="text-xl list-col-grow">
      <a class="hover:underline cursor-pointer" @click="goToDetail($router, 'disco',disco.ID_DISCO, disco.NOMBRE_CD)">
        {{ disco.NOMBRE_CD}}
      </a>
      <div v-if="disco.BANDA" class="text-sm font-semibold opacity-60 indent-4">
        <a class="hover:underline cursor-pointer" @click="goToDetail($router, 'banda',disco.ID_BANDA, disco.BANDA)">
          {{disco.BANDA}}
        </a>
      </div>
      <div v-else-if="disco.DISCOS > 1" class="text-sm font-semibold opacity-60 indent-4">
        {{ disco.DISCOS }} CDs, {{ disco.PISTAS }} marchas
      </div>
      <div v-else class="text-sm font-semibold opacity-60 indent-4">
        {{ disco.PISTAS }} marchas
      </div>
    </div>
    <div>
      {{ disco.FECHA_CD }}
    </div>
  </li>  
</ul>
</template> 

<script setup>
import { computed, ref } from 'vue';
import { goToDetail } from '@/services/goTo';

const props = defineProps({
  disco: {
    ID_DISCO: String,
    NOMBRE_CD: String,
    FECHA_CD: Number,
    ID_BANDA: Number,
    DISCOS: Number,
    BANDA: String,
    PISTAS: Number
  }
})

const hasCoverError = ref(false);
const defaultCoverPath = '/cover/default.png';

const srcImg = computed(() => {
  if (hasCoverError.value) {
    return defaultCoverPath;
  }
  return `/cover/${props.disco.ID_DISCO}.png`;
});

const onCoverError = (event) => {
  hasCoverError.value = true;
  // Evita bucles si la imagen por defecto tampoco existe.
  event.target.onerror = null;
  event.target.src = defaultCoverPath;
};
</script>

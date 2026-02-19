<template> 
  <ul v-if="apiData" class="timeline">
    <li v-for="(banda, index) in apiData.timeline">
      <hr
        v-if="banda.FECHA_FUND <= apiData.FECHA_FUND && index !== 0"
        class="bg-primary"
      />
      <hr v-else-if="index !== 0" />
      <div v-if="index !== banda.length - 1" class="timeline-start">{{ banda.FECHA_FUND }}</div>
      <div class="timeline-middle">
        <svg
          v-if="banda.FECHA_FUND < apiData.FECHA_FUND"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
          class="text-primary h-5 w-5"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
            clip-rule="evenodd"
          />
        </svg>
        <svg
          v-else
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
          :class="apiData.ID_BANDA === banda.ID_BANDA ? 'text-primary h-5 w-5' : 'h-5 w-5'"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
      <div class="timeline-end timeline-box bg-base-200">
        <a class="hover:underline cursor-pointer" @click="goToDetail($router,'banda', banda.ID_BANDA, banda.NOMBRE_BREVE)">
          {{ banda.NOMBRE_BREVE }}
        </a>
        </div>
      <hr
        v-if="banda.FECHA_FUND < apiData.FECHA_FUND"
        class="bg-primary"
      />
      <hr v-else />
    </li>
    <li>
      <hr />
      <div class="timeline-start">
        {{ isExtint ? isExtint : 'Hoy' }}
      </div>
      <div class="timeline-middle">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
          class="h-5 w-5"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
      <div v-if="isExtint" class="timeline-end timeline-box">Desaparece la banda</div>
    </li>
  </ul>
</template>

<script setup>
import { computed } from 'vue';
import { goToDetail } from '@/services/goTo';

const props = defineProps({
  apiData: {
    ID_BANDA: String,
    FECHA_FUND: String,
    FECHA_EXT: String,
    TIMELINE: Object
  }
})

const isExtint = computed(() => {
  const sizeBands = props.apiData.timeline.length -1;
  const lastBand = props.apiData.timeline[sizeBands];
  console.log("🚀 ~ lastBand:", lastBand)
  console.log("🚀 ~ lastBand.FECHA_EXT:", lastBand.FECHA_EXT)
  return lastBand.FECHA_EXT;
});
</script>

<template>
    <div class="card bg-base-100 w-96 shadow-sm">
  <figure>
    <img
      :src=srcImg
      :alt=id />
  </figure>
  <div class="card-body">
    <h2 class="card-title">{{titulo}}</h2>
    <p>A card component has a figure, a body part, and inside body there are title and actions parts</p>
    <div class="card-actions justify-end">
      <button class="btn btn-primary">Ver</button>
    </div>
  </div>
</div>
</template> 

<script setup>
import { ref } from 'vue';
const props = defineProps({
  id: Number,
  titulo: String
})

const srcImg = ref(`../src/assets/cover/${props.id}.png`);

function getImageUrl() {
  return new URL(`../../assets/cover/${props.id}`, import.meta.url)
}
</script>
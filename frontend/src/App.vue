<script setup>
import { ref, onMounted } from 'vue';
import DbCount from './components/molecules/DbCount.vue';
import { postLogin } from './services/getData';

const apiData = ref('');

onMounted (async () => {
  // const BASIC_USER = import.meta.env.BASIC_USER;
  // const BASIC_PASS = import.meta.env.BASIC_PASS;
  // apiData.value = await postLogin(BASIC_USER, BASIC_PASS);
});
</script>

<template>
  <header>
  </header>
  <nav class="grid justify-items-center py-5">
    <img class="h-10" alt="My Image" src="../src/assets/banner_mdc.png" />
    <ul class="menu menu-horizontal rounded-box">
      <li><RouterLink to="/">Home</RouterLink></li>
      <li><RouterLink to="/marcha">Marcha</RouterLink></li>
      <li><RouterLink to="/autor">Autor</RouterLink></li>
      <li><RouterLink to="/banda">Banda</RouterLink></li>
      <li><RouterLink to="/disco">Disco</RouterLink></li>
      <li><RouterLink to="/estadisticas">Estadisticas</RouterLink></li>
    </ul>  
  </nav>
  <body class="grid justify-items-center">    
    <div>
      <RouterView />
    </div>
  </body>
  <footer class="grid justify-items-center py-5">
    <DbCount />
  </footer>
</template>

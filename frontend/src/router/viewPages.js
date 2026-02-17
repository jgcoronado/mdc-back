import Home from '@/components/Home.vue';
import MarchaDetail from '@/components/MarchaDetail.vue';
import MarchaList from '@/components/MarchaList.vue';
import MarchaSearch from '@/components/MarchaSearch.vue';
import AutorDetail from '@/components/AutorDetail.vue';
import AutorList from '@/components/AutorList.vue';
import AutorSearch from '@/components/AutorSearch.vue';
import BandaDetail from '@/components/BandaDetail.vue';
import BandaList from '@/components/BandaList.vue';
import BandaSearch from '@/components/BandaSearch.vue';
import DiscoDetail from '@/components/DiscoDetail.vue';
import DiscoList from '@/components/DiscoList.vue';
import DiscoSearch from '@/components/DiscoSearch.vue';
import Stats from '@/components/Stats.vue';

const router = [
  { path: '/', name: 'home', component: Home },
  { path: '/marcha', name: 'marchaSearch', component: MarchaSearch },
  { path: '/marcha/:slugAndId([a-z0-9-]*-\\d+)', name: 'marchaDetail', component: MarchaDetail },
  { path: '/marcha/:id(\\d+)', name: 'marchaDetailLegacy', component: MarchaDetail },
  { path: '/marcha/search/:query', name: 'marchaList', component: MarchaList },
  { path: '/autor', name: 'autorSearch', component: AutorSearch },
  { path: '/autor/:slugAndId([a-z0-9-]*-\\d+)', name: 'autorDetail', component: AutorDetail },
  { path: '/autor/:id(\\d+)', name: 'autorDetailLegacy', component: AutorDetail },
  { path: '/autor/search/:query', name: 'autorList', component: AutorList },
  { path: '/banda', name: 'bandaSearch', component: BandaSearch },
  { path: '/banda/:slugAndId([a-z0-9-]*-\\d+)', name: 'bandaDetail', component: BandaDetail },
  { path: '/banda/:id(\\d+)', name: 'bandaDetailLegacy', component: BandaDetail },
  { path: '/banda/search/:query', name: 'bandaList', component: BandaList },
  { path: '/disco', name: 'discoSearch', component: DiscoSearch },
  { path: '/disco/:slugAndId([a-z0-9-]*-\\d+)', name: 'discoDetail', component: DiscoDetail },
  { path: '/disco/:id(\\d+)', name: 'discoDetailLegacy', component: DiscoDetail },
  { path: '/disco/search/:query', name: 'discoList', component: DiscoList },
  { path: '/estadisticas', name: 'stats', component: Stats }
]

export default router;

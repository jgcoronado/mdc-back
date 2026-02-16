/**
 * 
 * @param {*} page = ['marcha','autor','banda','disco']
 * @param {*} id 
 */
export function goToDetail(router,page, id) {
  router.push({
    name: `${page}Detail`,
    params: {
      id,
    },
  });
};
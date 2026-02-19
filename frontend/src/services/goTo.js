/**
 * 
 * @param {*} page = ['marcha','autor','banda','disco']
 * @param {*} id 
 */
function slugify(value) {
  if (!value) return '';
  return String(value)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-');
}

export function buildDetailPath(page, id, label) {
  const safeId = String(id ?? '').trim();
  const slug = slugify(label);
  if (!safeId) return `/${page}`;
  return slug ? `/${page}/${slug}-${safeId}` : `/${page}/${safeId}`;
}

export function goToDetail(router, page, id, label = '') {
  router.push(buildDetailPath(page, id, label));
}

export function canonicalizeDetailRoute(router, route, page, id, label) {
  const target = buildDetailPath(page, id, label);
  if (route.path !== target) {
    router.replace(target);
  }
}

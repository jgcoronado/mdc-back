export function slugify(value: string): string {
  if (!value) return '';
  return String(value)
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .replace(/-{2,}/g, '-');
}

export function buildDetailPath(page: string, id: string | number, label: string): string {
  const safeId = String(id ?? '').trim();
  if (!safeId) return `/${page}`;
  const slug = slugify(label);
  return slug ? `/${page}/${slug}-${safeId}` : `/${page}/${safeId}`;
}

export function extractId(slugAndId: string): string | null {
  const match = slugAndId.match(/(\d+)$/);
  return match ? match[1] : null;
}

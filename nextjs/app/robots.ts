import type { MetadataRoute } from 'next';

export default function robots(): MetadataRoute.Robots {
  const siteUrl = process.env.SITE_URL || 'https://marchasdecristo.com';
  return {
    rules: { userAgent: '*', allow: '/', disallow: ['/login', '/dashboard'] },
    sitemap: `${siteUrl}/sitemap.xml`,
  };
}

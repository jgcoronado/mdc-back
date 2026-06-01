import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  output: 'standalone',
  async rewrites() {
    const apiUrl = (process.env.INTERNAL_API_URL || 'http://localhost:3001').replace(/\/$/, '');
    return [
      {
        source: '/api/:path*',
        destination: `${apiUrl}/api/:path*`,
      },
      {
        source: '/cover/:path*',
        destination: `${apiUrl}/cover/:path*`,
      },
      {
        source: '/sitemap.xml',
        destination: `${apiUrl}/sitemap.xml`,
      },
    ];
  },
};

export default nextConfig;

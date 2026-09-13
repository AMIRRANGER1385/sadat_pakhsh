import type { NextConfig } from 'next';
const config: NextConfig = { poweredByHeader: false, images: { remotePatterns: [{ protocol: 'https', hostname: 'images.unsplash.com' }] }, async headers() { return [{ source: '/(.*)', headers: [{key:'X-Content-Type-Options',value:'nosniff'},{key:'X-Frame-Options',value:'DENY'},{key:'Referrer-Policy',value:'no-referrer'},{key:'Permissions-Policy',value:'camera=(), microphone=(), geolocation=()'},{key:'Strict-Transport-Security',value:'max-age=31536000'}] }]; } };
export default config;

import { NextRequest, NextResponse } from 'next/server';

export const config = {
  matcher: ['/dashboard/:path*'],
};

export async function middleware(request: NextRequest) {
  const apiUrl = (process.env.INTERNAL_API_URL || 'http://localhost:80').replace(/\/$/, '');
  const cookie = request.cookies.get('mdc_session');

  if (!cookie) {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  try {
    const res = await fetch(`${apiUrl}/api/login/verify`, {
      headers: { Cookie: `mdc_session=${cookie.value}` },
    });
    if (!res.ok) return NextResponse.redirect(new URL('/login', request.url));
  } catch {
    return NextResponse.redirect(new URL('/login', request.url));
  }

  return NextResponse.next();
}

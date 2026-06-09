import { NextResponse } from 'next/server';
import { dbAll } from '@/lib/db';

export const dynamic = 'force-dynamic';

export function GET() {
  try {
    dbAll('SELECT 1');
    return NextResponse.json({ status: 'ok', db: true });
  } catch {
    return NextResponse.json({ status: 'error', db: false }, { status: 503 });
  }
}

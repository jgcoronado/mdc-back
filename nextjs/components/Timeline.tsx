import Link from 'next/link';
import { buildDetailPath } from '@/lib/slugify';
import type { BandaDetail, BandaTimelineItem } from '@/lib/api';

interface TimelineProps {
  apiData: BandaDetail;
}

export default function Timeline({ apiData }: TimelineProps) {
  if (!apiData?.timeline?.length) return null;

  const sorted = [...apiData.timeline].sort((a, b) => a.FECHA_FUND - b.FECHA_FUND);
  const last = sorted[sorted.length - 1];
  const isExtinct = last?.FECHA_EXT ?? null;

  return (
    <ul className="timeline">
      {sorted.map((banda: BandaTimelineItem, index: number) => {
        const bandaPath = buildDetailPath('banda', banda.ID_BANDA, banda.NOMBRE_BREVE);
        const isCurrent = banda.ID_BANDA === apiData.ID_BANDA;
        const isOlder = banda.FECHA_FUND < apiData.FECHA_FUND;

        return (
          <li key={banda.ID_BANDA}>
            {index !== 0 && (
              <hr className={isOlder ? 'bg-primary' : undefined} />
            )}
            <div className="timeline-start">{banda.FECHA_FUND}</div>
            <div className="timeline-middle">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
                className={isCurrent || isOlder ? 'text-primary h-5 w-5' : 'h-5 w-5'}
              >
                <path
                  fillRule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                  clipRule="evenodd"
                />
              </svg>
            </div>
            <div className="timeline-end timeline-box bg-base-200">
              <Link href={bandaPath} className="hover:underline">
                {banda.NOMBRE_BREVE}
              </Link>
            </div>
            <hr className={isOlder ? 'bg-primary' : undefined} />
          </li>
        );
      })}
      <li>
        <hr />
        <div className="timeline-start">{isExtinct || 'Hoy'}</div>
        <div className="timeline-middle">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            className="h-5 w-5"
          >
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
              clipRule="evenodd"
            />
          </svg>
        </div>
        {isExtinct && (
          <div className="timeline-end timeline-box">Desaparece la banda</div>
        )}
      </li>
    </ul>
  );
}

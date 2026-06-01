import Link from 'next/link';
import { buildDetailPath } from '@/lib/slugify';

interface Disco {
  ID_DISCO: number;
  NOMBRE_CD: string;
  FECHA_CD: number;
  ID_BANDA?: number;
  BANDA?: string;
  DISCOS?: number;
  PISTAS?: number;
}

export default function CdList({ disco }: { disco: Disco }) {
  const coverSrc = `/cover/${disco.ID_DISCO}.png`;
  const discoPath = buildDetailPath('disco', disco.ID_DISCO, disco.NOMBRE_CD);
  const bandaPath = disco.ID_BANDA && disco.BANDA
    ? buildDetailPath('banda', disco.ID_BANDA, disco.BANDA)
    : null;

  return (
    <ul className="list bg-base-200 hover:bg-base-300 rounded-box shadow-md">
      <li className="list-row">
        <div>
          <Link href={discoPath}>
            <img
              className="size-15 rounded-box"
              src={coverSrc}
              alt={`Portada del disco '${disco.NOMBRE_CD}'`}
              onError={undefined}
              onContextMenu={(e) => e.preventDefault()}
            />
          </Link>
        </div>
        <div className="text-xl list-col-grow">
          <Link href={discoPath} className="hover:underline">
            {disco.NOMBRE_CD}
          </Link>
          {disco.BANDA && bandaPath ? (
            <div className="text-sm font-semibold opacity-60 indent-4">
              <Link href={bandaPath} className="hover:underline">
                {disco.BANDA}
              </Link>
            </div>
          ) : disco.DISCOS && disco.DISCOS > 1 ? (
            <div className="text-sm font-semibold opacity-60 indent-4">
              {disco.DISCOS} CDs, {disco.PISTAS} marchas
            </div>
          ) : (
            <div className="text-sm font-semibold opacity-60 indent-4">
              {disco.PISTAS} marchas
            </div>
          )}
        </div>
        <div>{disco.FECHA_CD}</div>
      </li>
    </ul>
  );
}

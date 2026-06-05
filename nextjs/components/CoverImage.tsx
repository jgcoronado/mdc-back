'use client';

interface CoverImageProps {
  src: string;
  alt: string;
  className?: string;
}

export default function CoverImage({ src, alt, className }: CoverImageProps) {
  return (
    <img
      className={className}
      src={src}
      alt={alt}
      onContextMenu={(event) => event.preventDefault()}
    />
  );
}

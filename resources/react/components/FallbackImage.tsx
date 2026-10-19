import { useState } from 'react';

type Props = {
    src?: string | null;
    alt: string;
    className?: string;
    kind?: 'avatar' | 'document' | 'photo';
    onClick?: React.MouseEventHandler<HTMLDivElement>;
};

/** Keeps image slots useful when a URL is missing or its resource cannot be loaded. */
export default function FallbackImage({ src, alt, className = '', kind = 'photo', onClick }: Props) {
    const [failedSrc, setFailedSrc] = useState<string | null>(null);
    const failed = !src || failedSrc === src;

    if (failed) {
        const icon = kind === 'avatar'
            ? <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>
            : kind === 'document'
                ? <><path d="M7 3h7l5 5v13H7z" /><path d="M14 3v6h5M10 14h6M10 17h6" /></>
                : <><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="8.5" cy="9" r="1.5" /><path d="m21 15-5-5L5 20" /></>;

        return <div role="img" aria-label={alt} onClick={onClick} className={`block flex items-center justify-center overflow-hidden bg-slate-100 text-slate-400 ${className}`}>
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="h-1/2 w-1/2 max-h-16 max-w-16">
                {icon}
            </svg>
        </div>;
    }

    return <img src={src} alt={alt} className={`block ${className}`} onClick={onClick} onError={() => setFailedSrc(src!)} />;
}

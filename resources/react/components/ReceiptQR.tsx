import { useEffect, useState } from 'react';
import QRCode from 'qrcode';

export default function ReceiptQR({ value, size = 110 }: { value: string; size?: number }) {
    const [dataUrl, setDataUrl] = useState('');

    useEffect(() => {
        let active = true;
        QRCode.toDataURL(value, { width: size, margin: 1, errorCorrectionLevel: 'M' })
            .then(url => { if (active) setDataUrl(url); })
            .catch(() => { if (active) setDataUrl(''); });
        return () => { active = false; };
    }, [value, size]);

    if (!dataUrl) return <div className="mx-auto flex items-center justify-center" style={{ width: size, height: size }}><i className="fas fa-qrcode text-gray-300 text-2xl"></i></div>;

    return <img src={dataUrl} alt="QR code" width={size} height={size} className="mx-auto" />;
}
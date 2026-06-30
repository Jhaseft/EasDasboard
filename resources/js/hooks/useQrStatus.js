import { jsonRequest } from '@/lib/http';
import { useEffect, useRef, useState } from 'react';

const POLL_GAP_MS = 3000;

/**
 * Hace polling del estado de un QR hasta que se resuelve (PAID | CANCELED).
 * Usa setTimeout encadenado (no setInterval): la siguiente consulta se agenda
 * recién cuando la anterior responde, así nunca se solapan aunque el banco
 * tarde 10-15s por consulta.
 *
 * @returns {'PENDING'|'PAID'|'CANCELED'} estado actual
 */
export function useQrStatus(qrId, initialStatus = 'PENDING') {
    const [status, setStatus] = useState(initialStatus);
    const timerRef = useRef(null);

    useEffect(() => {
        if (!qrId || status !== 'PENDING') return undefined;

        let cancelled = false;

        const poll = async () => {
            if (cancelled) return;
            const { data } = await jsonRequest(route('wallet.qr.status', qrId), 'GET');
            if (cancelled) return;

            if (data.status === 'PAID' || data.status === 'CANCELED') {
                setStatus(data.status);
                return;
            }
            timerRef.current = setTimeout(poll, POLL_GAP_MS);
        };

        poll();

        return () => {
            cancelled = true;
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, [qrId, status]);

    return status;
}

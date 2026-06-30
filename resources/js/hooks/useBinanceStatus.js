import { jsonRequest } from '@/lib/http';
import { useEffect, useRef, useState } from 'react';

const POLL_GAP_MS = 5000;

/**
 * Hace polling del estado de un intent de depósito Binance hasta que se resuelve
 * (CONFIRMED | EXPIRED | REJECTED). setTimeout encadenado: la próxima consulta se
 * agenda recién cuando la anterior responde, así nunca se solapan.
 *
 * @returns {{status: string, reason: ?string}}
 */
export function useBinanceStatus(intentId, initialStatus = 'PENDING') {
    const [status, setStatus] = useState(initialStatus);
    const [reason, setReason] = useState(null);
    const timerRef = useRef(null);

    useEffect(() => {
        if (!intentId || status !== 'PENDING') return undefined;

        let cancelled = false;

        const poll = async () => {
            if (cancelled) return;
            const { data } = await jsonRequest(route('wallet.binance.status', intentId), 'GET');
            if (cancelled) return;

            if (data.status && data.status !== 'PENDING') {
                setStatus(data.status);
                setReason(data.reason ?? null);
                return;
            }
            timerRef.current = setTimeout(poll, POLL_GAP_MS);
        };

        poll();

        return () => {
            cancelled = true;
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, [intentId, status]);

    return { status, reason, setStatus, setReason };
}

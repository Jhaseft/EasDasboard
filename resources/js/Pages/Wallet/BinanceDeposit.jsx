import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Spinner from '@/Components/Spinner';
import TextInput from '@/Components/TextInput';
import { useBinanceStatus } from '@/hooks/useBinanceStatus';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatMoney } from '@/lib/format';
import { jsonRequest } from '@/lib/http';
import { Head, Link, router } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { useEffect, useState } from 'react';

function NetworkSelector({ networks, current, amountUsd }) {
    if (networks.length <= 1) return null;

    const switchNetwork = (network) => {
        if (network === current) return;
        // Cambiar de red = nuevo intent (otra dirección). Redirige a su página.
        router.post(route('wallet.binance.store'), { amount: amountUsd, network });
    };

    return (
        <div className="mb-4">
            <div className="text-xs font-medium text-gray-600">Red</div>
            <div className="mt-1 flex flex-wrap gap-2">
                {networks.map((n) => (
                    <button
                        key={n.network}
                        type="button"
                        onClick={() => switchNetwork(n.network)}
                        className={
                            'rounded-md border px-3 py-1.5 text-sm transition ' +
                            (n.network === current
                                ? 'border-yellow-500 bg-yellow-50 font-medium text-yellow-700'
                                : 'border-gray-200 text-gray-600 hover:bg-gray-50')
                        }
                    >
                        {n.label}
                    </button>
                ))}
            </div>
        </div>
    );
}

function AddressBox({ address }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(address);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // ignorado: algunos navegadores bloquean clipboard sin HTTPS
        }
    };

    return (
        <div className="mt-4">
            <div className="text-xs font-medium text-gray-600">Dirección de depósito</div>
            <div className="mt-1 flex items-stretch gap-2">
                <code className="flex-1 break-all rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-800">
                    {address}
                </code>
                <button
                    type="button"
                    onClick={copy}
                    className="shrink-0 rounded-md border border-gray-200 px-3 text-sm text-gray-600 transition hover:bg-gray-50"
                >
                    {copied ? '¡Copiado!' : 'Copiar'}
                </button>
            </div>
        </div>
    );
}

function Countdown({ expiresAt }) {
    const [left, setLeft] = useState(() => Math.max(0, new Date(expiresAt) - new Date()));

    useEffect(() => {
        const t = setInterval(() => {
            setLeft(Math.max(0, new Date(expiresAt) - new Date()));
        }, 1000);
        return () => clearInterval(t);
    }, [expiresAt]);

    const mm = String(Math.floor(left / 60000)).padStart(2, '0');
    const ss = String(Math.floor((left % 60000) / 1000)).padStart(2, '0');

    return (
        <span className={left === 0 ? 'text-red-600' : 'text-gray-700'}>
            {mm}:{ss}
        </span>
    );
}

function DepositBreakdown({ expectedUsdt, amountUsd, coin, expiresAt }) {
    return (
        <dl className="mt-5 space-y-2 rounded-lg bg-gray-50 p-4 text-sm">
            <div className="flex items-center justify-between">
                <dt className="text-gray-500">Envía exactamente</dt>
                <dd className="text-lg font-bold text-gray-900">
                    {formatMoney(expectedUsdt)} {coin}
                </dd>
            </div>
            <div className="flex items-center justify-between">
                <dt className="text-gray-500">Recibirás</dt>
                <dd className="font-medium text-gray-700">${formatMoney(amountUsd)} USD</dd>
            </div>
            <div className="flex items-center justify-between border-t border-gray-200 pt-2">
                <dt className="text-gray-500">Expira en</dt>
                <dd className="font-mono font-medium">
                    <Countdown expiresAt={expiresAt} />
                </dd>
            </div>
        </dl>
    );
}

function TxidConfirm({ intentId, onResolved }) {
    const [txid, setTxid] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const confirm = async (e) => {
        e.preventDefault();
        setError('');
        setBusy(true);
        const { ok, data } = await jsonRequest(
            route('wallet.binance.confirm', intentId),
            'POST',
            { txid: txid.trim() },
        );
        setBusy(false);
        if (ok && data.status === 'CONFIRMED') {
            onResolved('CONFIRMED');
            return;
        }
        setError(data.message ?? 'No se pudo confirmar. Verifica el TXID.');
    };

    return (
        <form onSubmit={confirm} className="mt-5 border-t border-gray-100 pt-4">
            <div className="text-xs font-medium text-gray-600">¿Ya enviaste el pago? Pega tu TXID (hash)</div>
            <div className="mt-1 flex items-stretch gap-2">
                <TextInput
                    type="text"
                    className="block w-full text-xs"
                    placeholder="ej. 3f5a9c…"
                    value={txid}
                    onChange={(e) => setTxid(e.target.value)}
                />
                <SecondaryButton type="submit" disabled={busy || txid.trim().length < 6}>
                    {busy ? 'Verificando…' : 'Confirmar'}
                </SecondaryButton>
            </div>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </form>
    );
}

function ResultCard({ kind, amountUsd, reason }) {
    const ok = kind === 'CONFIRMED';
    return (
        <div className="rounded-lg bg-white p-8 text-center shadow-sm">
            <div
                className={
                    'mx-auto flex h-16 w-16 items-center justify-center rounded-full ' +
                    (ok ? 'bg-green-100' : 'bg-red-100')
                }
            >
                {ok ? (
                    <svg className="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                ) : (
                    <svg className="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                )}
            </div>
            <div className="mt-4 text-lg font-semibold text-gray-900">
                {ok ? '¡Pago confirmado!' : kind === 'EXPIRED' ? 'El intento expiró' : 'Pago rechazado'}
            </div>
            <div className="mt-1 text-sm text-gray-500">
                {ok
                    ? `Se acreditaron $${formatMoney(amountUsd)} USD a tu billetera.`
                    : reason || 'Crea una nueva recarga desde tu billetera.'}
            </div>
            <div className="mt-6">
                <Link href={route('wallet.index')}>
                    <PrimaryButton type="button">Ir a la billetera</PrimaryButton>
                </Link>
            </div>
        </div>
    );
}

export default function BinanceDeposit({ intent, networks = [] }) {
    const { status, reason, setStatus } = useBinanceStatus(intent.intentId, intent.status);

    useEffect(() => {
        if (status !== 'CONFIRMED') return undefined;
        const t = setTimeout(() => router.visit(route('wallet.index')), 3000);
        return () => clearTimeout(t);
    }, [status]);

    if (status !== 'PENDING') {
        return (
            <AuthenticatedLayout
                header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Recarga con USDT</h2>}
            >
                <Head title="Recarga con USDT" />
                <div className="py-6 sm:py-12">
                    <div className="mx-auto max-w-md px-4 sm:px-6 lg:px-8">
                        <ResultCard kind={status} amountUsd={intent.amountUsd} reason={reason} />
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Recarga con USDT</h2>}
        >
            <Head title="Recarga con USDT" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-md px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                            <h3 className="text-base font-semibold text-gray-900">Depósito con USDT</h3>
                            <span className="rounded bg-yellow-50 px-1.5 py-0.5 text-xs font-medium text-yellow-700">
                                Binance · {intent.network}
                            </span>
                        </div>

                        <div className="px-6 py-5">
                            <NetworkSelector networks={networks} current={intent.network} amountUsd={intent.amountUsd} />

                            <div className="flex justify-center">
                                <div className="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                                    <QRCodeSVG value={intent.walletAddress} size={208} level="M" />
                                </div>
                            </div>
                            <p className="mt-3 text-center text-xs text-gray-500">
                                Escanea con tu app de Binance o tu wallet.
                            </p>

                            <AddressBox address={intent.walletAddress} />

                            <DepositBreakdown
                                expectedUsdt={intent.expectedUsdt}
                                amountUsd={intent.amountUsd}
                                coin={intent.coin}
                                expiresAt={intent.expiresAt}
                            />

                            <div className="mt-4 rounded-md bg-amber-50 p-3 text-sm font-medium text-amber-700">
                                <div className="flex items-center justify-center gap-2">
                                    <Spinner className="h-4 w-4" />
                                    Esperando tu depósito…
                                </div>
                                <p className="mt-1 text-center text-xs font-normal text-amber-600">
                                    Envía solo {intent.coin} por la red {intent.network}. Otros activos o redes se perderán.
                                </p>
                            </div>

                            <TxidConfirm intentId={intent.intentId} onResolved={setStatus} />
                        </div>

                        <div className="border-t border-gray-100 px-6 py-4">
                            <Link href={route('wallet.index')}>
                                <SecondaryButton type="button">Volver</SecondaryButton>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

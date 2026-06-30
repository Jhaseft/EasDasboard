import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Spinner from '@/Components/Spinner';
import { useQrStatus } from '@/hooks/useQrStatus';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatMoney } from '@/lib/format';
import { jsonRequest } from '@/lib/http';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function QrImage({ image }) {
    return (
        <div className="flex justify-center">
            <div className="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                {image ? (
                    <img
                        src={`data:image/png;base64,${image}`}
                        alt="Código QR de pago"
                        className="h-64 w-64 object-contain"
                    />
                ) : (
                    <div className="flex h-64 w-64 items-center justify-center text-sm text-gray-400">
                        QR no disponible
                    </div>
                )}
            </div>
        </div>
    );
}

function PaymentBreakdown({ amountUsd, rate, amountBob }) {
    return (
        <dl className="mt-5 space-y-2 rounded-lg bg-gray-50 p-4 text-sm">
            <div className="flex items-center justify-between">
                <dt className="text-gray-500">Recargas</dt>
                <dd className="font-medium text-gray-900">${formatMoney(amountUsd)} USD</dd>
            </div>
            <div className="flex items-center justify-between">
                <dt className="text-gray-500">Tipo de cambio</dt>
                <dd className="font-medium text-gray-700">Bs {formatMoney(rate)} / USD</dd>
            </div>
            <div className="flex items-center justify-between border-t border-gray-200 pt-2">
                <dt className="font-semibold text-gray-700">Total a pagar</dt>
                <dd className="text-lg font-bold text-gray-900">Bs {formatMoney(amountBob)}</dd>
            </div>
        </dl>
    );
}

function SuccessCard({ amountUsd }) {
    return (
        <div className="rounded-lg bg-white p-8 text-center shadow-sm">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <svg className="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <div className="mt-4 text-lg font-semibold text-gray-900">¡Pago confirmado!</div>
            <div className="mt-1 text-sm text-gray-500">
                Se acreditaron{' '}
                <span className="font-semibold text-gray-700">${formatMoney(amountUsd)} USD</span> a tu billetera.
            </div>
            <div className="mt-2 text-xs text-gray-400">Redirigiendo a tu billetera…</div>
            <div className="mt-6">
                <Link href={route('wallet.index')}>
                    <PrimaryButton type="button">Ir a la billetera</PrimaryButton>
                </Link>
            </div>
        </div>
    );
}

export default function QrPayment({ qr }) {
    const status = useQrStatus(qr.qrId, qr.status);
    const [canceling, setCanceling] = useState(false);

    // Al confirmarse el pago, vuelve a la billetera tras un breve instante.
    useEffect(() => {
        if (status !== 'PAID') return undefined;
        const t = setTimeout(() => router.visit(route('wallet.index')), 2500);
        return () => clearTimeout(t);
    }, [status]);

    const cancel = async () => {
        setCanceling(true);
        await jsonRequest(route('wallet.qr.destroy', qr.qrId), 'DELETE');
        router.visit(route('wallet.index'));
    };

    const canceled = status === 'CANCELED';

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Pagar con QR</h2>}
        >
            <Head title="Pagar con QR" />

            <div className="py-4">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        onClick={() => window.history.back()}
                        className="inline-flex items-center gap-1 text-sm font-medium text-gray-600 transition hover:text-gray-900"
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Volver
                    </button>

                    <div className="mx-auto mt-4 max-w-md">
                        {status === 'PAID' ? (
                            <SuccessCard amountUsd={qr.amountUsd} />
                        ) : (
                            <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                                <div className="border-b border-gray-100 px-6 py-4">
                                    <h3 className="text-base font-semibold text-gray-900">Escanea para pagar</h3>
                                    <p className="text-xs text-gray-400">Banco Económico</p>
                                </div>

                                <div className="px-6 py-5">
                                    <QrImage image={qr.qrImage} />
                                    <p className="mt-3 text-center text-xs text-gray-500">
                                        Escanea el código con la app de tu banco para pagar.
                                    </p>

                                    <PaymentBreakdown amountUsd={qr.amountUsd} rate={qr.rate} amountBob={qr.amountBob} />

                                    <div className="mt-3 text-xs text-gray-400">Válido hasta: {qr.dueDate}</div>

                                    {canceled ? (
                                        <div className="mt-4 rounded-md bg-red-50 p-3 text-center text-sm text-red-700">
                                            El QR fue anulado o expiró.
                                        </div>
                                    ) : (
                                        <div className="mt-4 flex items-center justify-center gap-2 rounded-md bg-amber-50 p-3 text-sm font-medium text-amber-700">
                                            <Spinner className="h-4 w-4" />
                                            Esperando confirmación del pago…
                                        </div>
                                    )}
                                </div>

                                {!canceled && (
                                    <div className="flex justify-end border-t border-gray-100 px-6 py-4">
                                        <SecondaryButton type="button" onClick={cancel} disabled={canceling}>
                                            {canceling ? 'Cancelando…' : 'Cancelar pago'}
                                        </SecondaryButton>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

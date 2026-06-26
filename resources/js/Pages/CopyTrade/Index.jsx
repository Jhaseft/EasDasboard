import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_STYLES = {
    open:    'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    closed:  'bg-gray-100 text-gray-600',
    failed:  'bg-red-100 text-red-800',
};

export default function Index({ master, positions, slaves, history }) {
    const flash = usePage().props.flash ?? {};
    const [selectedPosition, setSelectedPosition] = useState(null);
    const [selectedSlaves, setSelectedSlaves] = useState([]);

    const openModal = (position) => {
        setSelectedPosition(position);
        setSelectedSlaves(slaves.map((s) => s.id));
    };

    const closeModal = () => {
        setSelectedPosition(null);
        setSelectedSlaves([]);
    };

    const toggleSlave = (id) => {
        setSelectedSlaves((prev) =>
            prev.includes(id) ? prev.filter((s) => s !== id) : [...prev, id]
        );
    };

    const confirmCopy = () => {
        if (!selectedPosition || selectedSlaves.length === 0) return;

        router.post(
            route('broker-accounts.copy-trade.copy', master.id),
            {
                master_position_id: selectedPosition.id,
                symbol:             selectedPosition.symbol,
                direction:          selectedPosition.type === 'POSITION_TYPE_BUY' ? 'buy' : 'sell',
                master_lot:         selectedPosition.volume,
                slave_account_ids:  selectedSlaves,
            },
            {
                preserveScroll: true,
                onSuccess: closeModal,
            }
        );
    };

    const slaveLot = (slave) =>
        Math.max(((selectedPosition?.volume ?? 0) * slave.lot_multiplier).toFixed(2), 0.01);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Copy Trading — {master.name}
                </h2>
            }
        >
            <Head title="Copy Trading" />

            <div className="py-6 sm:py-12">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    {/* Posiciones abiertas */}
                    <div className="rounded-lg bg-white shadow-sm">
                        <div className="border-b border-gray-100 px-6 py-4">
                            <h3 className="font-semibold text-gray-800">Posiciones abiertas</h3>
                        </div>

                        {positions.length === 0 ? (
                            <div className="p-6 text-sm text-gray-500">
                                No hay posiciones abiertas en esta cuenta.
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-100">
                                {positions.map((pos) => (
                                    <div key={pos.id} className="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                        <div>
                                            <span className="font-medium text-gray-900">{pos.symbol}</span>
                                            <span className={`ml-2 rounded px-2 py-0.5 text-xs font-semibold ${pos.type === 'POSITION_TYPE_BUY' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {pos.type === 'POSITION_TYPE_BUY' ? 'BUY' : 'SELL'}
                                            </span>
                                            <span className="ml-3 text-sm text-gray-500">{pos.volume} lot</span>
                                        </div>
                                        <div className="flex items-center gap-4">
                                            <span className={`text-sm font-medium ${pos.unrealizedProfit >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                {pos.unrealizedProfit >= 0 ? '+' : ''}{pos.unrealizedProfit?.toFixed(2)} USD
                                            </span>
                                            {slaves.length > 0 && (
                                                <button
                                                    onClick={() => openModal(pos)}
                                                    className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                                                >
                                                    Copiar a esclavas
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Historial */}
                    <div className="rounded-lg bg-white shadow-sm">
                        <div className="border-b border-gray-100 px-6 py-4">
                            <h3 className="font-semibold text-gray-800">Historial de copias</h3>
                        </div>

                        {history.length === 0 ? (
                            <div className="p-6 text-sm text-gray-500">
                                Aún no se ha copiado ninguna operación.
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-100">
                                {history.map((trade) => (
                                    <div key={trade.id} className="flex flex-wrap items-center justify-between gap-2 px-6 py-3 text-sm">
                                        <div className="flex items-center gap-3">
                                            <span className="font-medium text-gray-900">{trade.symbol}</span>
                                            <span className={`rounded px-2 py-0.5 text-xs font-semibold ${trade.direction === 'buy' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                {trade.direction.toUpperCase()}
                                            </span>
                                            <span className="text-gray-500">→ {trade.slave_account?.name ?? '—'}</span>
                                        </div>
                                        <div className="flex items-center gap-3 text-gray-500">
                                            <span>{trade.slave_lot} lot</span>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_STYLES[trade.status] ?? ''}`}>
                                                {trade.status}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal de selección de esclavas */}
            {selectedPosition && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-lg bg-white shadow-xl">
                        <div className="border-b border-gray-100 px-6 py-4">
                            <h3 className="font-semibold text-gray-800">
                                Copiar {selectedPosition.symbol} {selectedPosition.type === 'POSITION_TYPE_BUY' ? 'BUY' : 'SELL'} ({selectedPosition.volume} lot)
                            </h3>
                            <p className="mt-1 text-xs text-gray-500">Selecciona las cuentas donde abrir la operación</p>
                        </div>

                        <div className="divide-y divide-gray-100 px-6 py-2">
                            {slaves.map((slave) => (
                                <label key={slave.id} className="flex cursor-pointer items-center justify-between py-3">
                                    <div className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={selectedSlaves.includes(slave.id)}
                                            onChange={() => toggleSlave(slave.id)}
                                            className="rounded border-gray-300 text-indigo-600"
                                        />
                                        <span className="text-sm font-medium text-gray-800">{slave.name}</span>
                                    </div>
                                    <span className="text-xs text-gray-500">
                                        {slaveLot(slave)} lot (×{slave.lot_multiplier})
                                    </span>
                                </label>
                            ))}
                        </div>

                        <div className="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                            <button
                                onClick={closeModal}
                                className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={confirmCopy}
                                disabled={selectedSlaves.length === 0}
                                className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Confirmar copia ({selectedSlaves.length})
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

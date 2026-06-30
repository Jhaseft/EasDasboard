import { formatMoney } from '@/lib/format';

export default function PlatformCostCard({ platformCost }) {
    if (!platformCost) return null;

    return (
        <div className="rounded-lg bg-white p-6 shadow-sm">
            <div className="mb-3 text-sm font-medium text-gray-700">Costo mensual de plataforma</div>
            <div className="space-y-1 text-sm text-gray-600">
                <div className="flex justify-between">
                    <span>
                        {platformCost.accounts} cuenta(s) × ${formatMoney(platformCost.account_fee)}
                    </span>
                    <span>${formatMoney(platformCost.accounts_total)}</span>
                </div>
                {platformCost.webhook && (
                    <div className="flex justify-between">
                        <span>Módulo webhook</span>
                        <span>${formatMoney(platformCost.webhook_fee)}</span>
                    </div>
                )}
                <div className="flex justify-between border-t border-gray-100 pt-1 font-semibold text-gray-900">
                    <span>Total / mes</span>
                    <span>${formatMoney(platformCost.total)}</span>
                </div>
            </div>
            <p className="mt-2 text-xs text-gray-400">
                Se cobra automáticamente de tu billetera el día 1 de cada mes.
            </p>
        </div>
    );
}

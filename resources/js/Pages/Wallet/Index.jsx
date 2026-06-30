import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import BinanceDepositCard from './Partials/BinanceDepositCard';
import PlatformCostCard from './Partials/PlatformCostCard';
import TransactionList from './Partials/TransactionList';
import WalletBalanceCard from './Partials/WalletBalanceCard';

export default function Index({
    wallet,
    transactions,
    platformCost,
    exchangeRate,
    binanceNetworks,
    binanceDefaultNetwork,
}) {
    const flash = usePage().props.flash ?? {};

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Billetera</h2>}
        >
            <Head title="Billetera" />

            <div className="py-4">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    <WalletBalanceCard wallet={wallet} exchangeRate={exchangeRate} />

                    <BinanceDepositCard
                        networks={binanceNetworks}
                        defaultNetwork={binanceDefaultNetwork}
                    />

                    <PlatformCostCard platformCost={platformCost} />

                    <TransactionList transactions={transactions} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

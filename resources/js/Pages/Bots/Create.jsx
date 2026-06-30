import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import BotForm from './Partials/BotForm';

export default function Create({ strategyDefaults, brokerAccounts = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        is_active: false,
        broker_account_id: null,
        symbols: [],
        timeframe: 'H1',
        strategy: 'simple',
        parameters: {},
        direction: 'both',
        lot_size: '0.01',
        stop_loss_pips: '',
        take_profit_pips: '',
        max_open_trades: '1',
        risk_percent: '',
        trailing_stop_pips: '',
        trading_start_time: '',
        trading_end_time: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('bots.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Nuevo bot
                </h2>
            }
        >
            <Head title="Nuevo bot" />

            <div className="py-4">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-4 shadow-sm sm:p-6">
                        <BotForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Crear bot"
                            strategyDefaults={strategyDefaults}
                            brokerAccounts={brokerAccounts}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import BotForm from './Partials/BotForm';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        is_active: false,
        symbols: [],
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

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <BotForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Crear bot"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

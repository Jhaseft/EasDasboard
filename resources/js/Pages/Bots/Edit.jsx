import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import BotForm from './Partials/BotForm';

export default function Edit({ bot, strategyDefaults }) {
    const botStrategy = bot.strategy ?? 'simple';
    const { data, setData, put, processing, errors } = useForm({
        name: bot.name ?? '',
        is_active: !!bot.is_active,
        symbols: bot.symbols ?? [],
        timeframe: bot.timeframe ?? 'H1',
        strategy: botStrategy,
        // Fusiona los defaults de la estrategia con lo guardado en el bot.
        parameters: {
            ...(strategyDefaults?.[botStrategy] ?? {}),
            ...(bot.parameters ?? {}),
        },
        direction: bot.direction ?? 'both',
        lot_size: bot.lot_size ?? '0.01',
        stop_loss_pips: bot.stop_loss_pips ?? '',
        take_profit_pips: bot.take_profit_pips ?? '',
        max_open_trades: bot.max_open_trades ?? '1',
        risk_percent: bot.risk_percent ?? '',
        trailing_stop_pips: bot.trailing_stop_pips ?? '',
        trading_start_time: bot.trading_start_time
            ? String(bot.trading_start_time).slice(0, 5)
            : '',
        trading_end_time: bot.trading_end_time
            ? String(bot.trading_end_time).slice(0, 5)
            : '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('bots.update', bot.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Editar bot: {bot.name}
                </h2>
            }
        >
            <Head title={`Editar ${bot.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <BotForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Guardar cambios"
                            strategyDefaults={strategyDefaults}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

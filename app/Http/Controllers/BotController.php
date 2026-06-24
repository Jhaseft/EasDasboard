<?php

namespace App\Http\Controllers;

use App\Http\Requests\BotRequest;
use App\Models\Bot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotController extends Controller
{
    public function index(Request $request): Response
    {
        $bots = $request->user()->bots()
            ->latest()
            ->get();

        return Inertia::render('Bots/Index', [
            'bots' => $bots,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Bots/Create', [
            'strategyDefaults' => [
                'asian_breakout' => Bot::defaultParameters('asian_breakout'),
                'multitf_orderflow' => Bot::defaultParameters('multitf_orderflow'),
            ],
            'brokerAccounts' => $this->brokerAccountOptions($request),
        ]);
    }

    public function store(BotRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['parameters'] = $this->cleanParameters($data['strategy'], $data['parameters'] ?? []);

        $request->user()->bots()->create($data);

        return redirect()->route('bots.index')->with('success', 'Bot creado correctamente.');
    }

    public function edit(Request $request, Bot $bot): Response
    {
        $this->authorizeBot($request, $bot);

        return Inertia::render('Bots/Edit', [
            'bot' => $bot,
            'strategyDefaults' => [
                'asian_breakout' => Bot::defaultParameters('asian_breakout'),
                'multitf_orderflow' => Bot::defaultParameters('multitf_orderflow'),
            ],
            'brokerAccounts' => $this->brokerAccountOptions($request),
        ]);
    }

    /**
     * Cuentas de broker del usuario para el selector del formulario.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function brokerAccountOptions(Request $request)
    {
        return $request->user()->brokerAccounts()
            ->get(['id', 'name', 'platform', 'login', 'provision_state'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'label' => "{$a->name} ({$a->platform}/{$a->login})",
                'ready' => $a->provision_state === 'deployed',
            ]);
    }

    public function update(BotRequest $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        $data = $request->validated();
        $data['parameters'] = $this->cleanParameters($data['strategy'], $data['parameters'] ?? []);

        $bot->update($data);

        return redirect()->route('bots.index')->with('success', 'Bot actualizado correctamente.');
    }

    public function destroy(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        $bot->delete();

        return redirect()->route('bots.index')->with('success', 'Bot eliminado.');
    }

    /**
     * Toggle the active state of the bot quickly from the list.
     */
    public function toggle(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        $bot->update(['is_active' => ! $bot->is_active]);

        return back();
    }

    protected function authorizeBot(Request $request, Bot $bot): void
    {
        abort_unless($bot->user_id === $request->user()->id, 403);
    }

    /**
     * Mantiene solo las claves conocidas de la estrategia y castea los tipos
     * según el valor por defecto (bool/int/float).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function cleanParameters(string $strategy, array $input): array
    {
        $defaults = Bot::defaultParameters($strategy);
        $clean = [];

        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if (is_bool($default)) {
                $clean[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_int($default)) {
                $clean[$key] = (int) $value;
            } else {
                $clean[$key] = (float) $value;
            }
        }

        return $clean;
    }
}

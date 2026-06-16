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

    public function create(): Response
    {
        return Inertia::render('Bots/Create');
    }

    public function store(BotRequest $request): RedirectResponse
    {
        $request->user()->bots()->create($request->validated());

        return redirect()->route('bots.index')->with('success', 'Bot creado correctamente.');
    }

    public function edit(Request $request, Bot $bot): Response
    {
        $this->authorizeBot($request, $bot);

        return Inertia::render('Bots/Edit', [
            'bot' => $bot,
        ]);
    }

    public function update(BotRequest $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        $bot->update($request->validated());

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
}

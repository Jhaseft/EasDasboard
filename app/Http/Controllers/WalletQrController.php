<?php

namespace App\Http\Controllers;

use App\Exceptions\BanecoException;
use App\Models\QrDeposit;
use App\Services\Baneco\BanecoQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recarga de billetera vía QR de Banco Económico. El pago vive en su propia página
 * (Wallet/QrPayment): store genera el QR y redirige a show, que la renderiza. La
 * página hace polling de /status hasta que el banco confirma (allí se acredita).
 */
class WalletQrController extends Controller
{
    public function __construct(
        protected BanecoQrService $qr,
    ) {}

    /**
     * Genera un QR para recargar `amount` USD y redirige a su página de pago.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
        ]);

        try {
            $result = $this->qr->createDeposit($request->user(), (float) $data['amount']);
        } catch (BanecoException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('wallet.qr.show', $result['qrId']);
    }

    /**
     * Página de pago: muestra el QR, el desglose y hace polling del estado.
     */
    public function show(Request $request, string $qrId): Response
    {
        $deposit = $this->findUserDeposit($request, $qrId);

        return Inertia::render('Wallet/QrPayment', [
            'qr' => [
                'qrId'      => $deposit->qr_id,
                'qrImage'   => $deposit->qr_image,
                'amountUsd' => (float) $deposit->amount_usd,
                'amountBob' => (float) $deposit->amount_bob,
                'rate'      => (float) $deposit->exchange_rate,
                'dueDate'   => $deposit->due_date->toDateString(),
                'status'    => $this->mapStatus($deposit->status),
            ],
        ]);
    }

    /**
     * Estado del QR para el polling: PENDING | PAID | CANCELED.
     */
    public function status(Request $request, string $qrId): JsonResponse
    {
        try {
            $result = $this->qr->getStatus($request->user(), $qrId);
        } catch (BanecoException $e) {
            // Error transitorio del banco: el frontend reintentará en el siguiente poll.
            return response()->json(['status' => 'PENDING', 'message' => $e->getMessage()], 200);
        }

        return response()->json($result);
    }

    /**
     * Anula un QR pendiente.
     */
    public function destroy(Request $request, string $qrId): JsonResponse
    {
        try {
            $result = $this->qr->cancel($request->user(), $qrId);
        } catch (BanecoException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($result);
    }

    private function findUserDeposit(Request $request, string $qrId): QrDeposit
    {
        $deposit = QrDeposit::where('qr_id', $qrId)->first();

        if (! $deposit || $deposit->user_id !== $request->user()->id) {
            abort(404, 'QR no encontrado.');
        }

        return $deposit;
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            QrDeposit::STATUS_PAID => 'PAID',
            QrDeposit::STATUS_PENDING => 'PENDING',
            default => 'CANCELED',
        };
    }
}

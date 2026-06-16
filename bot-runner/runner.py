"""
Runner MT5 para EasDashboard.

Lee la configuracion de bots ACTIVOS desde la API del panel y, segun como
cada bot esta configurado para abrir operaciones, manda las ordenes al
terminal de MetaTrader 5 instalado en esta misma maquina.

Requisitos:
    pip install MetaTrader5 requests
    - Terminal MT5 instalado y con una cuenta abierta (login hecho).
    - Trading algoritmico habilitado en el terminal (boton "Algo Trading").

Uso:
    set DASHBOARD_URL=https://tu-dominio        (o http://127.0.0.1:8000 en local)
    set BOT_API_KEY=tu-clave-secreta
    python runner.py
"""

import os
import time

import requests
import MetaTrader5 as mt5

# --- Configuracion via variables de entorno ---
DASHBOARD_URL = os.getenv("DASHBOARD_URL", "http://127.0.0.1:8000").rstrip("/")
API_KEY = os.getenv("BOT_API_KEY", "")
POLL_SECONDS = int(os.getenv("POLL_SECONDS", "30"))  # cada cuanto consulta el panel

ACTIVE_ENDPOINT = f"{DASHBOARD_URL}/api/bots/active"
HEADERS = {"X-API-Key": API_KEY}


def fetch_active_bots():
    """Devuelve la lista de bots activos con sus instrucciones de operacion."""
    resp = requests.get(ACTIVE_ENDPOINT, headers=HEADERS, timeout=15)
    resp.raise_for_status()
    return resp.json().get("bots", [])


def pips_to_price(symbol_info, pips):
    """Convierte pips a distancia de precio para el simbolo dado."""
    if pips is None:
        return None
    # En brokers de 5/3 digitos, 1 pip = 10 * point. En 4/2 digitos, 1 pip = point.
    point = symbol_info.point
    factor = 10 if symbol_info.digits in (3, 5) else 1
    return pips * point * factor


def count_open_positions(symbol, magic):
    positions = mt5.positions_get(symbol=symbol)
    if positions is None:
        return 0
    return len([p for p in positions if p.magic == magic])


def open_operation(bot, symbol):
    """Abre una operacion para un bot+simbolo segun su configuracion basica."""
    entry = bot["entry"]
    magic = 1000000 + int(bot["id"])  # identifica las ordenes de este bot

    # Respeta el maximo de operaciones abiertas por bot/simbolo.
    if count_open_positions(symbol, magic) >= entry["max_open_trades"]:
        print(f"  [{bot['name']}] {symbol}: ya alcanzo max_open_trades, no abre.")
        return

    if not mt5.symbol_select(symbol, True):
        print(f"  [{bot['name']}] {symbol}: simbolo no disponible.")
        return

    info = mt5.symbol_info(symbol)
    tick = mt5.symbol_info_tick(symbol)
    if info is None or tick is None:
        print(f"  [{bot['name']}] {symbol}: sin info/precio.")
        return

    # Decide direccion. 'both' usa compra por defecto en este ejemplo basico;
    # aqui es donde luego conectarias tu logica/senal real.
    direction = entry["direction"]
    if direction == "sell":
        order_type = mt5.ORDER_TYPE_SELL
        price = tick.bid
    else:  # buy o both
        order_type = mt5.ORDER_TYPE_BUY
        price = tick.ask

    sl_dist = pips_to_price(info, entry["stop_loss_pips"])
    tp_dist = pips_to_price(info, entry["take_profit_pips"])

    if order_type == mt5.ORDER_TYPE_BUY:
        sl = price - sl_dist if sl_dist else 0.0
        tp = price + tp_dist if tp_dist else 0.0
    else:
        sl = price + sl_dist if sl_dist else 0.0
        tp = price - tp_dist if tp_dist else 0.0

    request = {
        "action": mt5.TRADE_ACTION_DEAL,
        "symbol": symbol,
        "volume": float(entry["lot_size"]),
        "type": order_type,
        "price": price,
        "sl": sl,
        "tp": tp,
        "deviation": 20,
        "magic": magic,
        "comment": f"EasDashboard:{bot['name']}",
        "type_time": mt5.ORDER_TIME_GTC,
        "type_filling": mt5.ORDER_FILLING_IOC,
    }

    result = mt5.order_send(request)
    if result.retcode == mt5.TRADE_RETCODE_DONE:
        print(f"  [{bot['name']}] {symbol}: orden {direction} ejecutada @ {price}")
    else:
        print(f"  [{bot['name']}] {symbol}: fallo ({result.retcode}) {result.comment}")


def loop():
    while True:
        try:
            bots = fetch_active_bots()
            print(f"Bots activos: {len(bots)}")
            for bot in bots:
                # within_trading_window lo calcula el panel segun el horario configurado.
                if not bot.get("within_trading_window", True):
                    print(f"  [{bot['name']}] fuera de horario, no opera.")
                    continue
                for symbol in bot.get("symbols", []):
                    open_operation(bot, symbol)
        except Exception as exc:  # noqa: BLE001
            print("Error en el ciclo:", exc)

        time.sleep(POLL_SECONDS)


if __name__ == "__main__":
    if not API_KEY:
        raise SystemExit("Define BOT_API_KEY en las variables de entorno.")
    if not mt5.initialize():
        raise SystemExit(f"No se pudo iniciar MT5: {mt5.last_error()}")
    print("Conectado a MT5. Cuenta:", mt5.account_info().login)
    try:
        loop()
    finally:
        mt5.shutdown()

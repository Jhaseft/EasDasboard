"""
Worker MetaApi Cloud para EasDashboard (SaaS).

A diferencia de runner.py (que usa un terminal MT5 LOCAL), este worker opera
TODAS las cuentas de los usuarios en la nube via MetaApi. No necesita ningun
MetaTrader instalado: cada cuenta corre en el cloud de MetaApi.

Flujo:
  1. Pregunta al panel:  GET /api/worker/accounts  (cabecera X-API-Key)
     -> devuelve el token de MetaApi + la lista de cuentas operables, y dentro
        de cada una los bots activos con su configuracion.
  2. Por cada cuenta, abre una conexion RPC a MetaApi y, segun la config de cada
     bot, abre operaciones (respetando horario y max_open_trades).
  3. Repite cada POLL_SECONDS.

Requisitos:
    pip install -r requirements.txt
Variables de entorno:
    set DASHBOARD_URL=https://tu-dominio     (o http://127.0.0.1:8000 en local)
    set BOT_API_KEY=la-misma-clave-del-.env-del-panel
    set POLL_SECONDS=30
    python metaapi_worker.py
"""

import asyncio
import os

import requests
from metaapi_cloud_sdk import MetaApi

DASHBOARD_URL = os.getenv("DASHBOARD_URL", "http://127.0.0.1:8000").rstrip("/")
API_KEY = os.getenv("BOT_API_KEY", "")
POLL_SECONDS = int(os.getenv("POLL_SECONDS", "30"))

ACCOUNTS_ENDPOINT = f"{DASHBOARD_URL}/api/worker/accounts"
HEADERS = {"X-API-Key": API_KEY}


def fetch_accounts():
    """Devuelve (metaapi_token, [cuentas]) desde el panel."""
    resp = requests.get(ACCOUNTS_ENDPOINT, headers=HEADERS, timeout=20)
    resp.raise_for_status()
    data = resp.json()
    return data.get("metaapi_token"), data.get("accounts", [])


def pips_to_price(spec, pips):
    """Convierte pips a distancia de precio segun los digitos del simbolo."""
    if pips is None:
        return 0.0
    digits = spec.get("digits", 5)
    point = 10 ** (-digits)
    factor = 10 if digits in (3, 5) else 1
    return float(pips) * point * factor


async def count_open_positions(connection, symbol, magic):
    positions = await connection.get_positions()
    return len([p for p in positions if p.get("symbol") == symbol and p.get("magic") == magic])


async def open_operation(connection, bot, symbol):
    """Abre una operacion para un bot+simbolo via MetaApi (logica basica)."""
    entry = bot["entry"]
    magic = 1000000 + int(bot["id"])

    if await count_open_positions(connection, symbol, magic) >= entry["max_open_trades"]:
        print(f"  [{bot['name']}] {symbol}: ya alcanzo max_open_trades.")
        return

    try:
        price = await connection.get_symbol_price(symbol)
        spec = await connection.get_symbol_specification(symbol)
    except Exception as exc:  # noqa: BLE001
        print(f"  [{bot['name']}] {symbol}: sin precio/spec ({exc}).")
        return

    direction = entry["direction"]
    sl_dist = pips_to_price(spec, entry["stop_loss_pips"])
    tp_dist = pips_to_price(spec, entry["take_profit_pips"])
    volume = float(entry["lot_size"])
    options = {"comment": f"Eas:{bot['id']}", "magic": magic}

    if direction == "sell":
        ref = price["bid"]
        sl = ref + sl_dist if sl_dist else None
        tp = ref - tp_dist if tp_dist else None
        result = await connection.create_market_sell_order(symbol, volume, sl, tp, options)
    else:  # buy o both -> compra por defecto (aqui conectas tu senal real)
        ref = price["ask"]
        sl = ref - sl_dist if sl_dist else None
        tp = ref + tp_dist if tp_dist else None
        result = await connection.create_market_buy_order(symbol, volume, sl, tp, options)

    print(f"  [{bot['name']}] {symbol}: {direction} -> {result.get('stringCode', result)}")


async def process_account(api, account_data):
    """Conecta una cuenta MetaApi y procesa sus bots activos."""
    account_id = account_data["metaapi_account_id"]
    bots = account_data.get("bots", [])
    if not bots:
        return

    account = await api.metatrader_account_api.get_account(account_id)
    connection = account.get_rpc_connection()
    await connection.connect()
    try:
        await connection.wait_synchronized({"timeoutInSeconds": 60})

        for bot in bots:
            if not bot.get("within_trading_window", True):
                print(f"  [{bot['name']}] fuera de horario, no opera.")
                continue
            for symbol in bot.get("symbols", []):
                await open_operation(connection, bot, symbol)
    finally:
        await connection.close()


async def loop():
    while True:
        try:
            token, accounts = fetch_accounts()
            if not token:
                print("El panel no devolvio METAAPI_TOKEN. Configuralo en el .env.")
            else:
                api = MetaApi(token)
                print(f"Cuentas operables: {len(accounts)}")
                for account_data in accounts:
                    try:
                        await process_account(api, account_data)
                    except Exception as exc:  # noqa: BLE001
                        print(f"Cuenta {account_data.get('metaapi_account_id')}: error {exc}")
        except Exception as exc:  # noqa: BLE001
            print("Error en el ciclo:", exc)

        await asyncio.sleep(POLL_SECONDS)


if __name__ == "__main__":
    if not API_KEY:
        raise SystemExit("Define BOT_API_KEY en las variables de entorno.")
    asyncio.run(loop())

# EasDashboard como SaaS con MetaApi Cloud

Esta es la arquitectura **multi-cuenta en la nube**: cada usuario conecta su
broker desde el panel y un único **worker de Python** opera todas las cuentas
vía [MetaApi](https://metaapi.cloud). Nadie instala MetaTrader.

```
 Usuario  ──(login/server/pass)──►  Panel Laravel  ──(provisioning REST)──►  MetaApi
                                          │                                     │ (corre el
                                          │ guarda metaapi_account_id           │  terminal
                                          ▼                                     │  en cloud)
                                  GET /api/worker/accounts                      │
                                          ▲                                     ▼
                                          │                          ┌──────────────────┐
                              Worker Python (metaapi_worker.py) ─────► abre/cierra trades
                                                  (SDK MetaApi)       └──────────────────┘
```

## Diferencia con el modo local (`runner.py` / EAs `.mq5`)

| | Local (lo viejo) | Cloud / SaaS (esto) |
|---|---|---|
| MetaTrader | Instalado en VPS del usuario | En la nube de MetaApi |
| Quién ejecuta | EA o `runner.py` local | `metaapi_worker.py` central |
| Escala a N clientes | ❌ cada uno monta su VPS | ✅ un worker, N cuentas |

## Puesta en marcha

### 1. Panel (Laravel)
1. En `.env` pon tu token de MetaApi:
   ```
   METAAPI_TOKEN=eyJhbGciOi...
   METAAPI_REGION=new-york
   BOT_API_KEY=<una clave secreta para el worker>
   ```
2. `php artisan migrate` (crea `broker_accounts` y enlaza `bots`).
3. El usuario entra a **Cuentas → Conectar cuenta** y mete login, servidor y
   contraseña de su broker. El panel llama a MetaApi (`provision`) y deja la
   cuenta `deployed`.
4. Al crear/editar un **bot**, asígnale una `broker_account_id` (la cuenta sobre
   la que debe operar) y ponlo `is_active`.

### 2. Worker (Python)
En cualquier servidor (no necesita MT5):
```bash
cd bot-runner
pip install -r requirements.txt
set DASHBOARD_URL=https://tu-dominio
set BOT_API_KEY=<la misma clave del .env>
set POLL_SECONDS=30
python metaapi_worker.py
```
El worker pide a `GET /api/worker/accounts` el token de MetaApi y la lista de
cuentas operables con sus bots, y abre operaciones por cada una.

## Seguridad / notas importantes
- La **contraseña del broker NO se guarda** en nuestra BD: se manda a MetaApi en
  el momento de conectar y MetaApi la almacena cifrada. Nosotros solo guardamos
  el `metaapi_account_id`.
- `GET /api/worker/accounts` devuelve el `METAAPI_TOKEN`. Está protegido por la
  `BOT_API_KEY`; en producción sirve **siempre sobre HTTPS** y restringe el
  acceso al worker.
- La lógica de entrada actual es básica (abre según `direction` cuando está en
  horario). Tu señal real (indicadores, breakout asiático, etc.) se implementa
  dentro de `open_operation()` en `metaapi_worker.py` o moviéndola al panel.
- **Legal:** operar cuentas de terceros automáticamente suele ser actividad
  regulada. Define el modelo (copy trading opt-in / PAMM / registro) antes de
  pasar de demo a cuentas reales.
```

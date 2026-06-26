# Manual de usuario — EasDashboard (SaaS de EAs)

Guía para **probar y usar** la plataforma. Está escrita para que cualquier
persona pueda levantarla, conectar una cuenta, elegir una estrategia y verla
operar — sin necesidad de programar.

---

## 1. ¿Qué es esto?

Es un **SaaS de robots de trading (EAs)**. La idea:

1. El cliente **conecta su cuenta** de broker (MT4/MT5).
2. **Elige una estrategia** y la configura (símbolos, lotaje, riesgo…).
3. La plataforma **opera por él en la nube** (vía MetaApi Cloud), sin tener
   MetaTrader abierto en ninguna PC.

Cada cliente opera **en su propia cuenta**; un solo motor maneja a todos.

---

## 2. Cómo está armado (las dos piezas)

```
┌──────────────────────────┐         ┌───────────────────────────┐
│   PANEL  (EasDashboard)   │  HTTPS  │   WORKER  (eas-worker)    │
│   Laravel + React         │ ◄─────► │   Python                  │
│                           │ API key │                           │
│ • Login de clientes       │         │ • Cada 30s pregunta al    │
│ • Conectar cuentas        │         │   panel qué cuentas/bots  │
│ • Crear bots / estrategia │         │   hay activos             │
│ • Guarda todo en la BD    │         │ • Corre la ESTRATEGIA     │
└──────────────────────────┘         │ • Abre/gestiona órdenes   │
            │                         │   vía MetaApi Cloud       │
            ▼                         └───────────────┬───────────┘
      Base de datos                                   ▼
                                            ☁️  MetaApi Cloud  →  Broker
```

- **Panel**: donde el cliente hace todo (web). Es el "cerebro de configuración".
- **Worker**: proceso en Python que ejecuta de verdad las estrategias y manda
  las órdenes. Corre aparte (en un servidor o en tu PC).
- **MetaApi Cloud**: servicio externo que conecta con el broker. Por eso **no
  hace falta** tener MetaTrader instalado.

> La lógica de las estrategias vive en el worker (`strategy_*.py`). MetaApi solo
> ejecuta la orden final.

---

## 3. Qué necesitas para probar

- **PHP 8.2+** y **Composer** (para el panel).
- **Node.js** (para compilar la interfaz).
- **Python 3.12+** (para el worker).
- Una **cuenta en [MetaApi Cloud](https://app.metaapi.cloud)** con su **token**.
- Una **cuenta de broker demo** (MT4/MT5) para probar sin dinero real.

---

## 4. Cómo levantar todo (en local)

### 4.1 El panel (carpeta `EasDashboard`)

```powershell
composer install
npm install
npm run dev          # deja esta ventana abierta (compila la interfaz)
```

En **otra** ventana:

```powershell
php artisan migrate  # solo la primera vez
php artisan serve    # el panel queda en http://127.0.0.1:8000
```

En **otra** ventana (¡importante!):

```powershell
php artisan queue:work
```

> ⚠️ El `queue:work` es **obligatorio**: conectar una cuenta de broker lanza un
> trabajo en segundo plano (validación + despliegue en MetaApi). Si no lo corres,
> la cuenta se queda "en validación" para siempre.

Configura el archivo `.env` del panel con tu token de MetaApi y una clave de bot:

```
METAAPI_TOKEN=eyJ...   (tu token de app.metaapi.cloud)
BOT_API_KEY=una-clave-secreta-cualquiera
```

### 4.2 El worker (carpeta `eas-worker`)

```powershell
pip install -r requirements.txt
```

Crea/edita el `.env` del worker con **la misma** `BOT_API_KEY` del panel:

```
DASHBOARD_URL=http://127.0.0.1:8000
BOT_API_KEY=una-clave-secreta-cualquiera   ← idéntica a la del panel
POLL_SECONDS=30
```

Arráncalo:

```powershell
python metaapi_worker.py
```

Deberías ver:

```
... INFO eas-worker iniciado | panel=http://127.0.0.1:8000 | cada 30s | health en :8080
... INFO Servidor de salud escuchando en http://0.0.0.0:8080/ ...
... INFO Ciclo #1: consultando panel ...
```

Para **detenerlo**: `Ctrl+C`.

---

## 5. Cómo usar la plataforma (paso a paso)

1. **Regístrate / inicia sesión** en `http://127.0.0.1:8000`.
2. Ve a **«Cuentas»** → **«Conectar cuenta»** y completa:
   - Nombre, plataforma (MT4/MT5), **login**, **servidor**, **contraseña**,
     región.
   - La contraseña se manda a MetaApi y **no se guarda** en la base de datos.
   - El estado pasa de **«en validación»** a **«lista» (deployed)** en 1-2 min
     (recuerda tener corriendo `php artisan queue:work`).
3. Ve a **«Bots»** → **«Crear bot»**:
   - Ponle nombre, elige la **cuenta de broker**.
   - Escribe los **símbolos** (ej. `EURUSD, XAUUSD`).
   - Elige la **temporalidad** (vela) y la **estrategia**.
   - Ajusta **lotaje, SL, TP, máx. operaciones** y la **gestión de riesgo**.
   - Marca **«Bot activo»**.
4. Guarda. En el siguiente ciclo (≤30s) el worker lo recoge y empieza a evaluar.

---

## 6. Las estrategias disponibles

### a) Simple (dirección fija)
Abre en la dirección que tú elijas (compra / venta), respetando el máximo de
operaciones y el horario. Útil para pruebas o ejecución manual asistida.

### b) Multi-TF Order Flow + IA
Solo abre cuando la **tendencia está alineada** en D1, H8 y H4 (por EMA) **y** el
**order flow** en M5 confirma (vela con volumen alto y cuerpo amplio).
- Parámetros clave: periodo EMA, multiplicador de volumen, cuerpo mínimo.
- Tiene **modo paper** (`live_mode` desactivado): registra la señal pero **no
  abre** — ideal para validar en demo.

### c) Asian Range Breakout (prop firm)
Forma el **rango de la sesión asiática** y, ya en sesión de **Londres o NY**,
opera cuando el precio **rompe** ese rango (con un buffer) **y** hay repunte de
volumen. El SL/TP se calculan **a partir del rango** (con ratio R:R y piso ATR).
- Parámetros clave: horas de cada sesión, buffer de ruptura, multiplicador de
  volumen, ratio TP:SL.

> Nota: de esta estrategia se porta el corazón (dirección + SL/TP). La gestión
> "prop firm" avanzada (límite de pérdida diaria, máx. operaciones por día,
> lotaje por % de riesgo) aún no está activa en el worker; esos campos del panel
> son informativos por ahora.

---

## 7. Cómo SABER que funciona (verificación)

Hay 4 niveles de prueba, de más rápido a más real:

### Nivel 1 — Probar el "cerebro" de las estrategias (sin broker)
En la carpeta `eas-worker`:

```powershell
python test_strategies.py
```

Alimenta a cada estrategia con velas de laboratorio y comprueba que decide bien
(comprar / vender / no operar). Debe terminar con:

```
Resultado: 9 pasaron, 0 fallaron.
TODAS LAS PRUEBAS PASARON - las estrategias deciden correctamente.
```

### Nivel 2 — Ver el worker razonando en los logs
Con el worker corriendo, en cada ciclo verás líneas como:

```
[Mi Bot] XAUUSD: sin señal (order flow no confirma la tendencia).
[Mi Bot] EURUSD: ruptura alcista del rango [LONDON].
[Mi Bot] EURUSD: buy @ 1.0850 (SL 1.0820 / TP 1.0910) -> ...
```

Te dice **qué evaluó y por qué** operó o no.

### Nivel 3 — Modo PAPER (sin arriesgar dinero)
En el bot, deja **`live_mode` desactivado** (estrategia Multi-TF). El worker
mostrará lo que **habría** hecho, sin abrir nada:

```
[Mi Bot] EURUSD: [PAPER] habría abierto buy 0.01 lotes @ 1.0850 (SL.../TP...).
```

### Nivel 4 — Operación real en cuenta DEMO
Activa `live_mode` (o usa Simple/Asian) con una **cuenta demo**. Cuando se cumpla
la condición, el worker abrirá la orden y la verás:
- en los **logs** del worker (`buy @ ... -> TRADE_RETCODE_DONE`),
- en tu **MetaTrader** / en el panel de **MetaApi Cloud**,
- el comentario de la orden será `Eas:<id-del-bot>`.

### Estado de salud del worker
Abre en el navegador `http://127.0.0.1:8080/` — responde un JSON con el estado:

```json
{ "service": "eas-worker", "ok": true, "cycles": 12,
  "accounts": 2, "last_poll": "...", "last_error": null }
```

---

## 8. Seguridad (importante)

- El **token de MetaApi** y la contraseña de la base de datos viven en el `.env`
  del panel. **Nunca** subas el `.env` a git ni lo compartas.
- La `BOT_API_KEY` protege la comunicación panel↔worker. Usa una clave larga en
  producción (no `test-secret-123`).
- Corre **una sola** instancia del worker. Si levantas varias, abrirían órdenes
  duplicadas.
- Prueba **siempre primero en demo / modo paper** antes de operar dinero real.

---

## 9. Problemas comunes

| Síntoma | Causa probable | Solución |
|---|---|---|
| La cuenta queda «en validación» | No está corriendo `php artisan queue:work` | Arráncalo |
| Worker: `401 Unauthorized` | La `BOT_API_KEY` no coincide | Igualar la clave en ambos `.env` |
| Logs del worker en blanco | Salida en buffer | Ya resuelto (`PYTHONUNBUFFERED`); reinicia |
| Página raíz da 404 | El worker no es web | Usa `/` o `/health` del **panel**; el worker expone su estado en `:8080` |
| El bot no abre nada | No se cumple la condición de la estrategia | Mira el motivo en los logs ("sin señal", "fuera de sesión", etc.) |
| `Ctrl+C` no cierra el worker | (Ya resuelto) | Reinicia con la versión actual |

---

## 10. Resumen rápido para el día a día

```
1. php artisan serve          (panel)
2. php artisan queue:work     (para conectar cuentas)
3. npm run dev                (interfaz)
4. python metaapi_worker.py   (motor de trading)
```

Luego: conectar cuenta → crear bot → elegir estrategia → activar → mirar logs.
Para verificar el cerebro en cualquier momento: `python test_strategies.py`.

---

## 11. Deploy en producción (Docker)

Son **dos servicios** independientes, cada uno con su `Dockerfile`:

| Servicio | Carpeta | Qué corre |
|---|---|---|
| **Panel** | `EasDashboard` | Servidor web **+ queue worker** (juntos, vía supervisor) |
| **Worker** | `eas-worker` | El motor de trading (Python) |

> El panel ya **incluye el `queue:work`** dentro del contenedor (supervisor lo
> levanta junto al web). No hay que arrancarlo aparte. Al iniciar, el contenedor
> también migra la base de datos y cachea la config.

### Variables de entorno (se ponen en el hosting, NO en un `.env`)

El `.env` **no** viaja dentro de la imagen (está en `.dockerignore`). En el panel
de tu hosting (Coolify/Railway/Render) define al menos:

**Panel:**
```
APP_KEY=base64:...        (genera con: php artisan key:generate --show)
APP_URL=https://tu-panel.com
DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
METAAPI_TOKEN=eyJ...
BOT_API_KEY=clave-fuerte-y-secreta
QUEUE_CONNECTION=database
```

**Worker:**
```
DASHBOARD_URL=https://tu-panel.com
BOT_API_KEY=la-misma-clave-del-panel
POLL_SECONDS=30
```

### Reglas de oro
- Una **sola** instancia del worker (varias = órdenes duplicadas).
- La `BOT_API_KEY` debe ser **idéntica** en panel y worker.
- Usa **HTTPS** en el panel (el endpoint del worker devuelve el token de MetaApi).
```

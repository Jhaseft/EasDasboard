# Conectar MT5 con EasDashboard

Arquitectura recomendada (la que pediste): **un Expert Advisor (EA) por par**,
corriendo en tu **VPS Linux con MT5**. Cada EA pregunta a tu web y el **servidor
decide** si debe operar según lo que hay en la base de datos.

```
EA en grafico EURUSD ─┐
EA en grafico GBPUSD ─┼─►  GET /api/bots/signal?symbol=XXX  ─►  Panel (BD decide)
EA en grafico XAUUSD ─┘                                          │
        ▲                                                        │
        └──────────────  should_trade / lot / sl / tp  ◄─────────┘
```

El servidor responde `should_trade: true/false`. Si es `false` (bot inactivo o
fuera de horario), el EA no hace nada. Si es `true`, el EA abre la operación con
el lotaje, SL, TP y máximo de operaciones configurados en el panel.

## Archivos

- **`EasDashboardEA.mq5`** ← lo que corres en MT5 (esta es la opción que quieres).
- `runner.py` ← alternativa en Python (solo si prefirieras un proceso externo en
  vez de un EA). Para el caso EA puedes ignorarlo.

## Instalar el EA en tu VPS Linux

1. Copia `EasDashboardEA.mq5` a la carpeta `MQL5/Experts` del terminal MT5
   (en Linux suele estar bajo el prefijo de Wine: `.../drive_c/.../MQL5/Experts`).
   O ábrelo desde MetaEditor → **Archivo > Abrir carpeta de datos**.
2. En **MetaEditor** pulsa **Compilar** (F7). Aparecerá en el Navegador de MT5.
3. **Autoriza tu dominio** (obligatorio para WebRequest):
   MT5 → **Herramientas > Opciones > Asesores Expertos** →
   marca *"Permitir WebRequest para las siguientes URL"* y añade `https://tu-dominio`.
4. Activa el botón **Algo Trading** en la barra superior.

## Usar

Arrastra el EA a un gráfico del par deseado (ej. EURUSD) y configura los inputs:

| Input | Valor |
|-------|-------|
| `ApiBaseUrl` | `https://tu-dominio` (sin `/` final) |
| `ApiKey` | la misma clave de `BOT_API_KEY` en el `.env` del panel |
| `PollSeconds` | cada cuántos segundos consulta (ej. 1–5) |

Repite el arrastre en un gráfico por cada par. **Importante:** el `symbol` que
configuras en el panel debe coincidir con el nombre del par en tu broker
(`EURUSD`, `EURUSD.m`, `XAUUSDm`, etc.).

## Endpoint que usa el EA

```
GET /api/bots/signal?symbol=EURUSD
Header: X-API-Key: <BOT_API_KEY>
```

Respuesta (plana, fácil de parsear en MQL5):

```json
{
  "found": true,
  "should_trade": true,
  "reason": "ok",
  "direction": "buy",
  "lot_size": 0.05,
  "stop_loss_pips": 20,
  "take_profit_pips": 40,
  "max_open_trades": 2,
  "trailing_stop_pips": 15,
  "bot_id": 1
}
```

> Nota: la lógica de entrada es básica (abre según la `direction` configurada
> cuando el servidor dice `should_trade`). El *cuándo* exacto (tu señal/indicador)
> se añade dentro de `PollAndTrade()` en el EA, o moviendo esa decisión al
> servidor en el método `signal()` del controlador.

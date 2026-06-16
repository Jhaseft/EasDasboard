//+------------------------------------------------------------------+
//|                              PropFirmBreakoutEA.mq5               |
//|        Session Breakout — Prop Firm Edition | EDITABLE v3.0      |
//|   Estrategia: Asian Range Breakout + Control DD Diario Estricto  |
//|                                                                  |
//|   EDITABLE: todos los parametros se cargan desde el panel web    |
//|   (EasDashboard) por BotId. El admin los cambia sin recompilar.  |
//+------------------------------------------------------------------+
#property copyright   "EasDashboard"
#property version     "3.00"
#property strict
#property description "Asian Range Breakout para prop firm, parametros editables desde la web."

#include <Trade\Trade.mqh>
#include <Trade\PositionInfo.mqh>
#include <Trade\OrderInfo.mqh>
#include <Trade\HistoryOrderInfo.mqh>
#include <Trade\DealInfo.mqh>

//+------------------------------------------------------------------+
//| INPUTS DE CONEXION (lo unico que se pone en MT5)                 |
//+------------------------------------------------------------------+
input string ApiBaseUrl       = "http://127.0.0.1:8000"; // URL del panel (sin / final)
input string ApiKey           = "test-secret-123";       // Igual a BOT_API_KEY del .env
input int    BotId            = 1;                        // ID del bot en el panel
input int    Config_Refresh_Sec = 15;                    // Cada cuanto recarga parametros de la web
input int    MagicBase        = 1000000;                 // Base del magic (magic = MagicBase + BotId)

//+------------------------------------------------------------------+
//| CONSTANTES FIJAS (no editables)                                 |
//+------------------------------------------------------------------+
#define POINT_TO_PIP 10.0

//+------------------------------------------------------------------+
//| PARAMETROS DE ESTRATEGIA — cargados desde la web                |
//| (inicializados con los mismos defaults que el panel)            |
//+------------------------------------------------------------------+
// Gestion prop firm
double g_max_daily_loss_pct  = 4.0;
double g_risk_per_trade_pct  = 0.5;
int    g_max_daily_trades    = 3;
double g_max_lot_cap         = 0.0;
// Filtro de senal
double g_volume_surge_mult   = 2.0;
// Ciclo de intento / forzado
bool   g_force_trade_cycle   = true;
int    g_attempt_interval_min= 5;
bool   g_auto_relax_filters  = true;
double g_relax_per_attempt   = 0.15;
int    g_max_relax_steps     = 6;
bool   g_force_entry_at_max  = true;
bool   g_trade_sessions_only = true;
// Sesiones
int    g_asian_start_hour    = 0;
int    g_asian_end_hour      = 7;
int    g_london_start_hour   = 8;
int    g_london_end_hour     = 10;
int    g_ny_start_hour       = 15;
int    g_ny_end_hour         = 17;
// R:R y tecnicos
double g_tp_rr_multiplier    = 2.0;
int    g_atr_period          = 14;
double g_atr_sl_floor_mult   = 1.0;
int    g_volume_lookback     = 20;
int    g_max_spread_points   = 60;
double g_breakout_buffer_pips= 2.0;
double g_min_free_margin_pct = 20.0;
bool   g_remove_ea_on_dd     = true;

// Estado de conexion / config
ENUM_TIMEFRAMES g_tf        = PERIOD_M10; // temporalidad del bot (de la web)
string g_botSymbol          = "";
bool   g_active             = false;      // should_trade del servidor
bool   g_loaded             = false;      // hubo al menos una carga correcta
int    g_magic              = 0;
datetime g_lastConfigLoad   = 0;
int    g_timerInterval      = 0;          // intervalo actual del timer (min)
int    g_atrHandlePeriod    = 0;
ENUM_TIMEFRAMES g_atrHandleTf = PERIOD_CURRENT;

//+------------------------------------------------------------------+
//| OBJETOS Y VARIABLES DE LA ESTRATEGIA                            |
//+------------------------------------------------------------------+
CTrade        Trade;
CPositionInfo PositionInfo;
COrderInfo    OrderInfo;
CDealInfo     DealInfo;

double asianHigh       = 0;
double asianLow        = DBL_MAX;
bool   asianRangeReady = false;

double dailyStartBalance = 0;
double dailyMaxLoss      = 0;
bool   tradingHalted     = false;
bool   ddBreached        = false;

datetime lastBarTime     = 0;
int      dailyTradeCount = 0;
datetime lastResetDay    = 0;
int      relaxLevel      = 0;

int handleATR = INVALID_HANDLE;

//+------------------------------------------------------------------+
//| OnInit                                                           |
//+------------------------------------------------------------------+
int OnInit()
{
    if(BotId <= 0)
    { Alert("PropFirmBreakoutEA: define un BotId valido (>0)."); return INIT_FAILED; }

    if(StringFind(Symbol(), "XAU") < 0 && StringFind(Symbol(), "GOLD") < 0)
        Print("AVISO: este EA esta pensado para XAUUSD/GOLD. Simbolo actual: ", Symbol());

    Trade.SetDeviationInPoints(25);
    Trade.SetTypeFilling(ORDER_FILLING_IOC);
    Trade.LogLevel(LOG_LEVEL_ERRORS);

    // Primer intento de cargar config (si la web no responde, sigue e intenta luego)
    if(LoadConfig())
        Print("Config cargada desde la web para bot #", BotId);
    else
        Print("No se pudo cargar config aun. Reintentara. Revisa URL/WebRequest/ApiKey.");

    InitDailyTracking();

    if(g_force_trade_cycle)
    {
        EventSetTimer(MathMax(1, g_attempt_interval_min) * 60);
        g_timerInterval = g_attempt_interval_min;
    }

    PrintFormat("PropFirmBreakoutEA v3.0 | Bot #%d | Simbolo: %s | TF estrategia: %s | Magic: %d",
                BotId, Symbol(), EnumToString(g_tf), g_magic);
    return INIT_SUCCEEDED;
}

void OnDeinit(const int reason)
{
    EventKillTimer();
    if(handleATR != INVALID_HANDLE) IndicatorRelease(handleATR);
    PrintFormat("EA detenido. Razon: %d | DD hoy: %.2f%%", reason, GetCurrentDailyDrawdown());
}

//+------------------------------------------------------------------+
//| OnTick — recarga config + DD + reset + rango asiatico            |
//+------------------------------------------------------------------+
void OnTick()
{
    //--- Recargar parametros de la web cada Config_Refresh_Sec
    if(TimeCurrent() - g_lastConfigLoad >= MathMax(3, Config_Refresh_Sec))
        LoadConfig();

    if(!g_loaded) return;

    //--- BLOQUE 1: Control de Drawdown Diario — PRIORIDAD MAXIMA
    if(!MonitorDailyDrawdown()) return;

    //--- BLOQUE 2: Reset diario
    CheckDailyReset();

    //--- BLOQUE 3: Logica por vela (construir rango asiatico)
    datetime currentBar = iTime(Symbol(), g_tf, 0);
    if(currentBar == lastBarTime) return;
    lastBarTime = currentBar;

    MqlDateTime timeStruct;
    TimeToStruct(TimeCurrent(), timeStruct);
    BuildAsianRange(timeStruct.hour);

    //--- Modo clasico (sin ciclo): intentar en cada vela nueva
    if(!g_force_trade_cycle)
    {
        relaxLevel = 0;
        AttemptEntry();
    }
}

//+------------------------------------------------------------------+
//| OnTimer — Ciclo de intento cada X minutos (modo forzado)         |
//+------------------------------------------------------------------+
void OnTimer()
{
    if(!g_loaded || !g_force_trade_cycle) return;

    if(ddBreached || tradingHalted) return;
    if(dailyTradeCount >= g_max_daily_trades) { relaxLevel = 0; return; }
    if(HasOpenPosition()) { relaxLevel = 0; return; }

    bool traded = AttemptEntry();
    if(traded)
        relaxLevel = 0;
    else if(g_auto_relax_filters && relaxLevel < g_max_relax_steps)
    {
        relaxLevel++;
        PrintFormat("CICLO | Sin ruptura. Relajando -> nivel %d/%d (factor %.2f).",
                    relaxLevel, g_max_relax_steps, RelaxFactor());
    }
}

//+------------------------------------------------------------------+
//| CARGAR CONFIG DESDE LA WEB                                       |
//+------------------------------------------------------------------+
bool LoadConfig()
{
    g_lastConfigLoad = TimeCurrent();

    string url = ApiBaseUrl + "/api/bots/" + (string)BotId + "/signal";
    string headers = "X-API-Key: " + ApiKey + "\r\n";
    char   post[];
    char   result[];
    string rh;

    ResetLastError();
    int status = WebRequest("GET", url, headers, 5000, post, result, rh);
    if(status == -1)
    {
        PrintFormat("WebRequest fallo (%d). Autoriza %s en Opciones > Asesores Expertos > WebRequest.",
                    GetLastError(), ApiBaseUrl);
        return false;
    }

    string body = CharArrayToString(result, 0, WHOLE_ARRAY, CP_UTF8);

    if(!JsonBool(body, "found")) { g_active = false; return false; }

    g_active    = JsonBool(body, "should_trade");
    g_botSymbol = JsonString(body, "symbol");
    g_tf        = TimeframeFromString(JsonString(body, "timeframe"));

    if(g_botSymbol != "" && StringFind(Symbol(), g_botSymbol) < 0 && StringFind(g_botSymbol, Symbol()) < 0)
        PrintFormat("AVISO: grafico %s pero bot #%d opera %s. Pon el EA en un grafico de %s.",
                    Symbol(), BotId, g_botSymbol, g_botSymbol);

    //--- Parametros de estrategia
    g_max_daily_loss_pct   = JsonNumber(body, "max_daily_loss_pct");
    g_risk_per_trade_pct   = JsonNumber(body, "risk_per_trade_pct");
    g_max_daily_trades     = (int)JsonNumber(body, "max_daily_trades");
    g_max_lot_cap          = JsonNumber(body, "max_lot_cap");
    g_volume_surge_mult    = JsonNumber(body, "volume_surge_multiplier");
    g_force_trade_cycle    = JsonBool(body, "force_trade_cycle");
    g_attempt_interval_min = (int)JsonNumber(body, "attempt_interval_min");
    g_auto_relax_filters   = JsonBool(body, "auto_relax_filters");
    g_relax_per_attempt    = JsonNumber(body, "relax_per_attempt");
    g_max_relax_steps      = (int)JsonNumber(body, "max_relax_steps");
    g_force_entry_at_max   = JsonBool(body, "force_entry_at_max");
    g_trade_sessions_only  = JsonBool(body, "trade_sessions_only");
    g_asian_start_hour     = (int)JsonNumber(body, "asian_start_hour");
    g_asian_end_hour       = (int)JsonNumber(body, "asian_end_hour");
    g_london_start_hour    = (int)JsonNumber(body, "london_start_hour");
    g_london_end_hour      = (int)JsonNumber(body, "london_end_hour");
    g_ny_start_hour        = (int)JsonNumber(body, "ny_start_hour");
    g_ny_end_hour          = (int)JsonNumber(body, "ny_end_hour");
    g_tp_rr_multiplier     = JsonNumber(body, "tp_rr_multiplier");
    g_atr_period           = (int)JsonNumber(body, "atr_period");
    g_atr_sl_floor_mult    = JsonNumber(body, "atr_sl_floor_mult");
    g_volume_lookback      = (int)JsonNumber(body, "volume_lookback");
    g_max_spread_points    = (int)JsonNumber(body, "max_spread_points");
    g_breakout_buffer_pips = JsonNumber(body, "breakout_buffer_pips");
    g_min_free_margin_pct  = JsonNumber(body, "min_free_margin_pct");
    g_remove_ea_on_dd      = JsonBool(body, "remove_ea_on_dd");

    //--- Saneo minimo
    if(g_atr_period < 1) g_atr_period = 14;
    if(g_max_daily_trades < 1) g_max_daily_trades = 1;
    if(g_attempt_interval_min < 1) g_attempt_interval_min = 1;

    //--- Magic
    g_magic = MagicBase + BotId;
    Trade.SetExpertMagicNumber(g_magic);

    //--- Recrear handle ATR si cambio periodo o temporalidad
    if(handleATR == INVALID_HANDLE || g_atr_period != g_atrHandlePeriod || g_tf != g_atrHandleTf)
    {
        if(handleATR != INVALID_HANDLE) IndicatorRelease(handleATR);
        handleATR = iATR(Symbol(), g_tf, g_atr_period);
        g_atrHandlePeriod = g_atr_period;
        g_atrHandleTf     = g_tf;
    }

    //--- Re-armar timer si cambio el intervalo
    if(g_force_trade_cycle && g_attempt_interval_min != g_timerInterval)
    {
        EventKillTimer();
        EventSetTimer(g_attempt_interval_min * 60);
        g_timerInterval = g_attempt_interval_min;
    }

    g_loaded = true;
    return true;
}

ENUM_TIMEFRAMES TimeframeFromString(const string tf)
{
    if(tf == "M1")  return PERIOD_M1;
    if(tf == "M5")  return PERIOD_M5;
    if(tf == "M15") return PERIOD_M15;
    if(tf == "M30") return PERIOD_M30;
    if(tf == "H1")  return PERIOD_H1;
    if(tf == "H4")  return PERIOD_H4;
    if(tf == "D1")  return PERIOD_D1;
    return PERIOD_M10;
}

//+------------------------------------------------------------------+
//| FACTOR DE RELAJACION                                            |
//+------------------------------------------------------------------+
double RelaxFactor() { return MathMax(0.1, 1.0 - g_relax_per_attempt * relaxLevel); }
bool   AtMaxRelax()  { return (g_auto_relax_filters && relaxLevel >= g_max_relax_steps); }

//+------------------------------------------------------------------+
//| INTENTAR ENTRADA — Breakout con relajacion                      |
//+------------------------------------------------------------------+
bool AttemptEntry()
{
    if(!g_active) return false;          // el servidor dice que no opere
    if(tradingHalted || ddBreached) return false;
    if(dailyTradeCount >= g_max_daily_trades) return false;
    if(!PreTradeChecks()) return false;
    if(HasOpenPosition()) return false;
    if(!asianRangeReady) return false;

    MqlDateTime ts; TimeToStruct(TimeCurrent(), ts);
    int hour = ts.hour;
    bool inLondon = (hour >= g_london_start_hour && hour < g_london_end_hour);
    bool inNY     = (hour >= g_ny_start_hour     && hour < g_ny_end_hour);
    bool inSession = inLondon || inNY;
    if(g_trade_sessions_only && !inSession) return false;
    string session = inLondon ? "LONDON" : (inNY ? "NY" : "OFF");

    double ask     = SymbolInfoDouble(Symbol(), SYMBOL_ASK);
    double bid     = SymbolInfoDouble(Symbol(), SYMBOL_BID);
    double pipSize = SymbolInfoDouble(Symbol(), SYMBOL_POINT) * POINT_TO_PIP;
    double effBuf  = g_breakout_buffer_pips * pipSize * RelaxFactor();
    double closeBar= iClose(Symbol(), g_tf, 1);

    bool breakUp   = (closeBar > asianHigh + effBuf);
    bool breakDown = (closeBar < asianLow  - effBuf);

    if(g_auto_relax_filters && relaxLevel >= 1 && !breakUp && !breakDown)
    {
        if(closeBar > asianHigh)      breakUp   = true;
        else if(closeBar < asianLow)  breakDown = true;
    }

    bool forced = false;
    if(!breakUp && !breakDown && g_force_entry_at_max && AtMaxRelax())
    {
        double mid = (asianHigh + asianLow) / 2.0;
        if(closeBar >= mid) breakUp = true; else breakDown = true;
        forced = true;
    }

    if(!breakUp && !breakDown) return false;
    if(!forced && !VolumeFilterPassed()) return false;

    int    digits   = (int)SymbolInfoInteger(Symbol(), SYMBOL_DIGITS);
    double atr      = GetATR(1);
    double atrFloor = atr * g_atr_sl_floor_mult;

    if(breakUp)
    {
        double sl = asianLow - effBuf;
        if((ask - sl) < atrFloor && atrFloor > 0) sl = ask - atrFloor;
        sl = NormalizeDouble(sl, digits);
        double slDist = ask - sl;
        double tp = NormalizeDouble(ask + slDist * g_tp_rr_multiplier, digits);
        if(!ValidateLevels(ORDER_TYPE_BUY, sl, tp, ask)) return false;
        double lots = ComputeLot(slDist);
        if(lots <= 0) { Print("Lote 0 (margen/limites)."); return false; }

        PrintFormat("%s COMPRA [%s] | Ask:%.5f | Rango:%.5f-%.5f | SL:%.5f | TP:%.5f | Lots:%.2f | Relax:%d",
                    forced ? "FORZADA" : "BREAKOUT", session, ask, asianLow, asianHigh, sl, tp, lots, relaxLevel);
        if(Trade.Buy(lots, Symbol(), ask, sl, tp, "PF_BUY_" + session))
        {
            PrintFormat("Compra ejecutada. Ticket:%d | Ops hoy:%d/%d",
                        (int)Trade.ResultOrder(), dailyTradeCount + 1, g_max_daily_trades);
            dailyTradeCount++;
            return true;
        }
        PrintFormat("Error COMPRA: %d - %s", (int)Trade.ResultRetcode(), Trade.ResultComment());
        return false;
    }
    else
    {
        double sl = asianHigh + effBuf;
        if((sl - bid) < atrFloor && atrFloor > 0) sl = bid + atrFloor;
        sl = NormalizeDouble(sl, digits);
        double slDist = sl - bid;
        double tp = NormalizeDouble(bid - slDist * g_tp_rr_multiplier, digits);
        if(!ValidateLevels(ORDER_TYPE_SELL, sl, tp, bid)) return false;
        double lots = ComputeLot(slDist);
        if(lots <= 0) { Print("Lote 0 (margen/limites)."); return false; }

        PrintFormat("%s VENTA [%s] | Bid:%.5f | Rango:%.5f-%.5f | SL:%.5f | TP:%.5f | Lots:%.2f | Relax:%d",
                    forced ? "FORZADA" : "BREAKOUT", session, bid, asianLow, asianHigh, sl, tp, lots, relaxLevel);
        if(Trade.Sell(lots, Symbol(), bid, sl, tp, "PF_SELL_" + session))
        {
            PrintFormat("Venta ejecutada. Ticket:%d | Ops hoy:%d/%d",
                        (int)Trade.ResultOrder(), dailyTradeCount + 1, g_max_daily_trades);
            dailyTradeCount++;
            return true;
        }
        PrintFormat("Error VENTA: %d - %s", (int)Trade.ResultRetcode(), Trade.ResultComment());
        return false;
    }
}

//+------------------------------------------------------------------+
//| MONITOR DRAWDOWN DIARIO                                          |
//+------------------------------------------------------------------+
bool MonitorDailyDrawdown()
{
    if(ddBreached) return false;

    double currentDD = GetCurrentDailyDrawdown();
    if(currentDD >= g_max_daily_loss_pct)
    {
        PrintFormat("DD DIARIO ALCANZADO: %.2f%% (limite: %.1f%%). Cierre de emergencia...",
                    currentDD, g_max_daily_loss_pct);
        EmergencyCloseAllPositions();
        CancelAllPendingOrders();
        tradingHalted = true;
        ddBreached    = true;
        PrintFormat("REPORTE DD | Balance inicio: %.2f | Perdida: %.2f%% | TRADING BLOQUEADO",
                    dailyStartBalance, currentDD);
        if(g_remove_ea_on_dd) ExpertRemove();
        return false;
    }
    return true;
}

double GetCurrentDailyDrawdown()
{
    if(dailyStartBalance <= 0) return 0;

    double closedLoss   = GetDailyClosedPnL();
    double floatingLoss = 0;
    for(int i = PositionsTotal() - 1; i >= 0; i--)
    {
        if(PositionInfo.SelectByIndex(i))
            if(PositionInfo.Symbol() == Symbol() && PositionInfo.Magic() == g_magic)
            {
                double profit = PositionInfo.Profit() + PositionInfo.Swap();
                if(profit < 0) floatingLoss += profit;
            }
    }
    double totalLoss = MathAbs(MathMin(closedLoss + floatingLoss, 0));
    return (totalLoss / dailyStartBalance) * 100.0;
}

double GetDailyClosedPnL()
{
    double pnl = 0;
    MqlDateTime todayStruct;
    TimeToStruct(TimeCurrent(), todayStruct);
    todayStruct.hour = 0; todayStruct.min = 0; todayStruct.sec = 0;
    datetime dayStart = StructToTime(todayStruct);

    if(!HistorySelect(dayStart, TimeCurrent())) return 0;

    int totalDeals = HistoryDealsTotal();
    for(int i = 0; i < totalDeals; i++)
    {
        ulong ticket = HistoryDealGetTicket(i);
        if(ticket == 0) continue;
        if(HistoryDealGetString(ticket, DEAL_SYMBOL) != Symbol()) continue;
        if((int)HistoryDealGetInteger(ticket, DEAL_MAGIC) != g_magic) continue;
        if((ENUM_DEAL_ENTRY)HistoryDealGetInteger(ticket, DEAL_ENTRY) != DEAL_ENTRY_OUT) continue;
        pnl += HistoryDealGetDouble(ticket, DEAL_PROFIT);
        pnl += HistoryDealGetDouble(ticket, DEAL_SWAP);
        pnl += HistoryDealGetDouble(ticket, DEAL_COMMISSION);
    }
    return pnl;
}

//+------------------------------------------------------------------+
//| TRACKING DIARIO                                                  |
//+------------------------------------------------------------------+
void InitDailyTracking()
{
    dailyStartBalance = AccountInfoDouble(ACCOUNT_BALANCE);
    dailyMaxLoss      = dailyStartBalance * (g_max_daily_loss_pct / 100.0);
    dailyTradeCount   = 0;
    tradingHalted     = false;
    ddBreached        = false;
    asianRangeReady   = false;
    asianHigh         = 0;
    asianLow          = DBL_MAX;
    relaxLevel        = 0;
    lastResetDay      = iTime(Symbol(), PERIOD_D1, 0);

    PrintFormat("RESET DIARIO | Balance: %.2f %s | Limite DD: %.2f (%.1f%%)",
                dailyStartBalance, AccountInfoString(ACCOUNT_CURRENCY),
                dailyMaxLoss, g_max_daily_loss_pct);
}

void CheckDailyReset()
{
    datetime currentDayBar = iTime(Symbol(), PERIOD_D1, 0);
    if(currentDayBar != lastResetDay && currentDayBar > 0)
    {
        Print("Nuevo dia detectado. Reiniciando contadores diarios...");
        InitDailyTracking();
    }
}

//+------------------------------------------------------------------+
//| RANGO ASIATICO                                                   |
//+------------------------------------------------------------------+
void BuildAsianRange(int currentHour)
{
    if(currentHour >= g_asian_start_hour && currentHour < g_asian_end_hour)
    {
        double high1 = iHigh(Symbol(), g_tf, 1);
        double low1  = iLow(Symbol(), g_tf, 1);
        if(high1 > asianHigh)            { asianHigh = high1; asianRangeReady = false; }
        if(low1 > 0 && low1 < asianLow)  { asianLow  = low1;  asianRangeReady = false; }
    }

    if(currentHour == g_asian_end_hour && !asianRangeReady)
    {
        if(asianHigh > 0 && asianLow < DBL_MAX && asianHigh > asianLow)
        {
            asianRangeReady = true;
            double rangePips = (asianHigh - asianLow) /
                               (SymbolInfoDouble(Symbol(), SYMBOL_POINT) * POINT_TO_PIP);
            PrintFormat("RANGO ASIATICO | High:%.5f | Low:%.5f | Amplitud:%.1f pips",
                        asianHigh, asianLow, rangePips);
        }
        else
            Print("Rango asiatico invalido (posible finde o datos faltantes).");
    }
}

//+------------------------------------------------------------------+
//| FILTRO DE VOLUMEN                                                |
//+------------------------------------------------------------------+
bool VolumeFilterPassed()
{
    double volCurrent = GetVolume(1);
    double avgVolume  = GetAverageVolume(g_volume_lookback, 2);
    if(avgVolume <= 0) { Print("Volumen promedio invalido. Senal bloqueada."); return false; }

    double effMult   = 1.0 + (g_volume_surge_mult - 1.0) * RelaxFactor();
    double threshold = avgVolume * effMult;
    if(volCurrent < threshold)
    {
        PrintFormat("Volumen %.0f < umbral %.0f (mult %.2f, relax %d). Ignorada.",
                    volCurrent, threshold, effMult, relaxLevel);
        return false;
    }
    return true;
}

//+------------------------------------------------------------------+
//| CALCULAR LOTE — Por % de riesgo                                 |
//+------------------------------------------------------------------+
double ComputeLot(double slDistancePrice)
{
    if(slDistancePrice <= 0) return 0;

    double lotStep = SymbolInfoDouble(Symbol(), SYMBOL_VOLUME_STEP);
    double minLot  = SymbolInfoDouble(Symbol(), SYMBOL_VOLUME_MIN);
    double maxLot  = SymbolInfoDouble(Symbol(), SYMBOL_VOLUME_MAX);
    if(g_max_lot_cap > 0) maxLot = MathMin(maxLot, g_max_lot_cap);

    double base       = MathMin(AccountInfoDouble(ACCOUNT_BALANCE), AccountInfoDouble(ACCOUNT_EQUITY));
    double riskAmount = base * (g_risk_per_trade_pct / 100.0);
    double tickValue  = SymbolInfoDouble(Symbol(), SYMBOL_TRADE_TICK_VALUE);
    double tickSize   = SymbolInfoDouble(Symbol(), SYMBOL_TRADE_TICK_SIZE);
    if(tickValue <= 0 || tickSize <= 0) return 0;

    double riskPerLot = (slDistancePrice / tickSize) * tickValue;
    if(riskPerLot <= 0) return 0;

    double lots = riskAmount / riskPerLot;
    lots = MathFloor(lots / lotStep) * lotStep;
    lots = MathMax(minLot, MathMin(maxLot, lots));
    return AdjustLotForMargin(lots);
}

double AdjustLotForMargin(double lots)
{
    double lotStep = SymbolInfoDouble(Symbol(), SYMBOL_VOLUME_STEP);
    double minLot  = SymbolInfoDouble(Symbol(), SYMBOL_VOLUME_MIN);
    double price   = SymbolInfoDouble(Symbol(), SYMBOL_ASK);
    double freeUse = AccountInfoDouble(ACCOUNT_MARGIN_FREE) * 0.8;
    double marginReq = 0;
    int guard = 0;
    while(lots >= minLot && guard < 200)
    {
        if(OrderCalcMargin(ORDER_TYPE_BUY, Symbol(), lots, price, marginReq))
            if(marginReq <= freeUse) return NormalizeDouble(lots, 2);
        lots = NormalizeDouble(lots - lotStep, 2);
        guard++;
    }
    return 0;
}

//+------------------------------------------------------------------+
//| VALIDAR NIVELES                                                  |
//+------------------------------------------------------------------+
bool ValidateLevels(ENUM_ORDER_TYPE type, double sl, double tp, double price)
{
    long   stopLvlPts = SymbolInfoInteger(Symbol(), SYMBOL_TRADE_STOPS_LEVEL);
    double stopLvlPrc = stopLvlPts * SymbolInfoDouble(Symbol(), SYMBOL_POINT);
    if(type == ORDER_TYPE_BUY)
    {
        if((price - sl) < stopLvlPrc) { PrintFormat("SL compra cercano. StopLevel %d pts.", stopLvlPts); return false; }
        if((tp - price) < stopLvlPrc) { PrintFormat("TP compra cercano. StopLevel %d pts.", stopLvlPts); return false; }
    }
    else
    {
        if((sl - price) < stopLvlPrc) { PrintFormat("SL venta cercano. StopLevel %d pts.", stopLvlPts); return false; }
        if((price - tp) < stopLvlPrc) { PrintFormat("TP venta cercano. StopLevel %d pts.", stopLvlPts); return false; }
    }
    return true;
}

//+------------------------------------------------------------------+
//| CIERRE DE EMERGENCIA / CANCELAR PENDIENTES                      |
//+------------------------------------------------------------------+
void EmergencyCloseAllPositions()
{
    int closed = 0;
    for(int i = PositionsTotal() - 1; i >= 0; i--)
    {
        if(PositionInfo.SelectByIndex(i))
            if(PositionInfo.Symbol() == Symbol() && PositionInfo.Magic() == g_magic)
            {
                ulong ticket = PositionInfo.Ticket();
                if(Trade.PositionClose(ticket, -1)) closed++;
                else
                {
                    Trade.SetDeviationInPoints(200);
                    if(Trade.PositionClose(ticket, -1)) closed++;
                    else PrintFormat("FALLO: no se cerro #%d. Retcode:%d. INTERVENCION MANUAL.",
                                     (int)ticket, (int)Trade.ResultRetcode());
                    Trade.SetDeviationInPoints(25);
                }
            }
    }
    PrintFormat("Cierre de emergencia: %d posicion(es).", closed);
}

void CancelAllPendingOrders()
{
    int cancelled = 0;
    for(int i = OrdersTotal() - 1; i >= 0; i--)
    {
        if(OrderInfo.SelectByIndex(i))
            if(OrderInfo.Symbol() == Symbol() && OrderInfo.Magic() == g_magic)
                if(Trade.OrderDelete(OrderInfo.Ticket())) cancelled++;
    }
    if(cancelled > 0) PrintFormat("%d orden(es) pendiente(s) cancelada(s).", cancelled);
}

//+------------------------------------------------------------------+
//| PRE-TRADE CHECKS                                                 |
//+------------------------------------------------------------------+
bool PreTradeChecks()
{
    if((ENUM_SYMBOL_TRADE_MODE)SymbolInfoInteger(Symbol(), SYMBOL_TRADE_MODE) != SYMBOL_TRADE_MODE_FULL)
    { Print("Simbolo no disponible para trading completo."); return false; }

    if(!AccountInfoInteger(ACCOUNT_TRADE_ALLOWED) || !AccountInfoInteger(ACCOUNT_TRADE_EXPERT))
    { Print("Trading algoritmico no habilitado."); return false; }

    long spread = SymbolInfoInteger(Symbol(), SYMBOL_SPREAD);
    if(spread > g_max_spread_points)
    { PrintFormat("Spread elevado: %d pts (max %d). Bloqueado.", spread, g_max_spread_points); return false; }

    double balance = AccountInfoDouble(ACCOUNT_BALANCE);
    double free    = AccountInfoDouble(ACCOUNT_MARGIN_FREE);
    if(balance > 0 && (free / balance) * 100.0 < g_min_free_margin_pct)
    { PrintFormat("Margen libre %.1f%% (min %.1f%%)", (free / balance) * 100.0, g_min_free_margin_pct); return false; }

    return true;
}

//+------------------------------------------------------------------+
//| POSICIONES / INDICADORES / VOLUMEN                              |
//+------------------------------------------------------------------+
bool HasOpenPosition()
{
    for(int i = PositionsTotal() - 1; i >= 0; i--)
        if(PositionInfo.SelectByIndex(i))
            if(PositionInfo.Symbol() == Symbol() && PositionInfo.Magic() == g_magic)
                return true;
    return false;
}

double GetATR(int shift)
{
    if(handleATR == INVALID_HANDLE) return 0;
    double buf[];
    ArraySetAsSeries(buf, true);
    if(CopyBuffer(handleATR, 0, shift, 1, buf) <= 0) return 0;
    return buf[0];
}

double GetVolume(int shift)
{
    long vol[];
    ArraySetAsSeries(vol, true);
    if(CopyTickVolume(Symbol(), g_tf, shift, 1, vol) <= 0) return 0;
    return (double)vol[0];
}

double GetAverageVolume(int count, int startShift)
{
    if(count < 1) count = 1;
    long vol[];
    ArraySetAsSeries(vol, true);
    int copied = CopyTickVolume(Symbol(), g_tf, startShift, count, vol);
    if(copied < 1) return 0;
    double sum = 0;
    for(int i = 0; i < copied; i++) sum += (double)vol[i];
    return sum / copied;
}

//+------------------------------------------------------------------+
//| MINI-PARSER JSON                                                 |
//+------------------------------------------------------------------+
string JsonRaw(const string json, const string key)
{
    string needle = "\"" + key + "\"";
    int p = StringFind(json, needle);
    if(p < 0) return "";
    p = StringFind(json, ":", p);
    if(p < 0) return "";
    p++;
    while(p < StringLen(json) && StringGetCharacter(json, p) == ' ') p++;
    int start = p, end = p;
    if(StringGetCharacter(json, p) == '"')
    {
        start = p + 1;
        end = StringFind(json, "\"", start);
    }
    else
    {
        while(end < StringLen(json))
        {
            ushort c = StringGetCharacter(json, end);
            if(c == ',' || c == '}') break;
            end++;
        }
    }
    if(end < start) return "";
    return StringSubstr(json, start, end - start);
}

string JsonString(const string json, const string key) { return JsonRaw(json, key); }

double JsonNumber(const string json, const string key)
{
    string v = JsonRaw(json, key);
    if(v == "" || v == "null") return 0.0;
    return StringToDouble(v);
}

bool JsonBool(const string json, const string key) { return (JsonRaw(json, key) == "true"); }
//+------------------------------------------------------------------+

//+------------------------------------------------------------------+
//|                                              EasDashboardEA.mq5   |
//|   EA por par: pregunta a la web si debe operar y ejecuta.        |
//|   La DECISION (operar o no) la toma el servidor segun la BD.    |
//|   La temporalidad, trailing y riesgo se leen de la web.         |
//+------------------------------------------------------------------+
#property copyright "EasDashboard"
#property version   "1.10"
#property strict

#include <Trade/Trade.mqh>

//--- Parametros configurables desde MT5 ---
input string  ApiBaseUrl     = "http://127.0.0.1:8000"; // URL del panel (sin / al final)
input string  ApiKey         = "test-secret-123";       // Igual a BOT_API_KEY del .env
input int     BotId          = 0;                        // ID del bot en el panel (recomendado). 0 = por simbolo
input int     PollSeconds    = 2;                        // Cada cuanto consulta la web
input int     Slippage       = 20;                       // Desviacion permitida (puntos)
input int     MagicBase      = 1000000;                  // Base para el magic number

CTrade   trade;
datetime lastBarTime = 0;   // hora de la ultima vela en la que abrimos (gate por temporalidad)

//+------------------------------------------------------------------+
int OnInit()
{
   EventSetTimer(MathMax(1, PollSeconds));
   Print("EasDashboardEA iniciado para ", _Symbol,
         ". IMPORTANTE: agrega ", ApiBaseUrl,
         " en Herramientas > Opciones > Asesores Expertos > WebRequest.");
   return(INIT_SUCCEEDED);
}

void OnDeinit(const int reason) { EventKillTimer(); }

void OnTimer() { PollAndTrade(); }

//+------------------------------------------------------------------+
//| Convierte "M15", "H1", etc. a ENUM_TIMEFRAMES                    |
//+------------------------------------------------------------------+
ENUM_TIMEFRAMES TimeframeFromString(const string tf)
{
   if(tf == "M1")  return PERIOD_M1;
   if(tf == "M5")  return PERIOD_M5;
   if(tf == "M10") return PERIOD_M10;
   if(tf == "M15") return PERIOD_M15;
   if(tf == "M30") return PERIOD_M30;
   if(tf == "H1")  return PERIOD_H1;
   if(tf == "H4")  return PERIOD_H4;
   if(tf == "D1")  return PERIOD_D1;
   return PERIOD_H1;
}

//+------------------------------------------------------------------+
//| Consulta la web y actua segun la respuesta                       |
//+------------------------------------------------------------------+
void PollAndTrade()
{
   // Recomendado: identificar por BotId (permite varios bots en el mismo par).
   // Si BotId = 0, se usa el modo antiguo por simbolo.
   string url;
   if(BotId > 0)
      url = ApiBaseUrl + "/api/bots/" + (string)BotId + "/signal";
   else
      url = ApiBaseUrl + "/api/bots/signal?symbol=" + _Symbol;

   string headers = "X-API-Key: " + ApiKey + "\r\n";
   char   post[];
   char   result[];
   string resultHeaders;

   ResetLastError();
   int status = WebRequest("GET", url, headers, 5000, post, result, resultHeaders);

   if(status == -1)
   {
      Print("WebRequest fallo (", GetLastError(),
            "). Falta autorizar la URL en Opciones > Asesores Expertos > WebRequest.");
      return;
   }

   string body = CharArrayToString(result, 0, WHOLE_ARRAY, CP_UTF8);

   bool found       = JsonBool(body, "found");
   bool shouldTrade = JsonBool(body, "should_trade");

   // Aviso si el grafico no coincide con el simbolo del bot (config en par equivocado).
   string botSymbol = JsonString(body, "symbol");
   if(BotId > 0 && botSymbol != "" &&
      StringFind(_Symbol, botSymbol) < 0 && StringFind(botSymbol, _Symbol) < 0)
   {
      Print("AVISO: este grafico es ", _Symbol, " pero el bot #", BotId,
            " opera ", botSymbol, ". Pon el EA en un grafico de ", botSymbol, ".");
   }

   int  botId     = (int)JsonNumber(body, "bot_id");
   long magic     = MagicBase + botId;
   trade.SetExpertMagicNumber(magic);
   trade.SetDeviationInPoints(Slippage);

   int trailPips = (int)JsonNumber(body, "trailing_stop_pips");

   // El trailing se gestiona SIEMPRE que haya posiciones, aunque el bot ya no
   // deba abrir nuevas (asi protege las operaciones vivas).
   if(found && trailPips > 0)
      ManageTrailing(magic, trailPips);

   if(!found || !shouldTrade)
      return; // El servidor dice que no operemos nuevas.

   string tfStr     = JsonString(body, "timeframe");
   string direction = JsonString(body, "direction");
   double lot       = JsonNumber(body, "lot_size");
   int    slPips    = (int)JsonNumber(body, "stop_loss_pips");
   int    tpPips    = (int)JsonNumber(body, "take_profit_pips");
   int    maxTrades = (int)JsonNumber(body, "max_open_trades");
   double riskPct   = JsonNumber(body, "risk_percent");

   // --- Gate por temporalidad: una operacion como maximo por vela ---
   ENUM_TIMEFRAMES tf = TimeframeFromString(tfStr);
   datetime barTime = iTime(_Symbol, tf, 0);
   if(barTime == lastBarTime)
      return; // Ya operamos en esta vela.

   if(CountOpenPositions(magic) >= maxTrades)
      return; // Ya alcanzo el maximo de operaciones.

   bool doBuy  = (direction == "buy"  || direction == "both");
   bool doSell = (direction == "sell");

   double point  = SymbolInfoDouble(_Symbol, SYMBOL_POINT);
   int    digits = (int)SymbolInfoInteger(_Symbol, SYMBOL_DIGITS);
   double factor = (digits == 3 || digits == 5) ? 10.0 : 1.0;
   double slDist = slPips * point * factor;
   double tpDist = tpPips * point * factor;

   // Lotaje: si hay riesgo %, se calcula; si no, lote fijo del panel.
   double volume = lot;
   if(riskPct > 0 && slDist > 0)
      volume = LotByRisk(riskPct, slDist);

   bool opened = false;
   if(doBuy)
   {
      double ask = SymbolInfoDouble(_Symbol, SYMBOL_ASK);
      double sl  = slPips > 0 ? ask - slDist : 0.0;
      double tp  = tpPips > 0 ? ask + tpDist : 0.0;
      opened = trade.Buy(volume, _Symbol, ask, sl, tp, "EasDashboard");
      Print(_Symbol, opened ? ": BUY ejecutada vol=" + DoubleToString(volume,2)
                             : ": fallo BUY " + (string)trade.ResultRetcode() + " " + trade.ResultComment());
   }
   else if(doSell)
   {
      double bid = SymbolInfoDouble(_Symbol, SYMBOL_BID);
      double sl  = slPips > 0 ? bid + slDist : 0.0;
      double tp  = tpPips > 0 ? bid - tpDist : 0.0;
      opened = trade.Sell(volume, _Symbol, bid, sl, tp, "EasDashboard");
      Print(_Symbol, opened ? ": SELL ejecutada vol=" + DoubleToString(volume,2)
                             : ": fallo SELL " + (string)trade.ResultRetcode() + " " + trade.ResultComment());
   }

   if(opened)
      lastBarTime = barTime; // marca la vela para no repetir hasta la proxima.
}

//+------------------------------------------------------------------+
//| Lotaje a partir del riesgo % del balance y la distancia del SL   |
//+------------------------------------------------------------------+
double LotByRisk(double riskPct, double slDist)
{
   double balance   = AccountInfoDouble(ACCOUNT_BALANCE);
   double riskMoney = balance * riskPct / 100.0;

   double tickValue = SymbolInfoDouble(_Symbol, SYMBOL_TRADE_TICK_VALUE);
   double tickSize  = SymbolInfoDouble(_Symbol, SYMBOL_TRADE_TICK_SIZE);
   if(tickValue <= 0 || tickSize <= 0 || slDist <= 0)
      return NormalizeLot(0.01);

   // Perdida por 1 lote si toca el SL.
   double lossPerLot = (slDist / tickSize) * tickValue;
   if(lossPerLot <= 0)
      return NormalizeLot(0.01);

   double lots = riskMoney / lossPerLot;
   return NormalizeLot(lots);
}

double NormalizeLot(double lots)
{
   double minLot = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MIN);
   double maxLot = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MAX);
   double step   = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_STEP);
   if(step <= 0) step = 0.01;
   lots = MathFloor(lots / step) * step;
   if(lots < minLot) lots = minLot;
   if(lots > maxLot) lots = maxLot;
   return lots;
}

//+------------------------------------------------------------------+
//| Trailing stop: mueve el SL detras del precio a trailPips         |
//+------------------------------------------------------------------+
void ManageTrailing(long magic, int trailPips)
{
   double point  = SymbolInfoDouble(_Symbol, SYMBOL_POINT);
   int    digits = (int)SymbolInfoInteger(_Symbol, SYMBOL_DIGITS);
   double factor = (digits == 3 || digits == 5) ? 10.0 : 1.0;
   double dist   = trailPips * point * factor;

   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0) continue;
      if(PositionGetString(POSITION_SYMBOL) != _Symbol) continue;
      if(PositionGetInteger(POSITION_MAGIC) != magic) continue;

      long   type   = PositionGetInteger(POSITION_TYPE);
      double openP  = PositionGetDouble(POSITION_PRICE_OPEN);
      double curSL  = PositionGetDouble(POSITION_SL);
      double curTP  = PositionGetDouble(POSITION_TP);

      if(type == POSITION_TYPE_BUY)
      {
         double bid     = SymbolInfoDouble(_Symbol, SYMBOL_BID);
         double newSL   = NormalizeDouble(bid - dist, digits);
         // Solo mueve si hay ganancia y el nuevo SL mejora al actual.
         if(bid - openP > dist && (curSL == 0.0 || newSL > curSL))
            trade.PositionModify(ticket, newSL, curTP);
      }
      else if(type == POSITION_TYPE_SELL)
      {
         double ask     = SymbolInfoDouble(_Symbol, SYMBOL_ASK);
         double newSL   = NormalizeDouble(ask + dist, digits);
         if(openP - ask > dist && (curSL == 0.0 || newSL < curSL))
            trade.PositionModify(ticket, newSL, curTP);
      }
   }
}

//+------------------------------------------------------------------+
//| Cuenta posiciones abiertas de este symbol + magic                |
//+------------------------------------------------------------------+
int CountOpenPositions(long magic)
{
   int total = 0;
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(ticket == 0) continue;
      if(PositionGetString(POSITION_SYMBOL) == _Symbol &&
         PositionGetInteger(POSITION_MAGIC) == magic)
         total++;
   }
   return total;
}

//+------------------------------------------------------------------+
//| Mini-parser JSON (para respuestas planas y simples)              |
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
   int start = p;
   int end = p;
   ushort ch = StringGetCharacter(json, p);
   if(ch == '"')
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

bool JsonBool(const string json, const string key)
{
   return (JsonRaw(json, key) == "true");
}
//+------------------------------------------------------------------+

//+------------------------------------------------------------------+
//|                                              EasDashboardEA.mq5   |
//|   EA por par: pregunta a la web si debe operar y ejecuta.        |
//|   La DECISION (operar o no) la toma el servidor segun la BD.    |
//+------------------------------------------------------------------+
#property copyright "EasDashboard"
#property version   "1.00"
#property strict

#include <Trade/Trade.mqh>

//--- Parametros configurables desde MT5 ---
input string  ApiBaseUrl     = "https://tu-dominio";   // URL del panel (sin / al final)
input string  ApiKey         = "tu-clave-secreta";     // Igual a BOT_API_KEY del .env
input int     PollSeconds    = 2;                       // Cada cuanto consulta la web
input int     Slippage       = 20;                      // Desviacion permitida (puntos)
input int     MagicBase      = 1000000;                 // Base para el magic number

CTrade trade;
datetime lastPoll = 0;

//+------------------------------------------------------------------+
int OnInit()
{
   EventSetTimer(MathMax(1, PollSeconds));
   Print("EasDashboardEA iniciado para ", _Symbol,
         ". IMPORTANTE: agrega ", ApiBaseUrl,
         " en Herramientas > Opciones > Asesores Expertos > WebRequest.");
   return(INIT_SUCCEEDED);
}

void OnDeinit(const int reason)
{
   EventKillTimer();
}

void OnTimer()
{
   PollAndTrade();
}

//+------------------------------------------------------------------+
//| Consulta la web y actua segun la respuesta                       |
//+------------------------------------------------------------------+
void PollAndTrade()
{
   string url = ApiBaseUrl + "/api/bots/signal?symbol=" + _Symbol;
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

   bool   shouldTrade = JsonBool(body, "should_trade");
   bool   found       = JsonBool(body, "found");

   if(!found || !shouldTrade)
      return; // El servidor dice que no operemos: no hacemos nada.

   string direction   = JsonString(body, "direction");
   double lot         = JsonNumber(body, "lot_size");
   int    slPips      = (int)JsonNumber(body, "stop_loss_pips");
   int    tpPips      = (int)JsonNumber(body, "take_profit_pips");
   int    maxTrades   = (int)JsonNumber(body, "max_open_trades");
   int    botId       = (int)JsonNumber(body, "bot_id");

   long   magic       = MagicBase + botId;
   trade.SetExpertMagicNumber(magic);
   trade.SetDeviationInPoints(Slippage);

   if(CountOpenPositions(magic) >= maxTrades)
      return; // Ya alcanzo el maximo de operaciones de este bot.

   // Resolver direccion. 'both' abre compra por defecto (logica basica).
   bool doBuy  = (direction == "buy"  || direction == "both");
   bool doSell = (direction == "sell");

   double point  = SymbolInfoDouble(_Symbol, SYMBOL_POINT);
   int    digits = (int)SymbolInfoInteger(_Symbol, SYMBOL_DIGITS);
   double factor = (digits == 3 || digits == 5) ? 10.0 : 1.0;
   double slDist = slPips * point * factor;
   double tpDist = tpPips * point * factor;

   if(doBuy)
   {
      double ask = SymbolInfoDouble(_Symbol, SYMBOL_ASK);
      double sl  = slPips > 0 ? ask - slDist : 0.0;
      double tp  = tpPips > 0 ? ask + tpDist : 0.0;
      if(trade.Buy(lot, _Symbol, ask, sl, tp, "EasDashboard"))
         Print(_Symbol, ": BUY ", lot, " ejecutada.");
      else
         Print(_Symbol, ": fallo BUY ", trade.ResultRetcode(), " ", trade.ResultComment());
   }
   else if(doSell)
   {
      double bid = SymbolInfoDouble(_Symbol, SYMBOL_BID);
      double sl  = slPips > 0 ? bid + slDist : 0.0;
      double tp  = tpPips > 0 ? bid - tpDist : 0.0;
      if(trade.Sell(lot, _Symbol, bid, sl, tp, "EasDashboard"))
         Print(_Symbol, ": SELL ", lot, " ejecutada.");
      else
         Print(_Symbol, ": fallo SELL ", trade.ResultRetcode(), " ", trade.ResultComment());
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
   // saltar espacios
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
      // hasta coma o cierre de objeto
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

string JsonString(const string json, const string key)
{
   return JsonRaw(json, key);
}

double JsonNumber(const string json, const string key)
{
   string v = JsonRaw(json, key);
   if(v == "" || v == "null") return 0.0;
   return StringToDouble(v);
}

bool JsonBool(const string json, const string key)
{
   string v = JsonRaw(json, key);
   return (v == "true");
}
//+------------------------------------------------------------------+

/* EDIT THIS FILE to change contact details, mentor posts, events and lessons. */
const DATA={
site:{name:"PIPSLAB Trading School",city:"Nairobi, Kenya",whatsapp:"254700000000",formspree:""},
posts:[
{date:"Sample post",pair:"XAUUSD",bias:"Bullish",title:"Why gold holds up when real yields fall",body:"Replace this with the mentor's weekly view. Explain the driver (rates, dollar, risk mood), what would change the view, and the risk to watch."},
{date:"Sample post",pair:"EURUSD",bias:"Neutral",title:"Waiting for the CPI print before taking a side",body:"Replace this text. Explain why patience is a position when a high-impact event is close."}
],
events:[
{day:"Wed",name:"US CPI",note:"Sample: high impact on USD pairs and gold. Reduce size or stand aside before the release."},
{day:"Thu",name:"ECB rate decision",note:"Sample: watch the press conference tone, not just the rate."}
],
modules:[
{t:"What forex is",l:[["Currency pairs","You always trade one currency against another. In EURUSD you buy euros by selling dollars. Price shows how many dollars one euro costs."],["Pips, lots and leverage","A pip is the standard small price move. A lot is your trade size. Leverage lets you control a larger position than your deposit, which multiplies gains and losses."]],q:{q:"What does leverage do?",o:["Removes risk","Multiplies gains and losses","Guarantees profit"],a:1}},
{t:"Reading a chart",l:[["Candlesticks","Each candle shows open, high, low and close for a period. Green closed higher, red closed lower. The long thin lines are wicks."],["Support and resistance","Price tends to react at levels where it turned before. Mark them on a higher timeframe first, then zoom in."]],q:{q:"A candle's wick shows:",o:["The highest and lowest prices reached","The trading volume","The broker's fee"],a:0}},
{t:"Fundamentals",l:[["Why currencies move","Interest rates, inflation, jobs data and central bank tone drive currencies. A currency usually strengthens when markets expect higher rates."],["Trading the news safely","High-impact releases cause fast, wide moves. Know the time, expect slippage, and cut size or stay out if you have no plan."]],q:{q:"A central bank signals higher rates. Its currency usually:",o:["Weakens","Strengthens","Is unaffected"],a:1}},
{t:"Risk management",l:[["Risk per trade","Decide before entry how much of your account you can lose, commonly 1% or less. Size the position from your stop distance, not from a feeling."],["Reward to risk","If you target twice what you risk, you can be wrong more than half the time and still break even. Use the Tools page to check any setup."]],q:{q:"You risk 1% per trade. After 5 straight losses you have lost about:",o:["5%","50%","1%"],a:0}},
{t:"Psychology and routine",l:[["Rules beat impulses","Write your plan and follow it. Revenge trading after a loss and oversized trades after a win are how most accounts end."],["Journal every trade","Record the setup, the result in R and what you felt. Review weekly. Your journal shows what to fix."]],q:{q:"The best response to a losing trade is to:",o:["Double the next position","Stick to the plan and log it","Remove your stop loss"],a:1}}
]};

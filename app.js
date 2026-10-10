const $=(s,r=document)=>r.querySelector(s),pg=document.body.dataset.page;
const pages=[["index.html","Home","home"],["academy.html","Academy","academy"],["tools.html","Risk tools","tools"],["mentor.html","Mentor's desk","mentor"],["join.html","Join","join"]];
$('#nav').innerHTML=`<div class=w><a href=index.html><img src=assets/logo.jpg alt="PIPSLAB Trading School"></a><nav>${pages.map(p=>`<a href=${p[0]} class="${p[2]==pg?'on':''} ${p[2]=='join'?'cta':''}">${p[1]}</a>`).join('')}</nav></div>`;
$('#foot').innerHTML=`<div class=w><b>${DATA.site.name}</b>, ${DATA.site.city}<small>Education only. Nothing here is financial advice or a signal. Forex and CFD trading carries a high risk of loss; trade only money you can afford to lose.</small></div>`;
const store=(k,v)=>{try{if(v===undefined)return JSON.parse(localStorage.getItem(k));localStorage.setItem(k,JSON.stringify(v))}catch(e){return null}};
const card=p=>`<article class=card><span class="tag ${p.bias=='Bullish'?'up':p.bias=='Bearish'?'dn':'ne'}">${p.pair} ${p.bias}</span><h3>${p.title}</h3><p>${p.body}</p><small>${p.date}</small></article>`;
const P={
home(){$('#latest').innerHTML=DATA.posts.slice(0,2).map(card).join('')},
mentor(){$('#posts').innerHTML=DATA.posts.map(card).join('');$('#events').innerHTML=DATA.events.map(e=>`<div class=card><h3>${e.day}: ${e.name}</h3><p>${e.note}</p></div>`).join('')},
academy(){
 const done=new Set(store('pl_done')||[]);
 const draw=()=>{$('#pct').textContent=done.size+' of '+DATA.modules.length+' modules complete';$('#fill').style.width=done.size/DATA.modules.length*100+'%'};
 $('#mods').innerHTML=DATA.modules.map((m,i)=>`<details ${i==0?'open':''}><summary>Module ${i+1}: ${m.t} <span class=ok id=d${i}>${done.has(i)?'Complete':''}</span></summary>${m.l.map(x=>`<h3>${x[0]}</h3><p>${x[1]}</p>`).join('')}<div class=card><b>${m.q.q}</b>${m.q.o.map((o,j)=>`<label class=opt><input type=radio name=q${i} value=${j}>${o}</label>`).join('')}<button class="btn g" data-m=${i}>Check answer</button> <span id=r${i}></span></div></details>`).join('');
 document.querySelectorAll('[data-m]').forEach(b=>b.onclick=()=>{const i=+b.dataset.m,s=document.querySelector(`input[name=q${i}]:checked`),r=$('#r'+i);
  if(!s){r.textContent='Pick an answer first.';r.className='no';return}
  if(+s.value==DATA.modules[i].q.a){r.textContent='Correct. Module complete.';r.className='ok';done.add(i);store('pl_done',[...done]);$('#d'+i).textContent='Complete';draw()}else{r.textContent='Not quite. Re-read the lessons and try again.';r.className='no'}});
 draw()},
tools(){
 const g=id=>+$('#'+id).value,pv={EURUSD:10,GBPUSD:10,AUDUSD:10,XAUUSD:10,USDJPY:6.7};
 const calc=()=>{const bal=g('bal'),rk=g('rk'),dl=g('dl'),st=g('st'),v=g('pv'),risk=bal*rk/100,lots=st>0&&v>0?risk/(st*v):0;
  $('#o1').innerHTML=`Risk per trade: <b>$${risk.toFixed(2)}</b><br>Position size: <b>${(Math.floor(lots*100)/100).toFixed(2)} lots</b>`+(lots>0&&lots<.01?'<br>Below the 0.01 minimum lot. Tighten your stop or raise risk %.':'');
  $('#o2').innerHTML=`Daily loss limit: <b>$${(bal*dl/100).toFixed(2)}</b><br>Stop for the day after <b>${rk>0?Math.floor(dl/rk):0}</b> losing trades at ${rk}% risk.`;
  const e=g('en'),s=g('sl'),t=g('tp'),rr=e&&s&&t&&e!==s?Math.abs(t-e)/Math.abs(e-s):0;
  $('#o3').innerHTML=rr?`Reward to risk: <b>1:${rr.toFixed(2)}</b><br>You need to win only <b>${(100/(1+rr)).toFixed(0)}%</b> of trades to break even.`:'Enter entry, stop and target prices.'};
 $('#pair').onchange=()=>{$('#pv').value=pv[$('#pair').value];calc()};
 document.querySelectorAll('.calc input').forEach(i=>i.oninput=calc);calc();
 let j=store('pl_j')||[];
 const draw=()=>{const w=j.filter(x=>x.r>0).length,tr=j.reduce((a,x)=>a+x.r,0);
  $('#js').innerHTML=j.length?`Trades: <b>${j.length}</b> | Win rate: <b>${(w/j.length*100).toFixed(0)}%</b> | Total: <b>${tr.toFixed(1)}R</b>`:'No trades logged yet. Add your first one above.';
  $('#jl').innerHTML=j.slice().reverse().slice(0,15).map(x=>`<p><b>${x.p}</b> <span class=${x.r>0?'ok':'no'}>${x.r>0?'+':''}${x.r}R</span> ${x.n}</p>`).join('')};
 $('#add').onclick=()=>{const r=parseFloat($('#jr').value);if(isNaN(r)){$('#jr').focus();return}j.push({p:$('#jp').value||'Trade',r,n:$('#jn').value});store('pl_j',j);$('#jn').value=$('#jr').value='';draw()};
 $('#clr').onclick=()=>{if(confirm('Delete all journal entries?')){j=[];store('pl_j',j);draw()}};draw()},
join(){$('#f').onsubmit=async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.target)),o=$('#res');
 const msg=`Hi PIPSLAB, I want to join. Name: ${d.name}. Phone: ${d.phone}. Level: ${d.level}.`;
 if(DATA.site.formspree){try{const r=await fetch(DATA.site.formspree,{method:'POST',headers:{Accept:'application/json'},body:JSON.stringify(d)});if(r.ok){o.textContent='Thanks. We will contact you soon.';e.target.reset();return}}catch(x){}o.textContent='Could not send. Opening WhatsApp instead.'}
 location.href=`https://wa.me/${DATA.site.whatsapp}?text=${encodeURIComponent(msg)}`}}
};
P[pg]&&P[pg]();

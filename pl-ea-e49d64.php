<?php
/* PipsLab EA admin. It is not linked from the website. Keep this file name private. */
require __DIR__.'/ea-lib.php';
const SETUP_CODE='PL-659fb793';
date_default_timezone_set('Africa/Nairobi');
@ini_set('session.cookie_samesite','Strict');
header('X-Robots-Tag: noindex, nofollow');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
$https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(0,'/','',$https,true);
session_start();

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function cut($s,$n){$s=trim((string)$s);return function_exists('mb_substr')?mb_substr($s,0,$n,'UTF-8'):substr($s,0,$n);}
function post($k,$def=''){return isset($_POST[$k])?$_POST[$k]:$def;}
function csrf(){if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(16));return $_SESSION['csrf'];}
function csrf_ok(){return !empty($_POST['csrf'])&&!empty($_SESSION['csrf'])&&hash_equals($_SESSION['csrf'],(string)$_POST['csrf']);}
function flash($t,$m){$_SESSION['flash']=array($t,$m);}
function go($q=''){header('Location: '.basename(__FILE__).$q);exit;}
function back_q(){$b=(string)post('back');return preg_match('/^[a-z]+(=[A-Za-z0-9_-]+)?$/',$b)?'?'.$b:'';}
function fmt($t){return $t?date('d M Y H:i',(int)$t):'-';}
function sub_idx(&$d,$id){foreach($d['subs'] as $i=>$s){if($s['id']===$id)return $i;}return -1;}
function mutate($fn,$ok='Saved.'){
 $r=ea_update($fn);
 if($r===false)flash('err','Could not save. Check that the private data folder can be written.');
 elseif(isset($r['err']))flash('err',$r['err']);
 else flash('ok',$ok);
 return $r;
}
$BACK='';
function btn($a,$id,$label,$cls='',$confirm='',$more=''){
 global $BACK;
 return '<form method="post" class="inl"'.($confirm!==''?' onsubmit="return confirm(\''.h($confirm).'\')"':'').'><input type="hidden" name="csrf" value="'.h(csrf()).'"><input type="hidden" name="a" value="'.h($a).'"><input type="hidden" name="id" value="'.h($id).'"><input type="hidden" name="back" value="'.h($BACK).'">'.$more.'<button class="sm '.$cls.'">'.$label.'</button></form>';
}
function pay_status($s){
 if(empty($s['paid']))return array('Awaiting payment','sch');
 if($s['paid_until']>0&&$s['paid_until']<time())return array('Expired '.fmt($s['paid_until']),'exp');
 return array('Paid until '.date('d M Y',(int)$s['paid_until']),'live');
}
function ea_status($s,$vps){
 $on=false;foreach($vps as $v){if($v['id']===$s['vps']&&time()-(int)$v['seen']<90)$on=true;}
 $st=$s['state'];$map=array('idle'=>array('Not started','off'),'starting'=>array('Starting','sch'),'running'=>array('Running','live'),'stopping'=>array('Stopping','sch'),'stopped'=>array('Stopped','off'),'killed'=>array('Killed (all trades closed)','exp'),'rule_stopped'=>array('Stopped by rule','exp'),'error'=>array('Problem','exp'));
 $r=isset($map[$st])?$map[$st]:array($st,'off');
 if($s['vps']!==''&&!$on&&$st!=='idle')$r=array($r[0].' (VPS offline)','exp');
 return $r;
}
function parse_plans($txt){
 $out=array();
 foreach(preg_split('/\r?\n/',(string)$txt) as $ln){
  $p=array_map('trim',explode('|',$ln));if(count($p)<4)continue;
  $id=strtolower(preg_replace('/[^a-z0-9-]+/i','-',$p[0]));if($id==='')continue;
  $out[]=array('id'=>cut($id,30),'name'=>cut($p[1],40),'days'=>max(1,min(400,(int)$p[2])),'price'=>cut($p[3],40));
 }
 return $out;
}

if(ea_dir()==='')die('Cannot create a private data folder. Ask your host to allow creating a folder next to public_html.');
$auth=ea_read('auth',null);$cfgOk=(is_array($auth)&&!empty($auth['hash']));
$authed=!empty($_SESSION['ok']);
$act=($_SERVER['REQUEST_METHOD']==='POST')?(string)post('a'):'';
$err='';$view='list';
$ip=isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'?';

if(!$cfgOk){
 $view='setup';
 if($act==='setup'){
  $code=trim((string)post('code'));$p1=(string)post('p1');$p2=(string)post('p2');
  if(!hash_equals(SETUP_CODE,$code))$err='That setup code is not right.';
  elseif(strlen($p1)<10)$err='Use a password of at least 10 characters.';
  elseif($p1!==$p2)$err='The two passwords do not match.';
  elseif(!ea_write('auth',array('hash'=>password_hash($p1,PASSWORD_DEFAULT))))$err='Could not save the password.';
  else{flash('ok','Password created. Please sign in.');go();}
 }
}elseif(!$authed){
 $view='login';
 if($act==='login'){
  $fails=ea_read('fails',array());$t=time();
  foreach($fails as $k=>$v){if(!is_array($v)||$t-$v[1]>900)unset($fails[$k]);}
  $n=isset($fails[$ip])?(int)$fails[$ip][0]:0;
  if($n>=6)$err='Too many attempts. Try again in 15 minutes.';
  elseif(password_verify((string)post('pw'),$auth['hash'])){unset($fails[$ip]);ea_write('fails',$fails);session_regenerate_id(true);$_SESSION['ok']=true;go();}
  else{sleep(1);$fails[$ip]=array($n+1,$t);ea_write('fails',$fails);$err='Wrong password.';}
 }
}else{
 if($act!==''){
  if(!csrf_ok())die('Your session expired. Go back, reload the page and try again.');
  $id=(string)post('id');
  if($act==='logout'){$_SESSION=array();session_destroy();go();}
  elseif($act==='pay'){
   $days=max(1,min(400,(int)post('days',30)));$ref=cut(post('pay_ref'),60);
   mutate(function(&$d)use($id,$days,$ref){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';$s=&$d['subs'][$i];
    $base=($s['paid']&&$s['paid_until']>time())?$s['paid_until']:time();$s['paid']=true;$s['paid_until']=$base+$days*86400;if($ref!=='')$s['pay_ref']=$ref;
    ea_log($d,'admin','Payment confirmed for '.$s['ref'].' (+'.$days.' days)');return true;},'Payment confirmed.');go(back_q());
  }
  elseif($act==='unpay'){
   mutate(function(&$d)use($id){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';$d['subs'][$i]['paid']=false;$d['subs'][$i]['desired']='stop';ea_log($d,'admin','Marked unpaid: '.$d['subs'][$i]['ref']);return true;},'Marked unpaid and stopped.');go(back_q());
  }
  elseif($act==='assign'){
   $pair=explode(':',(string)post('slot'));$vid=isset($pair[0])?$pair[0]:'';$sl=isset($pair[1])?(int)$pair[1]:0;
   mutate(function(&$d)use($id,$vid,$sl){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';$s=&$d['subs'][$i];
    if($vid===''){if($s['state']==='running')return 'Stop this subscriber before removing the VPS slot.';$s['vps']='';$s['slot']=0;return true;}
    $ok=false;foreach($d['vps'] as $v){if($v['id']===$vid&&$sl>=1&&$sl<=(int)$v['slots'])$ok=true;}
    if(!$ok)return 'That VPS slot does not exist.';
    foreach($d['subs'] as $o){if($o['id']!==$id&&$o['vps']===$vid&&(int)$o['slot']===$sl)return 'That slot is already used by '.$o['ref'].'.';}
    if(($s['vps']!==$vid||(int)$s['slot']!==$sl)&&in_array($s['state'],array('running','starting'),true))return 'Stop this subscriber before moving them to another slot.';
    $s['vps']=$vid;$s['slot']=$sl;ea_log($d,'admin',$s['ref'].' assigned to '.$vid.' slot '.$sl);return true;},'Slot assigned.');go(back_q());
  }
  elseif($act==='start'){
   mutate(function(&$d)use($id){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';$s=&$d['subs'][$i];
    if(!empty($d['settings']['global_kill']))return 'The kill-all switch is on. Clear it first.';
    if(empty($s['paid'])||($s['paid_until']>0&&$s['paid_until']<time()))return 'Confirm payment first.';
    if($s['vps']===''||!(int)$s['slot'])return 'Assign a VPS slot first.';
    if($d['settings']['expert']==='')return 'Set the EA name in Settings first.';
    $s['desired']='run';$s['msg']='';ea_log($d,'admin','START '.$s['ref']);return true;},'Start requested. The VPS picks it up within a few seconds.');go(back_q());
  }
  elseif($act==='stop'){
   mutate(function(&$d)use($id){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';$d['subs'][$i]['desired']='stop';ea_log($d,'admin','STOP '.$d['subs'][$i]['ref']);return true;},'Stop requested. Open trades stay open.');go(back_q());
  }
  elseif($act==='kill'){
   mutate(function(&$d)use($id){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';$d['subs'][$i]['desired']='kill';ea_log($d,'admin','KILL '.$d['subs'][$i]['ref']);return true;},'Kill requested. All trades will be closed, then the EA stops.');go(back_q());
  }
  elseif($act==='kill_all'){
   mutate(function(&$d){$d['settings']['global_kill']=true;foreach($d['subs'] as $i=>$s){if($s['vps']!=='')$d['subs'][$i]['desired']='kill';}ea_log($d,'admin','KILL ALL');return true;},'KILL ALL sent. Every assigned account will be closed out and stopped.');go();
  }
  elseif($act==='clear_kill'){
   mutate(function(&$d){$d['settings']['global_kill']=false;ea_log($d,'admin','Kill-all cleared');return true;},'Kill-all cleared. You can start accounts again.');go();
  }
  elseif($act==='stop_all'){
   mutate(function(&$d){foreach($d['subs'] as $i=>$s){if($s['desired']==='run')$d['subs'][$i]['desired']='stop';}ea_log($d,'admin','STOP ALL');return true;},'Stop sent to everyone. Open trades stay open.');go();
  }
  elseif($act==='rules'){
   $r=ea_rules();
   $r['daily_loss_pct']=max(0,min(100,(float)post('daily_loss_pct')));$r['max_dd_pct']=max(0,min(100,(float)post('max_dd_pct')));$r['profit_pct']=max(0,min(1000,(float)post('profit_pct')));
   $r['equity_floor']=max(0,(float)post('equity_floor'));$r['max_positions']=max(0,min(500,(int)post('max_positions')));
   $st=trim((string)post('stop_at'));$r['stop_ts']=($st!==''&&strtotime($st)!==false)?(int)strtotime($st):0;
   $r['on_trigger']=post('on_trigger')==='stop'?'stop':'kill';
   mutate(function(&$d)use($id,$r){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';$d['subs'][$i]['rules']=$r;ea_log($d,'admin','Rules updated for '.$d['subs'][$i]['ref']);return true;},'Rules saved. The VPS enforces them even if the website is offline.');go(back_q());
  }
  elseif($act==='meta'){
   $sf=cut(post('setfile'),80);$note=cut(post('note'),300);
   mutate(function(&$d)use($id,$sf,$note){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';$d['subs'][$i]['setfile']=$sf;$d['subs'][$i]['note']=$note;return true;});go(back_q());
  }
  elseif($act==='delete'){
   mutate(function(&$d)use($id){$i=sub_idx($d,$id);if($i<0)return 'Subscriber not found.';
    if($d['subs'][$i]['desired']==='run'||in_array($d['subs'][$i]['state'],array('running','starting'),true))return 'Stop or kill this subscriber first.';
    ea_log($d,'admin','Deleted '.$d['subs'][$i]['ref'].' and their encrypted password');array_splice($d['subs'],$i,1);return true;},'Subscriber and encrypted password deleted.');go();
  }
  elseif($act==='vps_add'){
   $name=cut(post('name'),40);$slots=max(1,min(10,(int)post('slots',10)));$tok=bin2hex(random_bytes(20));
   $r=mutate(function(&$d)use($name,$slots,$tok){if($name==='')return 'Give the VPS a name.';$n=1;$ids=array();foreach($d['vps'] as $v)$ids[]=$v['id'];while(in_array('vps'.$n,$ids,true))$n++;
    $d['vps'][]=array('id'=>'vps'.$n,'name'=>$name,'token'=>hash('sha256',$tok),'slots'=>$slots,'seen'=>0);ea_log($d,'admin','Added VPS '.$name);return true;},'VPS added.');
   if(isset($r['ok']))flash('ok','VPS added. Copy its agent token now, it is shown only once: '.$tok);
   go('?v=1');
  }
  elseif($act==='vps_token'){
   $tok=bin2hex(random_bytes(20));
   $r=mutate(function(&$d)use($id,$tok){foreach($d['vps'] as $i=>$v){if($v['id']===$id){$d['vps'][$i]['token']=hash('sha256',$tok);return true;}}return 'VPS not found.';},'New token created.');
   if(isset($r['ok']))flash('ok','New agent token for '.$id.' (shown only once): '.$tok);
   go('?v=1');
  }
  elseif($act==='vps_del'){
   mutate(function(&$d)use($id){foreach($d['subs'] as $s){if($s['vps']===$id)return 'Move the subscribers off this VPS first.';}foreach($d['vps'] as $i=>$v){if($v['id']===$id){array_splice($d['vps'],$i,1);return true;}}return 'VPS not found.';},'VPS removed.');go('?v=1');
  }
  elseif($act==='settings'){
   $plans=parse_plans(post('plans'));$pk=trim((string)post('public_key'));
   $set=array('open'=>!empty($_POST['open']),'expert'=>cut(post('expert'),120),'symbol'=>cut(post('symbol'),30),'tf'=>cut(post('tf'),6),'setfile'=>cut(post('setfile'),80),'public_key'=>$pk,'pay_text'=>cut(post('pay_text'),600),'notify'=>cut(post('notify'),100));
   if(!count($plans))flash('err','Add at least one plan, one per line: id|Name|days|price');
   elseif($pk!==''&&(base64_decode($pk,true)===false||strlen(base64_decode($pk,true))!==32))flash('err','The public key must be the 32-byte key printed by the agent (base64).');
   else{mutate(function(&$d)use($set,$plans){foreach($set as $k=>$v)$d['settings'][$k]=$v;$d['settings']['plans']=$plans;ea_log($d,'admin','Settings updated');return true;},'Settings saved.');}
   go('?cfg=1');
  }
 }
 if(isset($_GET['s']))$view='sub';elseif(isset($_GET['v']))$view='vps';elseif(isset($_GET['cfg']))$view='cfg';elseif(isset($_GET['log']))$view='log';
}

$fl=isset($_SESSION['flash'])?$_SESSION['flash']:null;unset($_SESSION['flash']);
$D=($authed)?ea_data():null;
if($authed){$BACK=($view==='sub')?'s='.preg_replace('/[^A-Za-z0-9_-]/','',(string)$_GET['s']):'';}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>PipsLab EA admin</title>
<style>
:root{--p:#5b3fb0;--pd:#3a2486;--cream:#f3effa;--ink:#120d24;--mute:#6a6486;--g:#16a34a;--r:#dc2626;box-sizing:border-box}*{box-sizing:border-box}
body{margin:0;background:var(--cream);color:var(--ink);font:16px/1.5 system-ui,-apple-system,'Segoe UI',sans-serif}
.w{max-width:820px;margin:0 auto;padding:16px}
header.t{background:var(--p);color:#fff;padding:12px 16px}header.t .w{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0;flex-wrap:wrap}header.t b{font-size:18px}
nav a{color:#fff;text-decoration:none;font-weight:700;font-size:14px;margin-right:14px;opacity:.85}nav a.on{opacity:1;border-bottom:2px solid #fff}
h1{font-size:25px;margin:16px 0 8px;letter-spacing:-.02em}h2{font-size:18px;margin:0 0 8px}
.card{background:#fff;border-radius:22px;padding:18px;margin:14px 0;box-shadow:0 8px 30px -14px rgba(58,36,134,.35)}
label{display:block;font-weight:700;margin:12px 0 5px;font-size:14px}small,.hint{color:var(--mute);font-size:13px}
input[type=text],input[type=password],input[type=number],input[type=datetime-local],textarea,select{width:100%;padding:12px 13px;border:1px solid #d9d2ee;border-radius:14px;font:inherit;background:#fff}textarea{min-height:84px}
button,.btn{font:700 15px system-ui;border:0;border-radius:30px;padding:12px 20px;background:var(--p);color:#fff;cursor:pointer;text-decoration:none;display:inline-block;box-shadow:0 4px 0 var(--pd)}
button.sm,.btn.sm{padding:8px 14px;font-size:13px;box-shadow:0 3px 0 var(--pd)}button.ghost,.btn.ghost{background:#fff;color:var(--ink);box-shadow:0 3px 0 #d9d2ee;border:1px solid #d9d2ee}
button.red,.btn.red{background:var(--r);box-shadow:0 3px 0 #9b1c1c}button.green{background:var(--g);box-shadow:0 3px 0 #0f6b2f}
.inl{display:inline-block;margin:3px 3px 0 0}.row{display:flex;gap:12px;flex-wrap:wrap}.row>div{flex:1;min-width:140px}
.b{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;border-radius:20px;padding:3px 10px;background:#ece7fa;color:var(--pd);margin:0 4px 4px 0}
.b.live{background:#dcfce7;color:#166534}.b.off{background:#eee;color:#555}.b.sch{background:#fef3c7;color:#92400e}.b.exp{background:#fee2e2;color:#991b1b}
.msg{border-radius:14px;padding:12px 14px;margin:14px 0;font-weight:600;word-break:break-all}.msg.ok{background:#dcfce7;color:#166534}.msg.err{background:#fee2e2;color:#991b1b}
.live2{font-size:14px}.live2 b{font-size:15px}.warn{background:#fef3c7;color:#92400e;border-radius:14px;padding:12px 14px;margin:12px 0;font-size:14px}
.slots{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-top:8px}.slots div{border-radius:10px;background:var(--cream);padding:6px;font-size:12px;text-align:center}.slots div.u{background:#ece7fa}
pre{background:#120d24;color:#e9e3ff;padding:12px;border-radius:12px;overflow:auto;font-size:13px}
</style></head><body>
<header class="t"><div class="w"><b>PipsLab EA</b>
<?php if($authed){ ?><nav><a href="<?= h(basename(__FILE__)) ?>" class="<?= $view==='list'||$view==='sub'?'on':'' ?>">Subscribers</a><a href="?v=1" class="<?= $view==='vps'?'on':'' ?>">VPS</a><a href="?cfg=1" class="<?= $view==='cfg'?'on':'' ?>">Settings</a><a href="?log=1" class="<?= $view==='log'?'on':'' ?>">Log</a></nav><?= btn('logout','','Log out','ghost') ?><?php } ?>
</div></header>
<div class="w">
<?php if($fl){ ?><div class="msg <?= h($fl[0]) ?>"><?= h($fl[1]) ?></div><?php } ?>
<?php if($err!==''){ ?><div class="msg err"><?= h($err) ?></div><?php } ?>

<?php if($view==='setup'){ ?>
 <h1>Create your admin password</h1>
 <form method="post" class="card"><input type="hidden" name="a" value="setup">
 <label>Setup code</label><input type="text" name="code" autocomplete="off" required>
 <label>New password (10+ characters)</label><input type="password" name="p1" autocomplete="new-password" required>
 <label>Repeat password</label><input type="password" name="p2" autocomplete="new-password" required>
 <p><button>Create password</button></p></form>

<?php } elseif($view==='login'){ ?>
 <h1>Sign in</h1>
 <form method="post" class="card"><input type="hidden" name="a" value="login"><label>Password</label><input type="password" name="pw" autocomplete="current-password" required autofocus><p><button>Sign in</button></p></form>

<?php } elseif($view==='vps'){ ?>
 <h1>VPS servers</h1><p class="hint">Each VPS runs up to 10 MT5 terminals (slots). Install the agent on every VPS and give it the token shown when you add it.</p>
 <?php foreach($D['vps'] as $v){ $on=(time()-(int)$v['seen']<90); $used=array();foreach($D['subs'] as $s){if($s['vps']===$v['id'])$used[(int)$s['slot']]=$s;} ?>
 <div class="card"><b><?= h($v['name']) ?></b> <small>(<?= h($v['id']) ?>)</small> <span class="b <?= $on?'live':'exp' ?>"><?= $on?'Online':'Offline' ?></span><br>
  <small>Last contact: <?= h(fmt($v['seen'])) ?> · Slots used: <?= count($used) ?> of <?= (int)$v['slots'] ?></small>
  <div class="slots"><?php for($i=1;$i<=(int)$v['slots'];$i++){ ?><div class="<?= isset($used[$i])?'u':'' ?>"><b><?= $i ?></b><br><?= isset($used[$i])?h($used[$i]['ref']):'free' ?></div><?php } ?></div>
  <p><?= btn('vps_token',$v['id'],'New agent token','ghost','Create a new token? The agent on this VPS must be updated with it.') ?><?= btn('vps_del',$v['id'],'Remove','red','Remove this VPS?') ?></p></div>
 <?php } ?>
 <form method="post" class="card"><h2>Add a VPS</h2><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="vps_add">
  <div class="row"><div><label>Name</label><input type="text" name="name" maxlength="40" placeholder="VPS 1" required></div><div><label>Terminals (max 10)</label><input type="number" name="slots" min="1" max="10" value="10"></div></div><p><button>Add VPS</button></p></form>

<?php } elseif($view==='cfg'){ $st=$D['settings']; $pl='';foreach($st['plans'] as $p){$pl.=$p['id'].'|'.$p['name'].'|'.$p['days'].'|'.$p['price']."\n";} ?>
 <h1>Settings</h1>
 <form method="post" class="card"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="settings">
  <label style="font-weight:600"><input type="checkbox" name="open" value="1" <?= !empty($st['open'])?'checked':'' ?>> Accept new subscribers on the website</label>
  <label>Encryption public key <small>(printed by the agent when you run --genkey; clients' passwords are locked with it)</small></label><input type="text" name="public_key" value="<?= h($st['public_key']) ?>">
  <div class="row"><div><label>EA name on the VPS <small>(path inside MQL5\Experts, no .ex5)</small></label><input type="text" name="expert" value="<?= h($st['expert']) ?>" placeholder="GoldEMACascade\GoldEMACascade"></div><div><label>Default settings file <small>(in MQL5\Presets)</small></label><input type="text" name="setfile" value="<?= h($st['setfile']) ?>" placeholder="pipslab.set"></div></div>
  <div class="row"><div><label>Symbol</label><input type="text" name="symbol" value="<?= h($st['symbol']) ?>"></div><div><label>Timeframe</label><input type="text" name="tf" value="<?= h($st['tf']) ?>" placeholder="M5"></div></div>
  <label>Plans <small>(one per line: id|Name|days|price)</small></label><textarea name="plans"><?= h($pl) ?></textarea>
  <label>Payment instructions shown after sign-up</label><textarea name="pay_text" maxlength="600"><?= h($st['pay_text']) ?></textarea>
  <label>Email me when someone subscribes <small>(optional)</small></label><input type="text" name="notify" value="<?= h($st['notify']) ?>">
  <p><button>Save settings</button></p></form>

<?php } elseif($view==='log'){ ?>
 <h1>Activity log</h1><div class="card"><?php foreach($D['log'] as $l){ ?><div style="padding:6px 0;border-bottom:1px solid #eee"><small><?= h(fmt($l[0])) ?> · <?= h($l[1]) ?></small><br><?= h($l[2]) ?></div><?php } if(!count($D['log']))echo 'Nothing yet.'; ?></div>

<?php } elseif($view==='sub'){
 $i=sub_idx($D,(string)$_GET['s']); if($i<0){ ?><div class="card">Subscriber not found. <a href="<?= h(basename(__FILE__)) ?>">Back</a></div><?php } else { $s=$D['subs'][$i]; $ps=pay_status($s); $es=ea_status($s,$D['vps']); $r=$s['rules']; $lv=$s['live'];
 $plan=$s['plan'];$pdays=30;foreach($D['settings']['plans'] as $p){if($p['id']===$s['plan']){$plan=$p['name'].' '.$p['price'];$pdays=(int)$p['days'];}} ?>
 <p><a href="<?= h(basename(__FILE__)) ?>">&larr; All subscribers</a></p>
 <h1><?= h($s['name']) ?> <small><?= h($s['ref']) ?></small></h1>
 <div class="card"><span class="b <?= h($ps[1]) ?>"><?= h($ps[0]) ?></span><span class="b <?= h($es[1]) ?>"><?= h($es[0]) ?></span><br>
  Contact: <b><?= h($s['contact']) ?></b><?= $s['email']!==''?' · '.h($s['email']):'' ?><br>MT5 account: <b><?= h($s['login']) ?></b> · Server: <b><?= h($s['server']) ?></b><br>Plan: <?= h($plan) ?> · Signed up <?= h(fmt($s['submitted'])) ?><?= $s['pay_ref']!==''?' · Payment ref: <b>'.h($s['pay_ref']).'</b>':'' ?><br>
  <small>The master password is stored encrypted and can only be unlocked on your VPS. You cannot see it here.</small>
  <?php if(!empty($lv)){ ?><p class="live2">Balance <b><?= number_format($lv['balance'],2) ?></b> · Equity <b><?= number_format($lv['equity'],2) ?></b> · Open trades <b><?= (int)$lv['positions'] ?></b> · P/L <b><?= number_format($lv['profit'],2) ?></b><br><small>Updated <?= h(fmt($lv['ts'])) ?></small></p><?php } ?>
  <?php if($s['msg']!==''){ ?><div class="warn"><?= h($s['msg']) ?></div><?php } ?>
  <p><?= btn('start',$s['id'],'▶ Start EA','green') ?><?= btn('stop',$s['id'],'■ Stop EA','ghost','Stop the EA? Open trades stay open.') ?><?= btn('kill',$s['id'],'KILL: close all trades','red','Close ALL trades on this account and stop the EA?') ?></p></div>
 <form method="post" class="card"><h2>1. Payment</h2><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="pay"><input type="hidden" name="id" value="<?= h($s['id']) ?>"><input type="hidden" name="back" value="<?= h($BACK) ?>">
  <div class="row"><div><label>Days to add</label><input type="number" name="days" min="1" max="400" value="<?= $pdays ?>"></div><div><label>Payment reference</label><input type="text" name="pay_ref" maxlength="60" value="<?= h($s['pay_ref']) ?>"></div></div>
  <p><button class="green">Confirm payment</button> <?= btn('unpay',$s['id'],'Mark unpaid','ghost','Mark unpaid and stop the EA?') ?></p></form>
 <form method="post" class="card"><h2>2. VPS slot</h2><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="assign"><input type="hidden" name="id" value="<?= h($s['id']) ?>"><input type="hidden" name="back" value="<?= h($BACK) ?>">
  <select name="slot"><option value="">Not assigned</option>
  <?php foreach($D['vps'] as $v){ for($n=1;$n<=(int)$v['slots'];$n++){ $o=null;foreach($D['subs'] as $x){if($x['vps']===$v['id']&&(int)$x['slot']===$n)$o=$x;} $mine=($o&&$o['id']===$s['id']); ?>
   <option value="<?= h($v['id'].':'.$n) ?>" <?= $mine?'selected':'' ?> <?= ($o&&!$mine)?'disabled':'' ?>><?= h($v['name']) ?> · terminal <?= $n ?> <?= $o?($mine?'(this client)':'- used by '.h($o['ref'])):'- free' ?></option>
  <?php } } ?></select>
  <p><button>Save slot</button></p><?php if(!count($D['vps'])){ ?><small>Add a VPS first on the VPS tab.</small><?php } ?></form>
 <form method="post" class="card"><h2>3. Stop rules <small>(the VPS enforces these on its own)</small></h2><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="rules"><input type="hidden" name="id" value="<?= h($s['id']) ?>"><input type="hidden" name="back" value="<?= h($BACK) ?>">
  <p class="hint">Use 0 to switch a rule off.</p>
  <div class="row"><div><label>Daily loss limit (%)</label><input type="number" step="0.1" min="0" name="daily_loss_pct" value="<?= h($r['daily_loss_pct']) ?>"></div><div><label>Max drawdown from peak (%)</label><input type="number" step="0.1" min="0" name="max_dd_pct" value="<?= h($r['max_dd_pct']) ?>"></div></div>
  <div class="row"><div><label>Profit target (%)</label><input type="number" step="0.1" min="0" name="profit_pct" value="<?= h($r['profit_pct']) ?>"></div><div><label>Stop if equity falls to</label><input type="number" step="0.01" min="0" name="equity_floor" value="<?= h($r['equity_floor']) ?>"></div></div>
  <div class="row"><div><label>Max open trades</label><input type="number" min="0" name="max_positions" value="<?= (int)$r['max_positions'] ?>"></div><div><label>Stop at (Nairobi time)</label><input type="datetime-local" name="stop_at" value="<?= $r['stop_ts']?h(date('Y-m-d\TH:i',(int)$r['stop_ts'])):'' ?>"></div></div>
  <label>When a rule is hit</label><select name="on_trigger"><option value="kill" <?= $r['on_trigger']!=='stop'?'selected':'' ?>>Close all trades, then stop the EA</option><option value="stop" <?= $r['on_trigger']==='stop'?'selected':'' ?>>Only stop the EA (trades stay open)</option></select>
  <p><button>Save rules</button></p><small>The subscription end date also stops the EA automatically.</small></form>
 <form method="post" class="card"><h2>4. Notes</h2><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="a" value="meta"><input type="hidden" name="id" value="<?= h($s['id']) ?>"><input type="hidden" name="back" value="<?= h($BACK) ?>">
  <label>Settings file for this client <small>(empty = default)</small></label><input type="text" name="setfile" value="<?= h($s['setfile']) ?>"><label>Private note</label><textarea name="note" maxlength="300"><?= h($s['note']) ?></textarea><p><button class="ghost">Save</button></p></form>
 <div class="card"><?= btn('delete',$s['id'],'Delete subscriber and their encrypted password','red','Delete this subscriber permanently?') ?></div>
 <?php } ?>

<?php } else {
 $f=isset($_GET['f'])?(string)$_GET['f']:'';$subs=$D['subs'];$gk=!empty($D['settings']['global_kill']);
 $cnt=array('all'=>count($subs),'pay'=>0,'ready'=>0,'run'=>0);
 foreach($subs as $s){ if(empty($s['paid']))$cnt['pay']++; elseif($s['vps']==='')$cnt['ready']++; if($s['state']==='running')$cnt['run']++; }
?>
 <h1>Subscribers</h1>
 <?php if($gk){ ?><div class="msg err">KILL ALL is ON. Nothing can be started. <?= btn('clear_kill','','Clear kill-all','ghost') ?></div><?php } ?>
 <div class="card"><b>Emergency</b><br><?= btn('kill_all','','KILL ALL: close every trade on every account','red','Close ALL trades on ALL client accounts and stop every EA?') ?><?= btn('stop_all','','Stop all EAs','ghost','Stop every running EA? Open trades stay open.') ?></div>
 <p><a class="btn sm <?= $f===''?'':'ghost' ?>" href="<?= h(basename(__FILE__)) ?>">All <?= $cnt['all'] ?></a> <a class="btn sm <?= $f==='pay'?'':'ghost' ?>" href="?f=pay">Awaiting payment <?= $cnt['pay'] ?></a> <a class="btn sm <?= $f==='ready'?'':'ghost' ?>" href="?f=ready">Paid, no slot <?= $cnt['ready'] ?></a> <a class="btn sm <?= $f==='run'?'':'ghost' ?>" href="?f=run">Running <?= $cnt['run'] ?></a></p>
 <?php $shown=0; foreach($subs as $s){
  if($f==='pay'&&!empty($s['paid']))continue; if($f==='ready'&&!(!empty($s['paid'])&&$s['vps']===''))continue; if($f==='run'&&$s['state']!=='running')continue;
  $shown++;$ps=pay_status($s);$es=ea_status($s,$D['vps']);$lv=$s['live']; ?>
 <div class="card"><b><?= h($s['name']) ?></b> <small><?= h($s['ref']) ?> · acct <?= h($s['login']) ?></small><br>
  <span class="b <?= h($ps[1]) ?>"><?= h($ps[0]) ?></span><span class="b <?= h($es[1]) ?>"><?= h($es[0]) ?></span><?= $s['vps']!==''?'<span class="b">'.h($s['vps']).' · slot '.(int)$s['slot'].'</span>':'<span class="b off">No slot</span>' ?>
  <?php if(!empty($lv)&&$s['state']==='running'){ ?><div class="live2">Equity <b><?= number_format($lv['equity'],2) ?></b> · Trades <b><?= (int)$lv['positions'] ?></b> · P/L <b><?= number_format($lv['profit'],2) ?></b></div><?php } ?>
  <p><a class="btn sm" href="?s=<?= h(urlencode($s['id'])) ?>">Open</a> <?= btn('start',$s['id'],'▶ Start','green') ?><?= btn('stop',$s['id'],'■ Stop','ghost','Stop the EA? Open trades stay open.') ?><?= btn('kill',$s['id'],'KILL','red','Close ALL trades on this account and stop the EA?') ?></p></div>
 <?php } if(!$shown){ ?><div class="card">No subscribers here yet.</div><?php } ?>
<?php } ?>
</div></body></html>

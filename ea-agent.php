<?php
/* Talks to the PipsLab agent on each VPS. Not for browsers. */
require __DIR__.'/ea-lib.php';
if($_SERVER['REQUEST_METHOD']!=='POST')ea_out(array('ok'=>false,'error'=>'POST only'),405);
$vid=isset($_SERVER['HTTP_X_AGENT_ID'])?(string)$_SERVER['HTTP_X_AGENT_ID']:'';
$tok=isset($_SERVER['HTTP_X_AGENT_TOKEN'])?(string)$_SERVER['HTTP_X_AGENT_TOKEN']:'';
$in=json_decode((string)file_get_contents('php://input'),true);if(!is_array($in))$in=array();
$a=isset($in['a'])?(string)$in['a']:'';
$d=ea_data();$ok=false;
foreach($d['vps'] as $v){if($v['id']===$vid&&hash_equals($v['token'],hash('sha256',$tok)))$ok=true;}
if(!$ok){usleep(400000);ea_out(array('ok'=>false,'error'=>'auth'),401);}
if($a==='poll'){
 ea_update(function(&$d)use($vid){foreach($d['vps'] as $i=>$v){if($v['id']===$vid)$d['vps'][$i]['seen']=time();}return true;});
 $now=time();$gk=!empty($d['settings']['global_kill']);$slots=array();
 foreach($d['subs'] as $s){
  if($s['vps']!==$vid||!(int)$s['slot'])continue;
  $exp=$s['paid']&&$s['paid_until']>0&&$s['paid_until']<$now;$paid=!empty($s['paid'])&&!$exp;
  $des=$s['desired'];$why='';
  if($gk)$des='kill';
  elseif($des==='run'&&!$paid){$des='stop';$why=$exp?'subscription expired':'payment not confirmed';}
  $row=array('slot'=>(int)$s['slot'],'sub'=>$s['id'],'login'=>$s['login'],'server'=>$s['server'],'desired'=>$des,'reason'=>$why,'rules'=>$s['rules'],'setfile'=>($s['setfile']!==''?$s['setfile']:$d['settings']['setfile']));
  if($des==='run'||$des==='kill')$row['pw']=$s['sealed'];
  $slots[]=$row;
 }
 ea_out(array('ok'=>true,'now'=>$now,'global_kill'=>$gk,'settings'=>array('expert'=>$d['settings']['expert'],'symbol'=>$d['settings']['symbol'],'tf'=>$d['settings']['tf']),'slots'=>$slots));
}
if($a==='report'){
 $rows=(isset($in['slots'])&&is_array($in['slots']))?$in['slots']:array();
 ea_update(function(&$d)use($vid,$rows){
  $allowed=array('idle','starting','running','stopping','stopped','killed','rule_stopped','error');
  foreach($rows as $r){
   if(!is_array($r)||empty($r['sub']))continue;
   foreach($d['subs'] as $i=>$s){
    if($s['id']!==(string)$r['sub']||$s['vps']!==$vid)continue;
    $st=(isset($r['state'])&&in_array($r['state'],$allowed,true))?$r['state']:'error';
    $msg=substr(isset($r['msg'])?(string)$r['msg']:'',0,160);
    if($st!==$s['state'])ea_log($d,'agent',$s['ref'].': '.$st.($msg!==''?' ('.$msg.')':''));
    $d['subs'][$i]['state']=$st;$d['subs'][$i]['msg']=$msg;
    $d['subs'][$i]['live']=array('balance'=>(float)(isset($r['balance'])?$r['balance']:0),'equity'=>(float)(isset($r['equity'])?$r['equity']:0),'profit'=>(float)(isset($r['profit'])?$r['profit']:0),'positions'=>(int)(isset($r['positions'])?$r['positions']:0),'ts'=>time());
    if($st==='killed'||$st==='rule_stopped')$d['subs'][$i]['desired']='stop';
   }
  }
  return true;});
 ea_out(array('ok'=>true));
}
ea_out(array('ok'=>false,'error'=>'unknown action'),400);

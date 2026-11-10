<?php
/* Shared helpers for the PipsLab EA subscription system. Safe to open directly: it prints nothing. */
if(defined('EA_LIB'))return;
define('EA_LIB',1);
function ea_dir(){
 static $d=null;if($d!==null)return $d;
 $c=array();
 if(!empty($_SERVER['DOCUMENT_ROOT']))$c[]=dirname(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\')).'/pipslab-ea-private';
 $c[]=__DIR__.'/ea-private';
 foreach($c as $p){if((is_dir($p)||@mkdir($p,0700,true))&&is_writable($p)){$d=$p;return $d;}}
 $d='';return $d;
}
function ea_file($n){return ea_dir().'/'.$n.'.php';}
function ea_read($n,$def){
 if(ea_dir()==='')return $def;$f=ea_file($n);if(!is_file($f))return $def;
 $raw=(string)@file_get_contents($f);$p=strpos($raw,"\n");if($p===false)return $def;
 $j=json_decode(substr($raw,$p+1),true);return is_array($j)?$j:$def;
}
function ea_write($n,$data){
 if(ea_dir()==='')return false;$f=ea_file($n);$tmp=$f.'.tmp';
 $ok=@file_put_contents($tmp,"<?php http_response_code(404);exit;?>\n".json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
 if($ok===false)return false;@chmod($tmp,0600);return @rename($tmp,$f);
}
function ea_rules(){return array('daily_loss_pct'=>0,'max_dd_pct'=>0,'profit_pct'=>0,'equity_floor'=>0,'max_positions'=>0,'stop_ts'=>0,'on_trigger'=>'kill');}
function ea_default(){
 return array('settings'=>array('open'=>true,'expert'=>'','symbol'=>'XAUUSDm','tf'=>'M5','setfile'=>'','public_key'=>'','pay_text'=>'','notify'=>'','global_kill'=>false,
  'plans'=>array(array('id'=>'monthly','name'=>'Monthly','days'=>30,'price'=>''))),'vps'=>array(),'subs'=>array(),'log'=>array());
}
function ea_data(){
 $d=ea_read('data',null);$x=ea_default();if(!is_array($d))return $x;
 foreach($x as $k=>$v){if(!isset($d[$k])||!is_array($d[$k]))$d[$k]=$v;}
 foreach($x['settings'] as $k=>$v){if(!array_key_exists($k,$d['settings']))$d['settings'][$k]=$v;}
 return $d;
}
function ea_log(&$d,$who,$msg){array_unshift($d['log'],array(time(),$who,$msg));if(count($d['log'])>300)$d['log']=array_slice($d['log'],0,300);}
/* Locked read-modify-write. The callback gets the data by reference and returns true to save, or a text to refuse. */
function ea_update($cb){
 $dir=ea_dir();if($dir==='')return false;
 $lock=@fopen($dir.'/ea.lock','c');if(!$lock)return false;
 flock($lock,LOCK_EX);$d=ea_data();$r=$cb($d);
 if($r===true){$out=ea_write('data',$d)?array('ok'=>true,'d'=>$d):false;}
 else{$out=array('err'=>is_string($r)?$r:'Not saved');}
 flock($lock,LOCK_UN);fclose($lock);return $out;
}
function ea_out($a,$code=200){http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($a);exit;}

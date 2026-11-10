<?php
/* Public form endpoint. The MT5 password is encrypted with the public key the moment it arrives and is never stored in plain text. */
require __DIR__.'/ea-lib.php';
if(ea_dir()==='')ea_out(array('ok'=>false,'error'=>'Subscriptions are not set up yet.'),503);
$d=ea_data();$s=$d['settings'];
if(isset($_GET['info'])){ea_out(array('ok'=>true,'open'=>(!empty($s['open'])&&$s['public_key']!==''),'plans'=>$s['plans'],'pay_text'=>$s['pay_text']));}
if($_SERVER['REQUEST_METHOD']!=='POST')ea_out(array('ok'=>false,'error'=>'Use the form on the website.'),405);
$https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
$host=isset($_SERVER['SERVER_NAME'])?$_SERVER['SERVER_NAME']:'';
if(!$https&&$host!=='localhost'&&$host!=='127.0.0.1')ea_out(array('ok'=>false,'error'=>'Please use the secure (https) version of this website.'),400);
if(!empty($_POST['hp']))ea_out(array('ok'=>true,'ref'=>'EA-0000'));
if(empty($s['open'])||$s['public_key']==='')ea_out(array('ok'=>false,'error'=>'New subscriptions are closed right now.'),403);
function g($k,$n){return isset($_POST[$k])?(function_exists('mb_substr')?mb_substr(trim((string)$_POST[$k]),0,$n,'UTF-8'):substr(trim((string)$_POST[$k]),0,$n)):'';}
$name=g('name',80);$contact=g('contact',60);$email=g('email',100);$login=g('login',12);$server=g('server',60);$pw=isset($_POST['password'])?(string)$_POST['password']:'';$plan=g('plan',30);$payref=g('pay_ref',60);
$err='';
if(strlen($name)<2)$err='Please enter your name.';
elseif(strlen($contact)<5)$err='Please enter a WhatsApp number or Telegram username.';
elseif($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$err='That email address does not look right.';
elseif(!preg_match('/^\d{4,12}$/',$login))$err='The MT5 account number should be digits only.';
elseif(!preg_match('/^[A-Za-z0-9 ._-]{3,60}$/',$server))$err='Please type your broker server exactly as shown in MT5, for example Exness-MT5Real.';
elseif(strlen($pw)<4||strlen($pw)>64)$err='Please enter your MT5 master password.';
elseif(empty($_POST['consent']))$err='Please tick the box to confirm you understand and agree.';
if($err!=='')ea_out(array('ok'=>false,'error'=>$err),400);
$ok=false;foreach($s['plans'] as $p){if($p['id']===$plan)$ok=true;}
if(!$ok)$plan=$s['plans'][0]['id'];
$ipk=hash('sha256',(isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'?').'pl');$rate=ea_read('rate',array());$now=time();
foreach($rate as $k=>$v){$rate[$k]=array_values(array_filter($v,function($t)use($now){return $now-$t<3600;}));if(!count($rate[$k]))unset($rate[$k]);}
if(isset($rate[$ipk])&&count($rate[$ipk])>=5)ea_out(array('ok'=>false,'error'=>'Too many attempts. Please try again later.'),429);
$rate[$ipk][]=$now;ea_write('rate',$rate);
if(!function_exists('sodium_crypto_box_seal'))ea_out(array('ok'=>false,'error'=>'The server cannot encrypt passwords yet. Please contact us.'),500);
$pk=base64_decode($s['public_key'],true);
if($pk===false||strlen($pk)!==SODIUM_CRYPTO_BOX_PUBLICKEYBYTES)ea_out(array('ok'=>false,'error'=>'Subscriptions are not set up correctly yet. Please contact us.'),500);
$sealed=base64_encode(sodium_crypto_box_seal($pw,$pk));
if(function_exists('sodium_memzero'))@sodium_memzero($pw);
$ref='EA-'.strtoupper(bin2hex(random_bytes(3)));
$res=ea_update(function(&$d)use($name,$contact,$email,$login,$server,$sealed,$plan,$payref,$ref){
 foreach($d['subs'] as $x){if($x['login']===$login&&$x['server']===$server)return 'That account is already registered. Please contact us if you need help.';}
 $d['subs'][]=array('id'=>bin2hex(random_bytes(6)),'ref'=>$ref,'name'=>$name,'contact'=>$contact,'email'=>$email,'login'=>$login,'server'=>$server,'sealed'=>$sealed,'plan'=>$plan,'submitted'=>time(),'pay_ref'=>$payref,'paid'=>false,'paid_until'=>0,'vps'=>'','slot'=>0,'desired'=>'stop','state'=>'idle','live'=>array(),'rules'=>ea_rules(),'setfile'=>'','note'=>'','msg'=>'');
 ea_log($d,'web','New subscriber '.$ref.' ('.$name.')');return true;});
if($res===false)ea_out(array('ok'=>false,'error'=>'Could not save your request. Please try again or contact us.'),500);
if(isset($res['err']))ea_out(array('ok'=>false,'error'=>$res['err']),409);
if($s['notify']!==''&&filter_var($s['notify'],FILTER_VALIDATE_EMAIL))@mail($s['notify'],'New EA subscriber '.$ref,"Name: $name\nContact: $contact\nAccount: $login\nPlan: $plan\nPayment reference: $payref\n");
ea_out(array('ok'=>true,'ref'=>$ref,'pay_text'=>$s['pay_text']));

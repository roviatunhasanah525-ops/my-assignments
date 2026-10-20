<?php
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
$token=(string)($_POST['csrf_token']??'');
if($_SERVER['REQUEST_METHOD']!=='POST'||empty($_SESSION['csrf_token'])||!hash_equals($_SESSION['csrf_token'],$token)){header('Location: index.php');exit;}
$_SESSION=[];
if(ini_get('session.use_cookies')){$params=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);}
session_destroy();header('Location: login.php');exit;

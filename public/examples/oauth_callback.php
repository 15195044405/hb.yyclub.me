<?php
use EasyWeChat\Foundation\Application;
$config = \think\Config::get("wechat");
$app = new Application($config);
$oauth = $app->oauth;
// 获取 OAuth 授权结果用户信息
$user = $oauth->user();
$_SESSION['wechat_user'] = $user->toArray();
$targetUrl = empty($_SESSION['target_url']) ? '/' : $_SESSION['target_url'];
header('location:'. $targetUrl); // 跳转到 user/profile
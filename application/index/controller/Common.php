<?php
/**
 * Created by PhpStorm.
 * User: zavier
 * Date: 2017/7/13
 * Time: 上午11:13
 */

namespace app\index\controller;

use EasyWeChat\Foundation\Application;
use think\Config;

class Common
{

//    /**
//     * 获取用户的信息
//     * 返回json格式数据
//     * @return $this|\think\response\Json
//     */
//    static public function getUserInfo()
//    {
//        $options = Config::get('wechat');
//        $app = new Application($options);
//        $oauth = $app->oauth;
//        // 未登录
//        if (empty($_SESSION['wechat_user'])) {
//            $_SESSION['target_url'] = '/index.php/index/common/getUserInfo';
//            return $oauth->redirect()->send();
//            // 这里不一定是return，如果你的框架action不是返回内容的话你就得使用
////             $oauth->redirect()->send();
//        }else{
//            return json($_SESSION['wechat_user']);
//        }
//    }

    /**
     * 回调接口
     */
    public function oauth_callback()
    {
        $config = Config::get("wechat");
        $app = new Application($config);
        $oauth = $app->oauth;
        // 获取 OAuth 授权结果用户信息
        $user = $oauth->user();
        $_SESSION['wechat_user'] = $user->toArray();
        $targetUrl = empty($_SESSION['target_url']) ? '/' : $_SESSION['target_url'];
        header('location:'. $targetUrl); // 跳转到 user/profile
        exit;
    }
}
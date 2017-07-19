<?php

namespace app\index\controller;

use app\index\model\User;
use EasyWeChat\Foundation\Application;
use think\Config;


class Index
{
    public function getUserInfo()
    {
        $options = Config::get('wechat');
        $app = new Application($options);
        $oauth = $app->oauth;
        // 未登录
        if (empty($_SESSION['wechat_user'])) {
            $_SESSION['target_url'] = '/index.php/index/index/getUserInfo';
            return $oauth->redirect()->send();
            // 这里不一定是return，如果你的框架action不是返回内容的话你就得使用
//             $oauth->redirect()->send();
        }
        // 已经获取的用户信息写入数据库
        $userInfo = $_SESSION['wechat_user']['original'];
        $getUser = User::get(['openid' => $userInfo['openid']]);
        // 从数据库查询当前用户是否存在,存在更新，不存在新增
        $user = new User();
        if ($getUser) {
            $updateTime = date('Y-m-d H:i:s',strtotime( $getUser->updatetime."   +1   hour"));;
            // 做个时间限制 每次更新都必须大于1小时
            if($updateTime < time()){
                $userInfo['updatetime'] = date('Y-m-d H:i:s',time());
                $user->allowField(true)->save($userInfo, ['id' => $getUser->id]);
            }
        } else {
            $userInfo['updatetime'] = date('Y-m-d H:i:s',time());
            $user->allowField(true)->save($userInfo);
        }

    }

}

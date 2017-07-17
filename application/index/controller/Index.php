<?php
namespace app\index\controller;

use EasyWeChat\Foundation\Application;


class Index
{
    public function index()
    {
        $options = require(APP_PATH.'/extra/wechat.php');
        $app = new Application($options);
        return view('index/index');
    }

    public function test(){
        echo 1;
    }
}

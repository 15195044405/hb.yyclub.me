<?php

namespace app\index\controller;

use app\index\model\Arr;
use app\index\model\Click;
use app\index\model\Pay;
use app\index\model\User;
use app\index\model\UserLog;
use EasyWeChat\Foundation\Application;
use think\cache\driver\Redis;
use think\Config;
use think\Log;
use think\Request;


class Index
{
    function __construct()
    {
        $level = Request::instance()->param('level');
        if (!$level && !Request::instance()->isPost()) {
            echo '请从官方入口进入';
            exit;
        }
        if ($_SERVER['HTTP_HOST'] == 'localhost.findayi.yyclub.me') {
            $_SESSION['wechat_user']['original']['openid'] = $this->make_openid('20');
            $_SESSION['wechat_user']['original']['nickname'] = '测试数据';
        }
        if (empty($_SESSION[$level])) {
            $hbArr = new Arr();
            $hb_arr = $hbArr->where("`level` = '$level' and `status` = 1")->find();
            // 把红包数据放入session
            if ($hb_arr) {
                $_SESSION[$level] = json_decode($hb_arr->json_arr, true);
            } else {
                $this->hb_amount($level);
            }
//            dump($_SESSION[$level]);
        }
        $pay = new Pay();
        $pay->where("`status` = 1 and `key` = '$level'")->setInc('click');
        $click = new Click();
        $click->key = $level;
        $click->save();
    }

    public function getUserInfo()
    {
        $level = Request::instance()->param('level');
        $options = Config::get('wechat');
        $app = new Application($options);
        $oauth = $app->oauth;
        // 未登录
        if (empty($_SESSION['wechat_user'])) {
            $_SESSION['target_url'] = '/hongbao/public/index.php/index/index/getUserInfo/level/' . $level;
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
            $updateTime = date('Y-m-d H:i:s', strtotime($getUser->updatetime . "   +1   hour"));;
            // 做个时间限制 每次更新都必须大于1小时
            if ($updateTime < time() || !$getUser->from) {
                $userInfo['updatetime'] = date('Y-m-d H:i:s', time());
                $userInfo['from'] = $level;
                $user->allowField(true)->save($userInfo, ['id' => $getUser->id]);
            }
        } else {
            $userInfo['updatetime'] = date('Y-m-d H:i:s', time());
            $userInfo['from'] = $level;
            $user->allowField(true)->save($userInfo);
        }
        if (!$level) $level = Request::instance()->param('level');
        return view('index', ['level' => $level, 'time' => time()]);
    }

    public function submit()
    {
        if (Request::instance()->isPost()) {
            $input = Request::instance()->post()["data"];
            if (!$input) {
                echo '选项不能为空';
                exit;
            }
            if (!strstr($input, '1-3')) {
                echo '答案有误，请检查后重新提交！';
                exit;
            } elseif (!strstr($input, '2-2')) {
                echo '答案有误，请检查后重新提交！';
                exit;
            } elseif (!strstr($input, '3-3')) {
                echo '答案有误，请检查后重新提交！';
                exit;
            } elseif (!strstr($input, '4-2')) {
                echo '答案有误，请检查后重新提交！';
                exit;
            } elseif (!strstr($input, '6-1') || !strstr($input, '6-2') || !strstr($input, '6-3') || !strstr($input, '6-4')) {
                echo '答案有误，请检查后重新提交！';
                exit;
            } else {
                echo '全对';
                exit;
            }
        }
    }

    public function index()
    {
        return view('doing', ['level' => 's9aKw8', 'time' => '1502380888']);
        echo '链接错误';
    }

    public function doing()
    {
        $level = Request::instance()->param('level');
        $options = Config::get('wechat');
        $app = new Application($options);
        $oauth = $app->oauth;
        // 未登录
        if (empty($_SESSION['wechat_user'])) {
            $_SESSION['target_url'] = '/hongbao/public/index.php/index/index/doing/level/' . $level;
            return $oauth->redirect()->send();
            // 这里不一定是return，如果你的框架action不是返回内容的话你就得使用
//             $oauth->redirect()->send();
        }
        $level = Request::instance()->param('level');
        return view('doing', ['level' => $level]);
    }

    /**
     * 发送红包
     */
    public function pay()
    {
        if (Request::instance()->isPost()) {
            $level = Request::instance()->post('level');
            if (!$level) {
                echo '链接错误';
                exit;
            } else {
                $openid = $userInfo = $_SESSION['wechat_user']['original']['openid'];
                $userLog = new UserLog();
                $userLogInfo = $userLog->where("`form` = '" . $level . "' and `openid`='" . $openid . "'")->order('id', 'desc')->limit(0, 1)->find();

                // 红包金额
                $key = array_rand($_SESSION[$level]);
                $total = $_SESSION[$level][$key];

                $pay = new Pay();
                $payInfo = $pay->where("`key`='" . $level . "' and `status` = '1' and `starttime` < '" . time() . "' and `endtime` > '" . time() . "'")->find();

                if ($userLogInfo && $payInfo) {
                    $createTime = strtotime($userLogInfo->createtime);
                    if ($payInfo->starttime < $createTime && $createTime < $payInfo->endtime) {
                        $thisTime = date('z', time());
                        if ($thisTime <= date('z', strtotime($userLogInfo->createtime))) {
                            echo '当前时间无法重复领取红包';
                            exit;
                        }
                    }
                }

                // 余额判断
                if (!$payInfo) {
                    echo '非活动时间，请查看活动说明。';
                    exit;
                } else if (($payInfo->total) < 100) {
                    echo '很遗憾没有抽中。。。';
                    exit;
                } elseif ($payInfo->total == 0) {
                    echo '活动已结束！';
                    exit;
                } else {
                    $payInfo->total = $payInfo->total - $total;
                }

                $nickname = $userInfo = $_SESSION['wechat_user']['original']['nickname'];
                $luckyMoneyData = [
                    'mch_billno' => 'D' . time() . rand(1000, 9999),
                    'send_name' => '中国银行',
                    're_openid' => $openid,
                    'total_num' => 1,  //固定为1，可不传
                    'total_amount' => $total * 100,  //单位为分，不小于100
                    'wishing' => '投资国债 安全理财',
                    'client_ip' => '192.168.0.1',  //可不传，不传则由 SDK 取当前客户端 IP
                    'act_name' => '国债宣传知识问答活动',
                    'remark' => '中国银行',
                    // ...
                ];

                $options = Config::get('wechat');
                $app = new Application($options);
                $luckyMoney = $app->lucky_money;

                // 启动事务
                $userLog->startTrans();
                try {
//                    $userLog = new UserLog();
                    $userLog->openid = $openid;
                    $userLog->nickname = $nickname;
                    $userLog->total_amount = $total;
                    $userLog->form = $level;
                    $userLog->save();

                    try {
                        // 余额更新
                        $payInfo = $pay->save([
                            'total' => $payInfo->total
                        ], ['id' => $payInfo->id]);

                        // session删除已使用数据
                        unset($_SESSION[$level][$key]);

                        // 红包数组更新
                        $hbArr = new Arr();
                        $hb_arr = $hbArr->save([
                            'json_arr' => json_encode($_SESSION[$level], JSON_UNESCAPED_UNICODE)
                        ], ['level' => $level]);

//                        $result = $luckyMoney->sendNormal($luckyMoneyData);
                        $result = true;
                        if ($result && $payInfo && $hb_arr) {
                            // 提交事务
                            $userLog->commit();
                            $pay->commit();
                            Log::write("$result", 'notice');
                            echo '恭喜您抽到了现金红包' . $total . '元';
                            exit;
                        }
                        Log::write("$result", 'notice');
                        // 回滚事务
                        $pay->rollback();
                        $userLog->rollback();
                        echo '参与活动人数过多，换个姿势提交试试看';
                        exit;
                    } catch (\Exception $e) {
                        // 回滚事务
                        $pay->rollback();
                        echo '参与活动人数过多，换个姿势提交试试看';
                        exit;
                    }
                } catch (\Exception $e) {
                    // 回滚事务
                    $userLog->rollback();
                    echo '参与活动人数过多，换个姿势提交试试看';
                    exit;
                }
            }
        }
    }

    /**
     * @param int $length
     * @return string
     */
    public function make_openid($length = 20)
    {
        // 密码字符集，可任意添加你需要的字符 
//        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
//            'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
//            't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
//            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
//            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
//            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '!',
//            '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_',
//            '[', ']', '{', '}', '<', ' > ', '~', '`', '+', '=', ',',
//            '.', ';', ':', '/', '?', '|');
        // 专属openid
        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '-', '_');

        // 在 $chars 中随机取 $length 个数组元素键名
        $password = "";
        for ($i = 0; $i < $length; $i++) {
            $keys = rand(0, (count($chars) - 1));
            // 将 $length 个数组元素连接成字符串 
            $password .= $chars[$keys];
        }
        return $password;
    }

    /**
     * 红包数据处理及放入session
     * @param $level
     * @return bool
     */
    public function hb_amount($level)
    {
        $pay = new Pay();
        $payInfo = $pay->where("`key`='" . $level . "' and `status` = '1' and `starttime` < '" . time() . "' and `endtime` > '" . time() . "'")->find();

        $hb_arr = $this->hb_arr($payInfo->total, $payInfo->num, $payInfo->min, $payInfo->max);
        if ($hb_arr) {
            $hb_json = json_encode($hb_arr, JSON_UNESCAPED_UNICODE);
            $hbArr = new Arr();
            $hbArr->json_arr = $hb_json;
            $hbArr->level = $level;
            if ($hbArr->save()) {
                $_SESSION[$level] = $hb_arr;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 生成红包的具体金额数组
     * @param $money
     * @param $num
     * @param $min
     * @param $max
     * @return array
     */
    public function hb_arr($money, $num, $min, $max)
    {
        // 第一个人
        $firstUse = $num * $min;
        // 剩余金额
        $surplusMoney = $money - $firstUse;

        $arr = array();
        for ($i = 1; $i <= $num; $i++) {
            $arr[] = $min;
        }

        $diff = $surplusMoney * 100;
        while ($diff > 0) {
            $randUid = rand(0, $num - 1);
            if ($arr[$randUid] < $max) {
                $arr[$randUid] += 0.01;
                $arr[$randUid] = number_format($arr[$randUid], 2, '.', '');
                $diff--;
            }
        }
        return $arr;
    }
}

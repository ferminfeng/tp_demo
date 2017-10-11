<?php
namespace xinge;

/*
 * Copyright ? 1998 - 2014 Tencent. All Rights Reserved. 腾讯公司 版权所有
 */

define('ACCESS_ID_IOS', config('XinGeConfig')['access_id_ios']);
define('ACCESS_KEY_IOS', config('XinGeConfig')['access_key_ios']);
define('SECRET_ID_IOS', config('XinGeConfig')['secret_key_ios']);
define('ACCESS_ID_ANDROID', config('XinGeConfig')['access_id_android']);
define('ACCESS_KEY_ANDROID', config('XinGeConfig')['access_id_android']);
define('SECRET_ID_ANDROID', config('XinGeConfig')['access_id_android']);
class XingeApp
{

    const DEVICE_ALL = 0;
    const DEVICE_BROWSER = 1;
    const DEVICE_PC = 2;
    const DEVICE_ANDROID = 3;
    const DEVICE_IOS = 4;
    const DEVICE_WINPHONE = 5;
    const IOSENV_PROD = 1;
    const IOSENV_DEV = 2;
    const IOS_MIN_ID = 2200000000;

    //IOS帐号
    protected $accessId_Ios = ACCESS_ID_IOS;
    protected $accessKey_Ios = ACCESS_KEY_IOS;
    protected $secretKey_Ios = SECRET_ID_IOS;

    //Android帐号
    protected $accessId_Android = ACCESS_ID_ANDROID;
    protected $accessKey_Android = ACCESS_KEY_ANDROID;
    protected $secretKey_Android = SECRET_ID_ANDROID;

    public function __construct($type)
    {
        if ($type == 'ios') {
            $this->accessId = $this->accessId_Ios;
            $this->secretKey = $this->secretKey_Ios;
        } elseif ($type == 'android') {
            $this->accessId = $this->accessId_Android;
            $this->secretKey = $this->secretKey_Android;
        }

    }

    public function __destruct()
    {

    }

    /*******************推送消息******************START****************/


    /**
     * 单个设备下发透传消息
     * 注：透传消息默认不展示
     * @param string $type 设备类型 ios/android
     * @param string $title 推送标题
     * @param string $content 推送描述
     * @param string $token 推送设备device_token
     * @param array $custom 自定义消息体
     * @param int $environment 向iOS设备推送时必填，1表示推送生产环境；2表示推送开发环境。推送Android平台不填或填0
     * @param int $ExpireTime 消息离线存储多久，单位为秒，最长存储时间 3 天。选填，默认为 3天， 设置为 0 等同于使用默认值
     * @return array|mixed
     */
    public function pushSingleDeviceApi($type = '', $title = '', $content = '', $token = '', $custom = [], $environment = 2, $ExpireTime = 86400)
    {
        if (!in_array($type, ['ios', 'android']) || $title == '' || $content == '' || $token == '' || !$custom || !in_array($environment, [0, 1, 2])) {
            return ['ret_code' => -1, 'err_msg' => '参数错误'];
        }

        //区分构建ios/android消息
        if ($type == 'ios') {
            $message = $this->pushMessageIOS($title, $ExpireTime, $custom);
        } else {
            $environment = 0;
            $message = $this->pushMessageAndroid($title, $content, $custom, $ExpireTime);
        }

        $ret = $this->PushSingleDevice($token, $message, $environment);
        return $ret;
    }

    /**
     * 大批量下发给设备
     * 注：透传消息默认不展示
     * @param string $type 设备类型 ios/android
     * @param string $title 推送标题
     * @param string $content 推送描述
     * @param array $tokenList 推送设备device_token列表
     * @param array $custom 自定义消息体
     * @param int $environment 向iOS设备推送时必填，1表示推送生产环境；2表示推送开发环境。推送Android平台不填或填0
     * @param int $ExpireTime 消息离线存储多久，单位为秒，最长存储时间 3 天。选填，默认为 3天， 设置为 0 等同于使用默认值
     * @return array|mixed ['ret_code','err_msg','result' => ['push_id']]
     */
    public function pushDeviceListApi($type = '', $title = '', $content = '', $tokenList = [], $custom = [], $environment = 2, $ExpireTime = 86400)
    {
        if (!in_array($type, ['ios', 'android']) || $title == '' || $content == '' || !$tokenList || !$custom || !in_array($environment, [0, 1, 2])) {
            return array('ret_code' => -1, 'err_msg' => '参数错误');
        }

        //区分构建ios/android消息
        if ($type == 'ios') {
            $message = $this->pushMessageIOS($title, $ExpireTime, $custom);
        } else {
            $environment = 0;
            $message = $this->pushMessageAndroid($title, $content, $custom, $ExpireTime);
        }

        $ret = $this->CreateMultipush($message, $environment);

        if (!($ret['ret_code'] === 0)) {
            return $ret;
        } else {
            $result = [];

            //单次发送token不超过1000个。
            if (count($tokenList) > 1000) {
                $tokenListChunk = array_chunk($tokenList, '1000');
                foreach ($tokenListChunk as $val) {
                    array_push($result, $this->PushDeviceListMultiple($ret['result']['push_id'], $val));
                }
            } else {
                $result = $this->PushDeviceListMultiple($ret['result']['push_id'], $tokenList);
            }

            $result['result']['push_id'] = $ret['result']['push_id'];
            return $result;
        }
    }

    /**
     * 下发所有设备
     * 注：透传消息默认不展示
     * 两次调用之间的时间间隔不能小于3秒
     *
     * @param string $type 设备类型 ios/android
     * @param string $title 推送标题
     * @param string $content 推送描述
     * @param array $custom 自定义消息体
     * @param int $environment 向iOS设备推送时必填，1表示推送生产环境；2表示推送开发环境。推送Android平台不填或填0
     * @param int $ExpireTime 消息离线存储多久，单位为秒，最长存储时间 3 天。选填，默认为 3天， 设置为 0 等同于使用默认值
     * @return array|mixed
     */
    public function pushAllDevicesApi($type = '', $title = '', $content = '', $custom = [], $environment = 2, $ExpireTime = 86400)
    {
        if (!in_array($type, ['ios', 'android']) || $title == '' || $content == '' || !$custom || !in_array($environment, [0, 1, 2])) {
            return array('ret_code' => -1, 'err_msg' => '参数错误');
        }

        //区分构建ios/android消息
        if ($type == 'ios') {
            $message = $this->pushMessageIOS($title, $ExpireTime, $custom);
        } else {
            $environment = 0;
            $message = $this->pushMessageAndroid($title, $content, $custom, $ExpireTime);
        }

        $ret = $this->PushAllDevices(0, $message, $environment);
        return $ret;
    }

    /**
     * 构建IOS消息
     * @param string $title
     * @param int $ExpireTime
     * @param array $custom
     * @return MessageIOS
     */
    private function pushMessageIOS($title = '', $ExpireTime = 86400, $custom = [])
    {

        $mess = new MessageIOS();

        //$mess->setSendTime("2014-03-13 16:00:00");//指定推送时间,格式为year-mon-day hour:min:sec 若小于服务器当前时间，则会立即推送

        $mess->setAlert($title); //推送标题
        //$mess->setAlert(array('key1'=>'value1'));//用法未知//"alert":{"body":"Bob wants to play poker","action-loc-key":"PLAY"}

        $mess->setBadge(1); //设置角标数值
        $mess->setSound("default");//推送声音

        $mess->setExpireTime($ExpireTime); //消息离线存储多久，单位为秒，最长存储时间 3 天。选填，默认为 3天， 设置为 0 等同于使用默认值
        //$custom = array('key1' => 'value1', 'key2' => 'value2');
        $mess->setCustom($custom); //用户自定义 key=>value,用于用户透传消息， 此项内容将记入消息字符数
        //表示允许推送的时间段， (开始时，开始分，结束时，结束分) 如需设置多个时间段，请实例化多个
        $acceptTime = new TimeInterval(0, 0, 23, 59);
        $mess->addAcceptTime($acceptTime);

        //定时推送
        //$raw = '{"xg_max_payload":1,"accept_time":[{"start":{"hour":"20","min":"0"},"end":{"hour":"23","min":"59"}}],"aps":{"alert":"="}}';
        //$mess->setRaw($raw);

        return $mess;
    }

    /**
     * 构建Android消息
     * @param string $title
     * @param string $content
     * @param array $custom
     * @param int $ExpireTime
     * @return Message
     */
    private function pushMessageAndroid($title = '', $content = '', $custom = [], $ExpireTime = 86400)
    {

        $mess = new Message();

        $mess->setTitle($title); //推送标题
        $mess->setContent($content); //推送内容
        $mess->setType(2); //通知类型 1：通知 2：透传消息。iOS平台请填0
        $mess->setExpireTime($ExpireTime); //消息离线存储多久，单位为秒，最长存储时间 3 天。选填，默认为 3天， 设置为 0 等同于使用默认值

        $style = new Style(0, 1, 1, 0, 0); //含义：样式编号0，响铃，震动，不可从通知栏清除，不影响先前通知
        $mess->setStyle($style);

        //$custom = array('key1' => 'value1', 'key2' => 'value2');
        $mess->setCustom($custom); //用户自定义 key=>value,用于用户透传消息， 此项内容将记入消息字符数
        //表示允许推送的时间段， (开始时，开始分，结束时，结束分) 如需设置多个时间段，请实例化多个
        $acceptTime = new TimeInterval(0, 0, 23, 59);
        $mess->addAcceptTime($acceptTime);

        return $mess;
    }

    /*******************推送消息******************END****************/


    /*******************未使用的推送消息******************START****************/


    //单个设备下发通知消息
    public function DemoPushSingleDeviceNotification()
    {

        $push = new \xinge\XingeApp($this->accessId, $this->secretKey);
        $mess = new \xinge\Message();

        $mess->setType(1); //通知类型 1：通知 2：透传消息
        $mess->setTitle("title"); //推送标题
        $mess->setContent("中午"); //推送内容
        $mess->setExpireTime(86400); //消息离线存储多久，单位为秒，最长存储时间 3 天。选填，默认为 3天， 设置为 0 等同于使用默认值

        $style = new \xinge\Style(0, 1, 1, 0, 0); //含义：样式编号0，响铃，震动，不可从通知栏清除，不影响先前通知
        $mess->setStyle($style);

        $action = new \xinge\ClickAction();
        $action->setActionType($action::TYPE_URL); //1打开activity或app本身，2打开url，3打开Intent
        $action->setUrl("http://xg.qq.com");
        #打开url需要用户确认
        $action->setComfirmOnUrl(1);
        $mess->setAction($action);

        $custom = array('key1' => 'value1', 'key2' => 'value2');
        $mess->setCustom($custom); //用户自定义 key=>value,用于用户透传消息， 此项内容将记入消息字符数
        //表示允许推送的时间段， (开始时，开始分，结束时，结束分) 如需设置多个时间段，请实例化多个
        $acceptTime1 = new \xinge\TimeInterval(0, 0, 23, 59);
        $mess->addAcceptTime($acceptTime1);

        $ret = $push->PushSingleDevice('token', $mess);
        return ($ret);
    }

    //单个设备下发通知Intent
    //setIntent()的内容需要使用intent.toUri(Intent.URI_INTENT_SCHEME)方法来得到序列化后的Intent(自定义参数也包含在Intent内）
    //终端收到后通过intent.parseUri()来反序列化得到Intent
    public function DemoPushSingleDeviceNotificationIntent()
    {
        $push = new XingeApp(000, 'secret_key');
        $mess = new Message();
        $mess->setExpireTime(86400);
        $mess->setType(Message::TYPE_NOTIFICATION);
        $mess->setTitle("title");
        $mess->setContent("通知点击执行Intent测试");
        $style = new Style(0);
        $style = new Style(0, 1, 1, 0);
        $action = new ClickAction();
        $action->setActionType(ClickAction::TYPE_INTENT);
        $action->setIntent('intent:10086#Intent;scheme=tel;action=android.intent.action.DIAL;S.key=value;end');
        $mess->setStyle($style);
        $mess->setAction($action);
        $ret = $push->PushSingleDevice('token', $mess);
        return ($ret);
    }

    //下发单个账号
    public function DemoPushSingleAccount()
    {
        $push = new XingeApp(000, 'secret_key');
        $mess = new Message();
        $mess->setExpireTime(86400);
        $mess->setTitle('title');
        $mess->setContent('content');
        $mess->setType(Message::TYPE_MESSAGE);
        $ret = $push->PushSingleAccount(0, 'joelliu', $mess);
        return ($ret);
    }

    //下发多个账号， IOS下发多个账号参考DemoPushSingleAccountIOS进行相应修改
    public function DemoPushAccountList()
    {
        $push = new XingeApp(000, 'secret_key');
        $mess = new Message();
        $mess->setExpireTime(86400);
        $mess->setTitle('title');
        $mess->setContent('content');
        $mess->setType(Message::TYPE_MESSAGE);
        $accountList = array('joelliu');
        $ret = $push->PushAccountList(0, $accountList, $mess);
        return ($ret);
    }

    //下发IOS账号消息
    public function DemoPushSingleAccountIOS()
    {
        $push = new \xinge\XingeApp($this->accessId_Ios, $this->secretKey_Ios);

        //开发环境下 给单个帐号下发通知
        var_dump($push::PushAccountIos($this->accessId_Ios, $this->secretKey_Ios, "content", "714930878", $push::IOSENV_DEV));
        die;

        $mess = new \xinge\MessageIOS();
        $mess->setExpireTime(86400);
        $mess->setAlert("ios test");
        //$mess->setAlert(array('key1'=>'value1'));
        $mess->setBadge(1);//设置角标数值
        $mess->setSound("default");//推送声音使用默认声音
        $custom = array('key1' => 'value1', 'key2' => 'value2');
        $mess->setCustom($custom);
        $acceptTime1 = new \xinge\TimeInterval(0, 0, 23, 59);
        $mess->addAcceptTime($acceptTime1);
        $ret = $push->PushSingleAccount(0, '714930878', $mess, $push::IOSENV_DEV);
        return $ret;
    }

    //下发标签选中设备
    public function DemoPushTags()
    {
        $push = new XingeApp(000, 'secret_key');
        $mess = new Message();
        $mess->setExpireTime(86400);
        $mess->setTitle('title');
        $mess->setContent('content');
        $mess->setType(Message::TYPE_MESSAGE);
        $tagList = array('Demo3');
        $ret = $push->PushTags(0, $tagList, 'OR', $mess);
        return ($ret);
    }

    //查询消息推送状态
    public function DemoQueryPushStatus()
    {
        $push = new XingeApp(000, 'secret_key');
        $pushIdList = array('31', '32');
        $ret = $push->QueryPushStatus($pushIdList);
        return ($ret);
    }

    //查询设备数量
    public function DemoQueryDeviceCount()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->QueryDeviceCount();
        return ($ret);
    }

    //查询标签
    public function DemoQueryTags()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->QueryTags(0, 100);
        return ($ret);
    }

    //查询某个tag下token的数量
    public function DemoQueryTagTokenNum()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->QueryTagTokenNum("tag");
        return ($ret);
    }

    //查询某个token的标签
    public function DemoQueryTokenTags()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->QueryTokenTags("token");
        return ($ret);
    }

    //取消定时任务
    public function DemoCancelTimingPush()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->CancelTimingPush("32");
        return ($ret);
    }

    // 设置标签
    public function DemoBatchSetTag()
    {
        // 切记把这里的示例tag和示例token修改为你的真实tag和真实token
        $pairs = array();
        array_push($pairs, new TagTokenPair("tag1", "token00000000000000000000000000000000001"));
        array_push($pairs, new TagTokenPair("tag1", "token00000000000000000000000000000000001"));

        $push = new XingeApp(000, 'secret_key');
        $ret = $push->BatchSetTag($pairs);
        return $ret;
    }

    // 删除标签
    public function DemoBatchDelTag()
    {
        // 切记把这里的示例tag和示例token修改为你的真实tag和真实token
        $pairs = array();
        array_push($pairs, new TagTokenPair("tag1", "token00000000000000000000000000000000001"));
        array_push($pairs, new TagTokenPair("tag1", "token00000000000000000000000000000000001"));

        $push = new XingeApp(000, 'secret_key');
        $ret = $push->BatchDelTag($pairs);
        return $ret;
    }

    //大批量下发给账号 android
    //iOS 请构建MessageIOS 消息
    public function DemoPushAccountListMultipleNotification()
    {
        $push = new XingeApp(000, 'secret_key');
        $mess = new Message();
        $mess->setExpireTime(86400);
        $mess->setTitle('title');
        $mess->setContent('content');
        $mess->setType(Message::TYPE_NOTIFICATION);
        $ret = $push->CreateMultipush($mess, XingeApp::IOSENV_DEV);
        if (!($ret['ret_code'] === 0))
            return $ret;
        else {
            $result = array();
            $accountList1 = array('joelliu', 'joelliu2', 'joelliu3'); //单次发送token不超过1000个。
            array_push($result, $push->PushAccountListMultiple($ret['result']['push_id'], $accountList1));
            $accountList2 = array('joelliu4', 'joelliu5', 'joelliu6');
            array_push($result, $push->PushAccountListMultiple($ret['result']['push_id'], $accountList2));
            return ($result);
        }
    }

    //查询某个token的信息
    public function DemoQueryInfoOfToken()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->QueryInfoOfToken("token");
        return ($ret);
    }

    //查询某个account绑定的token
    public function DemoQueryTokensOfAccount()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->QueryTokensOfAccount("nickName");
        return ($ret);
    }

    //删除某个account绑定的token
    public function DemoDeleteTokenOfAccount()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->DeleteTokenOfAccount("nickName", "token");
        return ($ret);
    }

    //删除某个account绑定的所有token
    public function DemoDeleteAllTokensOfAccount()
    {
        $push = new XingeApp(000, 'secret_key');
        $ret = $push->DeleteAllTokensOfAccount("nickName");
        return ($ret);
    }


    /*******************未使用的推送消息******************END****************/


    /**
     * 使用默认设置推送消息给单个android设备
     */
    public static function PushTokenAndroid($accessId, $secretKey, $title, $content, $token)
    {
        $push = new XingeApp($accessId, $secretKey);
        $mess = new Message();
        $mess->setTitle($title);
        $mess->setContent($content);
        $mess->setType(Message::TYPE_NOTIFICATION);
        $mess->setStyle(new Style(0, 1, 1, 1, 0));
        $action = new ClickAction();
        $action->setActionType(ClickAction::TYPE_ACTIVITY);
        $mess->setAction($action);
        $ret = $push->PushSingleDevice($token, $mess);
        return $ret;
    }

    /**
     * 使用默认设置推送消息给单个ios设备
     */
    public static function PushTokenIos($accessId, $secretKey, $content, $token, $environment)
    {
        $push = new XingeApp($accessId, $secretKey);
        $mess = new MessageIOS();
        $mess->setAlert($content);
        $ret = $push->PushSingleDevice($token, $mess, $environment);
        return $ret;
    }

    /**
     * 使用默认设置推送消息给单个android版账户
     */
    public static function PushAccountAndroid($accessId, $secretKey, $title, $content, $account)
    {
        $push = new XingeApp($accessId, $secretKey);
        $mess = new Message();
        $mess->setTitle($title);
        $mess->setContent($content);
        $mess->setType(Message::TYPE_NOTIFICATION);
        $mess->setStyle(new Style(0, 1, 1, 1, 0));
        $action = new ClickAction();
        $action->setActionType(ClickAction::TYPE_ACTIVITY);
        $mess->setAction($action);
        $ret = $push->PushSingleAccount(0, $account, $mess);
        return $ret;
    }

    /**
     * 使用默认设置推送消息给单个ios版账户
     */
    public static function PushAccountIos($accessId, $secretKey, $content, $account, $environment)
    {
        $push = new XingeApp($accessId, $secretKey);
        $mess = new MessageIOS();
        $mess->setAlert($content);
        $ret = $push->PushSingleAccount(0, $account, $mess, $environment);
        return $ret;
    }

    /**
     * 使用默认设置推送消息给所有设备android版
     */
    public static function PushAllAndroid($accessId, $secretKey, $title, $content)
    {
        $push = new XingeApp($accessId, $secretKey);
        $mess = new Message();
        $mess->setTitle($title);
        $mess->setContent($content);
        $mess->setType(Message::TYPE_NOTIFICATION);
        $mess->setStyle(new Style(0, 1, 1, 1, 0));
        $action = new ClickAction();
        $action->setActionType(ClickAction::TYPE_ACTIVITY);
        $mess->setAction($action);
        $ret = $push->PushAllDevices(0, $mess);
        return $ret;
    }

    /**
     * 使用默认设置推送消息给所有设备ios版
     */
    public static function PushAllIos($accessId, $secretKey, $content, $environment)
    {
        $push = new XingeApp($accessId, $secretKey);
        $mess = new MessageIOS();
        $mess->setAlert($content);
        $ret = $push->PushAllDevices(0, $mess, $environment);
        return $ret;
    }

    /**
     * 使用默认设置推送消息给标签选中设备android版
     */
    public static function PushTagAndroid($accessId, $secretKey, $title, $content, $tag)
    {
        $push = new XingeApp($accessId, $secretKey);
        $mess = new Message();
        $mess->setTitle($title);
        $mess->setContent($content);
        $mess->setType(Message::TYPE_NOTIFICATION);
        $mess->setStyle(new Style(0, 1, 1, 1, 0));
        $action = new ClickAction();
        $action->setActionType(ClickAction::TYPE_ACTIVITY);
        $mess->setAction($action);
        $ret = $push->PushTags(0, array(0 => $tag), 'OR', $mess);
        return $ret;
    }

    /**
     * 使用默认设置推送消息给标签选中设备ios版
     */
    public static function PushTagIos($accessId, $secretKey, $content, $tag, $environment)
    {
        $push = new XingeApp($accessId, $secretKey);
        $mess = new MessageIOS();
        $mess->setAlert($content);
        $ret = $push->PushTags(0, array(0 => $tag), 'OR', $mess, $environment);
        return $ret;
    }

    /**
     * 推送消息给单个设备
     */
    public function PushSingleDevice($deviceToken, $message, $environment = 0)
    {
        $ret = array('ret_code' => -1, 'err_msg' => 'message not valid');

        if (!($message instanceof Message) && !($message instanceof MessageIOS))
            return $ret;
        if (!$this->ValidateMessageType($message)) {
            $ret['err_msg'] = 'message type not fit accessId';
            return $ret;
        }
        if ($message instanceof MessageIOS) {
            if ($environment != XingeApp::IOSENV_DEV && $environment != XingeApp::IOSENV_PROD) {
                $ret['err_msg'] = "ios message environment invalid";
                return $ret;
            }
        }
        if (!$message->isValid())
            return $ret;
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['expire_time'] = $message->getExpireTime();
        $params['send_time'] = $message->getSendTime();
        if ($message instanceof Message)
            $params['multi_pkg'] = $message->getMultiPkg();
        $params['device_token'] = $deviceToken;
        $params['message_type'] = $message->getType();
        $params['message'] = $message->toJson();
        $params['timestamp'] = time();
        $params['environment'] = $environment;

        return $this->callRestful(self::RESTAPI_PUSHSINGLEDEVICE, $params);
    }

    /**
     * 推送消息给单个账户
     */
    public function PushSingleAccount($deviceType, $account, $message, $environment = 0)
    {
        $ret = array('ret_code' => -1);
        if (!is_int($deviceType) || $deviceType < 0 || $deviceType > 5) {
            $ret['err_msg'] = 'deviceType not valid';
            return $ret;
        }
        if (!is_string($account) || empty($account)) {
            $ret['err_msg'] = 'account not valid';
            return $ret;
        }
        if (!($message instanceof Message) && !($message instanceof MessageIOS)) {
            $ret['err_msg'] = 'message is not android or ios';
            return $ret;
        }
        if (!$this->ValidateMessageType($message)) {
            $ret['err_msg'] = 'message type not fit accessId';
            return $ret;
        }
        if ($message instanceof MessageIOS) {
            if ($environment != XingeApp::IOSENV_DEV && $environment != XingeApp::IOSENV_PROD) {
                $ret['err_msg'] = "ios message environment invalid";
                return $ret;
            }
        }
        if (!$message->isValid()) {
            $ret['err_msg'] = 'message not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['expire_time'] = $message->getExpireTime();
        $params['send_time'] = $message->getSendTime();
        if ($message instanceof Message)
            $params['multi_pkg'] = $message->getMultiPkg();
        $params['device_type'] = $deviceType;
        $params['account'] = $account;
        $params['message_type'] = $message->getType();
        $params['message'] = $message->toJson();
        $params['timestamp'] = time();
        $params['environment'] = $environment;

        return $this->callRestful(self::RESTAPI_PUSHSINGLEACCOUNT, $params);
    }

    /**
     * 推送消息给多个账户
     */
    public function PushAccountList($deviceType, $accountList, $message, $environment = 0)
    {
        $ret = array('ret_code' => -1);
        if (!is_int($deviceType) || $deviceType < 0 || $deviceType > 5) {
            $ret['err_msg'] = 'deviceType not valid';
            return $ret;
        }
        if (!is_array($accountList) || empty($accountList)) {
            $ret['err_msg'] = 'accountList not valid';
            return $ret;
        }
        if (!($message instanceof Message) && !($message instanceof MessageIOS)) {
            $ret['err_msg'] = 'message is not android or ios';
            return $ret;
        }
        if (!$this->ValidateMessageType($message)) {
            $ret['err_msg'] = 'message type not fit accessId';
            return $ret;
        }
        if ($message instanceof MessageIOS) {
            if ($environment != XingeApp::IOSENV_DEV && $environment != XingeApp::IOSENV_PROD) {
                $ret['err_msg'] = "ios message environment invalid";
                return $ret;
            }
        }
        if (!$message->isValid()) {
            $ret['err_msg'] = 'message not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['expire_time'] = $message->getExpireTime();
        if ($message instanceof Message)
            $params['multi_pkg'] = $message->getMultiPkg();
        $params['device_type'] = $deviceType;
        $params['account_list'] = json_encode($accountList);
        $params['message_type'] = $message->getType();
        $params['message'] = $message->toJson();
        $params['timestamp'] = time();
        $params['environment'] = $environment;

        return $this->callRestful(self::RESTAPI_PUSHACCOUNTLIST, $params);
    }

    /**
     * 推送消息给APP所有设备
     */
    public function PushAllDevices($deviceType, $message, $environment = 0)
    {
        $ret = array('ret_code' => -1, 'err_msg' => 'message not valid');
        if (!is_int($deviceType) || $deviceType < 0 || $deviceType > 5) {
            $ret['err_msg'] = 'deviceType not valid';
            return $ret;
        }

        if (!($message instanceof Message) && !($message instanceof MessageIOS))
            return $ret;
        if (!$this->ValidateMessageType($message)) {
            $ret['err_msg'] = 'message type not fit accessId';
            return $ret;
        }
        if ($message instanceof MessageIOS) {
            if ($environment != XingeApp::IOSENV_DEV && $environment != XingeApp::IOSENV_PROD) {
                $ret['err_msg'] = "ios message environment invalid";
                return $ret;
            }
        }
        if (!$message->isValid())
            return $ret;
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['expire_time'] = $message->getExpireTime();
        $params['send_time'] = $message->getSendTime();
        if ($message instanceof Message)
            $params['multi_pkg'] = $message->getMultiPkg();
        $params['device_type'] = $deviceType;
        $params['message_type'] = $message->getType();
        $params['message'] = $message->toJson();
        $params['timestamp'] = time();
        $params['environment'] = $environment;

        if (!is_null($message->getLoopInterval()) && $message->getLoopInterval() > 0 && !is_null($message->getLoopTimes()) && $message->getLoopTimes() > 0) {
            $params['loop_interval'] = $message->getLoopInterval();
            $params['loop_times'] = $message->getLoopTimes();
        }
        //var_dump($params);

        return $this->callRestful(self::RESTAPI_PUSHALLDEVICE, $params);
    }

    /**
     * 推送消息给指定tags的设备
     * 若要推送的tagList只有一项，则tagsOp应为OR
     */
    public function PushTags($deviceType, $tagList, $tagsOp, $message, $environment = 0)
    {
        $ret = array('ret_code' => -1, 'err_msg' => 'message not valid');
        if (!is_int($deviceType) || $deviceType < 0 || $deviceType > 5) {
            $ret['err_msg'] = 'deviceType not valid';
            return $ret;
        }
        if (!is_array($tagList) || empty($tagList)) {
            $ret['err_msg'] = 'tagList not valid';
            return $ret;
        }
        if (!is_string($tagsOp) || ($tagsOp != 'AND' && $tagsOp != 'OR')) {
            $ret['err_msg'] = 'tagsOp not valid';
            return $ret;
        }

        if (!($message instanceof Message) && !($message instanceof MessageIOS))
            return $ret;
        if (!$this->ValidateMessageType($message)) {
            $ret['err_msg'] = 'message type not fit accessId';
            return $ret;
        }
        if ($message instanceof MessageIOS) {
            if ($environment != XingeApp::IOSENV_DEV && $environment != XingeApp::IOSENV_PROD) {
                $ret['err_msg'] = "ios message environment invalid";
                return $ret;
            }
        }
        if (!$message->isValid())
            return $ret;

        $params = array();
        $params['access_id'] = $this->accessId;
        $params['expire_time'] = $message->getExpireTime();
        $params['send_time'] = $message->getSendTime();
        if ($message instanceof Message)
            $params['multi_pkg'] = $message->getMultiPkg();
        $params['device_type'] = $deviceType;
        $params['message_type'] = $message->getType();
        $params['tags_list'] = json_encode($tagList);
        $params['tags_op'] = $tagsOp;
        $params['message'] = $message->toJson();
        $params['timestamp'] = time();
        $params['environment'] = $environment;

        if (!is_null($message->getLoopInterval()) && $message->getLoopInterval() > 0 && !is_null($message->getLoopTimes()) && $message->getLoopTimes() > 0) {
            $params['loop_interval'] = $message->getLoopInterval();
            $params['loop_times'] = $message->getLoopTimes();
        }

        return $this->callRestful(self::RESTAPI_PUSHTAGS, $params);
    }

    /**
     * 创建批量推送任务
     */
    public function CreateMultipush($message, $environment = 0)
    {
        $ret = array('ret_code' => -1);
        if (!($message instanceof Message) && !($message instanceof MessageIOS)) {
            $ret['err_msg'] = 'message is not android or ios';
            return $ret;
        }
        if (!$this->ValidateMessageType($message)) {
            $ret['err_msg'] = 'message type not fit accessId';
            return $ret;
        }
        if ($message instanceof MessageIOS) {
            if ($environment != XingeApp::IOSENV_DEV && $environment != XingeApp::IOSENV_PROD) {
                $ret['err_msg'] = "ios message environment invalid";
                return $ret;
            }
        }
        if (!$message->isValid()) {
            $ret['err_msg'] = 'message not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['expire_time'] = $message->getExpireTime();
        if ($message instanceof Message)
            $params['multi_pkg'] = $message->getMultiPkg();
        $params['message_type'] = $message->getType();
        $params['message'] = $message->toJson();
        $params['timestamp'] = time();
        $params['environment'] = $environment;

        return $this->callRestful(self::RESTAPI_CREATEMULTIPUSH, $params);
    }

    /**
     * 按帐号大批量推送
     */
    public function PushAccountListMultiple($pushId, $accountList)
    {
        $pushId = intval($pushId);
        $ret = array('ret_code' => -1);
        if ($pushId <= 0) {
            $ret['err_msg'] = 'pushId not valid';
            return $ret;
        }
        if (!is_array($accountList) || empty($accountList)) {
            $ret['err_msg'] = 'accountList not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['push_id'] = $pushId;
        $params['account_list'] = json_encode($accountList);
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_PUSHACCOUNTLISTMULTIPLE, $params);
    }

    /**
     * 按Token大批量推送
     */
    public function PushDeviceListMultiple($pushId, $deviceList)
    {
        //$pushId = intval($pushId);
        $pushId = trim($pushId);
        $ret = array('ret_code' => -1);
        if ($pushId <= 0) {
            $ret['err_msg'] = 'pushId not valid';
            return $ret;
        }
        if (!is_array($deviceList) || empty($deviceList)) {
            $ret['err_msg'] = 'deviceList not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['push_id'] = $pushId;
        $params['device_list'] = json_encode($deviceList);
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_PUSHDEVICELISTMULTIPLE, $params);
    }

    /**
     * 查询消息推送状态
     * @param array $pushIdList pushId(string)数组
     */
    public function QueryPushStatus($pushIdList)
    {
        $ret = array('ret_code' => -1);
        $idList = array();
        if (!is_array($pushIdList) || empty($pushIdList)) {
            $ret['err_msg'] = 'pushIdList not valid';
            return $ret;
        }
        foreach ($pushIdList as $pushId) {
            $idList[] = array('push_id' => $pushId);
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['push_ids'] = json_encode($idList);
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_QUERYPUSHSTATUS, $params);
    }

    /**
     * 查询应用覆盖的设备数
     */
    public function QueryDeviceCount()
    {
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_QUERYDEVICECOUNT, $params);
    }

    /**
     * 查询应用标签
     */
    public function QueryTags($start = 0, $limit = 100)
    {
        $ret = array('ret_code' => -1);
        if (!is_int($start) || !is_int($limit)) {
            $ret['err_msg'] = 'start or limit not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['start'] = $start;
        $params['limit'] = $limit;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_QUERYTAGS, $params);
    }

    /**
     * 查询标签下token数量
     */
    public function QueryTagTokenNum($tag)
    {
        $ret = array('ret_code' => -1);
        if (!is_string($tag)) {
            $ret['err_msg'] = 'tag is not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['tag'] = $tag;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_QUERYTAGTOKENNUM, $params);
    }

    /**
     * 查询token的标签
     */
    public function QueryTokenTags($deviceToken)
    {
        $ret = array('ret_code' => -1);
        if (!is_string($deviceToken)) {
            $ret['err_msg'] = 'deviceToken is not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['device_token'] = $deviceToken;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_QUERYTOKENTAGS, $params);
    }

    /**
     * 取消定时发送
     */
    public function CancelTimingPush($pushId)
    {
        $ret = array('ret_code' => -1);
        if (!is_string($pushId) || empty($pushId)) {
            $ret['err_msg'] = 'pushId not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['push_id'] = $pushId;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_CANCELTIMINGPUSH, $params);
    }

    //json转换为数组
    protected function json2Array($json)
    {
        $json = stripslashes($json);
        return json_decode($json, true);
    }

    protected function callRestful($url, $params)
    {
        $paramsBase = new ParamsBase($params);
        $sign = $paramsBase->generateSign(RequestBase::METHOD_POST, $url, $this->secretKey);
        $params['sign'] = $sign;

        $requestBase = new RequestBase();
        $ret = $this->json2Array($requestBase->exec($url, $params, RequestBase::METHOD_POST));

        return $ret;
    }

    private function ValidateToken($token)
    {
        if (($this->accessId) >= 2200000000) {
            return strlen($token) == 64;
        } else {
            return (strlen($token) == 40 || strlen($token) == 64);
        }
    }

    public function InitParams()
    {

        $params = array();
        $params['access_id'] = $this->accessId;
        $params['timestamp'] = time();

        return $params;
    }

    public function BatchSetTag($tagTokenPairs)
    {
        $ret = array('ret_code' => -1);

        foreach ($tagTokenPairs as $pair) {
            if (!($pair instanceof TagTokenPair)) {
                $ret['err_msg'] = 'tag-token pair type error!';
                return $ret;
            }
            if (!$this->ValidateToken($pair->token)) {
                $ret['err_msg'] = sprintf("invalid token %s", $pair->token);
                return $ret;
            }
        }
        $params = $this->InitParams();

        $tag_token_list = array();
        foreach ($tagTokenPairs as $pair) {
            array_push($tag_token_list, array($pair->tag, $pair->token));
        }
        $params['tag_token_list'] = json_encode($tag_token_list);

        return $this->callRestful(self::RESTAPI_BATCHSETTAG, $params);
    }

    public function BatchDelTag($tagTokenPairs)
    {
        $ret = array('ret_code' => -1);

        foreach ($tagTokenPairs as $pair) {
            if (!($pair instanceof TagTokenPair)) {
                $ret['err_msg'] = 'tag-token pair type error!';
                return $ret;
            }
            if (!$this->ValidateToken($pair->token)) {
                $ret['err_msg'] = sprintf("invalid token %s", $pair->token);
                return $ret;
            }
        }
        $params = $this->InitParams();

        $tag_token_list = array();
        foreach ($tagTokenPairs as $pair) {
            array_push($tag_token_list, array($pair->tag, $pair->token));
        }
        $params['tag_token_list'] = json_encode($tag_token_list);

        return $this->callRestful(self::RESTAPI_BATCHDELTAG, $params);
    }

    public function QueryInfoOfToken($deviceToken)
    {
        $ret = array('ret_code' => -1);
        if (!is_string($deviceToken)) {
            $ret['err_msg'] = 'deviceToken is not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['device_token'] = $deviceToken;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_QUERYINFOOFTOKEN, $params);
    }

    public function QueryTokensOfAccount($account)
    {
        $ret = array('ret_code' => -1);
        if (!is_string($account)) {
            $ret['err_msg'] = 'account is not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['account'] = $account;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_QUERYTOKENSOFACCOUNT, $params);
    }

    public function DeleteTokenOfAccount($account, $deviceToken)
    {
        $ret = array('ret_code' => -1);
        if (!is_string($account) || !is_string($deviceToken)) {
            $ret['err_msg'] = 'account or deviceToken is not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['account'] = $account;
        $params['device_token'] = $deviceToken;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_DELETETOKENOFACCOUNT, $params);
    }

    public function DeleteAllTokensOfAccount($account)
    {
        $ret = array('ret_code' => -1);
        if (!is_string($account)) {
            $ret['err_msg'] = 'account is not valid';
            return $ret;
        }
        $params = array();
        $params['access_id'] = $this->accessId;
        $params['account'] = $account;
        $params['timestamp'] = time();

        return $this->callRestful(self::RESTAPI_DELETEALLTOKENSOFACCOUNT, $params);
    }

    private function ValidateMessageType($message)
    {
        if (($this->accessId) >= XingeApp::IOS_MIN_ID and $message instanceof MessageIOS)
            return true;
        else if (($this->accessId) < XingeApp::IOS_MIN_ID and $message instanceof Message)
            return true;
        else
            return false;
    }

    public $accessId = ''; //应用的接入Id
    public $secretKey = ''; //应用的skey

    const RESTAPI_PUSHSINGLEDEVICE = 'http://openapi.xg.qq.com/v2/push/single_device';
    const RESTAPI_PUSHSINGLEACCOUNT = 'http://openapi.xg.qq.com/v2/push/single_account';
    const RESTAPI_PUSHACCOUNTLIST = 'http://openapi.xg.qq.com/v2/push/account_list';
    const RESTAPI_PUSHALLDEVICE = 'http://openapi.xg.qq.com/v2/push/all_device';
    const RESTAPI_PUSHTAGS = 'http://openapi.xg.qq.com/v2/push/tags_device';
    const RESTAPI_QUERYPUSHSTATUS = 'http://openapi.xg.qq.com/v2/push/get_msg_status';
    const RESTAPI_QUERYDEVICECOUNT = 'http://openapi.xg.qq.com/v2/application/get_app_device_num';
    const RESTAPI_QUERYTAGS = 'http://openapi.xg.qq.com/v2/tags/query_app_tags';
    const RESTAPI_CANCELTIMINGPUSH = 'http://openapi.xg.qq.com/v2/push/cancel_timing_task';
    const RESTAPI_BATCHSETTAG = 'http://openapi.xg.qq.com/v2/tags/batch_set';
    const RESTAPI_BATCHDELTAG = 'http://openapi.xg.qq.com/v2/tags/batch_del';
    const RESTAPI_QUERYTOKENTAGS = 'http://openapi.xg.qq.com/v2/tags/query_token_tags';
    const RESTAPI_QUERYTAGTOKENNUM = 'http://openapi.xg.qq.com/v2/tags/query_tag_token_num';
    const RESTAPI_CREATEMULTIPUSH = 'http://openapi.xg.qq.com/v2/push/create_multipush';
    const RESTAPI_PUSHACCOUNTLISTMULTIPLE = 'http://openapi.xg.qq.com/v2/push/account_list_multiple';
    const RESTAPI_PUSHDEVICELISTMULTIPLE = 'http://openapi.xg.qq.com/v2/push/device_list_multiple';
    const RESTAPI_QUERYINFOOFTOKEN = 'http://openapi.xg.qq.com/v2/application/get_app_token_info';
    const RESTAPI_QUERYTOKENSOFACCOUNT = 'http://openapi.xg.qq.com/v2/application/get_app_account_tokens';
    const RESTAPI_DELETETOKENOFACCOUNT = 'http://openapi.xg.qq.com/v2/application/del_app_account_tokens';
    const RESTAPI_DELETEALLTOKENSOFACCOUNT = 'http://openapi.xg.qq.com/v2/application/del_app_account_all_tokens';

}

class TagTokenPair
{

    public function __construct($tag, $token)
    {
        $this->tag = strval($tag);
        $this->token = strval($token);
    }

    public function __destruct()
    {

    }

    public $tag;
    public $token;

}

class Message
{

    public function __construct()
    {
        $this->m_acceptTimes = array();
        $this->m_multiPkg = 0;
        $this->m_raw = "";
        $this->m_style = new Style(0);
        $this->m_action = new ClickAction();
    }

    public function __destruct()
    {

    }

    public function setTitle($title)
    {
        $this->m_title = $title;
    }

    public function setContent($content)
    {
        $this->m_content = $content;
    }

    public function setExpireTime($expireTime)
    {
        $this->m_expireTime = $expireTime;
    }

    public function getExpireTime()
    {
        return $this->m_expireTime;
    }

    public function setSendTime($sendTime)
    {
        $this->m_sendTime = $sendTime;
    }

    public function getSendTime()
    {
        return $this->m_sendTime;
    }

    public function addAcceptTime($acceptTime)
    {
        $this->m_acceptTimes[] = $acceptTime;
    }

    public function acceptTimeToJson()
    {
        $ret = array();
        foreach ($this->m_acceptTimes as $acceptTime) {
            $ret[] = $acceptTime->toArray();
        }
        return $ret;
    }

    /**
     * 消息类型
     * @param int $type 1：通知 2：透传消息
     */
    public function setType($type)
    {
        $this->m_type = $type;
    }

    public function getType()
    {
        return $this->m_type;
    }

    public function setMultiPkg($multiPkg)
    {
        $this->m_multiPkg = $multiPkg;
    }

    public function getMultiPkg()
    {
        return $this->m_multiPkg;
    }

    public function setStyle($style)
    {
        $this->m_style = $style;
    }

    public function setAction($action)
    {
        $this->m_action = $action;
    }

    public function setCustom($custom)
    {
        $this->m_custom = $custom;
    }

    public function setRaw($raw)
    {
        $this->m_raw = $raw;
    }

    public function getLoopInterval()
    {
        return $this->m_loopInterval;
    }

    public function setLoopInterval($loopInterval)
    {
        $this->m_loopInterval = $loopInterval;
    }

    public function getLoopTimes()
    {
        return $this->m_loopTimes;
    }

    public function setLoopTimes($loopTimes)
    {
        $this->m_loopTimes = $loopTimes;
    }

    public function toJson()
    {
        if (!empty($this->m_raw))
            return $this->m_raw;
        $ret = array();
        if ($this->m_type == self::TYPE_NOTIFICATION) {
            $ret['title'] = $this->m_title;
            $ret['content'] = $this->m_content;
            $ret['accept_time'] = $this->acceptTimeToJson();
            $ret['builder_id'] = $this->m_style->getBuilderId();
            $ret['ring'] = $this->m_style->getRing();
            $ret['vibrate'] = $this->m_style->getVibrate();
            $ret['clearable'] = $this->m_style->getClearable();
            $ret['n_id'] = $this->m_style->getNId();

            if (!is_null($this->m_style->getRingRaw())) {
                $ret['ring_raw'] = $this->m_style->getRingRaw();
            }
            $ret['lights'] = $this->m_style->getLights();
            $ret['icon_type'] = $this->m_style->getIconType();
            if (!is_null($this->m_style->getIconRes())) {
                $ret['icon_res'] = $this->m_style->getIconRes();
            }
            $ret['style_id'] = $this->m_style->getStyleId();
            if (!is_null($this->m_style->getSmallIcon())) {
                $ret['small_icon'] = $this->m_style->getSmallIcon();
            }

            $ret['action'] = $this->m_action->toJson();
        } else if ($this->m_type == self::TYPE_MESSAGE) {
            $ret['title'] = $this->m_title;
            $ret['content'] = $this->m_content;
            $ret['accept_time'] = $this->acceptTimeToJson();
        }
        $ret['custom_content'] = $this->m_custom;
        return json_encode($ret);
    }

    public function isValid()
    {
        if (is_string($this->m_raw) && !empty($this->raw))
            return true;
        if (!isset($this->m_title))
            $this->m_title = "";
        else if (!is_string($this->m_title) || empty($this->m_title))
            return false;
        if (!isset($this->m_content))
            $this->m_content = "";
        else if (!is_string($this->m_content) || empty($this->m_content))
            return false;
        if (!is_int($this->m_type) || $this->m_type < self::TYPE_NOTIFICATION || $this->m_type > self::TYPE_MESSAGE)
            return false;
        if (!is_int($this->m_multiPkg) || $this->m_multiPkg < 0 || $this->m_multiPkg > 1)
            return false;
        if ($this->m_type == self::TYPE_NOTIFICATION) {
            if (!($this->m_style instanceof Style) || !($this->m_action instanceof ClickAction))
                return false;
            if (!$this->m_style->isValid() || !$this->m_action->isValid())
                return false;
        }
        if (isset($this->m_expireTime)) {
            if (!is_int($this->m_expireTime) || $this->m_expireTime > 3 * 24 * 60 * 60)
                return false;
        } else {
            $this->m_expireTime = 0;
        }

        if (isset($this->m_sendTime)) {
            if (strtotime($this->m_sendTime) === false)
                return false;
        } else {
            $this->m_sendTime = "2013-12-19 17:49:00";
        }

        foreach ($this->m_acceptTimes as $value) {
            if (!($value instanceof TimeInterval) || !$value->isValid())
                return false;
        }

        if (isset($this->m_custom)) {
            if (!is_array($this->m_custom))
                return false;
        } else {
            $this->m_custom = array();
        }

        if (isset($this->m_loopInterval)) {
            if (!(is_int($this->m_loopInterval) && $this->m_loopInterval > 0)) {
                return false;
            }
        }

        if (isset($this->m_loopTimes)) {
            if (!(is_int($this->m_loopTimes) && $this->m_loopTimes > 0)) {
                return false;
            }
        }

        if (isset($this->m_loopInterval) && isset($this->m_loopTimes)) {
            if (($this->m_loopTimes - 1) * $this->m_loopInterval + 1 > self::MAX_LOOP_TASK_DAYS) {
                return false;
            }
        }

        return true;
    }

    private $m_title;
    private $m_content;
    private $m_expireTime;
    private $m_sendTime;
    private $m_acceptTimes;
    private $m_type;
    private $m_multiPkg;
    private $m_style;
    private $m_action;
    private $m_custom;
    private $m_raw;
    private $m_loopInterval;
    private $m_loopTimes;

    const TYPE_NOTIFICATION = 1;
    const TYPE_MESSAGE = 2;
    const MAX_LOOP_TASK_DAYS = 15;

}

class MessageIOS
{

    public function __construct()
    {
        $this->m_acceptTimes = array();
    }

    public function __destruct()
    {

    }

    public function setExpireTime($expireTime)
    {
        $this->m_expireTime = $expireTime;
    }

    public function getExpireTime()
    {
        return $this->m_expireTime;
    }

    public function setSendTime($sendTime)
    {
        $this->m_sendTime = $sendTime;
    }

    public function getSendTime()
    {
        return $this->m_sendTime;
    }

    public function addAcceptTime($acceptTime)
    {
        $this->m_acceptTimes[] = $acceptTime;
    }

    public function acceptTimeToJson()
    {
        $ret = array();
        foreach ($this->m_acceptTimes as $acceptTime) {
            $ret[] = $acceptTime->toArray();
        }
        return $ret;
    }

    public function setCustom($custom)
    {
        $this->m_custom = $custom;
    }

    public function setRaw($raw)
    {
        $this->m_raw = $raw;
    }

    public function setAlert($alert)
    {
        $this->m_alert = $alert;
    }

    public function setBadge($badge)
    {
        $this->m_badge = $badge;
    }

    public function setSound($sound)
    {
        $this->m_sound = $sound;
    }

    public function getType()
    {
        return 0;
    }

    public function getCategory()
    {
        return $this->m_category;
    }

    public function setCategory($category)
    {
        $this->m_category = $category;
    }

    public function getLoopInterval()
    {
        return $this->m_loopInterval;
    }

    public function setLoopInterval($loopInterval)
    {
        $this->m_loopInterval = $loopInterval;
    }

    public function getLoopTimes()
    {
        return $this->m_loopTimes;
    }

    public function setLoopTimes($loopTimes)
    {
        $this->m_loopTimes = $loopTimes;
    }

    public function toJson()
    {
        if (!empty($this->m_raw))
            return $this->m_raw;
        $ret = $this->m_custom;
        $aps = array();
        $ret['accept_time'] = $this->acceptTimeToJson();
        $aps['alert'] = $this->m_alert;
        if (isset($this->m_badge))
            $aps['badge'] = $this->m_badge;
        if (isset($this->m_sound))
            $aps['sound'] = $this->m_sound;
        if (isset($this->m_category))
            $aps['category'] = $this->m_category;
        $ret['aps'] = $aps;
        return json_encode($ret);
    }

    public function isValid()
    {
        if (isset($this->m_expireTime)) {
            if (!is_int($this->m_expireTime) || $this->m_expireTime > 3 * 24 * 60 * 60)
                return false;
        } else {
            $this->m_expireTime = 0;
        }

        if (isset($this->m_sendTime)) {
            if (strtotime($this->m_sendTime) === false)
                return false;
        } else {
            $this->m_sendTime = "2014-03-13 12:00:00";
        }

        if (!empty($this->m_raw)) {
            if (is_string($this->m_raw))
                return true;
            else
                return false;
        }

        foreach ($this->m_acceptTimes as $value) {
            if (!($value instanceof TimeInterval) || !$value->isValid())
                return false;
        }

        if (isset($this->m_custom)) {
            if (!is_array($this->m_custom))
                return false;
        } else {
            $this->m_custom = array();
        }
        if (!isset($this->m_alert))
            return false;
        if (!is_string($this->m_alert) && !is_array($this->m_alert))
            return false;
        if (isset($this->m_badge)) {
            if (!is_int($this->m_badge))
                return false;
        }
        if (isset($this->m_sound)) {
            if (!is_string($this->m_sound))
                return false;
        }
        if (isset($this->m_loopInterval)) {
            if (!(is_int($this->m_loopInterval) && $this->m_loopInterval > 0)) {
                return false;
            }
        }
        if (isset($this->m_loopTimes)) {
            if (!(is_int($this->m_loopTimes) && $this->m_loopTimes > 0)) {
                return false;
            }
        }
        if (isset($this->m_loopInterval) && isset($this->m_loopTimes)) {
            if (($this->m_loopTimes - 1) * $this->m_loopInterval + 1 > self::MAX_LOOP_TASK_DAYS) {
                return false;
            }
        }

        return true;
    }

    private $m_expireTime;
    private $m_sendTime;
    private $m_acceptTimes;
    private $m_custom;
    private $m_raw;
    private $m_alert;
    private $m_badge;
    private $m_sound;
    private $m_category;
    private $m_loopInterval;
    private $m_loopTimes;

    const MAX_LOOP_TASK_DAYS = 15;

}

class ClickAction
{

    /**
     * 动作类型
     * @param int $actionType 1打开activity或app本身，2打开url，3打开Intent
     */
    public function __construct()
    {
        $this->m_atyAttrIntentFlag = 0;
        $this->m_atyAttrPendingIntentFlag = 0;
        $this->m_confirmOnPackageDownloadUrl = 1;
    }

    public function setActionType($actionType)
    {
        $this->m_actionType = $actionType;
    }

    public function setUrl($url)
    {
        $this->m_url = $url;
    }

    public function setComfirmOnUrl($comfirmOnUrl)
    {
        $this->m_confirmOnUrl = $comfirmOnUrl;
    }

    public function setActivity($activity)
    {
        $this->m_activity = $activity;
    }

    public function setIntent($intent)
    {
        $this->m_intent = $intent;
    }

    public function setAtyAttrIntentFlag($atyAttrIntentFlag)
    {
        $this->m_atyAttrIntentFlag = $atyAttrIntentFlag;
    }

    public function setAtyAttrPendingIntentFlag($atyAttrPendingIntentFlag)
    {
        $this->m_atyAttrPendingIntentFlag = $atyAttrPendingIntentFlag;
    }

    public function setPackageDownloadUrl($packageDownloadUrl)
    {
        $this->m_packageDownloadUrl = $packageDownloadUrl;
    }

    public function setConfirmOnPackageDownloadUrl($confirmOnPackageDownloadUrl)
    {
        $this->m_confirmOnPackageDownloadUrl = $confirmOnPackageDownloadUrl;
    }

    public function setPackageName($packageName)
    {
        $this->m_packageName = $packageName;
    }

    public function toJson()
    {
        $ret = array();
        $ret['action_type'] = $this->m_actionType;
        $ret['browser'] = array('url' => $this->m_url, 'confirm' => $this->m_confirmOnUrl);
        $ret['activity'] = $this->m_activity;
        $ret['intent'] = $this->m_intent;

        $aty_attr = array();
        if (isset($this->m_atyAttrIntentFlag)) {
            $aty_attr['if'] = $this->m_atyAttrIntentFlag;
        }
        if (isset($this->m_atyAttrPendingIntentFlag)) {
            $aty_attr['pf'] = $this->m_atyAttrPendingIntentFlag;
        }
        $ret['aty_attr'] = $aty_attr;

        return $ret;
    }

    public function isValid()
    {
        if (!isset($this->m_actionType))
            $this->m_actionType = self::TYPE_ACTIVITY;
        if (!is_int($this->m_actionType))
            return false;
        if ($this->m_actionType < self::TYPE_ACTIVITY || $this->m_actionType > self::TYPE_INTENT)
            return false;

        if ($this->m_actionType == self::TYPE_ACTIVITY) {
            if (!isset($this->m_activity)) {
                $this->m_activity = "";
                return true;
            }
            if (isset($this->m_atyAttrIntentFlag)) {
                if (!is_int($this->m_atyAttrIntentFlag)) {
                    return false;
                }
            }
            if (isset($this->m_atyAttrPendingIntentFlag)) {
                if (!is_int($this->m_atyAttrPendingIntentFlag)) {
                    return false;
                }
            }

            if (is_string($this->m_activity) && !empty($this->m_activity))
                return true;
            return false;
        }

        if ($this->m_actionType == self::TYPE_URL) {
            if (is_string($this->m_url) && !empty($this->m_url) &&
                is_int($this->m_confirmOnUrl) &&
                $this->m_confirmOnUrl >= 0 && $this->m_confirmOnUrl <= 1
            )
                return true;
            return false;
        }

        if ($this->m_actionType == self::TYPE_INTENT) {
            if (is_string($this->m_intent) && !empty($this->m_intent))
                return true;
            return false;
        }
    }

    private $m_actionType;
    private $m_url;
    private $m_confirmOnUrl;
    private $m_activity;
    private $m_intent;
    private $m_atyAttrIntentFlag;
    private $m_atyAttrPendingIntentFlag;
    private $m_packageDownloadUrl;
    private $m_confirmOnPackageDownloadUrl;
    private $m_packageName;

    const TYPE_ACTIVITY = 1;
    const TYPE_URL = 2;
    const TYPE_INTENT = 3;

}

class Style
{

    public function __construct($builderId, $ring = 0, $vibrate = 0, $clearable = 1, $nId = 0, $lights = 1, $iconType = 0, $styleId = 1)
    {
        $this->m_builderId = $builderId;//本地通知样式
        $this->m_ring = $ring;//是否响铃，0否，1是
        $this->m_vibrate = $vibrate;//是否振动，0否，1是
        $this->m_clearable = $clearable;//通知栏是否可清除，0否，1是
        $this->m_nId = $nId;//通知id，选填。若大于0，则会覆盖先前弹出的相同id通知；若为0，展示本条通知且不影响其他通知；若为-1，将清除先前弹出的所有通知，仅展示本条通知。默认为0
        $this->m_lights = $lights;//是否呼吸灯，0否，1是
        $this->m_iconType = $iconType;//默认0，通知栏图标是应用内图标还是上传图标,0是应用内图标，1是上传图标
        $this->m_styleId = $styleId;//Web端设置是否覆盖编号的通知样式，默认1，0否，1是
    }

    public function __destruct()
    {

    }

    public function getBuilderId()
    {
        return $this->m_builderId;
    }

    public function getRing()
    {
        return $this->m_ring;
    }

    public function getVibrate()
    {
        return $this->m_vibrate;
    }

    public function getClearable()
    {
        return $this->m_clearable;
    }

    public function getNId()
    {
        return $this->m_nId;
    }

    public function getLights()
    {
        return $this->m_lights;
    }

    public function getIconType()
    {
        return $this->m_iconType;
    }

    public function getStyleId()
    {
        return $this->m_styleId;
    }

    public function setRingRaw($ringRaw)
    {
        return $this->m_ringRaw = $ringRaw;
    }

    public function getRingRaw()
    {
        return $this->m_ringRaw;
    }

    public function setIconRes($iconRes)
    {
        return $this->m_iconRes = $iconRes;
    }

    public function getIconRes()
    {
        return $this->m_iconRes;
    }

    public function setSmallIcon($smallIcon)
    {
        return $this->m_smallIcon = $smallIcon;
    }

    public function getSmallIcon()
    {
        return $this->m_smallIcon;
    }

    public function isValid()
    {
        if (!is_int($this->m_builderId) || !is_int($this->m_ring) ||
            !is_int($this->m_vibrate) || !is_int($this->m_clearable) ||
            !is_int($this->m_lights) || !is_int($this->m_iconType) ||
            !is_int($this->m_styleId)
        )
            return false;
        if ($this->m_ring < 0 || $this->m_ring > 1)
            return false;
        if ($this->m_vibrate < 0 || $this->m_vibrate > 1)
            return false;
        if ($this->m_clearable < 0 || $this->m_clearable > 1)
            return false;
        if ($this->m_lights < 0 || $this->m_lights > 1)
            return false;
        if ($this->m_iconType < 0 || $this->m_iconType > 1)
            return false;
        if ($this->m_styleId < 0 || $this->m_styleId > 1)
            return false;

        return true;
    }

    private $m_builderId;
    private $m_ring;
    private $m_vibrate;
    private $m_clearable;
    private $m_nId;
    private $m_ringRaw;
    private $m_lights;
    private $m_iconType;
    private $m_iconRes;
    private $m_styleId;
    private $m_smallIcon;

}

class TimeInterval
{

    public function __construct($startHour, $startMin, $endHour, $endMin)
    {
        $this->m_startHour = $startHour;
        $this->m_startMin = $startMin;
        $this->m_endHour = $endHour;
        $this->m_endMin = $endMin;
    }

    public function __destruct()
    {

    }

    public function toArray()
    {
        return array(
            'start' => array('hour' => strval($this->m_startHour), 'min' => strval($this->m_startMin)),
            'end' => array('hour' => strval($this->m_endHour), 'min' => strval($this->m_endMin))
        );
    }

    public function isValid()
    {
        if (!is_int($this->m_startHour) || !is_int($this->m_startMin) ||
            !is_int($this->m_endHour) || !is_int($this->m_endMin)
        )
            return false;

        if ($this->m_startHour >= 0 && $this->m_startHour <= 23 &&
            $this->m_startMin >= 0 && $this->m_startMin <= 59 &&
            $this->m_endHour >= 0 && $this->m_endHour <= 23 &&
            $this->m_endMin >= 0 && $this->m_endMin <= 59
        )
            return true;
        else
            return false;
    }

    private $m_startHour;
    private $m_startMin;
    private $m_endHour;
    private $m_endMin;

}

class ParamsBase
{

    /**
     * @var array 当前传入的参数列表
     */
    public $_params = array();

    /**
     * 构造函数
     */
    public function __construct($params)
    {
        if (!is_array($params)) {
            return array();
        }
        foreach ($params as $key => $value) {
            //如果是非法的key值，则不使用这个key
            $this->_params[$key] = $value;
        }
    }

    public function set($k, $v)
    {
        if (!isset($k) || !isset($v)) {
            return;
        }
        $this->_params[$k] = $v;
    }

    /**
     * 根据实例化传入的参数生成签名
     */
    public function generateSign($method, $url, $secret_key)
    {
        //将参数进行升序排序
        $param_str = '';
        $method = strtoupper($method);
        $url_arr = parse_url($url);
        if (isset($url_arr['host']) && isset($url_arr['path'])) {
            $url = $url_arr['host'] . $url_arr['path'];
        }
        if (!empty($this->_params)) {
            ksort($this->_params);
            foreach ($this->_params as $key => $value) {
                $param_str .= $key . '=' . $value;
            }
        }
        //print $method.$url.$param_str.$secret_key."\n";
        return md5($method . $url . $param_str . $secret_key);
    }

}

class RequestBase
{

    //get请求方式
    const METHOD_GET = 'get';
    //post请求方式
    const METHOD_POST = 'post';

    /**
     * 发起一个get或post请求
     * @param $url 请求的url
     * @param int $method 请求方式
     * @param array $params 请求参数
     * @param array $extra_conf curl配置, 高级需求可以用, 如
     * $extra_conf = array(
     *    CURLOPT_HEADER => true,
     *    CURLOPT_RETURNTRANSFER = false
     * )
     * @return bool|mixed 成功返回数据，失败返回false
     * @throws Exception
     */
    public static function exec($url, $params = array(), $method = self::METHOD_GET, $extra_conf = array())
    {
        $params = is_array($params) ? http_build_query($params) : $params;
        //如果是get请求，直接将参数附在url后面
        if ($method == self::METHOD_GET) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $params;
        }

        //默认配置
        $curl_conf = array(
            CURLOPT_URL => $url, //请求url
            CURLOPT_HEADER => false, //不输出头信息
            CURLOPT_RETURNTRANSFER => true, //不输出返回数据
            CURLOPT_CONNECTTIMEOUT => 3 // 连接超时时间
        );

        //配置post请求额外需要的配置项
        if ($method == self::METHOD_POST) {
            //使用post方式
            $curl_conf[CURLOPT_POST] = true;
            //post参数
            $curl_conf[CURLOPT_POSTFIELDS] = $params;
        }

        //添加额外的配置
        foreach ($extra_conf as $k => $v) {
            $curl_conf[$k] = $v;
        }

        $data = false;
        try {
            //初始化一个curl句柄
            $curl_handle = curl_init();
            //设置curl的配置项
            curl_setopt_array($curl_handle, $curl_conf);
            //发起请求
            $data = curl_exec($curl_handle);
            if ($data === false) {
                throw new Exception('CURL ERROR: ' . curl_error($curl_handle));
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        curl_close($curl_handle);

        return $data;
    }

}

?>

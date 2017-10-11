<?php
namespace payment\Wxpay;

ini_set('date.timezone', 'Asia/Shanghai');
error_reporting(E_ERROR);

require_once EXTEND_PATH . "payment" . DS . "Wxpay" . DS . "lib" . DS . "WxPay.Api.php";
require_once EXTEND_PATH . "payment" . DS . "Wxpay" . DS . "lib" . DS . "WxPay.Exception.php";
require_once EXTEND_PATH . "payment" . DS . "Wxpay" . DS . "lib" . DS . "WxPay.Config.JsApi.php";
require_once EXTEND_PATH . "payment" . DS . "Wxpay" . DS . "lib" . DS . "WxPay.Data.php";
require_once EXTEND_PATH . "payment" . DS . "Wxpay" . DS . "CLogFileHandler.php";

/**
 *
 * JSAPI支付实现类
 * 该类实现了从微信公众平台获取code、通过code获取openid和access_token、
 * 生成jsapi支付js接口所需的参数、生成获取共享收货地址所需的参数
 *
 * 该类是微信支付提供的样例程序，商户可根据自己的需求修改，或者使用lib中的api自行开发
 *
 * http://mp.weixin.qq.com/wiki/17/c0f37d5704f0b64713d5d2c37b468d75.html
 *
 * @author widy
 *
 */
class JsApiPay
{
    //微信流水号
    private $transaction_id = '';

    //总金额（分）
    private $total_fee = '';

    //退款金额（分）
    private $refund_fee = '';

    //构造函数
    public function __construct($transaction_id = '', $total_fee = '', $refund_fee = '')
    {
        //初始化日志
        $log_path = RUNTIME_PATH . 'payment' . DS . 'Wxpay' . DS . 'logs' . DS;
        if (is_dir($log_path) || @mkdir($log_path, 0777, true)) {
            $log_path .= 'jsapi_' . date('Y-m-d') . '.log';
            $logHandler = new CLogFileHandler($log_path);
            LogNew::Init($logHandler, 15);
        }

        $this->transaction_id = $transaction_id;
        $this->total_fee = $total_fee;
        $this->refund_fee = $refund_fee;
    }

    /**
     *
     * 网页授权接口微信服务器返回的数据，返回样例如下
     * {
     *  "access_token":"ACCESS_TOKEN",
     *  "expires_in":7200,
     *  "refresh_token":"REFRESH_TOKEN",
     *  "openid":"OPENID",
     *  "scope":"SCOPE",
     *  "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
     * }
     * 其中access_token可用于获取共享收货地址
     * openid是微信支付jsapi支付接口必须的参数
     * @var array
     */
    public $data = null;

    /**
     *
     * 通过跳转获取用户的openid，跳转流程如下：
     * 1、设置自己需要调回的url及其其他参数，跳转到微信服务器https://open.weixin.qq.com/connect/oauth2/authorize
     * 2、微信服务处理完成之后会跳转回用户redirect_uri地址，此时会带上一些参数，如：code
     *
     * @return 用户的openid
     */
    public function GetOpenid($data)
    {
        //通过code获得openid
        if (!isset($_GET['code'])) {
            //触发微信返回code码
            $baseUrl = urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . $_SERVER['QUERY_STRING']);
            $url = $this->__CreateOauthUrlForCode($baseUrl);
            $url = str_replace("STATE", json_encode($data), $url);
            Header("Location: $url");
            exit();
        } else {
            //获取code码，以获取openid
            $code = $_GET['code'];
            $openid = $this->getOpenidFromMp($code);
            return $openid;
        }
    }

    /**
     * 统一下单
     * @param $body
     * @param $out_sn
     * @param $total_fee
     * @param $order_type
     * @param bool $is_recharge
     */
    public function get_prepay_id($body, $out_sn, $total_fee, $openId, $attach = '', $is_recharge = false)
    {
        //②、统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody($body);

        //附加数据，在查询API和支付通知中原样返回
        $input->SetAttach($attach);

        $input->SetOut_trade_no($out_sn);
        $input->SetTotal_fee($total_fee);
        $input->SetTime_start(date("YmdHis"));
        //$input->SetTime_expire(date("YmdHis", time() + 600));
        //$input->SetGoods_tag("test");

        //判定充值不允许使用信用卡
        if ($is_recharge) {
            $input->SetLimit_Pay("no_credit");
        }

        $input->SetNotify_url(WxPayConfig::NOTIFY_URL);//异步通知url

        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        $result = WxPayApi::unifiedOrder($input);
        if (!isset($result['appid']) || !isset($result['prepay_id'])
            || $result['prepay_id'] == ""
        ) {
            LogNew::INFO(json_encode($result));
        }
        return $result;
    }

    /**
     *
     * 获取jsapi支付的参数
     * @param array $prepay_id 统一支付接口返回的数据
     * @throws WxPayException
     *
     * @return json数据，可直接填入js函数作为参数
     */
    public function GetJsApiParameters($prepay_id)
    {
        $jsapi = new WxPayJsApiPay();
        $jsapi->SetAppid(WxPayConfig::APPID);
        $timeStamp = time();
        $jsapi->SetTimeStamp("$timeStamp");
        $jsapi->SetNonceStr(WxPayApi::getNonceStr());
        $jsapi->SetPackage("prepay_id=" . $prepay_id);
        $jsapi->SetSignType("MD5");
        $jsapi->SetPaySign($jsapi->MakeSign());
        $parameters = json_encode($jsapi->GetValues());
        return $parameters;
    }

    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        $url = $this->__CreateOauthUrlForOpenid($code);
        //初始化curl
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->curl_timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if (WxPayConfig::CURL_PROXY_HOST != "0.0.0.0"
            && WxPayConfig::CURL_PROXY_PORT != 0
        ) {
            curl_setopt($ch, CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);
            curl_setopt($ch, CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);
        }
        //运行curl，结果以jason形式返回
        $res = curl_exec($ch);
        curl_close($ch);
        //取出openid
        $data = json_decode($res, true);
        $this->data = $data;
        $openid = $data['openid'];
        return $openid;
    }

    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v) {
            if ($k != "sign") {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     *
     * 获取地址js参数
     *
     * @return 获取共享收货地址js函数需要的参数，json格式可以直接做参数使用
     */
    public function GetEditAddressParameters()
    {
        $getData = $this->data;
        $data = array();
        $data["appid"] = WxPayConfig::APPID;
        $data["url"] = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $time = time();
        $data["timestamp"] = "$time";
        $data["noncestr"] = "1234568";
        $data["accesstoken"] = $getData["access_token"];
        ksort($data);
        $params = $this->ToUrlParams($data);
        $addrSign = sha1($params);

        $afterData = array(
            "addrSign" => $addrSign,
            "signType" => "sha1",
            "scope" => "jsapi_address",
            "appId" => WxPayConfig::APPID,
            "timeStamp" => $data["timestamp"],
            "nonceStr" => $data["noncestr"]
        );
        $parameters = json_encode($afterData);
        return $parameters;
    }

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = WxPayConfig::APPID;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE" . "#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?" . $bizString;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code ，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = WxPayConfig::APPID;
        $urlObj["secret"] = WxPayConfig::APPSECRET;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?" . $bizString;
    }

    //查询订单
    public function orderquery($out_sn = '', $trade_no = '')
    {
        //构造要请求的参数
        $input = new WxPayOrderQuery();

        if ($out_sn) {
            //通过商户订单号查询
            $input->SetOut_trade_no($out_sn);
        } elseif ($trade_no) {
            //通过支付流水号查询
            $input->SetTransaction_id($trade_no);
        } else {
            return false;
        }

        $result = WxPayApi::orderQuery($input);
        return $result;
    }

    //退款
    public function send()
    {
        //构造要请求的参数
        $input = new WxPayRefund();
        $input->SetTransaction_id($this->transaction_id);
        $input->SetTotal_fee($this->total_fee);
        $input->SetRefund_fee($this->refund_fee);
        $input->SetOut_refund_no(WxPayConfig::MCHID . date("YmdHis"));
        $input->SetOp_user_id(WxPayConfig::MCHID);
        $result = WxPayApi::refund($input);
        return $result;
    }

    /**
     * 退款查询
     * @param string $refund_id 微信退款单号
     * @param string $batch_no 商户退款单号
     * @param string $trade_no 微信交易流水号
     * @param string $out_sn 商户订单号
     * @return bool|array
     */
    public function refundQuery($refund_id = '', $batch_no = '', $trade_no = '', $out_sn = '')
    {
        //构造要请求的参数
        $input = new WxPayRefundQuery();
        if ($refund_id) {
            //通过微信退款单号查询退款
            $input->SetRefund_id($refund_id);
        } elseif ($batch_no) {
            $input->SetOut_refund_no($batch_no);
        } elseif ($trade_no) {
            $input->SetOut_trade_no($trade_no);
        } elseif ($out_sn) {
            $input->SetTransaction_id($out_sn);
        } else {
            return false;
        }

        $result = WxPayApi::refundQuery($input);
        return $result;
    }

    //异步通知
    public function check_notify()
    {
        //构造要请求的参数
        //$input = new WxPayRefund();
        $result = WxPayApi::notify();
        return $result;
    }

    //下载对账单
    public function downloadBill($data)
    {
        //构造要请求的参数
        $input = new WxPayDownloadBill();
        //对账单日期
        $input->SetBill_date($data);

        //账单类型
        /*
        ALL，返回当日所有订单信息，默认值
        SUCCESS，返回当日成功支付的订单
        REFUND，返回当日退款订单
        RECHARGE_REFUND，返回当日充值退款订单（相比其他对账单多一栏“返还手续费”）
         */
        $input->SetBill_type('ALL');
        $result = WxPayApi::downloadBill($input);

        return $result;
    }

}
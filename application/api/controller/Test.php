<?php

//                   _ooOoo_
//                  o8888888o
//                  88" . "88
//                  (| -_- |)
//                  O\  =  /O
//               ____/`---'\____
//             .'  \\|     |
//  `.
//            /  \\|||  :  |||
//  \
//           /  _||||| -:- |||||-  \
//           |   | \\\  -
/// |   |
//           | \_|  ''\---/''  |   |
//           \  .-\__  `-`  ___/-.
///         ___`. .'  /--.--\  `. . __
//      ."" '<  `.___\_<|>_/___.'  >'"".
//     | | :  `- \`.;`\ _ /`;.`/ - ` : | |
//     \  \ `-.   \_ __\ /__ _/   .-` /
///======`-.____`-.___\_____/___.-`____.-'======
//                   `=---='
//^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
//         佛祖保佑       永无BUG
//  本模块已经经过开光处理，绝无可能再产生bug
//=============================================

namespace app\api\controller;

use app\common\model\Payment as PaymentModel;

class Test extends Base
{
    /**
     * 支付宝+微信唤起支付
     */
    public function payment()
    {
        $model_payment = new PaymentModel();

        /**
         * payment_name 支付名称
         * payment_amount 支付金额(元)
         * out_sn 商户订单号
         * payment_way值含义
         *      1.支付宝H5
         *      2.支付宝App
         *      3.支付宝PC
         *      4.微信H5
         *      5.微信App
         *      6.小程序
         * 当为支付宝H5以及支付宝PC唤起支付时直接输出返回参数内data的数据即可，data参数内为from表单代码，它会自动提交
         *
         * payment_type 自定义的订单类型,用于在接收支付异步通知时区分多种类型订单 例:order;
         */
        $data = [
            'payment_name' => '支付订单',
            'payment_amount' => '0.01',
            'out_sn' => '41201608171156327403454352',
            'payment_way' => '4',
            'payment_type' => 'order',
        ];

        $result = $model_payment->payment($data);
        if ($result['code'] == 1) {
            if (in_array($data['payment_way'], [1, 3])) {
                echo $result['data'];
                die;
            } else {
                print_r($result);
            }
        } else {
            print_r($result);
        }
    }

    /**
     * 支付宝单笔退款
     */
    public function aliPayRefund()
    {
        $param = [
            'out_sn' => '41201708151521281912630721',
            'trade_no' => '2017081521001004500235698318',
            'batch_no' => 'HZ01RF002',
            'refund_amount' => '0.01',
            'refund_reason' => '退款备注',
        ];
        $model_payment = new PaymentModel();

        //查询退款
        $result_search = $model_payment->aliPaySearchRefund($param);
        if ($result_search['code'] != '1') {

            //申请退款
            $result_refund = $model_payment->aliPayRefund($param);
            print_r($result_refund);
            die;
        } else {
            print_r($result_search);
            die;
        }
    }

    /**
     * 微信单笔退款
     */
    public function wxAppRefund()
    {
        $param = [
            'out_sn' => '41201708151521281912630721',
            'trade_no' => '2017081521001004500235698318',
            'batch_no' => 'HZ01RF002',
            'refund_no' => '',
            'refund_amount' => '0.01',
            'refund_reason' => '退款备注',
            'payment_way' => '3',
            'total_fee' => '0.01',
        ];

        $model_payment = new PaymentModel();

        //查询退款
        $result_search = $model_payment->wxSearchRefund($param);
        if ($result_search['code'] != '1') {
            //申请退款
            $result_search = $model_payment->wxRefund($param);
            print_r($result_search);
            die;
        }
        print_r($result_search);
        die;
    }

    /**
     * 下载对账单
     */
    public function bill()
    {
        $param = [
            'ali_date' => '2017-03-06',
            'wx_date' => '20170810',
            'payment_way' => '1',
        ];

        $model_payment = new PaymentModel();
        $result = $model_payment->getBillDownload($param);
        print_r($result);
    }

    /**
     * 信鸽推送
     */
    public function xinGePush()
    {

        $type = input('param.type') ? input('param.type') : 'ios';//ios android

        //DeviceToken 单推时使用
        $token = input('param.token') ? input('param.token') : '542c77fa7a1e29fc8f39e7bae042929302a64082191f0de2c362aadf51d7d69a';

        $token2 = input('param.token2') ? input('param.token2') : '542c77fa7a1e29fc8f39e7bae042929302a64082191f0de2c362aadf51d7d69a';

        //DeviceToken 多推时使用
        $tokenList = array(
            $token
        );

        //区分推送内容：1：优质房源
        $correlation_type = input('param.correlation_type') ? input('param.correlation_type') : '1';

        //直播id/拍品id
        $correlation_id = input('param.correlation_id') ? input('param.correlation_id') : '131';

        //其他数据对象
        $correlation_content = input('param.correlation_content') ? input('param.correlation_content') : (object)[];

        //区分推送类型 1：单推 2：多推 3：群推送
        $push_type = input('param.push_type') ? input('param.push_type') : '1';

        $s = [
            '>_<|||',
            '^_^;',
            '⊙﹏⊙‖∣°',
            '^_^|||',
            '^_^"',
            '→_→',
            'o_o ....',
            'O__O"',
            '///^_^.......',
            '?o?|||',
            '( ^_^ )?',
            '‘(*>﹏<*)′',
            '★~★',
            '(^_^)∠※',
            '‘（*^﹏^*）′',
            '（*>.<*）',
            '……\ ( > < ) /',
            '( ^___^ )y',
            '(((m -_-)m',
            '( ~___~ )',
            '(^^)(((((((((((((((●~~~~☆',
            '>>d(˙_˙)b<<',
            '●_● ',
            '凸ˋ_ˊ#',
            '\(^_^)(^_^)/ ',
            'o(*≧▽≦)ツ┏━┓',
            '✿(づ￣3￣)づ~❤',
            '▄█▀█● ',
            '┭─────┮ ﹏ ┭─────┮ ',
            '૮(༼༼Ծ◞◟Ծ༽༽)ა ',
            '╰(๑◕ ▽ ◕๑)╯',
            '(˘•ω•˘)ง˙³˙',
            'o͡͡͡͡͡͡͡͡͡͡͡͡͡͡╮(｡❛ᴗ❛｡)╭o͡͡͡͡͡͡͡͡͡͡͡͡͡͡'
        ];

        $text = [
            '因为有了人海，相遇才会显得那么意外。',
            '别人家的孩子是谁？',
            '我的直觉告诉我不能再胖了，但是减肥没有那么容易，每块肉都有它的脾气。',
            '“我也想体验一次被人追啊！” “买东西不给钱就行了……',
            '莎士比亚说过：“不要考验老娘，老娘经不起考验！”',
            '只是想霸气的把你拥入怀抱，谁知道你竟体重超标。',
            '一天来多有打扰，请多包涵，可我还是会再来的     '
        ];

        $title = input('param.title') ? input('param.title') : ('' . $text[rand(0, count($text) - 1)]);
        $title .= $s[rand(0, count($s) - 1)];
        $title .= '          - |  ' . date('H:i:s', config('time'));
        $content = $title;//input('param.content') ? input('param.content') : '成吉思汗1';

        //自定义消息
        $custom = array(
            'correlation_type' => (string)$correlation_type, //区分推送内容：1：推送方式1
            'correlation_id' => (string)$correlation_id, //相关ID
            'correlation_content' => $correlation_content, //其他数据对象
        );

        //$environment = config('push_environment'); //向iOS设备推送时必填，1表示推送生产环境；2表示推送开发环境。推送Android平台不填或填0
        $environment = 1; //向iOS设备推送时必填，1表示推送生产环境；2表示推送开发环境。推送Android平台不填或填0

        $xinGe = new \xinge\XingeApp($type);

        switch ($push_type) {
            case '1'://给单个设备下发透传消息

                $result = $xinGe->pushSingleDeviceApi($type, $title, $content, $token, $custom, $environment);
                echo '给单个设备下发透传消息,type=' . $type . ';correlation_type=' . $correlation_type . ';correlation_id=' . $correlation_id . ';correlation_content=' . json_encode($correlation_content) . 'title=' . $title . ';content=' . $content;
                print_r($result);
                die;
                break;

            case '2'://大批量下发给设备
                $result = $xinGe->pushDeviceListApi($type, $title, $content, $tokenList, $custom, $environment);
                echo '大批量下发给设备,type=' . $type . ';correlation_type=' . $correlation_type . ';correlation_id=' . $correlation_id . ';correlation_content=' . json_encode($correlation_content) . 'title=' . $title . ';content=' . $content;
                print_r($result);
                die;
                break;

            case '3'://下发所有设备
                echo '上线啦，这个功能不能用哦';
                die;
//                $result = $xinGe->pushAllDevicesApi($type, $title, $content, $custom, $environment);
//                echo '下发所有设备,type=' . $type . ';correlation_type=' . $correlation_type . ';correlation_id=' . $correlation_id . ';correlation_content=' . json_encode($correlation_content) . 'title=' . $title . ';content=' . $content;
//                print_r($result);die;
                break;
            default :
                echo '你啥也没选';

        }


        //按账号下发消息
//        $result = $xinGe->DemoPushSingleAccountIOS();
//        print_r($result);die;
    }

    /**
     * 发送短信
     */
    public function sendSms()
    {
        $phone = trim(input('param.phone')) ? trim(input('param.phone')) : '18811352020';


        $countrycode = '86';

        $content = '短信内容';

        $czAccount = '1';//1:验证码短信 2:通知短信

        $result = sendSms($phone, $content, $czAccount, $countrycode);

        print_r($result);
    }

    /**
     * 发送钉钉消息
     */
    public function sendDingDing(){
        $res = sendDingDing('钉钉消息标题', '钉钉消息内容');
        print_r($res);
    }

    /**
     * H5分享接口签名
     */
    public function getShareSignPackage()
    {
        //微信分享接口
        $wx = new \share\WxShare();
        $wx_signPackage = $wx->GetSignPackage();
        $wx_sign = [
            'app_key' => $wx_signPackage['appId'],
            'timestamp' => (string)$wx_signPackage['timestamp'],
            'noncestr' => $wx_signPackage['nonceStr'],
            'signature' => $wx_signPackage['signature'],
        ];

        //新浪分享接口
        $wb = new \share\WbShare();
        $ticket = $wb->getTicketURL("https://api.weibo.com/oauth2/js_ticket/generate");
        $web_url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $noncestr = $wb->createNoncestr(10);
        $timestamp = config('time');
        $signature = $wb->getSignatureL($ticket, $noncestr, $timestamp, $web_url);


        $wb_sign = [
            'app_key' => $wb->getConfigData()['wx_app_id'],
            'timestamp' => (string)$timestamp,
            'noncestr' => $noncestr,
            'signature' => $signature,
        ];

        $data = [
            'wx_sign' => $wx_sign,
            'wb_sign' => $wb_sign,
        ];
        $this->apiReturn(1, '', $data);
    }

}

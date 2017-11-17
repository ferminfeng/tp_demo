<?php
/**
 * 交易与第三方支付机构通讯通用类
 * User: Fermin
 * Date: 2017/10/11
 * Time: 11:11
 */

namespace app\common\model;

use think\Model;

class Payment extends Model
{

    /**
     * 唤起支付
     *
     * @param $param
     *      payment_way值含义
     *      1.支付宝H5
     *      2.支付宝App
     *      3.支付宝PC
     *      4.微信H5
     *      5.微信App
     *      6.小程序
     *      当为支付宝H5以及支付宝PC唤起支付时直接输出返回参数内data的数据即可，data参数内为from表单代码，它会自动提交
     *
     *  payment_type 自定义的订单类型,用于在接收支付异步通知时区分多种类型订单 例:order;
     */
    public function payment($data)
    {

        if (!is_array($data)) {
            return ['code' => '-2', 'msg' => '参数错误'];
        }

        $payment_name = $data['payment_name'];
        $payment_amount = $data['payment_amount'];
        $out_sn = $data['out_sn'];
        $payment_way = $data['payment_way'];
        $payment_type = $data['payment_type'];

        //文字过长微信唤起支付会失败
        if (mb_strlen($payment_name) > 90) {
            $payment_name = "支付订单";
        }

        header("Pragma:no-cache");
        //开始进入支付
        $response = false;
        //异步通知原样返回数据
        $notify_data = ['payment_type' => $payment_type, 'payment_way' => $payment_way];
        switch ($payment_way) {
            //支付宝H5
            case '1':
                $aop = new \payment\AlipayAop\AopClient();
                $request = new \payment\AlipayAop\request\AlipayTradeWapPayRequest();
                $setBizContent = json_encode(
                    [
                        'body'            => $payment_name,
                        'subject'         => $payment_name,
                        'out_trade_no'    => $out_sn,
                        'timeout_express' => '24h',//该笔订单允许的最晚付款时间，逾期将关闭交易
                        'total_amount'    => $payment_amount,//订单总金额
                        'product_code'    => 'QUICK_WAP_WAY',
                        'passback_params' => urlencode(http_build_query($notify_data)),//回传参数，支付宝会在异步通知时将该参数原样返回。本参数必须进行UrlEncode之后才可以发送给支付宝
                    ]
                );
                $request->setBizContent($setBizContent);

                //同步通知地址
                $request->setReturnUrl(config('pay_notify_url') . config('aliPay_h5_return_path'));

                //异步通知地址
                $request->setNotifyUrl(config('pay_notify_url') . config('aliPay_notify_path'));

                $response = $aop->pageExecute($request);

                break;

            //支付宝App
            case '2':
                $aop = new \payment\AlipayAop\AopClient();
                $request = new \payment\AlipayAop\request\AlipayTradeAppPayRequest();
                $setBizContent = json_encode(
                    [
                        'body'            => $payment_name,
                        'subject'         => $payment_name,
                        'out_trade_no'    => $out_sn,
                        'timeout_express' => '24h',//该笔订单允许的最晚付款时间，逾期将关闭交易
                        'total_amount'    => $payment_amount,//订单总金额
                        'product_code'    => 'QUICK_MSECURITY_PAY',
                        'passback_params' => urlencode(http_build_query($notify_data)),//回传参数，支付宝会在异步通知时将该参数原样返回。本参数必须进行UrlEncode之后才可以发送给支付宝
                    ]
                );
                $request->setBizContent($setBizContent);

                $request->setNotifyUrl(config('pay_notify_url') . config('aliPay_notify_path'));

                //这里和普通的接口调用不同，使用的是sdkExecute
                $response = $aop->sdkExecute($request);

                break;

            //支付宝PC
            case '3':
                $aop = new \payment\AlipayAop\AopClient();
                $request = new \payment\AlipayAop\request\AlipayTradePagePayRequest();
                $setBizContent = json_encode(
                    [
                        'body'            => $payment_name,
                        'subject'         => $payment_name,
                        'out_trade_no'    => $out_sn,
                        'timeout_express' => '24h',//该笔订单允许的最晚付款时间，逾期将关闭交易
                        'total_amount'    => $payment_amount,//订单总金额
                        'product_code'    => 'FAST_INSTANT_TRADE_PAY',
                        'passback_params' => urlencode(http_build_query($notify_data)),//回传参数，支付宝会在异步通知时将该参数原样返回。本参数必须进行UrlEncode之后才可以发送给支付宝
                    ]
                );

                $request->setBizContent($setBizContent);

                //同步通知地址
                $request->setReturnUrl(config('pay_notify_url') . config('aliPay_pc_return_path'));

                //异步通知地址
                $request->setNotifyUrl(config('pay_notify_url') . config('aliPay_notify_path'));

                $response = $aop->pageExecute($request);

                break;

            //微信H5
            case '4':
                $total_fee = $payment_amount * 100;//支付金额

                $attach = urlencode(http_build_query($notify_data));//附加数据，在查询API和支付通知中原样返回

                $weiXinPay = new \payment\Wxpay\JsApiPay();

                //1.获取用户openid
                $openId = $weiXinPay->GetOpenid($data);
                if ($openId) {
                    //获取prepay_id
                    $prepay_data = $weiXinPay->get_prepay_id($payment_name, $out_sn, $total_fee, $openId, $attach, FALSE);
                    if (isset($prepay_data['appid']) && isset($prepay_data['prepay_id'])
                        && $prepay_data['prepay_id'] != ""
                    ) {
                        //获取支付参数
                        $response = $weiXinPay->GetJsApiParameters($prepay_data['prepay_id']);
                    } else {
                        //获取prepay_id失败,记录log,请重试
                        $prepay_data['支付方式'] = 'wxh5';
                        handleErrorInfo('wxpay_get_prepay_id_error', 1, $prepay_data);
                    }
                }
                break;

            //微信App
            case '5':
                $total_fee = $payment_amount * 100;//支付金额

                $attach = urlencode(http_build_query($notify_data));//附加数据，在查询API和支付通知中原样返回

                $weiXinPay = new \payment\Wxpay\WxPay();

                $prepay_data = $weiXinPay->get_prepay_id($payment_name, $out_sn, $total_fee, $attach, FALSE);

                if ($prepay_data['result_code'] == 'SUCCESS' && $prepay_data['return_code'] == 'SUCCESS') {
                    //获取支付参数
                    $response = $weiXinPay->createAppPayData($prepay_data['prepay_id']);
                    $response['packageValue'] = 'Sign=WXPay';
                } else {
                    //获取prepay_id失败,记录log,请重试
                    $prepay_data['支付方式'] = 'wxapp';
                    handleErrorInfo('wxpay_get_prepay_id_error', 1, $prepay_data);
                }

                break;

            //微信小程序
            case '6':
                return ['code' => '-23333', 'msg' => '暂未开发'];
                break;

            default :
                return ['code' => '-2', 'msg' => '参数错误'];
        }

        if ($response) {
            return ['code' => '1', 'msg' => '请求成功', 'data' => $response];
        } else {
            return ['code' => '-2', 'msg' => '请求失败', 'data' => $response];
        }
    }

    /**
     * 查询支付结果
     *
     * @param int    $payment_way    支付方式 1.支付宝H5 2.支付宝App 3.支付宝PC 4.微信H5 5.微信App 6.小程序
     * @param string $out_sn         商户订单号
     * @param string $trade_no       第三方交易流水号
     * @param string $payment_amount 交易金额
     *
     * @return array
     */
    public function searchPaymentResult($payment_way, $out_sn = '', $trade_no = '')
    {
        if (!in_array($payment_way, [1, 2, 3, 4, 5, 6]) || ($out_sn == '' && $trade_no == '')) {
            return ['code' => '-1', 'mag' => '交易不存在'];
        }

        //交易查询
        switch ($payment_way) {
            //支付宝H5 支付宝App 支付宝PC
            case '1' :
            case '2' :
            case '3' :
                $aop = new \payment\AlipayAop\AopClient();
                $request = new \payment\AlipayAop\request\AlipayTradeQueryRequest();
                if ($out_sn) {
                    $bizContent = ['out_trade_no' => $out_sn];
                } else {
                    $bizContent = ['trade_no' => $trade_no];
                }
                $setBizContent = json_encode($bizContent);
                $request->setBizContent($setBizContent);
                $result = $aop->execute($request);

                //解析返回数据
                $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                /*
                 * code
                 *  10000  业务处理成功
                 *  其他code   失败
                 */
                $resultCode = $result->$responseNode->code;
                if (!empty($resultCode) && $resultCode == 10000) {

                    /*
                     * trade_status
                     *  TRADE_SUCCESS   交易支付成功
                     *  TRADE_FINISHED  交易结束，不可退款
                     *  WAIT_BUYER_PAY  交易创建，等待买家付款
                     *  TRADE_CLOSED    未付款交易超时关闭，或支付完成后全额退款
                     */
                    $resultTradeStatus = $result->$responseNode->trade_status;
                    switch ($resultTradeStatus) {
                        //支付成功
                        case 'TRADE_SUCCESS':
                        case 'TRADE_FINISHED':
                            $return = ['code' => '1', 'mag' => '支付成功', 'data' => $result->$responseNode];
                            break;

                        default:
                            $return = ['code' => '3', 'mag' => '交易创建但未支付', 'data' => $result->$responseNode];
                            break;
                    }

                    return $return;
                } else {
                    return ['code' => '-1', 'mag' => '交易不存在', 'data' => $result->$responseNode];
                }
                break;

            //微信H5
            case '4' :
                $weiXinPay = new \payment\Wxpay\JsApiPay();
                $result = $weiXinPay->orderquery($out_sn, $trade_no);
                /*
                 * return_code  状态码
                 *      SUCCESS/FAIL
                 * result_code  业务结果
                 *      SUCCESS/FAIL
                 * trade_state  交易状态
                 *     SUCCESS—支付成功
                 *     REFUND—转入退款
                 *     NOTPAY—未支付
                 *     CLOSED—已关闭
                 *     REVOKED—已撤销（刷卡支付）
                 *     USERPAYING--用户支付中
                 *     PAYERROR--支付失败(其他原因，如银行返回失败)
                 *
                 */
                //只要查询到交易信息就返回true
                if ($result && $result['return_code'] == 'SUCCESS' && $result['return_code'] == 'SUCCESS') {
                    switch ($result['trade_state']) {
                        //支付成功
                        case 'SUCCESS':
                        case 'REFUND':
                            $return = ['code' => '1', 'mag' => '支付成功', 'data' => $result];
                            break;

                        //支付中状态
                        case 'USERPAYING':
                            $return = ['code' => '2', 'mag' => '交易创建等待支付', 'data' => $result];
                            break;

                        default :
                            $return = ['code' => '3', 'mag' => '未支付', 'data' => $result];
                            break;
                    }

                    return $return;
                } else {
                    return ['code' => '-1', 'mag' => '交易不存在', 'data' => $result];
                }
                break;

            //微信App
            case '5' :
                $weiXinPay = new \payment\Wxpay\WxPay();
                $result = $weiXinPay->orderquery($out_sn, $trade_no);
                /*
                 * return_code  状态码
                 *      SUCCESS/FAIL
                 * result_code  业务结果
                 *      SUCCESS/FAIL
                 * trade_state  交易状态
                 *     SUCCESS—支付成功
                 *     REFUND—转入退款
                 *     NOTPAY—未支付
                 *     CLOSED—已关闭
                 *     REVOKED—已撤销（刷卡支付）
                 *     USERPAYING--用户支付中
                 *     PAYERROR--支付失败(其他原因，如银行返回失败)
                 *
                 */
                //只要查询到交易信息就返回true
                if ($result && $result['return_code'] == 'SUCCESS' && $result['return_code'] == 'SUCCESS') {
                    switch ($result['trade_state']) {
                        //支付成功
                        case 'SUCCESS':
                        case 'REFUND':
                            $return = ['code' => '1', 'mag' => '支付成功', 'data' => $result];
                            break;

                        //支付中状态
                        case 'USERPAYING':
                            $return = ['code' => '2', 'mag' => '交易创建等待支付', 'data' => $result];
                            break;

                        default :
                            $return = ['code' => '3', 'mag' => '未支付', 'data' => $result];
                            break;
                    }

                    return $return;
                } else {
                    return ['code' => '-1', 'mag' => '交易不存在', 'data' => $result];
                }
                break;

            //微信小程序
            case '6':
                $return = ['code' => '-2333', 'mag' => '暂未开发'];
                break;

            default :
                $return = ['code' => '-1', 'mag' => '交易不存在'];
                break;
        }

        return $return;
    }

    /**
     * 支付宝单笔退款查询
     *
     * @param $param
     *
     * @return array
     */
    public function aliPaySearchRefund($param)
    {
        if (!is_array($param)) {
            return ['code' => '-1', 'msg' => '参数错误'];
        }

        $aop = new \payment\AlipayAop\AopClient();
        $request = new \payment\AlipayAop\request\AlipayTradeFastpayRefundQueryRequest();
        $setBizContent = json_encode(
            [
                'out_trade_no'   => $param['out_sn'],
                'trade_no'       => $param['trade_no'],
                'out_request_no' => $param['batch_no'],
            ]
        );
        $request->setBizContent($setBizContent);
        $result = $aop->execute($request);

        //解析返回数据
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $result = json_decode(json_encode($result->$responseNode), true);

        if ($result['code'] == '10000' && isset($result['out_trade_no']) && $result['out_trade_no'] == $param['out_sn'] && $result['refund_amount'] == $param['refund_amount']) {
            /*
             * 退款成功result返回数据如下
                [code] => 10000
                [msg] => Success
                [out_request_no] => HZ01RF002
                [out_trade_no] => 41201708151521281912630721
                [refund_amount] => 0.01
                [total_amount] => 0.04
                [trade_no] => 2017081521001004500235698318
             */
            return ['code' => '1', 'msg' => '退款成功', 'data' => $result];
        } else {
            return ['code' => '2', 'msg' => '不存在退款', 'data' => $result];
        }
    }

    /**
     * 支付宝单笔退款
     *
     * @param $param
     *
     * @return array
     */
    public function aliPayRefund($param)
    {
        if (!is_array($param)) {
            return ['code' => '-1', 'msg' => '参数错误'];
        }

        $aop = new \payment\AlipayAop\AopClient();
        $request = new \payment\AlipayAop\request\AlipayTradeRefundRequest();
        $setBizContent = json_encode(
            [
                'out_trade_no'   => $param['out_sn'],
                'trade_no'       => $param['trade_no'],
                'refund_amount'  => $param['refund_amount'],
                'refund_reason'  => $param['refund_reason'],
                'out_request_no' => $param['batch_no'],
            ]
        );
        $request->setBizContent($setBizContent);
        $result = $aop->execute($request);

        //解析返回数据
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $result = json_decode(json_encode($result->$responseNode), true);

        //异步通知数据入库
        $insert_payment_notify = [
            'out_sn'          => $param['out_sn'],
            'payment_way'     => '61',
            'payment_time'    => date('Y-m-d H:i:s', config('time')),
            'payment_content' => json_encode($result),
        ];
        $model_payment_notify = new \app\common\model\PaymentNotify();
        $model_payment_notify->insertPaymentNotify($insert_payment_notify);

        if ($result['code'] == '10000') {
            /*
             * 退款成功时result数据如下
                [code] => 10000
                [msg] => Success
                [buyer_logon_id] => 123***@qq.com
                [buyer_user_id] => 2088*************
                [fund_change] => N
                [gmt_refund_pay] => 2017-08-15 15:23:58
                [open_id] => 13245613132132132312132
                [out_trade_no] => 41201708151521281912630721
                [refund_fee] => 0.01
                [send_back_fee] => 0.00
                [trade_no] => 2017081521001004500235698318
             */
            return ['code' => '1', 'msg' => '退款成功', 'data' => $result];
        } else {
            return ['code' => '-2', 'msg' => '退款失败', 'data' => $result];
        }
    }

    /**
     * 微信单笔退款查询
     *
     * @param $param
     *
     * @return array
     */
    public function wxSearchRefund($param)
    {
        if (!is_array($param)) {
            return ['code' => '-1', 'msg' => '参数错误'];
        }

        //查询微信APP退款
        if ($param['payment_way'] == '5') {
            $weiXinPay = new \payment\Wxpay\WxPay();
        } elseif ($param['payment_way'] == '4') {
            //查询微信H5退款
            $weiXinPay = new \payment\Wxpay\JsApiPay();
        } else {
            return ['code' => '-1', 'msg' => '参数错误'];
        }

        $result = $weiXinPay->refundQuery($param['refund_no'], $param['batch_no'], $param['trade_no'], $param['out_sn']);
        if ($result && $result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            return ['code' => '1', 'msg' => '退款成功', 'data' => $result];
        } else {
            return ['code' => '2', 'msg' => '不存在退款', 'data' => $result];
        }
    }

    /**
     * 微信单笔退款
     *
     * @param $param
     *
     * @return array
     */
    public function wxRefund($param)
    {
        if (!is_array($param)) {
            return ['code' => '-1', 'msg' => '参数错误'];
        }

        //微信APP退款
        if ($param['payment_way'] == '5') {
            $weiXinPay = new \payment\Wxpay\WxPay($param['out_sn'], $param['total_fee'] * 100, $param['refund_amount'] * 100);
        } elseif ($param['payment_way'] == '4') {
            //微信H5退款
            $weiXinPay = new \payment\Wxpay\JsApiPay($param['out_sn'], $param['total_fee'] * 100, $param['refund_amount'] * 100);
        } else {
            return ['code' => '-1', 'msg' => '参数错误'];
        }

        $result = $weiXinPay->send();

        //异步通知数据入库
        $insert_payment_notify = [
            'out_sn'          => $param['out_sn'],
            'payment_way'     => $param['payment_way'] == '5' ? '62' : '63',//62.微信App退款 63.微信H5退款'
            'payment_time'    => date('Y-m-d H:i:s', config('time')),
            'payment_content' => json_encode($result),
        ];
        $model_payment_notify = new \app\common\model\PaymentNotify();
        $model_payment_notify->insertPaymentNotify($insert_payment_notify);

        if ($result && $result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            return ['code' => '1', 'msg' => '退款成功', 'data' => $result];
        } else {
            return ['code' => '2', 'msg' => '退款失败', 'data' => $result];
        }
    }

    /**
     * 查询对账单下载地址
     *
     * @param $param
     *
     * @return array
     */
    public function getBillDownload($param)
    {
//        $param = [
//            'ali_date' => '2017-03-06',
//            'wx_date' => '20170306',
//            'payment_way' => '1',
//        ];
        if (!is_array($param)) {
            return ['code' => '-1', 'msg' => '参数错误'];
        }

        if ($param['payment_way'] == '1') {

            $aop = new \payment\AlipayAop\AopClient();
            $request = new \payment\AlipayAop\request\AlipayDataDataserviceBillDownloadurlQueryRequest();
            $setBizContent = json_encode(['bill_type' => 'trade', 'bill_date' => $param['ali_date']]);
            $request->setBizContent($setBizContent);
            $result = $aop->execute($request);

            //解析返回数据
            $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
            $result = json_decode(json_encode($result->$responseNode), true);

            if ($result['code'] == '10000') {
                //下载对账单
            }

        } elseif ($param['payment_way'] == '3') {
            $weiXinPay = new \payment\Wxpay\WxPay();
            $result = $weiXinPay->downloadBill($param['wx_date']);

            //下载对账单

        }

        if ($result) {
            return ['code' => '1', 'msg' => '请求成功', 'data' => $result];
        } else {
            return ['code' => '-2', 'msg' => '数据为空'];
        }

    }


}

<?php
/**
 * 支付相关控制器
 * User: Fermin
 * Date: 2017/10/11
 * Time: 11:11
 */

namespace app\api\controller;

use app\common\model\Payment as PaymentModel;
use app\common\model\PaymentLog as PaymentLogModel;
use app\common\model\PaymentNotify as PaymentNotifyModel;

class Payment extends Base
{
    /**
     * 转发支付宝支付异步通知
     */
    public function fwAliPayNotify()
    {
        $notify_data     = $_POST;
        $params = json_decode(urldecode($notify_data['passback_params']));

        //异步通知数据入库
        $insert_payment_notify = [
            'out_sn'          => $notify_data['out_trade_no'],
            'payment_way'     => $params['payment_way'],
            'payment_time'    => $notify_data['gmt_payment'],
            'payment_content' => json_encode($notify_data),
        ];
        $model_payment_notify  = new PaymentNotifyModel();
        $model_payment_notify->insertPaymentNotify($insert_payment_notify);

        //支付宝验签
        $aop           = new \payment\AlipayAop\AopClient();
        $notify_result = $aop->rsaCheckV1($notify_data);
        if ($notify_result) {
            //区分异步通知状态 当trade_status=TRADE_SUCCESS时表明支付成功
            if ($notify_data['trade_status'] == 'TRADE_SUCCESS') {

                //修改交易状态
                $param  = [
                    'out_sn'         => $notify_data['out_trade_no'],
                    'trade_no'       => $notify_data['trade_no'],
                    'payment_amount' => $notify_data['total_amount'],
                    'payment_time'   => $notify_data['gmt_payment'],
                    'payment_way'    => $params['payment_way'],
                    'payment_type'   => $params['payment_type'],
                ];
                $result = $this->updatePaymentInfo($param);

                if ($result) {
                    exit('success');
                }
            }
        }

        //验签失败
        exit('fail');
    }

    /**
     * 转发微信支付异步通知
     */
    public function fwWxNotify()
    {
        //验签
        $new_content = file_get_contents("php://input");

        $weixinPay   = new \payment\Wxpay\WxPay();
        $notify_info = $weixinPay->check_notify();

        if (isset($notify_info['attach'])) {
            $attach = json_decode($notify_info['attach'], true);;
        }

        //异步通知数据入库
        $insert_payment_notify = [
            'out_sn'          => isset($notify_info['out_trade_no']) ? $notify_info['out_trade_no'] : '',
            'payment_way'     => isset($attach['payment_way']) ? $attach['payment_way'] : '3',
            'payment_content' => $new_content,
        ];
        //转换支付时间
        $payment_time = isset($notify_info['time_end']) ? date('Y-m-d H:i:s', strtotime($notify_info['time_end'])) : '';
        if ($payment_time) {
            $insert_payment_notify['payment_time'] = $payment_time;
        }
        $model_payment_notify = new PaymentNotifyModel();
        $model_payment_notify->insertPaymentNotify($insert_payment_notify);

        //验签失败 返回消息给微信服务器
        if (!$notify_info) {
            //返回消息给微信服务器
            $weixinPay->resultXmlToWx(['return_code' => 'FAIL', 'return_msg' => '签名失败']);
        }

        //支付状态验证
        if ($notify_info['result_code'] != 'SUCCESS' || $notify_info['return_code'] != 'SUCCESS' || !$notify_info['transaction_id']) {
            handleErrorInfo('insert_wxapy_notify_error', 1, $notify_info);
            //返回消息给微信服务器
            $weixinPay->resultXmlToWx(['return_code' => 'FAIL', 'return_msg' => '支付状态错误']);
        }

        //修改交易状态
        $param  = [
            'out_sn'         => $notify_info['out_trade_no'],
            'trade_no'       => $notify_info['transaction_id'],
            'payment_amount' => $notify_info['total_fee'] / 100,
            'payment_time'   => $payment_time,
            'payment_way'    => $attach['payment_way'],
            'payment_type'   => $attach['payment_type'],
        ];
        $result = $this->updatePaymentInfo($param);

        if ($result) {
            //返回消息给微信服务器
            $weixinPay->resultXmlToWx(['return_code' => 'SUCCESS', 'return_msg' => 'OK']);
        } else {
            //返回消息给微信服务器
            $weixinPay->resultXmlToWx(['return_code' => 'FAIL', 'return_msg' => '修改失败']);
        }

    }

    /**
     * 支付异步通知修改交易状态
     */
    private function updatePaymentInfo($param)
    {
        if (!is_array($param)) {
            return false;
        }

        //区分订单到payment_log查询是否支付成功
        $model_payment_log = new PaymentLogModel();
        $payment_log       = $model_payment_log->getPaymentLog([
            'trade_no'       => $param['trade_no'],
            'out_sn'         => $param['out_sn'],
            'payment_status' => '1',
            'payment_amount' => $param['payment_amount'],
        ]);

        //未查询到数据、支付失败则按照订单类型处理相关的业务逻辑
        if (!$payment_log) {
            //根据订单类型分别处理订单
            switch ($param['payment_type']) {
                case 'order' :
                    $payment            = [
                        'out_sn'         => $param['out_sn'],
                        'trade_no'       => $param['trade_no'],
                        'payment_amount' => $param['payment_amount'],
                        'payment_way'    => $param['payment_way'],
                    ];
                    //在这里写自己的业务逻辑
                    $result = ['code' => '1', 'msg' => '处理成功', 'data' => null];

                    break;

                default :
                    $result = ['code' => '-1', 'msg' => '处理失败', 'data' => ['sssss']];
                    break;
            }

            //业务逻辑处理成功时记录支付成功log到payment_log表并返回success
            if ($result['code'] == '1') {

                //支付成功log入库
                $insert_payment_notify = [
                    'out_sn'         => $param['out_sn'],
                    'trade_no'       => $param['trade_no'],
                    'payment_amount' => $param['payment_amount'],
                    'payment_way'    => $param['payment_way'],
                    'payment_type'   => $param['payment_type'],
                    'payment_time'   => $param['payment_time'],
                    'payment_status' => '1',
                ];
                $model_payment_log     = new PaymentLogModel();
                $insert_payment_status = $model_payment_log->insertPaymentLog($insert_payment_notify);
                //失败时记录失败信息
                if ($insert_payment_status['code'] != '1') {
                    handleErrorInfo('insert_payment_log_error', 1, $insert_payment_status);
                }

                return true;
            }
        }

        return false;

    }

    /**
     * 支付宝H5同步通知
     */
    public function aliPayH5Return()
    {
        $notify_data = $_GET;

        //支付宝验签
        $aop           = new \payment\AlipayAop\AopClient();
        $notify_result = $aop->rsaCheckV1($notify_data);
        if ($notify_result) {
            //查询是否支付
            $model_payment = new PaymentModel();
            $result        = $model_payment->searchPaymentResult(1, $notify_data['out_trade_no'], $notify_data['trade_no']);
            if ($result['code'] == '1') {
                echo '支付成功';
            }
        }
    }

    /**
     * 支付宝PC同步通知
     */
    public function aliPayPcReturn()
    {
        $notify_data = $_GET;

        //支付宝验签
        $aop           = new \payment\AlipayAop\AopClient();
        $notify_result = $aop->rsaCheckV1($notify_data);
        if ($notify_result) {
            //查询是否支付
            $model_payment = new PaymentModel();
            $result        = $model_payment->searchPaymentResult(6, $notify_data['out_trade_no'], $notify_data['trade_no']);
            if ($result['code'] == '1') {
                echo '支付成功';
            }
        }
    }
}

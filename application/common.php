<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

/**
 * +----------------------------------------------------------
 * 字符串截取，支持中文和其他编码
 * +----------------------------------------------------------
 * @static
 * @access public
 * +----------------------------------------------------------
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param boolean $title “...”处鼠标指向时是否显示详细内容
 * @param string $charset 编码格式
 * @param boolean $suffix 截断显示字符
 * +----------------------------------------------------------
 * @return string
 * +----------------------------------------------------------
 */
function m_substr($str, $start, $length, $title = true, $charset = "utf-8", $suffix = true)
{
    $strlen = strlen($str) / 3;
    if ($strlen <= $length) {
        return $str;
    }
    if (function_exists("mb_substr")) {
        $slice = mb_substr($str, $start, $length, $charset);
    } elseif (function_exists('iconv_substr')) {
        $slice = iconv_substr($str, $start, $length, $charset);
        if (false === $slice) {
            $slice = '';
        }
    } else {
        $re['utf-8']  = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice($match[0], $start, $length));
    }
    $suffix_str = ($title == true) ? " <a href='javascript:;' title='{$str}'>...</a>" : " ...";
    return $suffix ? $slice . $suffix_str : $slice;
}

/**
 * 发送短信
 */
/**
 * 发送短信
 * @param string $phone 接收短信的手机号，不带国家区码
 * @param string $content 短信内容
 * @param string $countryCode 国家区码
 * @param string $czAccount 1:验证码短信 2:通知短信
 * @return mixed
 */
function sendSms($phone = '', $content = '', $czAccount = '1', $countryCode = '86')
{
    $sms = new \sms\Sms();

    $content .= '【谷町君】';

    //国内号码发送短信
    if ($countryCode == '86') {
        $result = $sms->czSMS($phone, $content, $czAccount);
    } else {
        //处理国外手机号
        $string_zero = '00';
        //去除手机号前面的‘0’
        $phone  = preg_replace('/^0+/', '', $phone);
        $moblie = $string_zero . $countryCode . $phone;

        $result = $sms->mdSendSmsForeign($moblie, $content);
    }

    if ($result == '0') {
        return ['code' => '1', 'msg' => '发送成功'];
    } else {
        return ['code' => $result, 'msg' => '发送失败'];
    }
}

/**
 * 获取redis实例
 * @return bool|Redis
 */
function redisInstance()
{
    $redis_config = config('RedisConfig');
    if ($redis_config['use_redis']) {
        try {
            $redis = new \Redis();
            //连接redis
            if ($redis->connect($redis_config['redis_host'], $redis_config['redis_port'], $redis_config['timeout']) == false) {
                echo $redis->getLastError();
                \think\Log::record('[ Redis ] connect fail', 'error');
                return false;
            }
            if ($redis_config['instance_id'] && $redis_config['pwd']) {
                //鉴权
                if ($redis->auth($redis_config['instance_id'] . ":" . $redis_config['pwd']) == false) {
                    \think\Log::record('[ Redis ] auth fail', 'error');
                    return false;
                }
            }
            return $redis;
        } catch (\Exception $e) {
            \think\Log::record('[ Redis ] ' . $e->getMessage(), 'error');
            return false;
        }
    }
    return false;
}

/**
 * 发送钉钉消息
 * @param string $title 标题
 * @param string|array $message 消息
 * @param bool $isAtAll 是否@所有人
 * @param array $atMobiles 被@人手机号
 * @param string $access_token
 * @return array ['errmsg' => 'fail', 'errcode' => '0']
 */
function sendDingDing($title = '', $message = '', $isAtAll = false, $atMobiles = [], $access_token = '')
{
    if (!$title) {
        return ['errmsg' => 'fail', 'errcode' => '0'];
    }

    if($access_token == ''){
        $access_token = config('dd_access_token');
    }

    $url = "https://oapi.dingtalk.com/robot/send?access_token=" . $access_token;

    if (!is_string($message)) {
        $message = var_export($message, true);
    }

    $from_ip = getHostByName(getHostName());

    $title .= "-运行环境:" . config('project_dev');

    $post_data = [
        'msgtype' => 'text',
        'text'    => array('content' => $title . '-' . $from_ip . "\r\n" . $message),
        'at'      => [
            'isAtAll' => $isAtAll,
        ],
    ];
    if (is_array($atMobiles) && count($atMobiles) > 0) {
        $post_data['at']['atMobiles'] = $atMobiles;
    }
    $post_string = json_encode($post_data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=utf-8'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $curl_result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($curl_result, true);

    return $result;
}

/**
 * 记录、发送错误信息
 * @param string $key
 * @param string $level 1:紧急(发送消息) 2:一般(只记录错误信息)
 * @param array $info 错误信息
 * @return bool
 */
function handleErrorInfo($key, $level = '2', $info = [])
{
    if ($key == '' || empty($info)) {
        return false;
    }

    $time = date('Y-m-d H:i:s', time());

    //记录错误信息
    $redis         = redisInstance();
    $insert_status = false;
    if ($redis) {
        $insert_status = $redis->hSet($key, $time . '_' . rand(1000, 9999), var_export($info, true));
    }

    //发送消息
    $send_status = ['errmsg' => 'ok'];
    if ($level == '1') {
        $send_status = sendDingDing($key, $info);
    }

    return $insert_status && $send_status['errmsg'] == 'ok';
}

/**
 * 把数字1-1亿换成汉字表述，如：123->一百二十三
 * @param [num] $num [数字]
 * @return [string] [string]
 */
function numToZh($num)
{

    $chiNum = array('零', '一', '二', '三', '四', '五', '六', '七', '八', '九');
    $chiUni = array('', '十', '百', '千', '万', '亿', '十', '百', '千');

    $chiStr = '';

    $num_str = (string) $num;
    if ((!$num_str && $num_str != '0') || $num_str < '0') {
        return '';
    }

    if ($num_str == '2') {
        return '两';
    }

    $count     = strlen($num_str);
    $last_flag = true; //上一个 是否为0
    $zero_flag = true; //是否第一个
    $temp_num  = null; //临时数字

    $chiStr = ''; //拼接结果
    if ($count == 2) {
//两位数
        $temp_num = $num_str[0];
        $chiStr   = $temp_num == 1 ? $chiUni[1] : $chiNum[$temp_num] . $chiUni[1];
        $temp_num = $num_str[1];
        $chiStr .= $temp_num == 0 ? '' : $chiNum[$temp_num];
    } else if ($count > 2) {
        $index = 0;
        for ($i = $count - 1; $i >= 0; $i--) {
            $temp_num = $num_str[$i];
            if ($temp_num == 0) {
                if (!$zero_flag && !$last_flag) {
                    $chiStr    = $chiNum[$temp_num] . $chiStr;
                    $last_flag = true;
                }
            } else {
                $chiStr = $chiNum[$temp_num] . $chiUni[$index % 9] . $chiStr;

                $zero_flag = false;
                $last_flag = false;
            }
            $index++;
        }
    } else {
        $chiStr = $chiNum[$num_str[0]];
    }
    return $chiStr;
}


/**
 * 将日期转换成周数
 * @param $date 日期或时间戳
 * @param bool $is_timestamp 是否是时间戳
 */
function getDateWeek($date, $is_timestamp = true)
{
    if (!$is_timestamp) {
        $date = strtotime($date);
    }
    $week = '周' . config('week_str')[date('N', $date)];
    return $week;
}

/**
 * 解析支付宝打款错误信息
 * @param string $error_code
 * @param string $type  alipay/wx
 * @return string
 *
 */
function parseNotifyFailDetail($error_code = '', $type = '')
{
    $ret = "";
    if (!$error_code) {
        return $ret;
    }

    $aipay_fail_config = [
        'UPLOAD_FILE_NOT_FOUND'           => '非常抱歉，找不到上传的文件!',
        'UPLOAD_FILE_NAME_ERROR'          => '上传文件名不能为空',
        'UPLOAD_USERID_ERROR'             => '上传用户ID不能为空',
        'UPLOAD_ISCONFIRM_ERROR'          => '复核参数错误',
        'UPLOAD_XTEND_NAME_ERROR'         => '抱歉，上传文件的格式不正确，文件扩展名必须是xls、csv',
        'UPLOAD_CN_FILE_NAME_ERROR'       => '抱歉，上传文件的文件名中不能有乱码',
        'UPLOAD_FILE_NAME_LENGTH_ERROR'   => '抱歉，上传文件的文件名长度不能超过64个字节',
        'UPLOAD_FILE_NAME_DUPLICATE'      => '抱歉，您上传文件的文件名不能和以前上传过的有重复',
        'EMAIL_ACCOUNT_LOCKED'            => '您暂时无法使用此功能，请立即补全您的认证信息',
        'BATCH_OUT_BIZ_NO_DUPLICATE'      => '业务唯一校验失败',
        'BATCH_OUT_BIZ_NO_LIMIT_ERROR'    => '抱歉，上传文件的批次号必须为11~32位的数字、字母或数字与字母的组合',
        'AMOUNT_FORMAT_ERROR'             => '抱歉，您上传的文件中，第二行第五列的金额不正确。格式必须为半角的数字，最高精确到分，金额必须大于0',
        'PAYER_FORMAT_ERROR'              => '您上传的文件中付款账户格式错误',
        'PAYER_IS_NULL'                   => '抱歉，您上传的文件中付款账户不能为空',
        'FILE_CONTENT_NULL'               => '抱歉，您上传的文件内容不能为空',
        'FILE_CONTENT_LIMIT'              => '抱歉，您上传的文件付款笔数超过了最大限制',
        'PAYER_USERINFO_NOT_EXIST'        => '抱歉，您上传文件中的付款账户，与其所对应的账户信息不匹配或状态异常',
        'DAILY_QUOTA_LIMIT_EXCEED'        => '日限额超限',
        'FILE_SUMMARY_NOT_MATCH'          => '抱歉，您填入的信息与上传文件中的数据不一致',
        'ILLEGAL_CONTENT_FORMAT'          => '文件内容格式非法',
        'DETAIL_OUT_BIZ_NO_REPEATE'       => '同一批次中商户流水号重复',
        'TOTAL_COUNT_NOT_MATCH'           => '总笔数与明细汇总笔数不一致',
        'TOTAL_AMOUNT_NOT_MATCH'          => '总金额与明细汇总金额不一致',
        'PAYER_ACCOUNT_IS_RELEASED'       => '付款账户名与他人重复，无法进行收付款。为保障资金安全，建议及时修改账户名',
        'PAYEE_ACCOUNT_IS_RELEASED'       => '收款账户名与他人重复，无法进行收付款',
        'ERROR_INVALID_UPLOAD_FILE'       => '抱歉，您上传的文件无效！请确认文件是否存在，并且是有效的文件格式。',
        'FILE_SAVE_ERROR'                 => '文件上传到服务器失败，请确认您是否已关闭待上传的文件',
        'ERROR_FILE_NAME_DUPLICATE'       => '上传的文件名不能重复',
        'BATCH_ID_NULL'                   => '批次明细查询时批次ID为空',
        'BATCH_NO_NULL'                   => '批次号为空',
        'PARSE_DATE_ERROR'                => '到账户批次查询日期格式错误',
        'USER_NOT_UPLOADER'               => '用户查询不是其上传的批次信息',
        'ERROR_ACCESS_DATA'               => '无权访问该数据',
        'ILLEGAL_FILE_NAME'               => '文件名不合法，只允许为数字、英文（半角）、中文、点以及下划线',
        'ERROR_FILE_EMPTY'                => '非常抱歉，找不到上传的文件或文件内容为空!',
        'ERROR_FILE_NAME_SURFFIX'         => '错误的文件后缀名',
        'ERROR_FILE_NAME_LENGTH'          => '过长的文件名',
        'ERROR_SEARCH_DATE'               => '付款记录的查询时间段跨度不能超过15天',
        'ERROR_BALANCE_NULL'              => '用户余额不存在',
        'ERROR_USER_INFO_NULL'            => '用户信息为空',
        'ERROR_USER_ID_NULL'              => '用户名为空',
        'ERROR_BATCH_ID_NULL'             => '批次ID为空',
        'ERROR_BATCH_NO_NULL'             => '批次号为空',
        'STATUS_NOT_VALID'                => '请等待该批次明细校验完成后再下载',
        'USER_SERIAL_NO_ERROR'            => '商户流水号的长度不正确，不能为空或必须小于等于32个字符',
        'USER_SERIAL_NO_REPEATE'          => '同一批次中商户流水号重复',
        'RECEIVE_EMAIL_ERROR'             => '收款人EMAIL的长度不正确，不能为空或必须小于等于100个字符',
        'RECEIVE_NAME_ERROR'              => '收款人姓名的长度不正确，不能为空或必须小于等于128个字符',
        'RECEIVE_REASON_ERROR'            => '付款理由的长度不正确，不能为空或必须小于等于100个字符',
        'RECEIVE_MONEY_ERROR'             => '收款金额格式必须为半角的数字，最高精确到分，金额必须大于0',
        'RECEIVE_ACCOUNT_ERROR'           => '收款账户有误或不存在',
        'RECEIVE_SINGLE_MONEY_ERROR'      => '收款金额超限',
        'LINE_LENGTH_ERROR'               => '流水列数不正确，流水必须等于5列',
        'SYSTEM_DISUSE_FILE'              => '用户逾期15天未复核，批次失败',
        'MERCHANT_DISUSE_FILE'            => '用户复核不通过，批次失败',
        'TRANSFER_AMOUNT_NOT_ENOUGH'      => '转账余额不足，批次失败',
        'RECEIVE_USER_NOT_EXIST'          => '收款用户不存在',
        'ILLEGAL_USER_STATUS'             => '用户状态不正确',
        'ACCOUN_NAME_NOT_MATCH'           => '用户姓名和收款名称不匹配',
        'ERROR_OTHER_CERTIFY_LEVEL_LIMIT' => '收款账户实名认证信息不完整，无法收款',
        'ERROR_OTHER_NOT_REALNAMED'       => '收款账户尚未实名认证，无法收款',
        '用户撤销'                    => '用户撤销',
        'USER_NOT_EXIST'                  => '用户不存在',
        'RECEIVE_EMAIL_NAME_NOT_MATCH'    => '收款方email账号与姓名不匹配',
        'SYSTEM_ERROR'                    => '支付宝系统异常',
        'REFUND_TRADE_FEE_ERROR'          => '退款交易金额不正确 ',

        //AOP错误码
        'ACQ.SYSTEM_ERROR'                => '系统错误',
        'ACQ.INVALID_PARAMETER'           => '参数无效',
        'ACQ.SELLER_BALANCE_NOT_ENOUGH'   => '卖家余额不足',
        'ACQ.REFUND_AMT_NOT_EQUAL_TOTAL'  => '退款金额超限',
        'ACQ.REASON_TRADE_BEEN_FREEZEN'   => '请求退款的交易被冻结',
        'ACQ.TRADE_NOT_EXIST'             => '交易不存在',
        'ACQ.TRADE_HAS_FINISHED'          => '交易已完结',
        'ACQ.TRADE_STATUS_ERROR'          => '交易状态非法',
        'ACQ.DISCORDANT_REPEAT_REQUEST'   => '不一致的请求',
        'ACQ.REASON_TRADE_REFUND_FEE_ERR' => '退款金额无效',
        'ACQ.TRADE_NOT_ALLOW_REFUND'      => '当前交易不允许退款',

    ];

    //微信错误码
    $wx_fail_config = [
        'NOAUTH'                => '商户无此接口权限',
        'NOTENOUGH'             => '余额不足',
        'ORDERPAID'             => '商户订单已支付',
        'ORDERCLOSED'           => '订单已关闭',
        'SYSTEMERROR'           => '系统错误', //系统超时,请用相同参数再次调用API
        'APPID_NOT_EXIST'       => 'APPID不存在',
        'MCHID_NOT_EXIST'       => 'MCHID不存在',
        'APPID_MCHID_NOT_MATCH' => 'appid和mch_id不匹配',
        'LACK_PARAMS'           => '缺少参数',
        'OUT_TRADE_NO_USED'     => '商户订单号重复',
        'SIGNERROR'             => '签名错误',
        'XML_FORMAT_ERROR'      => 'XML格式错误',
        'REQUIRE_POST_METHOD'   => '请使用post方法',
        'POST_DATA_EMPTY'       => 'post数据为空',
        'NOT_UTF8'              => '编码格式错误',
        'USER_ACCOUNT_ABNORMAL' => '退款申请失败，请线下进行退款', //此状态代表退款申请失败，商户可自行处理退款
        'INVALID_TRANSACTIONID' => '无效transaction_id', //请求参数错误，检查原交易号是否存在或发起支付交易接口返回失败
        'PARAM_ERROR'           => '参数错误', //请求参数错误，请重新检查再调用退款申请
        'ORDERNOTEXIST'         => '此交易订单号不存在', //该API只能查提交支付交易返回成功的订单，请商户检查需要查询的订单号是否正确
        'REFUNDNOTEXIST'        => '退款订单查询失败',
        'BIZERR_NEED_RETRY'     => '退款业务流程错误，需要商户触发重试来解决',
        'TRADE_OVERDUE'         => '订单已经超过退款期限',
        'ERROR'                 => '业务错误',
        'INVALID_REQ_TOO_MUCH'  => '无效请求过多',
        'FREQUENCY_LIMITED'     => '频率限制',
    ];

    if ($type == 'alipay') {
        $ret = isset($aipay_fail_config[$error_code]) ? $aipay_fail_config[$error_code] : $error_code;
    } elseif ($type == 'wx') {
        $ret = isset($wx_fail_config[$error_code]) ? $wx_fail_config[$error_code] : $error_code;
    }

    return $ret;
}

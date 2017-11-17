<?php

namespace Sms;

define('MD_USERNAME', config('SmsConfig')['md_username']);
define('MD_PASSWORD', config('SmsConfig')['md_password']);

define('CZ_USERNAME', config('SmsConfig')['cz_username']);
define('CZ_PASSWORD', config('SmsConfig')['cz_password']);
define('CZ_USERNAME_TZ', config('SmsConfig')['cz_username_tz']);
define('CZ_PASSWORD_TZ', config('SmsConfig')['cz_password_tz']);

class Sms
{

    /**
     * 漫道账号
     */
    var $serialNumber = MD_USERNAME;

    /**
     * 漫道密码
     */
    var $password = MD_PASSWORD;

    /**
     * 畅卓账号、密码
     */
    var $czNumber = CZ_USERNAME;
    var $czPassword = CZ_PASSWORD;

    /**
     * 畅卓通知 账号、密码
     */
    var $czNumber_tz = CZ_USERNAME_TZ;
    var $czPassword_tz = CZ_PASSWORD_TZ;

    /**
     * 畅卓url
     */
    var $czTarget = 'http://sms.chanzor.com:8001/sms.aspx';

    /**
     * 畅卓扩展号
     */
    var $czExtno = '5568';

    /**
     * 畅卓短信
     * @param int $phone 接收验证码的手机号
     * @param string $content 发送内容
     * @param int $czAccount 1:验证码短信 2:通知短信
     *
     * return int 0:成功 其他:失败
     */
    public function czSMS($phone, $content, $czAccount = '1')
    {

        if ($czAccount == '2') {
            $czNumber = $this->czNumber_tz;
            $czPassword = $this->czPassword_tz;
        } else {
            $czNumber = $this->czNumber;
            $czPassword = $this->czPassword;
        }

        if(!$czNumber || !$czPassword){
            return ['code' => '-10001', 'msg' => '发送短信帐号不存在', 'data' => ''];
        }

        $post_data = "action=send&userid=&account=" . $czNumber . "&password=" . $czPassword . "&mobile=" . $phone . "&sendTime=&content=" . rawurlencode($content) . "&extno=" . $this->czExtno;

        $gets = $this->Post($post_data, $this->czTarget);

        $xml = simplexml_load_string($gets);
        $send_result = json_decode(json_encode($xml), TRUE);

        //发送成功
        if ($send_result['returnstatus'] == 'Success') {
            $result = ['code' => '0', 'msg' => '', 'data' => $send_result];
        }else{
            //发送失败
            $result = ['code' => '-1', 'msg' => '', 'data' => $send_result];
        }

        return $result;
    }

    /**
     * 畅卓短信
     * @param $data
     * @param $target
     * @return string
     */
    private function Post($data, $target)
    {
        $url_info = parse_url($target);
        $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
        $httpheader .= "Host:" . $url_info['host'] . "\r\n";
        $httpheader .= "Content-Type:application/x-www-form-urlencoded\r\n";
        $httpheader .= "Content-Length:" . strlen($data) . "\r\n";
        $httpheader .= "Connection:close\r\n\r\n";
        //$httpheader .= "Connection:Keep-Alive\r\n\r\n";
        $httpheader .= $data;

        $fd = fsockopen($url_info['host'], 80);
        fwrite($fd, $httpheader);
        $gets = "";
        while (!feof($fd)) {
            $gets .= fread($fd, 128);
        }
        fclose($fd);
        if ($gets != '') {
            $start = strpos($gets, '<?xml');
            if ($start > 0) {
                $gets = substr($gets, $start);
            }
        }
        return $gets;
    }

    /**
     * 漫道国内短信发送
     * @param int $phone 接收验证码的手机号
     * @param string $content 发送内容
     *
     * return int 0:成功 其他:失败
     */
    public function mdSendSms($phone, $content)
    {
        if(!$this->serialNumber || !$this->password){
            return ['code' => '-10001', 'msg' => '发送短信帐号不存在', 'data' => ''];
        }
        $flag = 0;

        //要post的数据
        $argv = array(
            'sn' => $this->serialNumber, ////替换成您自己的序列号
            'pwd' => strtoupper(md5($this->serialNumber . $this->password)), //此处密码需要加密 加密方式为 md5(sn+password) 32位大写
            'mobile' => $phone, //手机号 多个用英文的逗号隔开 post理论没有长度限制.推荐群发一次小于等于10000个手机号
            'content' => iconv("UTF-8", "gb2312//IGNORE", $content), //短信内容
            'ext' => '',
            'stime' => '', //定时时间 格式为2011-6-29 11:09:21
            'rrid' => ''
        );

        //构造要post的字符串
        $params = '';
        foreach ($argv as $key => $value) {
            if ($flag != 0) {
                $params .= "&";
                $flag = 1;
            }
            $params .= $key . "=";
            $params .= urlencode($value);
            $flag = 1;
        }

        $length = strlen($params);
        //创建socket连接
        $fp = fsockopen("sdk2.entinfo.cn", 8060, $errno, $errstr, 10) or exit($errstr . "--->" . $errno);
        //构造post请求的头
        $header = "POST /webservice.asmx/mt HTTP/1.1\r\n";
        $header .= "Host:sdk2.entinfo.cn\r\n";
        $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $header .= "Content-Length: " . $length . "\r\n";
        $header .= "Connection: Close\r\n\r\n";
        //添加post的字符串
        $header .= $params . "\r\n";
        //发送post的数据
        fputs($fp, $header);
        $inheader = 1;
        while (!feof($fp)) {
            $line = fgets($fp, 1024); //去除请求包的头只显示页面的返回数据
            if ($inheader && ($line == "\n" || $line == "\r\n")) {
                $inheader = 0;
            }
        }
        $line = str_replace("<string xmlns=\"http://tempuri.org/\">", "", $line);
        $line = str_replace("</string>", "", $line);
        $result = explode("-", $line);
        fclose($fp);

        $code = (count($result) > 1) ? $line : 0;
        return ['code' => $code, 'msg' => '', 'data' => ''];
    }

    /**
     * 漫道国外短信发送
     * @param int $phone 接收验证码的手机号
     * @param string $content 发送内容
     *
     * return int 0:成功 其他:失败
     */
    public function mdSendSmsForeign($phone, $content)
    {
        if(!$this->serialNumber || !$this->password){
            return ['code' => '-10001', 'msg' => '发送短信帐号不存在', 'data' => ''];
        }

        $sn = $this->serialNumber; ////替换成您自己的序列号
        $pwd = strtoupper(md5($this->serialNumber . $this->password)); //此处密码需要加密 加密方式为 md5(sn+password) 32位大写

        $data = [
            'sn' => $sn, //提供的账号
            'pwd' => $pwd, //此处密码需要加密 加密方式为 md5(sn+password) 32位大写
            'mobile' => $phone, //手机号 多个用英文的逗号隔开 post理论没有长度限制.推荐群发一次小于等于10000个手机号
            'content' => $content, //短信内容
            'ext' => '',
            'stime' => '', //定时时间 格式为2011-6-29 11:09:21
            'rrid' => ''//默认空 如果空返回系统生成的标识串 如果传值保证值唯一 成功则返回传入的值
        ];

        $url = "http://sdk.entinfo.cn:8060/gjwebservice.asmx/mdSmsSend_g";

        $retult = $this->api_notice_increment($url, $data);

        $retult = str_replace("<?xml version=\"1.0\" encoding=\"utf-8\"?>", "", $retult);
        $retult = str_replace("<string xmlns=\"http://tempuri.org/\">", "", $retult);
        $retult = str_replace("</string>", "", $retult);

        return ['code' => $retult, 'msg' => '', 'data' => ''];
    }

    /**
     * 畅卓国外短信
     * @param $url
     * @param $data
     * @return mixed
     */
    private function api_notice_increment($url, $data)
    {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        $data = http_build_query($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回

        $lst = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        return $lst;
    }


}

<?php
/**
 * Base Controller
 * User: Fermin
 * Date: 2017/10/11
 * Time: 11:11
 */

namespace app\api\controller;

use app\common\model\Member;
use think\Controller;

class Base extends Controller
{

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 接口返回信息 json格式
     * @param int $code
     * @param string $msg
     * @param array $data
     * @param array $other
     * $other['img_url'] 个性化图片域名
     */
    public function apiReturn($code = 1, $msg = '', $data = null, $other = [])
    {
        switch ($code) {
            case 1:
                $msg = $msg != '' ? $msg : "请求成功";
                break;
            case 0:
                $msg = $msg != '' ? $msg : "数据为空";
                break;
            case -1:
                $msg = $msg != '' ? $msg : "请求失败";
                break;
            case -2:
                $msg = $msg != '' ? $msg : "参数错误";
                break;
        }

        // 将data数组值中的null换为空字符串
        if (!empty($data) && is_array($data)) {
            array_walk_recursive(
                $data,
                function (&$v) {
                    is_null($v) && $v = '';
                }
            );
        }

        $result = ['code' => strval($code), 'msg' => $msg, 'data' => $data];
        $img_url = isset($other['img_url']) ? $other['img_url'] : config('img_url');
        unset($other['img_url']);
        if (!empty($other)) {
            $result = array_merge($result, $other);
        }
        if (!empty($result['data']) || count($result) > 3) {
            $result['img_url'] = $img_url;
        }

        // 判断是否jsonp格式
        $jsonp_param_name = config('var_jsonp_handler');
        $jsonp_param_value = input('get.' . $jsonp_param_name);
        $jsonp_handler_name = config('default_jsonp_handler');

        if (!empty($jsonp_param_value) && $jsonp_param_value == $jsonp_handler_name) {
            $response = jsonp($result);
        } else {
            $response = json($result);
        }

        $response->send();
        exit;
    }
}

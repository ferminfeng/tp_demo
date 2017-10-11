<?php
/**
 * 记录支付异步通知-Model
 * User: Fermin
 * Date: 2017/10/11
 * Time: 11:11
 */

namespace app\common\model;

use think\Model;

class PaymentNotify extends Model
{

    /**
     * 定义时间戳字段名
     */
    protected $createTime = 'create_time';

    /**
     * 定义自动完成字段
     */
    protected $insert = ['create_time'];

    /**
     * 定义自动完成修改器
     * create_time
     */
    protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s', config('time'));
    }

    /**
     * 插入异步通知log
     */
    public function insertPaymentNotify($param)
    {
        try{
            $result = $this->save($param);
            if (false === $result) {
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            } else {
                return ['code' => 1, 'data' => '', 'msg' => '提交成功'];
            }
        } catch (PDOException $e) {
            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

}

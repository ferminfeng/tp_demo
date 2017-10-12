/*
Navicat MySQL Data Transfer

Source Server         : localhost
Source Server Version : 50528
Source Host           : localhost:3306
Source Database       : tp_demo

Target Server Type    : MYSQL
Target Server Version : 50528
File Encoding         : 65001

Date: 2017-10-12 18:40:06
*/

SET FOREIGN_KEY_CHECKS=0;
-- ----------------------------
-- Table structure for `tp_payment_log`
-- ----------------------------
DROP TABLE IF EXISTS `tp_payment_log`;
CREATE TABLE `tp_payment_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
  `trade_no` varchar(100) NOT NULL DEFAULT '' COMMENT '支付流水号',
  `out_sn` varchar(100) NOT NULL DEFAULT '' COMMENT '商户订单编号',
  `payment_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '支付金额',
  `payment_way` tinyint(2) NOT NULL COMMENT '支付方式 1.支付宝H5 2.支付宝App 3.微信App 5.微信H5 6.支付宝PC 8.小程序 9.银联支付[预留] 10.银行卡转账 11.后台操作',
  `payment_type` tinyint(2) DEFAULT NULL COMMENT '订单类型 01:说明会;02:小秘书;03:民宿;',
  `payment_time` datetime NOT NULL COMMENT '支付时间',
  `payment_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '支付状态 -1.支付失败 0.禁用 1.支付成功 ',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='支付log表';

-- ----------------------------
-- Records of tp_payment_log
-- ----------------------------
-- ----------------------------
-- Table structure for `tp_payment_notify`
-- ----------------------------
DROP TABLE IF EXISTS `tp_payment_notify`;
CREATE TABLE `tp_payment_notify` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
  `out_sn` varchar(100) DEFAULT '' COMMENT '商户订单编号',
  `payment_way` tinyint(2) NOT NULL COMMENT '支付方式 1.支付宝H5 2.支付宝App 3.微信App 5.微信H5 6.支付宝PC 8.小程序 9.银联支付[预留] 10.银行卡转账 11.后台操作 61.支付宝退款 62.微信App退款 63.微信H5退款',
  `payment_time` datetime DEFAULT NULL COMMENT '支付时间/退款时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `payment_content` text NOT NULL COMMENT '详细数据',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='异步通知log表';

-- ----------------------------
-- Records of tp_payment_notify
-- ----------------------------

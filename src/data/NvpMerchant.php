<?php
/*
 * @Author: He bin 
 * @Date: 2022-01-26 15:15:22 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-05-31 10:46:14
 */

namespace Netflying\Paypal\data;

/**
 * 支付通道基础数据结构
 */
class NvpMerchant extends Merchant
{
    protected $apiData = [
        /**
         * API请求的URL,获取token,根据token查订单详情等
         * live: https://api-3t.paypal.com/nvp
         * sandbox: https://api-3t.sandbox.paypal.com/nvp
         */
        'endpoint' => 'string',
        /**
         * 取到TOKEN后需要跳转到的链接
         * 变量{$token}
         * live: https://www.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token={$token}
         * sandbox: https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token={$token}
         */
        'token_direct' => 'string',
        //通知地址
        'notify_url' => 'string',
        //完成地址(成功或失败)
        'complete_url' => 'string',
        //支付成功 变量{$sn}
        'return_url' => 'string',
        //支付取消 变量{$sn}
        'cancel_url' => 'string',
        //授权取消返回地址 变量{$sn}
        'authorise_cancel_url' => 'string',
        //授权成功进入页面 变量{$sn}
        'authorise_renturn_url' => 'string',
        //是否显示站点名字
        'band_name' => 'string',
    ];
    protected $apiDataNull = [
        'endpoint' => null,
        'token_direct' => null,
        'notify_url' => null,
        'complete_url' => null,
        'return_url' => null,
        'cancel_url' => null,
        'authorise_cancel_url' => null,
        'authorise_renturn_url' => null,
        'band_name' => ''
    ];
}

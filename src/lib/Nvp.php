<?php

namespace Netflying\Paypal\lib;

use Netflying\Payment\lib\PayInterface;
use Netflying\Payment\common\Curl;

use Netflying\Payment\data\Merchant;
use Netflying\Payment\data\Order;
use Netflying\Payment\data\OrderProduct;
use Netflying\Payment\data\Redirect;
use Netflying\Payment\data\OrderPayment;

class Nvp implements PayInterface
{
    protected $merchant = null;
    
    /**
     * 初始化商户
     * @param Merchant $merchant
     * @return self
     */
    public function merchant($merchant)
    {
        $this->merchant = $merchant;
        return $this;
    }
    
    /**
     * 提交支付信息(有的支付需要提交之后payment最后确认并完成)
     * @param Order
     * @param OrderProduct
     * @return Redirect
     */
    public function purchase($order, $product): Redirect 
    {


        return new Redirect([
            'status' => 1,
            'url' => 'string',
            'type' => 'string',
            'data' => 'array',
            'exception' => []
        ]);
    }
    /**
     *  统一回调通知接口
     * @return OrderPayment
     */
    public function notify(): OrderPayment 
    {
        return new OrderPayment();
    }

    public function request($method,array $fields)
    {
        $merchant = $this->merchant;
        $datas = array(
            'METHOD'    => $method,
            'VERSION'   => $merchant['version'],
            'USER'      => $merchant['user'],
            'PWD'       => $merchant['password'],
            'SIGNATURE' => $merchant['signature'],
        );
        $datas = array_merge($datas, $fields);

    }



}
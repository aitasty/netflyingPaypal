<?php

namespace NetflyingPaypal;

use Netflying\interface\Pay as PayInterface;
use Netflying\common\Curl;

use NetflyingPaypal\data\Merchant;
use NetflyingPaypal\data\Order;
use NetflyingPaypal\data\OrderProduct;
use NetflyingPaypal\data\Redirect;
use NetflyingPaypal\data\OrderPayment;

class Pay implements PayInterface
{
    protected $merchant = null;
    /**
     * 初始化商户
     * @param Merchant $merchant
     * @return void
     */
    public function init(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }
    
    /**
     * 提交支付信息(有的支付需要提交之后payment最后确认并完成)
     * @param Order
     * @return Redirect
     */
    public function purchase(Order $order, OrderProduct $product) {

    }
    /**
     *  统一回调通知接口
     * @return OrderPayment
     */
    public function notify() {

    }

}
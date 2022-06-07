<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-31 13:55:07 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-03 23:16:08
 */

namespace Netflying\PaypalTest;

use Netflying\Payment\common\Utils;
use Netflying\Payment\common\Request;

use Netflying\Paypal\data\NvpMerchant;
use Netflying\PaymentTest\Data;
use Netflying\Paypal\lib\Nvp;

class Test
{

    protected $url = '';

    public $type = 'Paypal';

    protected $merchant = [];

    /**
     * @param $url 回调通知等相对路径
     *
     * @param string $url 站点回调通知相对路径
     */
    public function __construct($url='')
    {
        $this->url = $url;
    }

    /**
     * 商家数据结构
     *
     * @return this
     */
    public function setMerchant($realMerchant = [])
    {
        $url = $this->url . '?type=' . $this->type;
        $returnUrl = $url .'&act=return_url&async=0&sn={$sn}';
        $cancelUrl = $url . '&act=cancen_url&async=0&sn={$sn}';
        $completeUrl = $url . '&act=complete_url&async=0&sn={$sn}';
        $notifyUrl = $url . '&act=notify_url&async=1&sn={$sn}';
        $authoriseCancel = $url . '&act=authorise_cancel_url&async=0&sn={$sn}';
        $authoriseReturen = $url . '&act=authorise_return_url&async=0&sn={$sn}';
        $merchant = [
            'type' => 'Paypal',
            'is_test' => 1,
            'merchant' => 'sb-iatgu5187084@business.example.com',
            'api_account' => [
                'version' => '**',
                'user' => '**',
                'password' => '***',
                'signature' => '****',
            ],
            'api_data' => [
                'band_name' => 'callie',
                'endpoint' => 'https://api-3t.sandbox.paypal.com/nvp',
                'token_direct' => 'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&useraction=commit&token={$token}',
                'authorise_cancel_url' => $authoriseCancel,
                'authorise_renturn_url' => $authoriseReturen,
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'complete_url' => $completeUrl,
                'notify_url' => $notifyUrl,
            ]
        ];
        $this->merchant = Utils::arrayMerge($merchant,$realMerchant);
        return $this;
    }
    /**
     * 提交支付
     *
     * @return void
     */
    public function pay()
    {
        $PaypalLog = new Log;
        $PaypalMerchant = new NvpMerchant($this->merchant);
        $Data = new Data;
        $Order = $Data->order();
        $Paypal = new Nvp($PaypalMerchant);
        $redirect = $Paypal->log($PaypalLog)->purchase($Order);
        return $redirect;
    }
    
    /**
     * 登录地址授权
     *
     * @return void
     */
    public function authoirzation()
    {
        $PaypalLog = new Log;
        $PaypalMerchant = new NvpMerchant($this->merchant);
        $Data = new Data;
        $Order = $Data->order();
        $Paypal = new Nvp($PaypalMerchant);
        $redirect = $Paypal->log($PaypalLog)->authoirzation($Order);
        return $redirect;
    }
    /**根据token获取详情 */
    public function tokenDetails($token)
    {
        $PaypalMerchant = new NvpMerchant($this->merchant);
        $Paypal = new Nvp($PaypalMerchant);
        return $Paypal->tokenCheckoutDetails($token);
    }
    /**
     * 根据token获取地址
     */
    public function tokenAddress($token)
    {
        $PaypalMerchant = new NvpMerchant($this->merchant);
        $Paypal = new Nvp($PaypalMerchant);
        return $Paypal->tokenAddress($token);
    }
    /**
     * 最后确认提交支付
     *
     * @return void
     */
    public function continue()
    {
        $data = Utils::mapData([
            'type' => '',
            'act' => '',
            'sn' => '',
        ],Request::receive());
        if ($data['act']=='return_url') {
            $PaypalMerchant = new NvpMerchant($this->merchant);
            $Paypal = new Nvp($PaypalMerchant);
            return $Paypal->doPurchase();
        }
        return Request::receive();
    }


    /**
     * 支付回调
     *
     * @return void
     */
    public function notify()
    {
        $PaypalMerchant = new NvpMerchant($this->merchant);
        $Paypal = new Nvp($PaypalMerchant);
        return $Paypal->notify();
    }
}

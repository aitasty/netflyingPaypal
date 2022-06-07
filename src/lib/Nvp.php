<?php
/*
 * @Author: He.Bin 
 * @Date: 2022-05-31 13:55:46 
 * @Last Modified by: He.Bin
 * @Last Modified time: 2022-06-06 15:40:31
 */

namespace Netflying\Paypal\lib;

use Netflying\Payment\common\Utils;
use Netflying\Payment\common\Request as Rt;
use Netflying\Payment\lib\PayInterface;
use Netflying\Payment\lib\Request;

use Netflying\Payment\data\Merchant;
use Netflying\Payment\data\Order;
use Netflying\Payment\data\OrderProduct;
use Netflying\Payment\data\Redirect;
use Netflying\Payment\data\OrderPayment;
use Netflying\Payment\data\RequestCreate;
use Netflying\Payment\data\Address;

class Nvp implements PayInterface
{
    protected $merchant = null;

    protected $log = '';
    //成功状态
    protected static $completeArr = ['completed'];
    //撤消
    protected static $cancelArr = [
        'canceled',
        'cancelled',
        'canceled-reversal'
    ];
    //退款
    protected static $refundArr = [
        'refunded',
        'reversed'
    ];

    public function __construct(Merchant $Merchant = null, $Log = '')
    {
        $this->merchant($Merchant);
        $this->log($Log);
    }

    /**
     * 初始化商户
     * @param Merchant $Merchant
     * @return self
     */
    public function merchant(Merchant $Merchant)
    {
        $this->Merchant = $Merchant;
        return $this;
    }
    /**
     * 日志对象
     */
    public function log($Log = '')
    {
        $this->log = $Log;
        return $this;
    }

    /**
     * 提交支付信息
     * @param Order
     * @param OrderProduct
     * @return Redirect
     */
    public function purchase(Order $Order): Redirect
    {
        $token = $this->checkoutToken($Order);
        $url = $this->getTokenUrl($token);
        $status = 1;
        if (empty($url)) {
            $status = 0;
        }
        return new Redirect([
            'status' => $status,
            'url' => $url,
            'type' => 'get',
            'params' => [],
            'exception' => []
        ]);
    }
    /**
     * 用户确认后,最终提交完成
     * 通过 RETURNURL 返回路径
     * @return Redirect
     */
    public function doPurchase()
    {
        $data = Utils::modeData(
            [
                'sn' => '',
                'PayerID' => '',
                'token' => '',
            ],
            Rt::receive()
        );
        $merchant = $this->merchant;
        $apiData = $merchant['api_data'];
        $sn = $data['sn'];
        $status = 0;
        if (!empty($data['sn']) && !empty($data['token'])) {
            $status = 1;
            $details = $this->tokenCheckoutDetails($data['token']);
            if (empty($details)) {
                try {
                    $fields   = array(
                        'PAYERID'         => $data['PayerID'],
                        'AMT'             => $details['AMT'],
                        'ITEMAMT'         => $details['AMT'],
                        'CURRENCYCODE'    => $details['CURRENCYCODE'],
                        'RETURNFMFDETAILS' => 1,
                        'TOKEN'          => $data['token'],
                        'PAYMENTACTION'  => 'Sale', //Sale或者...
                        'NOTIFYURL'      => $apiData['notify_url'],
                        'INVNUM'         => $details['INVNUM'],
                        'CUSTOM'         => '',
                        //'SHIPPINGAMT'=>'', //总运费
                        //'INSURANCEAMT' =>'', //货物保险费用
                    );
                    $rs = $this->request('DoExpressCheckoutPayment', $fields);
                    if (!$rs) {
                        $status = -1;
                    }
                } catch (\Exception $e) {
                    $status = -1;
                }
            }
        }
        $sn = $data['sn'];
        $urlReplace = function ($val) use ($sn) {
            return str_replace('{$sn}', $sn, $val);
        };
        $urlData = Utils::modeData([
            'complete_url' => '',
        ], $apiData, [
            'complete_url' => $urlReplace,
        ]);
        return new Redirect([
            'type' => 'get',
            'status' => $status,
            'url' => $urlData['complete_url'],
            'params' => [
                'sn' => $sn
            ],
            'exception' => []
        ]);
    }

    /**
     * 授权支付信息,并获取返回的地址
     */
    public function authoirzation(Order $Order): Redirect
    {
        $token = $this->checkoutToken($Order, 'Authorization');
        $url = $this->getTokenUrl($token);
        $status = 1;
        if (empty($url)) {
            $status = 0;
        }
        return new Redirect([
            'status' => $status,
            'url' => $url,
            'type' => 'get',
            'params' => [],
            'exception' => []
        ]);
    }

    /**
     *  统一回调通知接口
     * @return OrderPayment
     */
    public function notify(): OrderPayment
    {
        $data = Utils::mapData([
            'sn' => '',
            'amount' => 0,
            'fee' => 0,
            'txn_id' => '',
            'ipn_track_id' => '',
            'currency' => '',
            'status_str' => '',
            'pay_time' => 0,
        ], Rt::receive(), [
            'sn' => "item_number,invoice",
            'amount' => 'payment_gross,mc_gross',
            'fee' => 'payment_fee,mc_fee',
            'currency' => 'mc_currency',
            'status_str' => 'payment_status',
            'pay_time' => 'payment_date'
        ]);
        $status = -2;
        $statusStr = strtolower($data['status_str']);
        if (in_array($statusStr, self::$completeArr)) {
            $status = 1;
        } elseif (in_array($statusStr, self::$cancelArr)) {
            $status = 0;
        } elseif (in_array($statusStr, self::$refundArr)) {
            $status = -1;
        }
        return new OrderPayment([
            'sn' => $data['sn'],
            'type' => $this->merchant['type'],
            'merchant' => $this->merchant['merchant'],
            'pay_id' => $data['txn_id'],
            'pay_sn' => $data['ipn_track_id'],
            'currency' => $data['currency'],
            'amount' => $data['amount'],
            'fee' => $data['fee'],
            'status' => $status,
            'status_descrip' => $data['status_str'],
            'pay_time' => $data['pay_time']
        ]);
    }
    /**
     * token获取授权地址
     *
     * @param string $token
     * @return Address
     */
    public function tokenAddress($token)
    {
        $rs = $this->tokenCheckoutDetails($token);
        $data = Utils::mapData([
            'first_name'      => '',
            'last_name'       => '',
            'email'           => '',
            'phone'           => '',
            'country_code'    => '',
            'region'          => '',
            'city'            => '',
            'district'        => '',
            'postal_code'     => '',
            'street_address'  => '',
            'street_address2' => ''
        ], $rs, [
            'first_name'      => 'SHIPTONAME',
            'last_name'       => 'SHIPTONAME',
            'email'           => 'EMAIL',
            'phone'           => '',
            'country_code'    => 'SHIPTOCOUNTRYCODE',
            'region'          => 'SHIPTOSTATE',
            'city'            => 'SHIPTOCITY',
            'district'        => '',
            'postal_code'     => 'SHIPTOZIP',
            'street_address'  => 'SHIPTOSTREET',
            'street_address2' => ''
        ]);
        if (!empty($data['first_name'])) {
            $shipNameArr = explode(' ', $data['first_name']);
            $nameData = Utils::mapData([
                'first_name' => '',
                'last_name' => '',
            ], $shipNameArr, [
                'first_name' => [0],
                'last_name' => [1]
            ]);
            $data = array_merge($data, $nameData);
        }
        return new Address($data);
    }
    /**
     * 根据token获取订单详情
     */
    public function tokenCheckoutDetails($token)
    {
        if (empty($token)) {
            return false;
        }
        return $this->request('GetExpressCheckoutDetails', [
            'TOKEN' => $token
        ]);
    }
    /**
     * 获取跳转支付token
     *
     * @param Order $order
     * @return void
     */
    protected function checkoutToken(Order $Order, $type = '')
    {
        $merchant = $this->merchant;
        $apiData = $merchant['api_data'];
        $amount = Utils::caldiv($Order['purchase_amount'], 100);
        $maxAmt = $amount + 1;
        $sn = $Order['sn'];
        $urlReplace = function ($val) use ($sn) {
            return str_replace('{$sn}', $sn, $val);
        };
        $urlData = Utils::modeData([
            'cancel_url' => '',
            'return_url' => '',
            'notify_url' => '',
            'authorise_cancel_url' => '',
            'authorise_renturn_url' => '',
        ], $apiData, [
            'cancel_url' => $urlReplace,
            'return_url' => $urlReplace,
            'notify_url' => $urlReplace,
            'authorise_cancel_url' => $urlReplace,
            'authorise_renturn_url' => $urlReplace,
        ]);
        $action = 'Sale';
        $noShipping = 2; //物流信息必需
        $cancelUrl = $urlData['cancel_url'];
        $returnUrl = $urlData['return_url'];
        $notifyUrl = $urlData['notify_url'];
        if (!empty($type) && in_array($type, ['Sale', 'Authorization', 'order'])) {
            $action = $type;
        }
        if ($action == 'Authorization') {
            $noShipping = 0; //在 PayPal 页面上显示送货地址。
            $cancelUrl = $urlData['authorise_cancel_url'];
            $returnUrl = $urlData['authorise_renturn_url'];
        }
        $fields   = [
            'CANCELURL' => $cancelUrl,  //支付取消返回
            'RETURNURL' => $returnUrl, //支付成功返回
            'NOTIFYURL' => $notifyUrl,
            'AMT'       => $amount,
            'ITEMAMT'   => $amount,
            'CURRENCYCODE' => $Order['currency'],
            'MAXAMT'    =>  $maxAmt, //最高可能总额(最大运费,汇率差等)
            'CUSTOM'    => '',
            'INVNUM'    => $Order['sn'], //唯一标识值，一般可为订单号
            'DESC'      => '', //描述
            'PAYMENTACTION' => $action, //支付动作 Sale,Authorization,Order 此付款是一项基本授权，需通过 PayPal Authorization and Capture 进行结算。
            //other fields
            'SHIPPINGAMT' => 0, //物流费用，如果有物流
            'NOSHIPPING'  => $noShipping, //一定要有物流信息
        ];
        $bandName = $this->merchant['band_name'];
        $localCode = $this->getLocalCode();
        if ($localCode) {
            //可能出现中文,传递浏览器语言
            $fields['LOCALECODE'] = $localCode;
        }
        if (!empty($bandName)) {
            $fields['BRANDNAME'] = $bandName; //显示品牌为站点名字
        }
        $ret = $this->request('SetExpressCheckout', $fields);
        return isset($ret['TOKEN']) ? $ret['TOKEN'] : false;
    }
    protected function getTokenUrl($token)
    {
        if (empty($token)) {
            return '';
        }
        $token = $token ? urlencode($token) : '';
        return  str_replace('{$token}', $token, $this->merchant['api_data']['token_direct']);
    }
    protected function doPayment($payerId, $token)
    {
        $detail = $this->tokenCheckoutDetails($token);
        if (empty($detail)) {
            //token信息异常
            return false;
        }
        $merchant = $this->merchant;
        $apiData = $merchant['apiData'];
        $fields   = array(
            'PAYERID'         => $payerId,
            'AMT'             => $detail['AMT'],
            'ITEMAMT'       => $detail['AMT'],
            'CURRENCYCODE'     => $detail['CURRENCYCODE'],
            'RETURNFMFDETAILS' => 1,
            'TOKEN'         => $token,
            'PAYMENTACTION' => 'Sale', //Sale或者...
            'NOTIFYURL'     => $apiData['notify_url'],
            'INVNUM'         => $detail['INVNUM'],
            'CUSTOM'         => '',
            //'SHIPPINGAMT'=>'', //总运费
            //'INSURANCEAMT' =>'', //货物保险费用
        );
        $rs = $this->request('DoExpressCheckoutPayment', $fields);
        if (empty($rs)) {
            //提交异常
            return false;
        }
        return $rs;
    }
    /**
     * 解析浏览器带的，得到localcode
     * 
     * @return bool|mixed|string
     */
    protected function getLocalCode()
    {
        $ret = '';
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
            $tmp = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            if (stripos($tmp, '-') == 2) {
                $ret = substr($tmp, 0, 5);
                $ret = str_replace('-', '_', $ret);
            }
        }
        return $ret;
    }

    protected function request($method, array $fields)
    {
        $merchant = $this->merchant;
        $apiAccount = $merchant['api_account'];
        $apiData = $merchant['api_data'];
        $data = array(
            'METHOD'    => $method,
            'VERSION'   => $apiAccount['version'],
            'USER'      => $apiAccount['user'],
            'PWD'       => $apiAccount['password'],
            'SIGNATURE' => $apiAccount['signature'],
        );
        $data = array_merge($data, $fields);
        $url = $apiData['endpoint'];
        $res = Request::create(new RequestCreate([
            'type' => 'post',
            'url' => $url,
            'data' => $data,
            'log' => $this->log
        ]));
        $code = $res['code'];
        $body = $res['body'];
        if ($code == 200) {
            parse_str($body, $rs);
            if (is_array($rs) && isset($rs['ACK']) && $rs['ACK'] == 'Success') {
                return $rs;
            }
            //业务错误
            return false;
        } else {
            //请求错误
            return false;
        }
    }
}

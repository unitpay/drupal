<?php

namespace Drupal\commerce_unitpay_gateway;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\PaymentInterface;

/**
 * Class UnitPayCore
 * @package Drupal\commerce_unitpay_gateway
 */
class UnitPayCore
{
    /**
     * @var
     */
    protected $currentMethod;

    /**
     * @var
     */
    protected $domain;
    /**
     * @var
     */
    protected $publicKey;
    /**
     * @var
     */
    protected $secretKey;

    /**
     * @var bool
     */
    public $processSuccess = false;

    /**
     * @return bool
     */
    public function isProcessSuccess()
    {
        return $this->processSuccess;
    }

    /**
     * @param bool $processSuccess
     */
    public function setProcessSuccess($processSuccess)
    {
        $this->processSuccess = $processSuccess;
    }

    /**
     * @return mixed
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @return mixed
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * @param mixed $publicKey
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /**
     * @return mixed
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * @param mixed $secretKey
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * @return string
     */
    public function endpoint()
    {
        return 'https://' . $this->getDomain() . '/pay/' . $this->getPublicKey();
    }

    /**
     * @return mixed
     */
    public function getCurrentMethod()
    {
        return $this->currentMethod;
    }

    /**
     * @param $order_id
     * @param $currency
     * @param $desc
     * @param $sum
     * @return string
     */
    public function generateSignature($order_id, $currency, $desc, $sum)
    {
        return hash('sha256', join('{up}', array(
            $order_id,
            $currency,
            $desc,
            $sum,
            $this->getSecretKey()
        )));
    }

    /**
     * @param $method
     * @param array $params
     * @return string
     */
    public function getSignature($method, array $params)
    {
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        array_push($params, $this->getSecretKey());
        array_unshift($params, $method);

        return hash('sha256', join('{up}', $params));
    }

    /**
     * @param $params
     * @param $method
     * @return bool
     */
    public function verifySignature($params, $method)
    {
        return $params['signature'] == $this->getSignature($method, $params);
    }

    /**
     * @param $items
     * @return string
     */
    public function cashItems($items)
    {
        return base64_encode(json_encode($items));
    }

    /**
     * @param $rate
     * @return string
     */
    function getTaxRates($rate)
    {
        switch (intval($rate)) {
            case 10:
                $vat = 'vat10';
                break;
            case 20:
                $vat = 'vat20';
                break;
            case 0:
                $vat = 'vat0';
                break;
            default:
                $vat = 'none';
        }

        return $vat;
    }

    /**
     * @param $value
     * @return string
     */
    public function priceFormat($value)
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param $value
     * @return string
     */
    public function phoneFormat($value)
    {
        return preg_replace('/\D/', '', $value);
    }


    /**
     * @param Order $order
     * @param PaymentInterface $payment
     * @param string $nds
     * @return array
     */
    public function getRedirectUrl(Order $order, PaymentInterface $payment, $nds = "none")
    {
        $items = [];
        $description = "Оплата заказа № " . $order->id();
        $sum = $this->priceFormat($order->getTotalPrice()->getNumber());

        foreach ($order->getItems() as $item) {
            //$var = $item->getPurchasedEntity();

            $items[] = [
                "name" => $item->getTitle(),
                "count" => round($item->getQuantity()),
                "price" => $this->priceFormat($item->getTotalPrice()->getNumber() / round($item->getQuantity())),
                "sum" => $this->priceFormat($item->getAdjustedTotalPrice()->getNumber()), // getAdjustedUnitPrice
                "type" => "commodity",
                "currency" => $item->getTotalPrice()->getCurrencyCode(),
                "nds" => $nds,
            ];
        }

        $cashItems = $this->cashItems($items);

        $signature = $this->generateSignature($order->id(), $order->getTotalPrice()->getCurrencyCode(), $description,
                $sum);

        $data = [];
        $data['order_id'] = $payment->getOrderId();
        $data['currency'] = $payment->getAmount()->getCurrencyCode();
        $data['amount'] = $this->priceFormat($payment->getAmount()->getNumber());

        $params = [
            'account' => $order->id(),
            'desc' => $description,
            'sum' => $sum,
            'signature' => $signature,
            'currency' => $order->getTotalPrice()->getCurrencyCode(),
            'cashItems' => $cashItems,
            'customerEmail' => $order->getEmail(),
        ];

        return [
            'params' => $params,
            'url' => $this->endpoint(),
            'request_url' => $this->endpoint() . "?" . http_build_query($params)
        ];
    }


    /**
     * @param Order $order
     * @return array
     */
    public function validateCallback(Order $order)
    {
        $method = '';
        $params = [];
        $result = [];

        try {
            if (!$order || !$order->id()) {
                $result = array(
                    'error' => array('message' => 'Order not found')
                );
            } else {
                if ((isset($_GET['params'])) && (isset($_GET['method'])) && (isset($_GET['params']['signature']))) {
                    $params = $_GET['params'];
                    $method = $_GET['method'];
                    $signature = $params['signature'];

                    if (empty($signature)) {
                        $status_sign = false;
                    } else {
                        $status_sign = $this->verifySignature($params, $method);
                    }

                } else {
                    $status_sign = false;
                }

                if ($status_sign) {
                    if (in_array($method, array('check', 'pay', 'error'))) {
                        $this->currentMethod = $method;

                        $result = $this->findErrors($params, $this->priceFormat($order->getTotalPrice()->getNumber()), $order->getTotalPrice()->getCurrencyCode());
                    } else {
                        $result = array(
                            'error' => array('message' => 'Method not exists')
                        );
                    }
                } else {
                    $result = array(
                        'error' => array('message' => 'Signature verify error')
                    );
                }
            }

        } catch (\Exception $e) {

        }

        return $result;
    }

    /**
     * @param $params
     * @param $sum
     * @param $currency
     * @return array
     */
    public function findErrors($params, $sum, $currency)
    {
        $this->setProcessSuccess(false);

        $order_id = $params['account'];

        if (is_null($order_id)) {
            $result = array(
                'error' => array('message' => 'Order id is required')
            );
        } elseif ((float)$this->priceFormat($sum) != (float)$this->priceFormat($params['orderSum'])) {
            $result = array(
                'error' => array('message' => 'Price not equals ' . $sum . ' != ' . $params['orderSum'])
            );
        } elseif ($currency != $params['orderCurrency']) {
            $result = array(
                'error' => array('message' => 'Currency not equals ' . $currency . ' != ' . $params['orderCurrency'])
            );
        } else {
            $this->setProcessSuccess(true);

            $result = array(
                'result' => array('message' => 'Success')
            );
        }

        return $result;
    }
}

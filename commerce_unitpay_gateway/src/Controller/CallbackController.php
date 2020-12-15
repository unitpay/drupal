<?php

namespace Drupal\commerce_unitpay_gateway\Controller;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_unitpay_gateway\UnitPayCore;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Endpoints for the routes defined.
 */
class CallbackController extends ControllerBase
{
    /**
     * @var EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var UnitPayCore
     */
    protected $unitPayCore;

    /**
     * CallbackController constructor.
     * @param EntityTypeManagerInterface $entityTypeManager
     * @param UnitPayCore $unitPayCore
     */
    public function __construct(EntityTypeManagerInterface $entityTypeManager, UnitPayCore $unitPayCore)
    {
        $this->entityTypeManager = $entityTypeManager;
        $this->unitPayCore = $unitPayCore;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('entity_type.manager'),
            $container->get('commerce_unitpay_gateway.unitpay_core')
        );
    }

    /**
     * Callback action.
     *
     * Listen for callbacks from unitpay and creates any payment specified.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function callback(Request $request)
    {
        $params = $request->get("params");
        $result = [];

        if (!isset($params["account"])) {
            $result = array(
                'error' => array('message' => 'Param account is required')
            );
        } else {
            $order = Order::load($params["account"]);

            //$order->getBillingProfile()->get('address');

            if (!$order) {
                $result = array(
                    'error' => array('message' => 'Order not found')
                );
            } else {
                $payment_gateway = $order->get('payment_gateway')->entity;
                if ($payment_gateway->id() != "unitpay") {
                    $result = array(
                        'error' => array('message' => 'Bad payment')
                    );
                } else {
                    $payment_gateway_plugin = $payment_gateway->getPlugin();
                    $config = $payment_gateway_plugin->getConfiguration();
                    //$payment_gateway_plugin_id = $payment_gateway_plugin->getBaseId();

                    $this->unitPayCore->setDomain($config["domain"]);
                    $this->unitPayCore->setPublicKey($config["public_key"]);
                    $this->unitPayCore->setSecretKey($config["private_key"]);

                    $result = $this->unitPayCore->validateCallback($order);

                    if ($this->unitPayCore->isProcessSuccess()) {
                        $payment_gateway_plugin->onNotify($request);

                        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
                        $payment = $payment_storage->create([
                            'state' => 'completed', //authorization
                            'amount' => $order->getTotalPrice(),
                            'payment_gateway' => $payment_gateway->id(),
                            'order_id' => $order->id(),
                            'remote_id' => uniqid(),
                            'remote_state' => "success",
                        ]);

                        $payment->save();
                    }
                }
            }
        }

        return new JsonResponse($result);
    }
}

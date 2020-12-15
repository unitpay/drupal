<?php

namespace Drupal\commerce_unitpay_gateway\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_unitpay_gateway\UnitPayCore;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Class RedirectCheckoutForm
 * @package Drupal\commerce_unitpay_gateway\PluginForm
 */
class RedirectCheckoutForm extends PaymentOffsiteForm implements ContainerInjectionInterface
{
    /**
     * @var UnitPayCore
     */
    protected $unitPayCore;

    /**
     * RedirectCheckoutForm constructor.
     * @param UnitPayCore $unitPayCore
     */
    function __construct(UnitPayCore $unitPayCore)
    {
        $this->unitPayCore = $unitPayCore;
    }

    /**
     * @param ContainerInterface $container
     * @return RedirectCheckoutForm
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('commerce_unitpay_gateway.unitpay_core')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);
        $configuration = $this->getConfiguration();

        $this->unitPayCore->setDomain($configuration["domain"]);
        $this->unitPayCore->setPublicKey($configuration["public_key"]);
        $this->unitPayCore->setSecretKey($configuration["private_key"]);

        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;

        $order = Order::load($payment->getOrderId());

        $redirectData = $this->unitPayCore->getRedirectUrl($order, $payment, $configuration["nds"]);

        return $this->buildRedirectForm(
            $form,
            $form_state,
            $redirectData['url'],
            $redirectData['params'],
            PaymentOffsiteForm::REDIRECT_POST
        );
    }

    /**
     * @return array
     */
    private function getConfiguration()
    {
        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
        $payment = $this->entity;

        /** @var \Drupal\commerce_unitpay_gateway\Plugin\Commerce\PaymentGateway\RedirectCheckout $payment_gateway_plugin */
        $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
        return $payment_gateway_plugin->getConfiguration();
    }
}

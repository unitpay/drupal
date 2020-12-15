<?php

namespace Drupal\commerce_unitpay_gateway\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Provides the unitpay offsite Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "unitpay_redirect_checkout",
 *   label = @Translation("unitpay (Redirect to unitpay)"),
 *   display_label = @Translation("unitpay"),
 *    forms = {
 *     "offsite-payment" = "Drupal\commerce_unitpay_gateway\PluginForm\RedirectCheckoutForm",
 *   },
 * )
 */
class RedirectCheckout extends OffsitePaymentGatewayBase
{
    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
            'domain' => 'unitpay.ru',
            'public_key' => '',
            'private_key' => '',
            'nds' => 'none',
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        $form['domain'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Domain'),
            '#description' => $this->t('unitpay payment domain'),
            '#default_value' => $this->configuration['domain'],
            '#required' => true,
        ];

        $form['public_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Public key'),
            '#description' => $this->t('This is the public key from the unitpay manager.'),
            '#default_value' => $this->configuration['public_key'],
            '#required' => true,
        ];

        $form['private_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Private key'),
            '#description' => $this->t('This is the private key from the unitpay manager.'),
            '#default_value' => $this->configuration['private_key'],
            '#required' => true,
        ];

        $form['nds'] = [
            '#type' => 'select',
            '#title' => $this->t('NDS'),
            '#description' => $this->t('none, vat0, vat10, vat20'),
            '#default_value' => $this->configuration['nds'],
            '#options' => [
                'none' => $this->t('none'),
                'vat0' => $this->t('vat0'),
                'vat10' => $this->t('vat10'),
                'vat20' => $this->t('vat20'),
            ],
            '#required' => true,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['domain'] = $values['domain'];
            $this->configuration['public_key'] = $values['public_key'];
            $this->configuration['private_key'] = $values['private_key'];
            $this->configuration['nds'] = $values['nds'];
        }
    }
}

<?php

namespace FacturaScripts\Plugins\PortalCliente\Lib\Pay;

use Exception;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Plugins\PortalCliente\Contract\PortalPaymentGatewayInterface;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalTools;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPaymentGatewayStripe implements PortalPaymentGatewayInterface
{
    public function getHtml(SalesDocument $model, Contacto $contact): string
    {
        // obtenemos la empresa del documento
        $company = $model->getCompany();

        // si no tiene configurado Stripe, terminamos
        if (empty($company->pc_stripe_pk)
            || empty($company->pc_stripe_sk)
            || empty($company->pc_stripe_codpago)
            || empty($company->cifnif)) {
            return '';
        }

        // comprobamos que la empresa de la forma de pago sea la misma que la del documento
        $paymentMethod = new FormaPago();
        if (false === $paymentMethod->loadFromCode($company->pc_stripe_codpago)
            || $paymentMethod->idempresa !== $company->idempresa) {
            return '';
        }

        // si el contacto no tiene email, terminamos
        if (empty($contact->email)) {
            return '<div class="alert alert-warning text-center">'
                . Tools::lang()->trans('you-need-an-email-to-pay-online')
                . '</div>';
        }

        // creamos la instancia de Stripe
        $stripe = new StripeApi($company->pc_stripe_sk, $company->cifnif);

        // obtenemos o creamos el cliente
        $stripeCustomer = $stripe->checkCustomer($contact);

        if (empty($stripeCustomer)) {
            return '';
        }

        $description = $model->modelClassName() === 'FacturaCliente' ?
            Tools::lang()->trans('invoice') . ' #' . $model->codigo :
            Tools::lang()->trans('order') . ' #' . $model->codigo;

        $session = $stripe->addSession([
            'customer' => $stripeCustomer->id,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($model->coddivisa),
                    'product_data' => [
                        'name' => $description,
                    ],
                    'unit_amount' => $model->total * 100,
                ],
                'quantity' => 1,
            ]],
            'payment_intent_data' => [
                'description' => $description,
                'metadata' => [
                    'model_code' => $model->codigo,
                    'model_id' => $model->primaryColumnValue(),
                    'model_name' => $model->modelClassName(),
                ]
            ],
            'mode' => 'payment',
            'success_url' => PortalTools::getSiteUrl() . '/' . $model->url('public') . '&session_id={CHECKOUT_SESSION_ID}&action=pay&platform=' . $this->name(),
            'cancel_url' => PortalTools::getSiteUrl() . '/' . $model->url('public'),
            'currency' => strtolower($model->coddivisa),
        ]);

        if (empty($session)) {
            return '';
        }

        return '<script src="https://js.stripe.com/v3/"></script>'
            . '<script>'
            . 'function payWithStripe() {'
            . 'animateSpinner("add");'
            . "const stripe = Stripe('" . $company->pc_stripe_pk . "');"
            . "stripe.redirectToCheckout({sessionId: '" . $session->id . "'}).then(function (result) {"
            . "console.log(result);"
            . "});"
            . 'return false;'
            . '}'
            . '</script>'
            . '<a href="#" class="btn btn-primary btn-block mb-3 btn-spin-action" onclick="return payWithStripe();">'
            . '<i class="fa-brands fa-cc-stripe mr-2"></i>'
            . Tools::lang()->trans('pay-with-stripe')
            . '<div class="small">' . Tools::lang()->trans('pay-with-stripe-desc') . '</div>'
            . '</a>';
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function payAction(SalesDocument &$model, Request $request): bool
    {
        try {
            // obtenemos la empresa del documento
            $company = $model->getCompany();

            // creamos la instancia de Stripe
            $stripe = new StripeApi($company->pc_stripe_sk, $company->cifnif);

            // obtenemos la sesión de Stripe
            $sessionID = $request->get('session_id', '');
            $session = $stripe->getSession($sessionID);
            if (empty($session)) {
                Tools::log('stripe')->critical('session-not-found: ' . $sessionID);
                return false;
            }

            // obtenemos la intención de pago
            $paymentIntent = $stripe->getPaymentIntent($session->payment_intent);
            if (is_null($paymentIntent)) {
                Tools::log('stripe')->critical('payment-intent-not-found: ' . $session->payment_intent);
                return false;
            }

            // comprobamos que el pago se ha realizado
            if ('succeeded' !== $paymentIntent->status) {
                Tools::log('stripe')->critical('payment-not-succeeded: ' . $paymentIntent->id);
                return false;
            }

            // obtenemos la forma de pago de Stripe
            $paymentMethod = new FormaPago();
            if ($paymentMethod->loadFromCode($company->pc_stripe_codpago)
                && $paymentMethod->idempresa === $company->idempresa) {
                $model->codpago = $company->pc_stripe_codpago;
            }

            // añadimos el ID de la intención de pago
            $model->pc_payment_intent_stripe = $session->payment_intent;

            // actualizamos el documento
            return $model->save();
        } catch (Exception $e) {
            Tools::log('stripe')->critical('error-pay-stripe', $e->getMessage());
            return false;
        }
    }
}
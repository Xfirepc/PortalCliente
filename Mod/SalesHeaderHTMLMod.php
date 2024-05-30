<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Mod;

use FacturaScripts\Core\Base\Contract\SalesModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\User;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\StripeApi;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class SalesHeaderHTMLMod implements SalesModInterface
{

    public function apply(SalesDocument &$model, array $formData, User $user)
    {
    }

    public function applyBefore(SalesDocument &$model, array $formData, User $user)
    {
    }

    public function assets(): void
    {
    }

    public function newBtnFields(): array
    {
        return [];
    }

    public function newFields(): array
    {
        return [];
    }

    public function newModalFields(): array
    {
        return ['stripe'];
    }

    public function renderField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        return match ($field) {
            'stripe' => $this->stripe($i18n, $model),
            default => null,
        };

    }

    private function stripe(Translator $i18n, SalesDocument $model): string
    {
        if (false === in_array($model->modelClassName(), ['PedidoCliente', 'FacturaCliente'])) {
            return '';
        }

        return '<div class="col-sm-6">'
            . '<div class="form-group">'
            . '<a href="' . StripeApi::urlDashboard($model->getCompany(), 'payments/' . $model->pc_payment_intent_stripe) . '" target="_blank">'
            . $i18n->trans('payment-stripe')
            . '</a>'
            . '<input type="text" value="' . $model->pc_payment_intent_stripe . '" class="form-control" disabled>'
            . '</div>'
            . '</div>';
    }
}
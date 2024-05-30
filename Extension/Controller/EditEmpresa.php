<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\DataSrc\FormasPago;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditEmpresa
{
    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            $mvn = $this->getMainViewName();
            if ($viewName !== $mvn) {
                return;
            }

            $this->loadPaymentMethodsValues($viewName);
        };
    }

    protected function loadPaymentMethodsValues(): Closure
    {
        return function (string $viewName) {
            $column = $this->views[$viewName]->columnForName('payment-method');
            if (empty($column) || $column->widget->getType() !== 'select') {
                return;
            }

            $values = [];
            $idempresa = $this->getViewModelValue($viewName, 'idempresa');
            foreach (FormasPago::all() as $paymentMethod) {
                if ($paymentMethod->idempresa === $idempresa) {
                    $values[] = ['value' => $paymentMethod->codpago, 'title' => $paymentMethod->descripcion];
                }
            }

            $column->widget->setValuesFromArray($values, false, true);

        };
    }
}

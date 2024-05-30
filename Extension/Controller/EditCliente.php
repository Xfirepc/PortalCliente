<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditCliente
{
    use DocFilesTrait;

    public function createViews(): Closure
    {
        return function() {
            $viewName = 'ListPortalCliente';
            $this->addListView($viewName, 'Contacto', 'client-portal', 'fas fa-chalkboard-user');
            $this->setSettings($viewName, 'btnNew', false);
            $this->setSettings($viewName, 'btnDelete', false);
            $this->setSettings($viewName, 'checkBoxes', false);
        };
    }

    public function loadData(): Closure
    {
        return function($viewName, $view) {
            if ($viewName !== 'ListPortalCliente') {
                return;
            }

            $codcliente = $this->getViewModelValue('EditCliente', 'codcliente');
            $where = [new DataBaseWhere('codcliente', $codcliente)];
            $view->loadData('', $where);
        };
    }
}

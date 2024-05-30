<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class ListCliente
{
    public function createViews(): Closure
    {
        return function() {
            $this->addFilterAutocomplete('ListContacto', 'pc_nick', 'nick', 'pc_nick', 'contactos', 'idcontacto', 'pc_nick');
            $this->createViewTickets();
        };
    }

    protected function createViewTickets(): Closure
    {
        return function (string $viewName = 'ListPortalTicket') {
            $this->addView($viewName, 'PortalTicket', 'tickets', 'far fa-comment-dots')
                ->addOrderBy(['creation_date'], 'date')
                ->addOrderBy(['last_update'], 'last-update', 2)
                ->addSearchFields(['body']);

            $this->setSettings($viewName, 'btnNew', false);
            $this->setSettings($viewName, 'btnDelete', false);
            $this->setSettings($viewName, 'checkBoxes', false);
        };
    }
}

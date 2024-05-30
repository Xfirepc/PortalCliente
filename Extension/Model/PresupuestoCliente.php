<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Model;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PresupuestoCliente
{
    public function url(): Closure
    {
        return function ($type, $list) {
            if ($type !== 'public') {
                return;
            }

            return 'PortalPresupuesto?code=' . $this->primaryColumnValue();
        };
    }
}
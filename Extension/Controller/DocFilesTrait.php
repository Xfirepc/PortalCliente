<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait DocFilesTrait
{
    public function addFileAction(): Closure
    {
        return function ($fileRelation, $request) {
            $fileRelation->pc_show = (bool)$request->request->get('pc_show', false);
        };
    }

    public function editFileAction(): Closure
    {
        return function ($fileRelation, $request) {
            $fileRelation->pc_show = (bool)$request->request->get('pc_show', false);
        };
    }
}
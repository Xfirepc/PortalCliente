<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente;

use FacturaScripts\Core\Base\AjaxForms\SalesHeaderHTML;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\InitClass;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PortalPaymentGateway;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PortalPaymentGatewayBank;
use FacturaScripts\Plugins\PortalCliente\Lib\Pay\PortalPaymentGatewayStripe;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Init extends InitClass
{
    public function init()
    {
        $this->loadExtension(new Extension\Controller\EditAlbaranCliente());
        $this->loadExtension(new Extension\Controller\EditCliente());
        $this->loadExtension(new Extension\Controller\EditContacto());
        $this->loadExtension(new Extension\Controller\EditEmpresa());
        $this->loadExtension(new Extension\Controller\EditFacturaCliente());
        $this->loadExtension(new Extension\Controller\EditPedidoCliente());
        $this->loadExtension(new Extension\Controller\EditPresupuestoCliente());
        $this->loadExtension(new Extension\Controller\ListCliente());
        $this->loadExtension(new Extension\Model\Contacto());
        $this->loadExtension(new Extension\Model\FacturaCliente());
        $this->loadExtension(new Extension\Model\PedidoCliente());
        $this->loadExtension(new Extension\Model\PresupuestoCliente());

        SalesHeaderHTML::addMod(new Mod\SalesHeaderHTMLMod());

        // cargamos las pasarelas de pago
        PortalPaymentGateway::register(new PortalPaymentGatewayStripe());
        PortalPaymentGateway::register(new PortalPaymentGatewayBank());
    }

    public function update()
    {
        $this->createLoginContact();
    }

    private function createLoginContact(): void
    {
        $contactModel = new Contacto();
        $where = [new DataBaseWhere('pc_nick', null)];
        foreach ($contactModel->all($where, [], 0, 0) as $contact) {
            $contact->save();
        }
    }
}
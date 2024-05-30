<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\PortalTicket;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditPortalTicket extends EditController
{
    public function getModelClassName(): string
    {
        return 'PortalTicket';
    }

    protected function createViews(): void
    {
        // obtenemos el modelo
        $model = new PortalTicket();

        if (false === $model->loadFromCode($this->request->get('code'))) {
            $this->redirect('ListCliente?activetab=ListPortalTicket');
            return;
        }

        $contact = $model->getContact();
        $contact->updatePCActivity();
        $contact->save();

        $expire = time() + FS_COOKIES_EXPIRE;
        $this->response->headers->setCookie(
            Cookie::create('pc_idcontacto', $contact->idcontacto, $expire, Tools::config('route', '/'))
        );
        $this->response->headers->setCookie(
            Cookie::create('pc_log_key', $contact->pc_log_key, $expire, Tools::config('route', '/'))
        );

        $this->redirect($model->url('public'));
    }
}
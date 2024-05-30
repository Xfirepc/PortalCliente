<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Extension\Controller;

use Closure;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalTools;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class EditContacto
{
    use DocFilesTrait;

    protected function createViews(): Closure
    {
        return function () {
            $this->createViewTickets();
        };
    }

    protected function createViewTickets(): Closure
    {
        return function (string $viewName = 'ListPortalTicket') {
            $this->addListView($viewName, 'PortalTicket', 'tickets', 'far fa-comment-dots')
                ->addOrderBy(['creation_date'], 'date')
                ->addOrderBy(['last_update'], 'last-update', 2)
                ->addSearchFields(['body'])
                ->disableColumn('contact', true);

            $this->setSettings($viewName, 'btnNew', false);
            $this->setSettings($viewName, 'btnDelete', false);
            $this->setSettings($viewName, 'checkBoxes', false);
        };
    }

    public function execPreviousAction(): Closure
    {
        return function($action) {
            switch ($action) {
                case 'client-portal';
                    $this->clientPortalAction();
                    break;

                case 'send-link-client-portal':
                    $this->sendLinkClientPortalAction();
                    break;
            }
        };
    }

    public function loadData(): Closure
    {
        return function($viewName, $view) {
            $mvn = $this->getMainViewName();

            switch ($viewName) {
                case 'ListPortalTicket':
                    $idcontacto = $this->getViewModelValue($mvn, 'idcontacto');
                    $where = [new DataBaseWhere('idcontacto', $idcontacto)];
                    $view->loadData('', $where);
                    break;

                case $mvn:
                    $this->addButton($mvn, [
                        'action' => 'client-portal',
                        'color' => 'primary',
                        'icon' => 'fas fa-chalkboard-user',
                        'label' => 'client-portal',
                        'type' => 'action',
                    ]);

                    $this->addButton($mvn, [
                        'action' => 'send-link-client-portal',
                        'color' => 'light',
                        'icon' => 'fas fa-paper-plane',
                        'title' => 'send-link-client-portal',
                        'label' => 'send-link-client-portal-abb',
                        'type' => 'action',
                    ]);
                    break;
            }
        };
    }

    protected function clientPortalAction(): Closure
    {
        return function () {
            $contact = $this->getModel();
            if (false === $contact->loadFromCode($this->request->get('code'))) {
                return;
            }

            $contact->updatePCActivity($this->response->headers->get('User-Agent'));
            $contact->save();

            $expire = time() + FS_COOKIES_EXPIRE;
            $this->response->headers->setCookie(
                Cookie::create('pc_idcontacto', $contact->idcontacto, $expire, Tools::config('route', '/'))
            );
            $this->response->headers->setCookie(
                Cookie::create('pc_log_key', $contact->pc_log_key, $expire, Tools::config('route', '/'))
            );

            $this->redirect('PortalCliente');
        };
    }

    protected function sendLinkClientPortalAction(): Closure
    {
        return function () {
            $contact = $this->getModel();
            if (false === $contact->loadFromCode($this->request->get('code'))) {
                return;
            }

            // si el contacto no tiene email, terminamos
            if (empty($contact->email)) {
                Tools::log()->warning('contact-without-email-portal', ['%nick%' => $contact->pc_nick]);
                return;
            }

            // si el contacto no está activo, terminamos
            if (false === $contact->pc_active) {
                Tools::log()->warning('contact-not-active-portal', ['%nick%' => $contact->pc_nick]);
                return;
            }

            // establecemos una nueva contraseña
            $newPassword = Tools::randomString(8);
            $contact->setPCPassword($newPassword);
            if (false === $contact->save()) {
                Tools::log()->error('record-save-error');
                return;
            }

            // enviamos el correo
            $email = NewMail::create()
                ->to($contact->email)
                ->subject(Tools::lang($contact->langcode)->trans('client-portal-link-subject'))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('client-portal-access')))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('client-portal-access-nick', ['%nick%' => $contact->pc_nick])))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('client-portal-access-password', ['%password%' => $newPassword])))
                ->addMainBlock(new SpaceBlock(10))
                ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('client-portal-link-body')))
                ->addMainBlock(new ButtonBlock(Tools::lang($contact->langcode)->trans('client-portal'), PortalTools::getSiteUrl() . '/PortalCliente'));

            if ($email->send()) {
                Tools::log()->info('client-portal-link-sent');
                return;
            }

            Tools::log()->error('client-portal-link-not-sent');
        };
    }
}

<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Controller\PortalLogin;
use FacturaScripts\Dinamic\Lib\PortalPanelController;
use FacturaScripts\Dinamic\Model\Ciudad;
use FacturaScripts\Dinamic\Model\Provincia;
use FacturaScripts\Plugins\Plesk\Lib\ContactLoaderTrait;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocFilesTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalCliente extends PortalPanelController
{
    use PortalDocFilesTrait;
    use ContactLoaderTrait;

    public function commonCore(): void
    {
        parent::commonCore();
        if (empty($this->loadContact())) {
            $this->redirect('MeLogin');
        }

    }

    const ACCOUNT_TEMPLATE = 'PortalCliente';

    public function getCities(): array
    {
        $model = new Ciudad();
        return $model->all([], ['ciudad' => 'ASC'], 0, 0);
    }

    public function getLanguages(): array
    {
        $langs = [];
        foreach (Tools::lang()->getAvailableLanguages() as $key => $value) {
            $langs[] = ['value' => $key, 'title' => $value];
        }
        return $langs;
    }

    public function getProvincies(): array
    {
        $model = new Provincia();
        return $model->all([], ['provincia' => 'ASC'], 0, 0);
    }

    protected function createViews(): void
    {
        if (empty($this->contact)) {
            $this->redirect('PortalLogin');
            return;
        }

        $this->createViewsAccount();
        $this->createViewsBudgets();
        $this->createViewsOrders();
        $this->createViewsInvoices();
        $this->createViewDocFiles();
        $this->createViewTickets();
    }

    protected function createViewsAccount(string $viewName = 'PortalCuenta'): void
    {
        $this->setTemplate(self::ACCOUNT_TEMPLATE);
        $this->title = Tools::lang()->trans('my-profile');
        $this->addHtmlView($viewName, 'Tab/PortalCuenta', 'Contacto', 'details', 'fas fa-user-circle');
    }

    protected function createViewsBudgets(string $viewName = 'ListPortalPresupuesto'): void
    {
        $this->addListView($viewName, 'PresupuestoCliente', 'estimations', 'far fa-file-powerpoint')
            ->addOrderBy(['fecha', 'hora'], 'date', 2)
            ->addOrderBy(['total'], 'total')
            ->addSearchFields(['codigo', 'direccion', 'nombrecliente', 'observaciones']);

        $this->disableButtons($viewName);
    }

    protected function createViewsInvoices(string $viewName = 'ListPortalFactura'): void
    {
        $this->addListView($viewName, 'FacturaCliente', 'invoices', 'fas fa-file-invoice-dollar')
            ->addOrderBy(['fecha', 'hora'], 'date', 2)
            ->addOrderBy(['total'], 'total')
            ->addSearchFields(['codigo', 'direccion', 'nombrecliente', 'observaciones']);

        $this->disableButtons($viewName);
    }

    protected function createViewsOrders(string $viewName = 'ListPortalPedido'): void
    {
        $this->addListView($viewName, 'PedidoCliente', 'orders', 'fas fa-file-powerpoint')
            ->addOrderBy(['fecha', 'hora'], 'date', 2)
            ->addOrderBy(['total'], 'total')
            ->addSearchFields(['codigo', 'direccion', 'nombrecliente', 'observaciones']);

        $this->disableButtons($viewName);
    }

    protected function createViewTickets(string $viewName = 'ListPortalTicket'): void
    {
        $this->addListView($viewName, 'PortalTicket', 'tickets', 'far fa-comment-dots')
            ->addOrderBy(['creation_date'], 'date')
            ->addOrderBy(['last_update'], 'last-update')
            ->addOrderBy(['closed'], 'closed', 1)
            ->addSearchFields(['id', 'body'])
            ->disableColumn('contact', true);

        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    protected function deleteAction(): bool
    {
        return true;
    }

    protected function disableButtons(string $viewName): void
    {
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);
    }

    protected function editAction(): bool
    {
        return true;
    }

    protected function editPasswordAction(): bool
    {
        if (PortalLogin::isIpBlocked(Session::getClientIp())) {
            Tools::log()->error('ip-banned');
            return true;
        }

        if (false === $this->validateFormToken()) {
            return true;
        } elseif (empty($this->contact)
            || $this->contact->idcontacto !== (int)$this->request->request->get('idcontacto')) {
            PortalLogin::blockIp(Session::getClientIp());
            return true;
        }


        $fields = ['new_password', 'repeat_password'];
        foreach ($fields as $field) {
            $this->contact->{$field} = $this->request->request->get($field);
        }

        if (false === $this->contact->save()) {
            Tools::log()->warning('record-save-error');

            return true;
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function editProfileAction(): bool
    {
        if (PortalLogin::isIpBlocked(Session::getClientIp())) {
            Tools::log()->error('ip-banned');
            return true;
        }

        if (false === $this->validateFormToken()) {
            return true;
        } elseif (empty($this->contact)
            || $this->contact->idcontacto !== (int)$this->request->request->get('idcontacto')) {
            PortalLogin::blockIp(Session::getClientIp());
            return true;
        }

        $this->contact->admitemarketing = (bool)$this->request->request->get('admitemarketing', false);

        $fields = [
            'nombre', 'apellidos', 'empresa', 'tipoidfiscal', 'cifnif', 'direccion', 'apartado', 'email',
            'codpostal', 'ciudad', 'provincia', 'codpais', 'langcode'
        ];
        foreach ($fields as $field) {
            $this->contact->{$field} = $this->request->request->get($field);
        }

        if (false === $this->contact->save()) {
            Tools::log()->warning('record-save-error');

            return true;
        }

        Tools::log()->notice('record-updated-correctly');
        return true;
    }

    protected function execAfterAction($action): bool
    {
        if ($action === 'logout') {
            return $this->logoutAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action): bool
    {
        switch ($action) {
            case 'edit-password':
                return $this->editPasswordAction();

            case 'edit-profile':
                return $this->editProfileAction();
        }

        return parent::execPreviousAction($action);
    }

    protected function insertAction(): bool
    {
        return true;
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view): void
    {
        $this->hasData = true;

        switch ($viewName) {
            case 'docfiles':
                $view->cursor = $this->getContactDocFiles();
                $view->count = count($view->cursor);
                $view->setSettings('active', $view->count > 0 && $this->contact->pc_allow_show_files);
                break;

            case 'ListPortalTicket':
                $where = [new DataBaseWhere('idcontacto', $this->contact->idcontacto)];
                $view->loadData('', $where);
                break;

            case 'ListPortalPresupuesto':
                $where = [new DataBaseWhere('codcliente', $this->contact->codcliente)];
                $view->loadData('', $where);
                $this->setSettings($viewName, 'active', $view->count > 0 && $this->contact->pc_allow_show_estimation);
                break;

            case 'ListPortalFactura':
                $where = [new DataBaseWhere('codcliente', $this->contact->codcliente)];
                $view->loadData('', $where);
                $this->setSettings($viewName, 'active', $view->count > 0 && $this->contact->pc_allow_show_invoice);
                break;

            case 'ListPortalPedido':
                $where = [new DataBaseWhere('codcliente', $this->contact->codcliente)];
                $view->loadData('', $where);
                $this->setSettings($viewName, 'active', $view->count > 0 && $this->contact->pc_allow_show_order);
                break;
        }
    }

    protected function logoutAction(): bool
    {
        // restablecemos el idioma por defecto
        Tools::lang()->setLang(constant('FS_LANG'));

        $this->response->headers->clearCookie('pc_idcontacto');
        $this->response->headers->clearCookie('pc_log_key');

        if ($this->user) {
            $this->redirect($this->contact->url());
        } else {
            $this->redirect('PortalCliente');
        }

        $this->contact = null;
        return true;
    }
}

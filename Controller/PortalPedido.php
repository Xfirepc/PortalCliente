<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\Controller;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\PortalViewController;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocFilesTrait;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocNoticeTrait;
use FacturaScripts\Plugins\PortalCliente\Lib\PortalDocPaymentTrait;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalPedido extends PortalViewController
{
    use PortalDocNoticeTrait;
    use PortalDocFilesTrait;
    use PortalDocPaymentTrait;

    public function getModelClassName(): string
    {
        return 'PedidoCliente';
    }

    protected function cancelAction(): bool
    {
        $model = $this->preloadModel();
        if (false === $model->exists()) {
            $this->redirect('PortalCliente');
            return true;
        } elseif (false === $this->validateFormToken()) {
            return true;
        } elseif (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied');
            return true;
        }

        foreach ($model->getAvailableStatus() as $status) {
            if (false === $status->editable && empty($status->generadoc)) {
                $model->idestado = $status->idestado;
                break;
            }
        }

        if ($model->save()) {
            $this->sendNotice($model, 'group_cancel_orders');
            Tools::log()->notice('record-updated-correctly');
            return true;
        }

        Tools::log()->error('record-save-error');
        return true;
    }

    protected function createViews()
    {
        if (false === $this->preloadModel()->exists()) {
            return $this->error404();
        }

        $this->setContactPermissions();
        if (false === $this->permissions->allowAccess) {
            return $this->error403();
        }

        parent::createViews();
        $this->addHtmlView('info', 'Tab/PortalInfoPedido', 'PedidoCliente', 'detail', 'fas fa-info-circle');
        $this->createViewDocFiles();
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'cancel':
                return $this->cancelAction();

            case 'pay':
                return $this->payAction();

            case 'print':
                return $this->printAction();
        }

        return parent::execPreviousAction($action);
    }

    /**
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'docfiles':
                $view->cursor = $this->getModelDocFiles($this->views['main']->model->modelClassName(), $this->views['main']->model->primaryColumnValue());
                $view->count = count($view->cursor);
                $view->setSettings('active', $view->count > 0 && $this->contact->pc_allow_show_files);
                break;

            case self::MAIN_VIEW_NAME:
                parent::loadData($viewName, $view);
                $this->title = Tools::lang()->trans('order') . ' ' . $view->model->codigo;
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }

    private function printAction(): bool
    {
        if (false === $this->permissions->allowAccess) {
            Tools::log()->warning('access-denied');
            return true;
        }

        $this->setTemplate(false);
        $exportManager = new ExportManager();
        $exportManager->newDoc($exportManager->defaultOption());
        $exportManager->addBusinessDocPage($this->preloadModel());
        $exportManager->show($this->response);
        return false;
    }

    private function setContactPermissions(): void
    {
        // anónimo
        if (empty($this->contact)) {
            $this->permissions->set(false, 0, false, false);
            return;
        }

        // si no tiene permisos de ver
        if (false === $this->contact->pc_allow_show_order) {
            $this->permissions->set(false, 0, false, false);
            return;
        }

        // dirección de facturación
        $model = $this->preloadModel();
        if ($model->idcontactofact === $this->contact->idcontacto) {
            $this->permissions->set(true, 1, false, false);
            return;
        }

        // no autorizado
        $this->permissions->set(false, 0, false, false);
    }
}

<?php

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\Contact;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\RoleUser;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalDocNoticeTrait
{
    public function sendNotice(ModelClass $model, string $type): void
    {
        $emails = [];

        // obtenemos el cliente del documento
        $client = $model->getSubject();

        // si no existe, terminamos
        if (false === $client->exists()) {
            return;
        }

        // obtenemos el email del agente del cliente
        $this->getEmailAgent($client, $emails);

        // obtenemos el email del agente del contacto de la dirección de facturación
        $this->getEmailAgentContactBilling($model, $emails);

        // obtenemos los emails de los usuarios del grupo solicitado
        $this->getEmailUserGroup($type, $emails);

        foreach ($emails as $email => $langcode) {
            // preparamos el email
            $newEmail = NewMail::create()
                ->to($email);

            // añadimos el contenido del email
            $this->getEmailContent($newEmail, $model, $type, $langcode);

            // enviamos el email
            $newEmail->send();
        }
    }

    private function getEmailAgent(Contact $client, array &$emails): void
    {
        if (false === isset($client->codagente)) {
            return;
        }

        $agent = new Agente();
        if (false === $agent->loadFromCode($client->codagente)) {
            return;
        }

        if (false === in_array($agent->email, array_keys($emails))) {
            $emails[$agent->email] = $agent->langcode;
        }
    }

    private function getEmailAgentContactBilling(ModelClass $model, array &$emails): void
    {
        if (false === isset($model->idcontactofact)) {
            return;
        }

        $contact = new Contacto();
        if (false === $contact->loadFromCode($model->idcontactofact)) {
            return;
        }

        $agent = new Agente();
        if (false === $agent->loadFromCode($contact->codagente)) {
            return;
        }

        if (false === in_array($agent->email, array_keys($emails))) {
            $emails[$agent->email] = $agent->langcode;
        }
    }

    private function getEmailContent(&$newEmail, ModelClass $model, string $type, ?string $langcode): void
    {
        switch ($type) {
            case 'group_approve_estimations':
                $newEmail->subject(Tools::lang($langcode)->trans('estimation-approved-by-customer', ['%code%' => $model->codigo]))
                    ->addMainBlock(new TextBlock(Tools::lang($langcode)->trans('estimation-approved-by-customer', ['%code%' => $model->codigo])))
                    ->addMainBlock(new SpaceBlock(10))
                    ->addMainBlock(new ButtonBlock(Tools::lang($langcode)->trans('view-estimation'), PortalTools::getSiteUrl() . "/" . $model->url()));
                break;

            case 'group_cancel_estimations':
                $newEmail->subject(Tools::lang($langcode)->trans('estimation-canceled-by-customer', ['%code%' => $model->codigo]))
                    ->addMainBlock(new TextBlock(Tools::lang($langcode)->trans('estimation-canceled-by-customer', ['%code%' => $model->codigo])))
                    ->addMainBlock(new SpaceBlock(10))
                    ->addMainBlock(new ButtonBlock(Tools::lang($langcode)->trans('view-estimation'), PortalTools::getSiteUrl() . "/" . $model->url()));
                break;

            case 'group_cancel_orders':
                $newEmail->subject(Tools::lang($langcode)->trans('order-canceled-by-customer', ['%code%' => $model->codigo]))
                    ->addMainBlock(new TextBlock(Tools::lang($langcode)->trans('order-canceled-by-customer', ['%code%' => $model->codigo])))
                    ->addMainBlock(new SpaceBlock(10))
                    ->addMainBlock(new ButtonBlock(Tools::lang($langcode)->trans('view-order'), PortalTools::getSiteUrl() . "/" . $model->url()));
                break;
        }
    }

    private function getEmailUserGroup(string $type, array &$emails): void
    {
        $userGroup = Tools::settings('portalcliente', $type);

        if (empty($userGroup)) {
            return;
        }

        $roleUserModel = new RoleUser();
        $where = [new DataBaseWhere('codrole', $userGroup)];
        foreach ($roleUserModel->all($where, [], 0, 0) as $roleUser) {
            $user = new User();
            if (false === $user->loadFromCode($roleUser->nick)) {
                continue;
            }

            if (false === in_array($user->email, array_keys($emails))) {
                $emails[$user->email] = $user->langcode;
            }
        }
    }
}
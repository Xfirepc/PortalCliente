<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\CronJob;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Lib\PortalTools;
use FacturaScripts\Dinamic\Model\PortalTicket;
use FacturaScripts\Dinamic\Model\PortalTicketComment;
use FacturaScripts\Dinamic\Model\RoleUser;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class PortalTicketManager
{
    use PortalSaveEchoTrait;

    const JOB_NAME = 'portal-ticket-manager';
    const JOB_PERIOD = '5 minutes';

    public static function run(): void
    {
        echo "\n\n* JOB: " . self::JOB_NAME . ' ...';

        self::checkNewTickets();
        self::checkNewComments();

        self::saveEcho(self::JOB_NAME);
    }

    private static function checkNewComments(): void
    {
        // buscamos los nuevos comentarios de las issues
        $commentModel = new PortalTicketComment();
        $where = [new DataBaseWhere('notify', true)];
        $orderBy = ['creation_date' => 'ASC'];
        foreach ($commentModel->all($where, $orderBy, 0, 0) as $comment) {
            // obtenemos el ticket
            $ticket = $comment->getTicket();

            // si el comentario tiene nick, entonces notificamos al contacto
            if ($comment->nick) {
                self::sendEmailToContact($ticket);
            } else {
                self::sendEmailToUser($ticket);
            }

            // marcamos el comentario como notificado
            $comment->notify = false;
            $comment->save();
        }
    }

    private static function checkNewTickets(): void
    {
        // buscamos los ticket nuevos
        $ticketModel = new PortalTicket();
        $whereNotify = [new DataBaseWhere('notify', true)];
        $orderByCreation = ['creation_date' => 'ASC'];
        foreach ($ticketModel->all($whereNotify, $orderByCreation, 0, 0) as $ticket) {

            // mandamos email de notificación
            self::sendEmailNewTicket($ticket);

            // marcamos el ticket como notificado
            $ticket->notify = false;
            $ticket->save();

        }
    }

    private static function sendEmailNewTicket(PortalTicket $ticket): void
    {
        $userGroup = Tools::settings('portalcliente', 'group_notify_tickets');
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

            // preparamos y enviamos el email
            NewMail::create()
                ->to($user->email)
                ->subject(Tools::lang($user->langcode)->trans('new-support-ticket-subject', ['%code%' => $ticket->id]))
                ->addMainBlock(new TextBlock(Tools::lang($user->langcode)->trans('new-support-ticket-body', ['%code%' => $ticket->id, '%nick%' => $ticket->getContact()->pc_nick])))
                ->addMainBlock(new SpaceBlock(10))
                ->addMainBlock(new ButtonBlock(Tools::lang($user->langcode)->trans('view-support-ticket'), PortalTools::getSiteUrl() . "/" . $ticket->url('public')))
                ->send();
        }
    }

    private static function sendEmailToContact(PortalTicket $ticket): void
    {
        $contact = $ticket->getContact();
        if (empty($contact->email)) {
            return;
        }

        // preparamos y enviamos el email
        NewMail::create()
            ->to($contact->email)
            ->subject(Tools::lang($contact->langcode)->trans('new-comments-support-ticket-subject', ['%code%' => $ticket->id]))
            ->addMainBlock(new TextBlock(Tools::lang($contact->langcode)->trans('new-comments-support-ticket-body', ['%code%' => $ticket->id, '%nick%' => $ticket->getContact()->pc_nick])))
            ->addMainBlock(new SpaceBlock(10))
            ->addMainBlock(new ButtonBlock(Tools::lang($contact->langcode)->trans('view-support-ticket'), PortalTools::getSiteUrl() . "/" . $ticket->url('public')))
            ->send();
    }

    private static function sendEmailToUser(PortalTicket $ticket): void
    {
        $userGroup = Tools::settings('portalcliente', 'group_notify_tickets');
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

            // preparamos y enviamos el email
            NewMail::create()
                ->to($user->email)
                ->subject(Tools::lang($user->langcode)->trans('new-comments-support-ticket-subject', ['%code%' => $ticket->id]))
                ->addMainBlock(new TextBlock(Tools::lang($user->langcode)->trans('new-comments-support-ticket-body', ['%code%' => $ticket->id, '%nick%' => $ticket->getContact()->pc_nick])))
                ->addMainBlock(new SpaceBlock(10))
                ->addMainBlock(new ButtonBlock(Tools::lang($user->langcode)->trans('view-support-ticket'), PortalTools::getSiteUrl() . "/" . $ticket->url('public')))
                ->send();
        }
    }
}
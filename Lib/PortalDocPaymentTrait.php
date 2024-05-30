<?php

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Email\ButtonBlock;
use FacturaScripts\Dinamic\Lib\Email\NewMail;
use FacturaScripts\Dinamic\Lib\Email\SpaceBlock;
use FacturaScripts\Dinamic\Lib\Email\TextBlock;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\Pay\PortalPaymentGateway;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\EstadoDocumento;
use FacturaScripts\Dinamic\Model\FacturaCliente;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalDocPaymentTrait
{
    public function getPaymentGatewayHtml(): string
    {
        $model = $this->preloadModel();
        if ($model->total <= 0 || false === $model->editable
            || false === $this->contact->pc_allow_pay) {
            return '';
        }

        return PortalPaymentGateway::getHtml($model, $this->contact);
    }

    protected function generatePdfInvoice(string $fileTitle, FacturaCliente $invoice, Cliente $subject): string
    {
        $pdf = new ExportManager();
        $nameFile = str_replace('.pdf', '_' . mt_rand() . '.pdf', $fileTitle);
        $pdf->newDoc('PDF', $nameFile);
        $pdf->addBusinessDocPage($invoice);
        return file_put_contents($nameFile, $pdf->getDoc()) ? $nameFile : '';
    }

    protected function payAction(): bool
    {
        $model = $this->preloadModel();
        if (empty($model) || false === $model->exists()) {
            $this->redirect('PortalCliente');
            return true;
        } elseif (false === $model->editable) {
            Tools::log()->error('non-editable-document');
            return true;
        }

        if (false === PortalPaymentGateway::payAction($model, $this->request)) {
            Tools::log()->error('payment-failed');
            return true;
        }

        // actualizamos el documento
        $model->loadFromCode($model->primaryColumnValue());

        if ($model->modelClassName() === 'PedidoCliente') {
            // obtenemos los estados de los documentos
            // buscamos el estado del pedido que generé la factura
            $statusModel = new EstadoDocumento();
            $where = [
                new DataBaseWhere('tipodoc', 'PedidoCliente'),
                new DataBaseWhere('generadoc', 'FacturaCliente')
            ];

            if (false === $statusModel->loadFromCode('', $where)) {
                Tools::log()->warning('order-paid-but-invoice-could-not-be-created');
                return true;
            }

            $model->idestado = $statusModel->idestado;
            if (false === $model->save()) {
                Tools::log()->warning('order-paid-but-status-could-not-be-updated');
                return true;
            }

            // obtenemos la factura creada
            $invoices = $model->childrenDocuments();
            if (empty($invoices)) {
                Tools::log()->critical('order-paid-but-invoices-empty');
                return true;
            }

            $invoice = $invoices[0];
        } else {
            $invoice = $model;
        }

        if (false === $invoice->save()) {
            Tools::log()->critical('error-updating-invoice');
            return true;
        }

        // marcamos los recibos como pagados
        foreach ($invoice->getReceipts() as $receipt) {
            if (false === $receipt->pagado) {
                $receipt->pagado = true;
                $receipt->fechapago = Tools::date();
            }

            if (false === $receipt->save()) {
                Tools::log()->critical('error-updating-receipt');
            }
        }

        // recargamos la factura
        $invoice->loadFromCode($invoice->primaryColumnValue());

        // ponemos la factura como emitida
        // cambiamos el estado de la factura si su estado actual es editable
        foreach ($invoice->getAvailableStatus() as $stat) {
            if (false === $stat->editable) {
                $invoice->idestado = $stat->idestado;
                if (false === $invoice->save()) {
                    Tools::log()->critical('error-updating-invoice-status');
                    return false;
                }
            }
        }

        Tools::log()->notice('payment-success');
        $this->sendEmailInvoice($invoice);
        return true;
    }

    protected function sendEmailInvoice(FacturaCliente $invoice): void
    {
        // obtenemos el cliente
        $subject = $invoice->getSubject();

        // si el cliente no tiene email, terminamos
        if (empty($subject->email)) {
            return;
        }

        $fileTitle = Tools::lang($subject->langcode)->trans('invoice') . '_' . $invoice->codigo . '.pdf';
        $pdfFileName = $this->generatePdfInvoice($fileTitle, $invoice, $subject);
        if (empty($pdfFileName)) {
            Tools::log()->warning('error-generating-invoice-pdf');
            return;
        }

        $mail = NewMail::create()
            ->to($subject->email, $subject->nombre)
            ->subject(Tools::lang($subject->langcode)->trans('invoice') . ' ' . $invoice->codigo)
            ->addAttachment($pdfFileName, $fileTitle);

        $mail->addMainBlock(new TextBlock(
            Tools::lang($subject->langcode)->trans('hello') . ' ' . $subject->nombre
                . "\n\n"
                . Tools::lang($subject->langcode)->trans('email-invoice-text-portal-cliente', ['%code%' => $invoice->codigo, '%total%' => Tools::money($invoice->total, $invoice->coddivisa)])
            ))
            ->addMainBlock(new SpaceBlock(10))
            ->addMainBlock(new ButtonBlock(
                Tools::lang($subject->langcode)->trans('view-invoice'),
                PortalTools::getSiteUrl() . '/' . $invoice->url('public'),
            ));


        if (false === $mail->send()) {
            Tools::log()->warning('error-sending-invoice-email');
            return;
        }

        Tools::log()->info('invoice-email-sent');
        $invoice->femail = Tools::date();
        $invoice->save();
    }
}
<?php

namespace FacturaScripts\Plugins\PortalCliente\Lib;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\AttachedFileRelation;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalDocFilesTrait
{
    public function getContactDocFiles(): array
    {
        $docModel = new AttachedFileRelation();

        // obtenemos los archivos del contacto
        $whereContact = [new DataBaseWhere('pc_show', true)];
        $whereContact[] = new DataBaseWhere('model', 'Contacto');
        $whereContact[] = new DataBaseWhere('modelid|modelcode', $this->contact->idcontacto);
        $contactFiles = $docModel->all($whereContact, ['creationdate' => 'DESC']);

        // si no hay cliente, devolvemos los archivos del contacto
        if (empty($this->contact->codcliente)) {
            return $contactFiles;
        }

        // obtenemos los archivos del cliente
        $whereClient = [new DataBaseWhere('pc_show', true)];
        $whereClient[] = new DataBaseWhere('model', 'Cliente');
        $whereClient[] = new DataBaseWhere('modelid|modelcode', $this->contact->codcliente);
        $clientFiles = $docModel->all($whereClient, ['creationdate' => 'DESC']);

        // unimos los dos arrays
        $result = array_merge($contactFiles, $clientFiles);

        // ordenamos el array por fecha de creación en php 8
        usort($result, fn($a, $b) => $a->creationdate <=> $b->creationdate);

        return $result;
    }

    public function getModelDocFiles(string $modelName, $code): array
    {
        $docModel = new AttachedFileRelation();

        $where = [
            new DataBaseWhere('pc_show', true),
            new DataBaseWhere('model', $modelName),
        ];

        $where[] = is_numeric($code) ?
            new DataBaseWhere('modelid|modelcode', $code) :
            new DataBaseWhere('modelcode', $code);

        return $docModel->all($where, ['creationdate' => 'DESC']);
    }

    protected function createViewDocFiles(string $viewName = 'docfiles', string $template = 'Tab/PortalDocFiles'): void
    {
        $this->addHtmlView($viewName, $template, 'AttachedFileRelation', 'files', 'fas fa-paperclip');
    }
}
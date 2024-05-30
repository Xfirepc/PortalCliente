<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Plugins\PortalCliente\CronJob\PortalTicketManager;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Cron extends CronClass
{
    public function run(): void
    {
        $this->job(PortalTicketManager::JOB_NAME)
            ->every(PortalTicketManager::JOB_PERIOD)
            ->run(function () {
                PortalTicketManager::run();
            });
    }
}


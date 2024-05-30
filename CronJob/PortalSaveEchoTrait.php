<?php
/**
 * Copyright (C) 2024 Daniel Fernández Giménez <hola@danielfg.es>
 */

namespace FacturaScripts\Plugins\PortalCliente\CronJob;

use FacturaScripts\Dinamic\Model\LogMessage;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
trait PortalSaveEchoTrait
{
    /** @var string */
    private static $echo = '';

    protected static function echo(string $text): void
    {
        echo $text;
        ob_flush();

        self::$echo .= $text;
    }

    protected static function getEcho(): string
    {
        return self::$echo;
    }

    protected static function text(string $text): void
    {
        self::$echo .= $text;
    }

    protected static function saveEcho(string $jobName): void
    {
        if (empty($jobName) || empty(self::$echo)) {
            return;
        }

        // el texto está limitado a 3000 caracteres, así que debemos guardar un registro por cada 3000
        $max = 3000;
        while (strlen(self::$echo) > $max) {
            $log = new LogMessage();
            $log->channel = $jobName;
            $log->level = 'info';
            $log->message = substr(self::$echo, 0, $max);
            $log->save();

            self::$echo = substr(self::$echo, $max);
        }
    }
}

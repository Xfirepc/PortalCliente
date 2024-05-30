<?php

namespace FacturaScripts\Plugins\PortalCliente\Lib;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PortalTools
{
    public static function getSiteUrl(): string
    {
        $url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $url .= '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        return substr($url, 0, strrpos($url, '/'));
    }
}
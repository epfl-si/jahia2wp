<?php

namespace EPFL\Menus\CLI;

if (! defined( 'ABSPATH' )) {
    die( 'Access denied.' );
}

use \WP_CLI;
use \WP_CLI_Command;

require_once(dirname(__DIR__) . '/lib/i18n.php');
use function EPFL\I18N\___;

require_once(__DIR__ . '/epfl-menus.php');
use \EPFL\Menus\ExternalMenuItem;

add_filter('epfl_rest_rewrite_connect_to', function($hostport, $host, $port) {
    // TODO: this is not a way to go through life, son
    if ($hostport === "www.epfl.ch:443") {
        return "httpd-www:8443";
    } elseif ($hostport === "jahia2wp-httpd:443") {
        return "jahia2wp-httpd:8443";
    } else {
        return $hostport;
    }
}, 10, 3);


class EPFLMenusCLICommand extends WP_CLI_Command
{
    public static function hook () {
        WP_CLI::add_command('epfl-menus', get_called_class());
    }

    public function refresh () {
        WP_CLI::log(___('Enumerating menus on filesystem...'));
        $local = ExternalMenuItem::load_from_filesystem();
        WP_CLI::log(sprintf(___('... Success, found %d local menus'),
                            count($local)));

        $all = ExternalMenuItem::all();
        WP_CLI::log(sprintf(___('Refreshing %d instances...'),
                            count($all)));
        foreach ($all as $emi) {
            try {
                $emi->refresh();
                WP_CLI::log(sprintf(___('✓ %s'), $emi));
            } catch (\Throwable $t) {
                WP_CLI::log(sprintf(___('\u001b[31m✗ %s\u001b[0m'), $emi));
            }
        }
        
    }
}

EPFLMenusCLICommand::hook();

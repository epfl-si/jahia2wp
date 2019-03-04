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

        WP_CLI::log(___('Enumerating menus in config file...'));
        $local = ExternalMenuItem::load_from_config_file();
        WP_CLI::log(sprintf(___('... Success, found %d site-configured menus'),
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

    /**
     * @example wp epfl-menus add_external_menu_item --menu-location-slug=top urn:epfl:labs "laboratoires"
     */
    public function add_external_menu_item ($args, $assoc_args) {
        list($urn, $title) = $args;

        $menu_location_slug = $assoc_args['menu-location-slug'];
        if (!empty($menu_location_slug)) $menu_location_slug = "top";

        # todo: check that params is format urn:epfl
        WP_CLI::log(___('Add a new external menu item...'));

        $external_menu_item = ExternalMenuItem::get_or_create($urn);
        $external_menu_item->set_title($title);
        $external_menu_item->meta()->set_remote_slug($menu_location_slug);
        $external_menu_item->meta()->set_items_json('[]');

        WP_CLI::log(sprintf(___('External menu item ID %d...'),$external_menu_item->ID));
    }
}

EPFLMenusCLICommand::hook();

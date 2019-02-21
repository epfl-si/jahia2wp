<?php
/**
 * Sync user privileges from Accred from the command line.
 */
namespace EPFL\Accred;
use \WP_CLI;

class CLI
{
    public function __construct ($parent_controller)
    {
        $this->controller = $parent_controller;
    }

    public function hook ()
    {
        if ( class_exists( '\WP_CLI' ) ) {
            WP_CLI::add_command( 'accred', array($this, 'invoke'), array( 'short_desc' => 'Accred command-line operations' ) );
        }
    }

    public function invoke ()
    {
        WP_CLI::log( 'Hello, yes this is accred.' );
    }
}

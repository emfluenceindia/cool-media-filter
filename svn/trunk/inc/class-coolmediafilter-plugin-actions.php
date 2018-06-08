<?php

/**
 * @package: CoolMediaFilter
 */

class CoolMediaFilterPluginAction {
    public static function activate() {
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function uninstall() {
        // To be implemented!
    }
}
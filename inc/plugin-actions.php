<?php

/**
 * @package: CoolMediaFilter
 */

class PluginAction {
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
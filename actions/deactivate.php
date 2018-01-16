<?php

/**
 * @package: CoolMediaFilter
 */

function deactivate()
{
    flush_rewrite_rules();
}
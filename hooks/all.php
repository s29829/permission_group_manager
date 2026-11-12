<?php
// Function name format: Hook + <plugin> + <page> + <hook name>
// Creates the first saved version of all group permissions after the plugin has been activated.
// After that it only checks that one file exists.
function HookPermission_group_managerAllInitialise()
{
    require_once dirname(__FILE__, 2) . '/include/functions.php';
    try {
        permission_group_manager_ensure_first_version();
    } catch (Throwable $e) {
        // never break a page because of the version history
    }
}

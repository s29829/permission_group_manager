<?php
// Helper functions for the Permission Group Manager page.
//
// The permission catalogue mirrors ResourceSpace 11 pages/admin/admin_group_permissions.php
// (same codes, same "reverse" options, same dependencies). Labels use ResourceSpace's own
// $lang strings, so they follow the user's language; the English text is only a fallback.
//
// Permissions of active ResourceSpace plugins (workflow, brand guidelines, CLIP, consent,
// faces, license manager) get their own sections, like the plugins' "additionalperms" hooks.
//
// Permissions are stored in usergroup.permissions as a comma separated list of codes.
// All other codes (featured collection categories, permissions of other plugins, ...) are
// "custom permissions": each one is a table row with its own description, kept in
// <versions dir>/custom_codes.json (code => description).

// Label from $lang, or the fallback when the string does not exist
function permission_group_manager_lang($key, $fallback)
{
    $lang = $GLOBALS['lang'] ?? array();
    return (isset($lang[$key]) && is_string($lang[$key]) && $lang[$key] !== '') ? $lang[$key] : $fallback;
}

// Translated text of multilingual values such as "~en:Title~pl:Tytuł"
function permission_group_manager_tr($text)
{
    return function_exists('i18n_get_translated') ? (string) i18n_get_translated((string) $text) : (string) $text;
}

// Name of a workflow state
function permission_group_manager_state_name($n)
{
    return permission_group_manager_lang('status' . $n, 'State ' . $n);
}

function permission_group_manager_resource_types()
{
    if (function_exists('get_all_resource_types')) {
        $rows = get_all_resource_types();
    } else {
        $rows = ps_query('SELECT ref, name FROM resource_type ORDER BY order_by, ref', array(), 'schema');
    }
    $out = array();
    foreach ((array) $rows as $r) {
        $out[] = array('ref' => (int) $r['ref'], 'name' => permission_group_manager_tr($r['name'] ?? ''));
    }
    return $out;
}

function permission_group_manager_fields()
{
    $out = array();
    try {
        $rows = ps_query('SELECT ref, title, name FROM resource_type_field ORDER BY active DESC, order_by', array(), 'schema');
    } catch (Throwable $e) {
        $rows = ps_query('SELECT ref, title, name FROM resource_type_field ORDER BY order_by', array(), 'schema');
    }
    foreach ((array) $rows as $r) {
        $out[] = array('ref' => (int) $r['ref'], 'title' => permission_group_manager_tr($r['title'] ?? ''), 'name' => (string) ($r['name'] ?? ''));
    }
    return $out;
}

// All permissions shown in the matrix, grouped in sections (same order as ResourceSpace).
// Each row: code, label, reverse (checkbox ticked = code NOT set), requires (codes that must be
// set, "!code" = must not be set, for the row to apply - like the options ResourceSpace hides).
function permission_group_manager_catalogue()
{
    $row = function ($code, $label, $reverse = false, array $requires = array()) {
        return array('code' => (string) $code, 'label' => (string) $label, 'reverse' => (bool) $reverse, 'requires' => $requires);
    };
    $with = function ($label, $name) {
        return $label . ': ' . $name;
    };

    $states = range(-2, 3);
    foreach ((array) ($GLOBALS['additional_archive_states'] ?? array()) as $s) {
        if (is_numeric($s) && !in_array((int) $s, $states, true)) {
            $states[] = (int) $s;
        }
    }
    $rtypes = permission_group_manager_resource_types();
    $fields = permission_group_manager_fields();
    $max_access = !empty($GLOBALS['custom_access']) ? 3 : 2;
    $access_names = array(0 => 'Open', 1 => 'Restricted', 2 => 'Confidential', 3 => 'Custom');

    $sec = array();

    // Searching and access
    $r   = array();
    $r[] = $row('s', permission_group_manager_lang('searchcapability', 'Search capability'));
    $r[] = $row('v', permission_group_manager_lang('access_to_restricted_and_confidential_resources', 'Can download restricted resources and view confidential resources'));
    foreach ($states as $n) {
        $r[] = $row('z' . $n, $with(permission_group_manager_lang('hide_view_access_to_workflow_state', 'Hide view access to workflow state'), permission_group_manager_state_name($n)));
    }
    $r[] = $row('g', permission_group_manager_lang('restrict_access_to_all_available_resources', 'Restrict access to all available resources'), true);
    foreach ($states as $n) {
        $r[] = $row('rws' . $n, 'Restricted access to resources in workflow state: ' . permission_group_manager_state_name($n));
    }
    $r[] = $row('q', permission_group_manager_lang('can_make_resource_requests', 'Can make resource requests'));
    $r[] = $row('w', permission_group_manager_lang('show_watermarked_previews_and_thumbnails', 'Show watermarked previews and thumbnails'));
    $sec[] = array('key' => 'searching_and_access', 'title' => permission_group_manager_lang('searching_and_access', 'Searching and access'), 'rows' => $r);

    // Metadata fields
    $r   = array();
    $r[] = $row('f*', permission_group_manager_lang('can_see_all_fields', 'Can see all fields'));
    foreach ($fields as $f) {
        $r[] = $row('f' . $f['ref'], $with(permission_group_manager_lang('can_see_field', 'Can see field'), $f['title'] . ' (' . $f['name'] . ')'), false, array('!f*'));
    }
    $r[] = $row('F*', permission_group_manager_lang('can_edit_all_fields', 'Can edit all fields'), true);
    foreach ($fields as $f) {
        $r[] = $row('F-' . $f['ref'], $with(permission_group_manager_lang('can_edit_field', 'Can edit field'), $f['title'] . ' (' . $f['name'] . ')'), false, array('F*'));
    }
    $sec[] = array('key' => 'metadatafields', 'title' => permission_group_manager_lang('metadatafields', 'Metadata fields'), 'rows' => $r, 'collapsed' => count($fields) > 10);

    // Resource types
    $r = array();
    foreach ($rtypes as $t) {
        $r[] = $row('T' . $t['ref'], str_replace('%TYPE', $t['name'], permission_group_manager_lang('can_see_resource_type', 'Can see resource type %TYPE')), true);
        $r[] = $row('X' . $t['ref'], $with(permission_group_manager_lang('restricted_access_only_to_resource_type', 'Restricted access only to resource type'), $t['name']));
        $r[] = $row('XU' . $t['ref'], $with(permission_group_manager_lang('restricted_upload_for_resource_of_type', 'Restricted upload for resource of type'), $t['name']));
        $r[] = $row('ert' . $t['ref'], $with(permission_group_manager_lang('force_edit_resource_type', 'Can edit resource type'), $t['name']));
        $r[] = $row('XE' . $t['ref'], $with(permission_group_manager_lang('deny_edit_resource_type', 'Deny edit access to resource type'), $t['name']));
    }
    $r[] = $row('XE', permission_group_manager_lang('deny_edit_all_resource_types', 'Deny edit access to all resource types'));
    foreach ($rtypes as $t) {
        $r[] = $row('XE-' . $t['ref'], str_replace('[resourcetype]', $t['name'], permission_group_manager_lang('can_edit_resource_type', 'Can edit resource type [resourcetype]')), false, array('XE'));
    }
    $sec[] = array('key' => 'resourcetypes', 'title' => permission_group_manager_lang('resourcetypes', 'Resource types'), 'rows' => $r, 'collapsed' => count($rtypes) > 6);

    // Resource creation and management
    $r = array();
    foreach ($states as $n) {
        $r[] = $row('e' . $n, $with(permission_group_manager_lang('edit_access_to_workflow_state', 'Edit access to workflow state'), permission_group_manager_state_name($n)));
    }
    for ($n = 0; $n <= $max_access; $n++) {
        $r[] = $row('ea' . $n, str_replace('[state]', permission_group_manager_lang('access' . $n, $access_names[$n]), permission_group_manager_lang('edit_access_to_access', 'Edit access to resources with access: [state]')), true);
    }
    $r[] = $row('c', permission_group_manager_lang('can_create_resources_and_upload_files-admins', 'Can create resources / upload files (admin users)'));
    $r[] = $row('d', permission_group_manager_lang('can_create_resources_and_upload_files-general_users', 'Can create resources / upload files (normal users)'));
    $r[] = $row('D', permission_group_manager_lang('can_delete_resources', 'Can delete resources'), true);
    $r[] = $row('i', permission_group_manager_lang('can_manage_archive_resources', 'Can manage archive resources'));
    $r[] = $row('A', permission_group_manager_lang('can_manage_alternative_files', 'Can manage alternative files'), true);
    $r[] = $row('xupr', permission_group_manager_lang('can_upload_preview_images', 'Can upload preview images'), true);
    $sec[] = array('key' => 'resource_creation_and_management', 'title' => permission_group_manager_lang('resource_creation_and_management', 'Resource creation and management'), 'rows' => $r);

    // Featured collections and collections
    $r   = array();
    $r[] = $row('b', permission_group_manager_lang('enable_bottom_collection_bar', 'Enable the bottom collection bar'), true);
    $r[] = $row('h', permission_group_manager_lang('can_publish_collections_as_themes', 'Can publish collections as featured collections'));
    $r[] = $row('exup', permission_group_manager_lang('permission_share_upload_link', 'Can share upload links'));
    $r[] = $row('hdta', permission_group_manager_lang('manage_all_dash_h', 'Can manage all dash tiles'), true, array('h'));
    $r[] = $row('hdt_ug', permission_group_manager_lang('manage_user_group_dash_tiles', 'Can manage user group dash tiles'), false, array('h'));
    $r[] = $row('dta', permission_group_manager_lang('manage_all_dash_perm', 'Can manage all dash tiles'), false, array('!h'));
    $r[] = $row('dtu', permission_group_manager_lang('manage_own_dash', 'Can manage own dash'), true);
    $r[] = $row('j*', permission_group_manager_lang('can_see_all_theme_categories', 'Can see all featured collection categories'));
    $r[] = $row('J', permission_group_manager_lang('display_only_resources_within_accessible_themes', 'Show only resources within accessible featured collections'));
    $sec[] = array('key' => 'themes_and_collections', 'title' => permission_group_manager_lang('themes_and_collections', 'Featured collections and collections'), 'rows' => $r);

    // Administration
    $r   = array();
    $r[] = $row('t', permission_group_manager_lang('can_access_team_centre', 'Can access the Admin area'));
    $r[] = $row('r', permission_group_manager_lang('can_manage_research_requests', 'Can manage research requests'), false, array('t'));
    $r[] = $row('R', permission_group_manager_lang('can_manage_resource_requests', 'Can manage resource requests'), false, array('t'));
    $r[] = $row('Ra', permission_group_manager_lang('can_assign_resource_requests', 'Can assign resource requests'), false, array('t', 'R'));
    $r[] = $row('Rb', permission_group_manager_lang('can_be_assigned_resource_requests', 'Can be assigned resource requests'), false, array('t', 'R'));
    $r[] = $row('o', permission_group_manager_lang('can_manage_content', 'Can manage content (site text)'), false, array('t'));
    $r[] = $row('m', permission_group_manager_lang('can_bulk-mail_users', 'Can bulk mail users'), false, array('t'));
    $r[] = $row('u', permission_group_manager_lang('can_manage_users', 'Can manage users'), false, array('t'));
    $r[] = $row('k', permission_group_manager_lang('can_manage_keywords', 'Can manage keywords'), false, array('t'));
    $r[] = $row('a', permission_group_manager_lang('can_access_system_setup', 'Can access System setup'), false, array('t'));
    $r[] = $row('ex', permission_group_manager_lang('permission_manage_external_shares', 'Can manage external shares'));
    $sec[] = array('key' => 'administration', 'title' => permission_group_manager_lang('administration', 'Administration'), 'rows' => $r);

    // Other
    $r   = array();
    $r[] = $row('p', permission_group_manager_lang('can_change_own_password', 'Can change own password'), true);
    $r[] = $row('U', permission_group_manager_lang('can_manage_users_in_children_groups', 'Can manage users in children groups only'));
    $r[] = $row('E', permission_group_manager_lang('can_email_resources_to_own_and_children_and_parent_groups', 'Can email resources to own, children and parent groups only'));
    $r[] = $row('x', permission_group_manager_lang('allow_user_group_selection_for_access_when_sharing_externally', 'Allow user group selection when sharing externally'));
    $r[] = $row('noex', permission_group_manager_lang('prevent_user_group_sharing_externally', 'Prevent sharing externally'));
    $r[] = $row('nolock', permission_group_manager_lang('permission_nolock', 'Disable locking of resources'));
    $sec[] = array('key' => 'other', 'title' => permission_group_manager_lang('other', 'Other'), 'rows' => $r);

    // Permissions added by active plugins (hook "additionalperms" in ResourceSpace)
    foreach (permission_group_manager_plugin_sections($row) as $s) {
        $sec[] = $s;
    }

    return $sec;
}

// Is the plugin active?
function permission_group_manager_plugin_active($name)
{
    return in_array($name, (array) ($GLOBALS['plugins'] ?? array()), true);
}

// Title from the plugin's YAML file (fallback when it cannot be read)
function permission_group_manager_plugin_title($name, $fallback)
{
    if (function_exists('get_plugin_yaml')) {
        try {
            $y = get_plugin_yaml($name, false, true);
            if (is_array($y) && !empty($y['title']) && is_string($y['title'])) {
                return $y['title'];
            }
        } catch (Throwable $e) {
            // use the fallback
        }
    }
    return $fallback;
}

// Workflow actions of the rse_workflow plugin: [ref, name]
function permission_group_manager_workflow_actions()
{
    try {
        $rows = ps_query('SELECT ref, name FROM workflow_actions ORDER BY ref', array());
    } catch (Throwable $e) {
        return array();
    }
    $out = array();
    foreach ((array) $rows as $r) {
        $out[] = array('ref' => (int) $r['ref'], 'name' => permission_group_manager_tr($r['name'] ?? ''));
    }
    return $out;
}

// Sections for the permissions of ResourceSpace 11 plugins - same codes, labels, "reverse"
// options and conditions as each plugin's hooks/admin_group_permissions.php.
// Only sections of active plugins are returned. 'implied' = codes that already include
// the permission (ResourceSpace shows the option ticked and disabled then).
function permission_group_manager_plugin_sections(callable $row)
{
    $sec = array();
    $add = function ($plugin, $title, array $rows) use (&$sec) {
        if ($rows) {
            $sec[] = array('key' => 'plugin_' . $plugin, 'title' => $title, 'plugin' => $plugin, 'rows' => $rows);
        }
    };

    if (permission_group_manager_plugin_active('rse_workflow')) {
        $r = array();
        foreach (permission_group_manager_workflow_actions() as $wa) {
            $r[] = $row('wf' . $wa['ref'], permission_group_manager_lang('rse_workflow_access', 'Access to workflow action') . ' ' . $wa['name']);
        }
        $add('rse_workflow', permission_group_manager_lang('rse_workflow_actions_heading', 'Workflow actions'), $r);
    }
    if (permission_group_manager_plugin_active('brand_guidelines')) {
        $add('brand_guidelines', permission_group_manager_plugin_title('brand_guidelines', 'Brand guidelines'), array(
            $row('bgv', permission_group_manager_lang('brand_guidelines_permission_bgv', 'Can view the Brand Guidelines'), true),
            // shown by ResourceSpace only for groups without System setup access
            $row('bge', permission_group_manager_lang('brand_guidelines_permission_bge', 'Can edit the Brand Guidelines'), false, array('!a')),
        ));
    }
    if (permission_group_manager_plugin_active('clip')) {
        $add('clip', permission_group_manager_lang('clip-ai-smart-search', 'AI smart search'), array(
            $row('clip-sb', permission_group_manager_lang('clip_show_on_searchbar', 'Show CLIP features on the search bar'), true),
            $row('clip-v', permission_group_manager_lang('clip_show_on_view', 'Show CLIP features on the resource view page'), true),
        ));
    }
    if (permission_group_manager_plugin_active('consentmanager')) {
        $cm = $row('cm', permission_group_manager_lang('consent_manager_access', 'Manage consent data'));
        $cm['implied'] = array('a');
        $add('consentmanager', permission_group_manager_lang('consent_management', 'Consent management'), array($cm));
    }
    if (permission_group_manager_plugin_active('faces')) {
        $add('faces', permission_group_manager_lang('faces-configuration', 'AI faces configuration'), array(
            $row('faces-v', permission_group_manager_lang('faces-show-view', 'Show the AI Faces functionality on the view page'), true),
        ));
    }
    if (permission_group_manager_plugin_active('licensemanager')) {
        $lm = $row('lm', permission_group_manager_lang('license_manager_access', 'Manage license data'));
        $lm['implied'] = array('a');
        $add('licensemanager', permission_group_manager_lang('license_management', 'License management'), array($lm));
    }
    return $sec;
}

// Allowed format of a custom permission code (ResourceSpace stores codes comma separated)
function permission_group_manager_code_valid($code)
{
    return is_string($code) && preg_match('/^[^\s,\'"<>]{1,100}\z/u', $code) === 1;
}

// ---- Custom permissions (rows added on the page) -----------------------------------

function permission_group_manager_custom_file()
{
    return permission_group_manager_versions_dir() . '/custom_codes.json';
}

// Saved custom permissions: code => description (in the order they were added)
function permission_group_manager_custom_defs()
{
    $data = json_decode(permission_group_manager_read_file(permission_group_manager_custom_file()), true);
    $out  = array();
    foreach (is_array($data) ? $data : array() as $code => $desc) {
        if (permission_group_manager_code_valid((string) $code)) {
            $out[(string) $code] = permission_group_manager_custom_clean_desc($desc);
        }
    }
    return $out;
}

function permission_group_manager_custom_clean_desc($desc)
{
    $desc = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $desc)));
    return function_exists('mb_substr') ? mb_substr($desc, 0, 200) : substr($desc, 0, 200);
}

// Stores the list of custom permissions; codes of the table rows and invalid codes are skipped
function permission_group_manager_custom_save(array $defs, array $known)
{
    $out = array();
    foreach ($defs as $code => $desc) {
        $code = (string) $code;
        if (permission_group_manager_code_valid($code) && !isset($known[$code])) {
            $out[$code] = permission_group_manager_custom_clean_desc($desc);
        }
    }
    return permission_group_manager_versions_mkdir()
        && file_put_contents(permission_group_manager_custom_file(), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

// Names of featured collections, for the default description of j<ref> / -j<ref> codes
function permission_group_manager_collection_names(array $refs)
{
    $refs = array_values(array_unique(array_filter(array_map('intval', $refs))));
    if (!$refs) {
        return array();
    }
    $params = array();
    foreach ($refs as $r) {
        $params[] = 'i';
        $params[] = $r;
    }
    $out = array();
    try {
        foreach ((array) ps_query('SELECT ref, name FROM collection WHERE ref IN (' . implode(',', array_fill(0, count($refs), '?')) . ')', $params) as $c) {
            $out[(int) $c['ref']] = permission_group_manager_tr($c['name'] ?? '');
        }
    } catch (Throwable $e) {
        // names are optional
    }
    return $out;
}

// Default description of a custom code without a saved description
function permission_group_manager_custom_default_desc($code, array $collections)
{
    if (preg_match('/^(-?)j(\d+)\z/', $code, $m)) {
        $name = $collections[(int) $m[2]] ?? ('#' . $m[2]);
        return ($m[1] === '-' ? 'No access to featured collection category: ' : 'Can see featured collection category: ') . $name;
    }
    return '';
}

// Set of all codes in the catalogue: code => true
function permission_group_manager_known_codes(array $catalogue)
{
    $out = array();
    foreach ($catalogue as $s) {
        foreach ($s['rows'] as $r) {
            $out[$r['code']] = true;
        }
    }
    return $out;
}

// "a,b, c,,a" -> array('a', 'b', 'c')
function permission_group_manager_parse($csv)
{
    $out = array();
    foreach (explode(',', (string) $csv) as $c) {
        $c = trim($c);
        if ($c !== '' && !in_array($c, $out, true)) {
            $out[] = $c;
        }
    }
    return $out;
}

// All user groups: ref => [ref, name, parent, perms, inherits]
function permission_group_manager_groups()
{
    $out = array();
    foreach ((array) ps_query('SELECT * FROM usergroup ORDER BY name', array()) as $g) {
        $flags = permission_group_manager_parse($g['inherit_flags'] ?? '');
        $ref   = (int) $g['ref'];
        $out[$ref] = array(
            'ref'      => $ref,
            'name'     => permission_group_manager_tr($g['name'] ?? ('#' . $ref)),
            'parent'   => (int) ($g['parent'] ?? 0),
            'perms'    => permission_group_manager_parse($g['permissions'] ?? ''),
            'inherits' => in_array('permissions', $flags, true) && (int) ($g['parent'] ?? 0) > 0,
        );
    }
    uasort($out, function ($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $out;
}

// Permissions that apply to a group (follows "inherit permissions from parent")
function permission_group_manager_effective(array $groups, $ref, $depth = 0)
{
    if (!isset($groups[$ref])) {
        return array();
    }
    $g = $groups[$ref];
    if ($g['inherits'] && isset($groups[$g['parent']]) && $depth < 20) {
        return permission_group_manager_effective($groups, $g['parent'], $depth + 1);
    }
    return $g['perms'];
}

// Writes the permissions of one group (same functions as ResourceSpace's own page)
function permission_group_manager_save_group($ref, array $new, array $old)
{
    $csv = implode(',', $new);
    if (function_exists('save_usergroup')) {
        save_usergroup((int) $ref, array('permissions' => $csv));
    } else {
        ps_query('UPDATE usergroup SET permissions = ? WHERE ref = ?', array('s', $csv, 'i', (int) $ref));
    }
    if (function_exists('log_activity') && defined('LOG_CODE_EDITED')) {
        try {
            log_activity(null, LOG_CODE_EDITED, $csv, 'usergroup', 'permissions', (int) $ref, null, implode(',', $old));
        } catch (Throwable $e) {
            // logging must not stop the save
        }
    }
    $stored = ps_value('SELECT permissions value FROM usergroup WHERE ref = ?', array('i', (int) $ref), '');
    $a = permission_group_manager_parse($stored);
    $b = $new;
    sort($a);
    sort($b);
    return $a === $b;
}

// Applies the changes sent by the page: {"<group ref>": {"add": [...], "remove": [...]}, ...}
// Changes are applied to the CURRENT stored value, so codes not shown on the page are kept.
// Returns the number of saved groups; problems are added to $errors.
function permission_group_manager_apply($json, $own_group, array &$errors)
{
    $data = json_decode((string) $json, true);
    if (!is_array($data)) {
        $errors[] = 'No changes received.';
        return 0;
    }
    $known  = permission_group_manager_known_codes(permission_group_manager_catalogue());
    $groups = permission_group_manager_groups();
    $saved  = 0;
    foreach ($data as $ref => $chg) {
        $ref = (int) $ref;
        if (!isset($groups[$ref]) || !is_array($chg)) {
            $errors[] = 'Unknown user group #' . $ref . '.';
            continue;
        }
        $g = $groups[$ref];
        if ($g['inherits']) {
            $errors[] = '"' . $g['name'] . '" inherits its permissions from its parent group and was not changed.';
            continue;
        }
        // "add" / "remove": codes of the table rows; "custom_add" / "custom_remove": codes from the
        // "Custom permissions" field (any valid code that is NOT a table row)
        $pick = function ($list, $custom) use ($known, &$errors, $g) {
            $out = array();
            foreach ((array) $list as $c) {
                if (!is_string($c) || in_array($c, $out, true)) {
                    continue;
                }
                if ($custom ? (permission_group_manager_code_valid($c) && !isset($known[$c])) : isset($known[$c])) {
                    $out[] = $c;
                } elseif ($custom) {
                    $errors[] = 'Ignored custom permission "' . $c . '" for "' . $g['name'] . '" (invalid code or a code of the table).';
                }
            }
            return $out;
        };
        $add    = array_merge($pick($chg['add'] ?? array(), false), $pick($chg['custom_add'] ?? array(), true));
        $remove = array_diff(array_merge($pick($chg['remove'] ?? array(), false), $pick($chg['custom_remove'] ?? array(), true)), $add);
        if ($ref === (int) $own_group && array_intersect(array('t', 'a'), $remove)) {
            $errors[] = 'Not saved for "' . $g['name'] . '": you cannot remove Admin / System setup access from your own user group.';
            continue;
        }
        $old = $g['perms'];
        $new = array_values(array_diff($old, $remove));
        foreach ($add as $c) {
            if (!in_array($c, $new, true)) {
                $new[] = $c;
            }
        }
        if ($new === $old) {
            continue;
        }
        if (permission_group_manager_save_group($ref, $new, $old)) {
            $saved++;
        } else {
            $errors[] = 'Saving "' . $g['name'] . '" failed.';
        }
    }
    return $saved;
}

// ---------------------------------------------------------------------------
// Version history (works like the Custom CSS plugin). All data of the plugin is stored in
// <filestore>/permission_group_manager/ (since 1.22; before that the plugin was "groupperms" - see
// permission_group_manager_relocate()):
//   <filestore>/permission_group_manager/data_<key>/v<N>_<YYYYMMDD>_<HHMMSS>.json   saved permissions of all groups
//   <filestore>/permission_group_manager/data_<key>/current_version.txt            current version number
//   <filestore>/permission_group_manager/data_<key>/custom_codes.json              descriptions of custom permissions
// Every "Save changes" first stores the previous permissions under the current version number;
// "Save as new version" starts the next number. The folder name contains a key derived from
// $scramble_key, so the files cannot be guessed from outside.
// ---------------------------------------------------------------------------

const PERMISSION_GROUP_MANAGER_KEEP_VERSIONS = 50;

// $storagedir of ResourceSpace (default: <ResourceSpace root>/filestore)
function permission_group_manager_storage_dir()
{
    $s = (string) ($GLOBALS['storagedir'] ?? '');
    return rtrim($s !== '' ? $s : dirname(__FILE__, 4) . '/filestore', '/');
}

// <filestore>/permission_group_manager - everything the plugin stores (removed by "Reset plugin")
function permission_group_manager_base_dir()
{
    return permission_group_manager_storage_dir() . '/permission_group_manager';
}

function permission_group_manager_data_key()
{
    $key = (string) ($GLOBALS['scramble_key'] ?? '');
    return $key !== '' ? '_' . substr(md5($key . 'permission_group_manager'), 0, 12) : '';
}

function permission_group_manager_versions_dir()
{
    return permission_group_manager_base_dir() . '/data' . permission_group_manager_data_key();
}

// Before 1.22 the plugin was called "groupperms". Its data is copied (not moved - the old plugin may
// still be installed, and nothing is deleted) to <filestore>/permission_group_manager/data_<key>/ the first time the new
// data folder is needed. Sources, newest first:
//   <filestore>/groupperms/data_<old key>/          (groupperms with data in the filestore)
//   <filestore>/groupperms_versions_<old key>/      (groupperms versions before 1.19)
// The old key is derived from $scramble_key and the old plugin name. Returns a message or ''.
function permission_group_manager_relocate()
{
    $new = permission_group_manager_versions_dir();
    if (is_dir($new)) {
        return '';
    }
    $storage = permission_group_manager_storage_dir();
    $key     = (string) ($GLOBALS['scramble_key'] ?? '');
    $oldkey  = $key !== '' ? '_' . substr(md5($key . 'groupperms'), 0, 12) : '';
    $old     = '';
    foreach (array($storage . '/groupperms/data' . $oldkey, $storage . '/groupperms_versions' . $oldkey) as $dir) {
        if (is_dir($dir) && is_readable($dir)) {
            $old = $dir;
            break;
        }
    }
    if ($old === '') {
        return '';
    }
    $base = permission_group_manager_base_dir();
    if (!is_dir($base) && (!is_writable($storage) || !mkdir($base, 0755, true))) {
        return 'The data of the old plugin "groupperms" in ' . $old . ' could not be copied to ' . $base . ' (no write access).';
    }
    if (permission_group_manager_copy_dir($old, $new)) {
        return 'The data of the old plugin "groupperms" was copied from ' . $old . ' to ' . $new . ' (the old folder was kept).';
    }
    return 'The data of the old plugin "groupperms" in ' . $old . ' could not be copied to ' . $new . ' (check the permissions).';
}

// Copies a folder with all its content (no "@")
function permission_group_manager_copy_dir($from, $to)
{
    if (!is_dir($to) && !mkdir($to, 0755, true)) {
        return false;
    }
    $ok = true;
    foreach (scandir($from) ?: array() as $e) {
        if ($e === '.' || $e === '..' || is_link($from . '/' . $e)) {
            continue;
        }
        $src = $from . '/' . $e;
        if (is_dir($src)) {
            $ok = permission_group_manager_copy_dir($src, $to . '/' . $e) && $ok;
        } elseif (is_readable($src)) {
            $ok = copy($src, $to . '/' . $e) && $ok;
        } else {
            $ok = false;
        }
    }
    return $ok;
}

// Deletes a folder with all its content (no "@")
function permission_group_manager_rrmdir($dir)
{
    if (is_link($dir) || is_file($dir)) {
        return unlink($dir);
    }
    if (!is_dir($dir)) {
        return true;
    }
    foreach (scandir($dir) ?: array() as $e) {
        if ($e !== '.' && $e !== '..') {
            permission_group_manager_rrmdir($dir . '/' . $e);
        }
    }
    return rmdir($dir);
}

// "Reset plugin": deletes the version history and the descriptions of custom permissions
// (<filestore>/permission_group_manager). The permissions of the user groups are NOT changed.
function permission_group_manager_reset()
{
    return permission_group_manager_rrmdir(permission_group_manager_base_dir());
}

// ResourceSpace shows its error page for every PHP warning - also for calls with "@" (PHP 8 no
// longer sets error_reporting() to 0 for "@"). So files and folders are checked before use.
function permission_group_manager_read_file($file)
{
    return (is_file($file) && is_readable($file)) ? (string) file_get_contents($file) : '';
}

function permission_group_manager_delete_file($file)
{
    return is_file($file) && is_writable(dirname($file)) && unlink($file);
}

function permission_group_manager_versions_mkdir()
{
    permission_group_manager_relocate();
    $dir = permission_group_manager_versions_dir();
    if (!is_dir($dir)) {
        $storage = permission_group_manager_storage_dir();
        $base    = permission_group_manager_base_dir();
        $parent  = is_dir($base) ? $base : $storage;
        if (!is_dir($parent) || !is_writable($parent) || !mkdir($dir, 0755, true)) {
            return false;
        }
    }
    if (!is_writable($dir)) {
        return false;
    }
    if (!is_file($dir . '/index.html')) {
        file_put_contents($dir . '/index.html', '');
    }
    return true;
}

function permission_group_manager_version_valid($name)
{
    return is_string($name) && preg_match('/^v\d+_\d{8}_\d{6}(_\d+)?\z/', $name) === 1;
}

// "v3_20261109_142051_1" -> num 3, stamp 20261109_142051, seq 1
function permission_group_manager_version_parse($name)
{
    if (!is_string($name) || !preg_match('/^v(\d+)_(\d{8}_\d{6})(?:_(\d+))?\z/', $name, $m)) {
        return null;
    }
    return array('num' => (int) $m[1], 'stamp' => $m[2], 'seq' => isset($m[3]) ? (int) $m[3] : 0);
}

// Version names in chronological order (oldest first)
function permission_group_manager_versions()
{
    $out = array();
    foreach (glob(permission_group_manager_versions_dir() . '/v*.json') ?: array() as $f) {
        $n = basename($f, '.json');
        if (permission_group_manager_version_valid($n)) {
            $out[] = $n;
        }
    }
    usort($out, function ($a, $b) {
        $pa = permission_group_manager_version_parse($a);
        $pb = permission_group_manager_version_parse($b);
        return array($pa['stamp'], $pa['seq'], $pa['num']) <=> array($pb['stamp'], $pb['seq'], $pb['num']);
    });
    return $out;
}

function permission_group_manager_current_version()
{
    $num = 1;
    $f   = permission_group_manager_versions_dir() . '/current_version.txt';
    $num = max($num, (int) trim(permission_group_manager_read_file($f)));
    foreach (permission_group_manager_versions() as $v) {
        $num = max($num, permission_group_manager_version_parse($v)['num']);
    }
    return $num;
}

function permission_group_manager_set_current_version($num)
{
    return permission_group_manager_versions_mkdir()
        && file_put_contents(permission_group_manager_versions_dir() . '/current_version.txt', (string) (int) $num, LOCK_EX) !== false;
}

// Stored permissions of all groups: ref => [name, permissions]
function permission_group_manager_state()
{
    $out = array();
    foreach ((array) ps_query('SELECT ref, name, permissions FROM usergroup ORDER BY ref', array()) as $g) {
        $out[(string) (int) $g['ref']] = array('name' => permission_group_manager_tr($g['name'] ?? ''), 'permissions' => implode(',', permission_group_manager_parse($g['permissions'] ?? '')));
    }
    return $out;
}

function permission_group_manager_version_read($name)
{
    if (!permission_group_manager_version_valid($name)) {
        return null;
    }
    $data = json_decode(permission_group_manager_read_file(permission_group_manager_versions_dir() . '/' . $name . '.json'), true);
    return (is_array($data) && isset($data['groups']) && is_array($data['groups'])) ? $data : null;
}

// e.g. "v2 - 2026-11-09 14:20:51 - 12 groups - admin"
function permission_group_manager_version_label($name)
{
    $p    = permission_group_manager_version_parse($name);
    $dt   = $p ? DateTime::createFromFormat('Ymd_His', $p['stamp']) : false;
    $data = permission_group_manager_version_read($name);
    return ($p ? 'v' . $p['num'] . ' - ' : '') . ($dt ? $dt->format('Y-m-d H:i:s') : $name)
        . ($data ? ' - ' . count($data['groups']) . ' groups' : ' - unreadable')
        . (!empty($data['user']) ? ' - ' . $data['user'] : '');
}

// Stores the CURRENT permissions of all groups under the current version number (or $num).
// Returns the stored name, '' when nothing had to be stored (same as the newest entry), false on error.
function permission_group_manager_snapshot($num = null)
{
    if (!permission_group_manager_versions_mkdir()) {
        return false;
    }
    $dir      = permission_group_manager_versions_dir();
    $num      = $num === null ? permission_group_manager_current_version() : (int) $num;
    $state    = permission_group_manager_state();
    $versions = permission_group_manager_versions();
    $last     = end($versions);
    if ($last !== false && permission_group_manager_version_parse($last)['num'] === $num) {
        $prev = permission_group_manager_version_read($last);
        if ($prev && $prev['groups'] == $state) {
            return '';
        }
    }
    $stamp = 'v' . $num . '_' . date('Ymd_His');
    $name  = $stamp;
    for ($i = 1; is_file($dir . '/' . $name . '.json'); $i++) {
        $name = $stamp . '_' . $i;
    }
    $data = array(
        'version' => $num,
        'created' => date('Y-m-d H:i:s'),
        'user'    => (string) ($GLOBALS['username'] ?? ''),
        'groups'  => $state,
        'custom'  => permission_group_manager_custom_defs(),
    );
    if (file_put_contents($dir . '/' . $name . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        return false;
    }
    $versions = permission_group_manager_versions();
    foreach (array_slice($versions, 0, max(0, count($versions) - PERMISSION_GROUP_MANAGER_KEEP_VERSIONS)) as $old) {
        permission_group_manager_delete_file($dir . '/' . $old . '.json');
    }
    return $name;
}

// Saves the current permissions as the next version number. Returns the new name or false.
function permission_group_manager_new_version()
{
    $num = permission_group_manager_current_version() + 1;
    if (!permission_group_manager_set_current_version($num)) {
        return false;
    }
    $name = permission_group_manager_snapshot($num);
    return $name === '' ? false : $name;
}

// First version after the plugin is activated (called from the "initialise" hook and the page)
function permission_group_manager_ensure_first_version()
{
    permission_group_manager_relocate(); // take over the data of the old plugin "groupperms"
    $dir = permission_group_manager_versions_dir();
    if (is_file($dir . '/current_version.txt')) {
        return;
    }
    if (permission_group_manager_set_current_version(1)) {
        permission_group_manager_snapshot(1);
    }
}

// Deletes all files of version number $num; returns the number of deleted files
function permission_group_manager_delete_version($num)
{
    $n = 0;
    foreach (permission_group_manager_versions() as $v) {
        if (permission_group_manager_version_parse($v)['num'] === (int) $num && permission_group_manager_delete_file(permission_group_manager_versions_dir() . '/' . $v . '.json')) {
            $n++;
        }
    }
    return $n;
}

// Deletes the whole history except the newest entry; returns the number of deleted files
function permission_group_manager_clear_history()
{
    $versions = permission_group_manager_versions();
    array_pop($versions);
    $n = 0;
    foreach ($versions as $v) {
        if (permission_group_manager_delete_file(permission_group_manager_versions_dir() . '/' . $v . '.json')) {
            $n++;
        }
    }
    return $n;
}

// Writes the saved permissions of a version back to all groups that still exist
// (all codes, including the ones the table does not show). Returns the number of changed groups.
function permission_group_manager_restore_version($name, $own_group, array &$errors)
{
    $data = permission_group_manager_version_read($name);
    if ($data === null) {
        $errors[] = 'The selected version cannot be read.';
        return 0;
    }
    $groups  = permission_group_manager_groups();
    $changed = 0;
    // descriptions of custom permissions stored with the version are added back
    if (!empty($data['custom']) && is_array($data['custom'])) {
        permission_group_manager_custom_save(permission_group_manager_custom_defs() + $data['custom'], permission_group_manager_known_codes(permission_group_manager_catalogue()));
    }
    foreach ($data['groups'] as $ref => $g) {
        $ref = (int) $ref;
        if (!isset($groups[$ref]) || !is_array($g)) {
            continue;
        }
        $old = $groups[$ref]['perms'];
        $new = permission_group_manager_parse($g['permissions'] ?? '');
        if ($ref === (int) $own_group && array_diff(array_intersect(array('t', 'a'), $old), $new)) {
            $errors[] = 'Your own group "' . $groups[$ref]['name'] . '" was not restored: it would lose Admin / System setup access.';
            continue;
        }
        $a = $old;
        $b = $new;
        sort($a);
        sort($b);
        if ($a === $b) {
            continue;
        }
        if (permission_group_manager_save_group($ref, $new, $old)) {
            $changed++;
        } else {
            $errors[] = 'Restoring "' . $groups[$ref]['name'] . '" failed.';
        }
    }
    return $changed;
}

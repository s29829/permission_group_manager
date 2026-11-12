<?php
// Export and import of user groups (since 1.18).
//
// Export file (JSON):
//   {"format": "permission_group_manager-groups", "version": 1, "exported": "...", "plugin_version": "...",
//    "groups": [{"name": "...", "parent": "<name of the parent group or empty>",
//                "inherit_flags": ["permissions", ...], "permissions": ["a", "t", ...]}, ...]}
// Groups are matched by name (raw value from the database, case-insensitive), not by number,
// because numbers differ between ResourceSpace installations.
//
// Import runs in two steps: the uploaded file is kept in <temp>/permission_group_manager_import/<token>.json
// (only for the user who uploaded it, max. 1 hour) until the choices are confirmed.

require_once dirname(__FILE__) . '/functions.php';

const PERMISSION_GROUP_MANAGER_EXPORT_FORMAT = 'permission_group_manager-groups';
const PERMISSION_GROUP_MANAGER_IMPORT_TTL    = 3600;
const PERMISSION_GROUP_MANAGER_IMPORT_MAX    = 2097152; // 2 MB

function permission_group_manager_lower($s)
{
    return function_exists('mb_strtolower') ? mb_strtolower(trim((string) $s)) : strtolower(trim((string) $s));
}

// All groups with raw (untranslated) names: ref => [ref, raw, name, parent, perms, flags]
function permission_group_manager_raw_groups()
{
    $out = array();
    foreach ((array) ps_query('SELECT ref, name, parent, permissions, inherit_flags FROM usergroup ORDER BY name', array()) as $g) {
        $ref = (int) $g['ref'];
        $out[$ref] = array(
            'ref'    => $ref,
            'raw'    => (string) ($g['name'] ?? ''),
            'name'   => permission_group_manager_tr($g['name'] ?? ('#' . $ref)),
            'parent' => (int) ($g['parent'] ?? 0),
            'perms'  => permission_group_manager_parse($g['permissions'] ?? ''),
            'flags'  => permission_group_manager_parse($g['inherit_flags'] ?? ''),
        );
    }
    uasort($out, function ($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $out;
}

// ---------------------------------------------------------------------------
// Export
// ---------------------------------------------------------------------------

function permission_group_manager_export_data(array $refs)
{
    $all    = permission_group_manager_raw_groups();
    $groups = array();
    foreach ($all as $ref => $g) {
        if (!in_array($ref, $refs, true)) {
            continue;
        }
        $groups[] = array(
            'name'          => $g['raw'],
            'parent'        => isset($all[$g['parent']]) ? $all[$g['parent']]['raw'] : '',
            'inherit_flags' => $g['flags'],
            'permissions'   => $g['perms'],
        );
    }
    $plugin_yaml = dirname(__FILE__, 2) . '/permission_group_manager.yaml';
    $version     = '';
    if (is_file($plugin_yaml) && preg_match('/^version:\s*(\S+)/m', (string) file_get_contents($plugin_yaml), $m)) {
        $version = $m[1];
    }
    return array(
        'format'         => PERMISSION_GROUP_MANAGER_EXPORT_FORMAT,
        'version'        => 1,
        'exported'       => date('c'),
        'plugin_version' => $version,
        'groups'         => $groups,
    );
}

// ---------------------------------------------------------------------------
// Import: reading and checking the file
// ---------------------------------------------------------------------------

// Returns the list of groups from the file (name, parent, inherit_flags, permissions) or null + $error.
// Invalid codes and repeated group names are skipped and reported in $notes.
function permission_group_manager_import_parse($json, &$error, array &$notes)
{
    $error = '';
    $data  = json_decode((string) $json, true);
    if (!is_array($data) || ($data['format'] ?? '') !== PERMISSION_GROUP_MANAGER_EXPORT_FORMAT || !isset($data['groups']) || !is_array($data['groups'])) {
        $error = 'This is not a user group export of the Permission Group Manager plugin.';
        return null;
    }
    $out  = array();
    $seen = array();
    foreach ($data['groups'] as $i => $g) {
        $name = is_array($g) ? trim((string) ($g['name'] ?? '')) : '';
        if ($name === '' || strlen($name) > 200) {
            $notes[] = 'Entry ' . ($i + 1) . ' skipped: missing or too long group name.';
            continue;
        }
        $key = permission_group_manager_lower($name);
        if (isset($seen[$key])) {
            $notes[] = 'Group "' . $name . '" appears more than once in the file - only the first entry is used.';
            continue;
        }
        $seen[$key] = true;
        $perms      = array();
        foreach ((array) ($g['permissions'] ?? array()) as $c) {
            $c = trim((string) $c);
            if ($c === '' || in_array($c, $perms, true)) {
                continue;
            }
            if (permission_group_manager_code_valid($c)) {
                $perms[] = $c;
            } else {
                $notes[] = 'Invalid permission code skipped for "' . $name . '".';
            }
        }
        $flags = array();
        foreach ((array) ($g['inherit_flags'] ?? array()) as $f) {
            if (is_string($f) && preg_match('/^[a-z_]{1,50}$/', $f) && !in_array($f, $flags, true)) {
                $flags[] = $f;
            }
        }
        $out[] = array(
            'name'          => $name,
            'parent'        => trim((string) ($g['parent'] ?? '')),
            'inherit_flags' => $flags,
            'permissions'   => $perms,
        );
    }
    if (!$out) {
        $error = 'The file contains no user groups.';
        return null;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Import: uploaded files waiting for the confirmation
// ---------------------------------------------------------------------------

function permission_group_manager_import_dir()
{
    $base = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
    return rtrim((string) $base, '/') . '/permission_group_manager_import';
}

function permission_group_manager_import_cleanup()
{
    foreach (glob(permission_group_manager_import_dir() . '/*.json') ?: array() as $f) {
        if (is_file($f) && filemtime($f) < time() - PERMISSION_GROUP_MANAGER_IMPORT_TTL) {
            unlink($f);
        }
    }
}

// Keeps the checked groups until the user confirms. Returns the token or ''.
function permission_group_manager_import_save(array $groups, array $notes, $user, $filename)
{
    $dir = permission_group_manager_import_dir();
    if (!is_dir($dir) && (!is_writable(dirname($dir)) || !mkdir($dir, 0700, true))) {
        return '';
    }
    $token = bin2hex(random_bytes(16));
    $data  = array('user' => (int) $user, 'file' => (string) $filename, 'groups' => $groups, 'notes' => $notes);
    return file_put_contents($dir . '/' . $token . '.json', json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) === false ? '' : $token;
}

function permission_group_manager_import_get($token, $user)
{
    if (!is_string($token) || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
        return null;
    }
    $f    = permission_group_manager_import_dir() . '/' . $token . '.json';
    $data = (is_file($f) && is_readable($f)) ? json_decode((string) file_get_contents($f), true) : null;
    if (!is_array($data) || (int) ($data['user'] ?? -1) !== (int) $user || !is_array($data['groups'] ?? null)) {
        return null;
    }
    return $data;
}

function permission_group_manager_import_drop($token)
{
    if (is_string($token) && preg_match('/^[a-f0-9]{32}$/', $token) === 1) {
        $f = permission_group_manager_import_dir() . '/' . $token . '.json';
        if (is_file($f)) {
            unlink($f);
        }
    }
}

// ---------------------------------------------------------------------------
// Import: comparison with the groups in the system
// ---------------------------------------------------------------------------

// Sorts the imported groups into: new (not in the system), same (identical permissions) and
// diff (different permissions). Keys = position in the import list.
function permission_group_manager_import_plan(array $groups)
{
    $existing = array();
    foreach (permission_group_manager_raw_groups() as $g) {
        $existing[permission_group_manager_lower($g['raw'])] = $g;
    }
    $names = array();
    foreach ($groups as $g) {
        $names[permission_group_manager_lower($g['name'])] = true;
    }
    $plan = array('new' => array(), 'same' => array(), 'diff' => array());
    foreach ($groups as $i => $g) {
        $key  = permission_group_manager_lower($g['name']);
        $pkey = permission_group_manager_lower($g['parent']);
        if ($g['parent'] === '') {
            $g['parent_info'] = 'top level';
        } elseif (isset($existing[$pkey])) {
            $g['parent_info'] = 'subgroup of ' . $existing[$pkey]['name'] . ' (in the system)';
        } elseif (isset($names[$pkey])) {
            $g['parent_info'] = 'subgroup of ' . $g['parent'] . ' (from this file - only when it is imported too)';
        } else {
            $g['parent_info'] = 'parent "' . $g['parent'] . '" not found - top level';
        }
        if (!isset($existing[$key])) {
            $plan['new'][$i] = $g;
            continue;
        }
        $e        = $existing[$key];
        $g['ref'] = $e['ref'];
        $g['current'] = $e['perms'];
        $g['inherits'] = in_array('permissions', $e['flags'], true) && $e['parent'] > 0;
        $a = $g['permissions'];
        $b = $e['perms'];
        sort($a);
        sort($b);
        if ($a === $b) {
            $plan['same'][$i] = $g;
        } else {
            $plan['diff'][$i] = $g;
        }
    }
    return $plan;
}

// Labels of all known permission codes: code => [label, reverse, order]
function permission_group_manager_code_labels()
{
    $out   = array();
    $order = 0;
    foreach (permission_group_manager_catalogue() as $s) {
        foreach ($s['rows'] as $r) {
            if (!isset($out[$r['code']])) {
                $out[$r['code']] = array('label' => $r['label'], 'reverse' => $r['reverse'], 'order' => $order++);
            }
        }
    }
    foreach (permission_group_manager_custom_defs() as $code => $desc) {
        if (!isset($out[$code])) {
            $out[$code] = array('label' => $desc !== '' ? $desc : 'Custom permission', 'reverse' => false, 'order' => $order++);
        }
    }
    return $out;
}

// Rows of the difference table: [code, label, reverse, system, import] in catalogue order
function permission_group_manager_diff_rows(array $current, array $imported, array $labels)
{
    $codes = array_values(array_unique(array_merge($current, $imported)));
    usort($codes, function ($a, $b) use ($labels) {
        $oa = $labels[$a]['order'] ?? PHP_INT_MAX;
        $ob = $labels[$b]['order'] ?? PHP_INT_MAX;
        return $oa === $ob ? strnatcasecmp($a, $b) : $oa <=> $ob;
    });
    $rows = array();
    foreach ($codes as $c) {
        $rows[] = array(
            'code'    => $c,
            'label'   => $labels[$c]['label'] ?? 'Permission not in the list of this installation',
            'reverse' => $labels[$c]['reverse'] ?? false,
            'system'  => in_array($c, $current, true),
            'import'  => in_array($c, $imported, true),
        );
    }
    return $rows;
}

// ---------------------------------------------------------------------------
// Import: applying the choices
// ---------------------------------------------------------------------------

// $choices: new => [positions to create], mode => [position => keep|import|custom],
//           custom => [position => [codes]]
// Returns array(created, updated); problems are added to $errors.
function permission_group_manager_import_apply(array $groups, array $choices, $own_group, array &$errors)
{
    $plan     = permission_group_manager_import_plan($groups);
    $existing = array();
    foreach (permission_group_manager_raw_groups() as $g) {
        $existing[permission_group_manager_lower($g['raw'])] = $g['ref'];
    }
    $created = 0;
    $updated = 0;

    // 1. Existing groups with different permissions
    foreach ($plan['diff'] as $i => $g) {
        $mode = (string) ($choices['mode'][$i] ?? 'keep');
        if ($mode === 'import') {
            $new = $g['permissions'];
        } elseif ($mode === 'custom') {
            $allowed = array_unique(array_merge($g['current'], $g['permissions']));
            $new     = array_values(array_intersect($allowed, array_map('strval', (array) ($choices['custom'][$i] ?? array()))));
        } else {
            continue;
        }
        if ((int) $g['ref'] === (int) $own_group) {
            foreach (array('t', 'a') as $keep) {
                if (in_array($keep, $g['current'], true) && !in_array($keep, $new, true)) {
                    $new[]    = $keep;
                    $errors[] = 'Kept "' . $keep . '" for your own user group "' . $g['name'] . '" (Admin / System setup access cannot be removed from it).';
                }
            }
        }
        if (permission_group_manager_save_group($g['ref'], $new, $g['current'])) {
            $updated++;
        } else {
            $errors[] = 'Saving "' . $g['name'] . '" failed.';
        }
    }

    // 2. New groups - parents first (a parent can also come from the same file)
    $todo = array();
    foreach ((array) ($choices['new'] ?? array()) as $i) {
        if (isset($plan['new'][(int) $i])) {
            $todo[(int) $i] = $plan['new'][(int) $i];
        }
    }
    for ($round = 0; $todo && $round < 50; $round++) {
        foreach ($todo as $i => $g) {
            $pkey = permission_group_manager_lower($g['parent']);
            $waits = $g['parent'] !== '' && !isset($existing[$pkey]) && array_filter($todo, function ($x) use ($pkey) {
                return permission_group_manager_lower($x['name']) === $pkey;
            });
            if ($waits && $round < 49) {
                continue; // create the parent first
            }
            $parent = ($g['parent'] !== '' && isset($existing[$pkey])) ? (int) $existing[$pkey] : 0;
            $data   = array(
                'name'         => $g['name'],
                'permissions'  => implode(',', $g['permissions']),
                'request_mode' => 1,
            );
            if ($parent > 0) {
                $data['parent']        = $parent;
                $data['inherit_flags'] = implode(',', $g['inherit_flags']);
            }
            $ref = function_exists('save_usergroup') ? (int) save_usergroup(0, $data) : 0;
            if ($ref > 0) {
                $existing[permission_group_manager_lower($g['name'])] = $ref;
                $created++;
                if (function_exists('log_activity') && defined('LOG_CODE_CREATED')) {
                    try {
                        log_activity(null, LOG_CODE_CREATED, null, 'usergroup', null, $ref);
                        log_activity(null, LOG_CODE_CREATED, $g['name'], 'usergroup', 'name', $ref, null, '');
                        log_activity(null, LOG_CODE_CREATED, $data['permissions'], 'usergroup', 'permissions', $ref, null, '');
                    } catch (Throwable $e) {
                        // logging must not stop the import
                    }
                }
            } else {
                $errors[] = 'The group "' . $g['name'] . '" could not be created.';
            }
            unset($todo[$i]);
        }
    }
    return array($created, $updated);
}

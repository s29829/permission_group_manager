<?php
include '../../../include/db.php';
include '../../../include/authenticate.php';
require_once dirname(__FILE__, 2) . '/include/functions.php';

// Admins only
if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

$self        = $_SERVER['PHP_SELF'];
$plugins_url = $baseurl_short . 'pages/team/team_plugins.php';
$own_group   = (int) ($usergroup ?? 0);
$action      = (string) ($_POST['action'] ?? '');
$reset_open  = false; // open the collapsible "Reset plugin" box after a failed reset
$message     = '';
$errors      = array();

$relocated = permission_group_manager_relocate();
permission_group_manager_ensure_first_version();

// ---- Handle form actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    if (function_exists('enforcePostRequest') && !enforcePostRequest(false)) {
        $errors[] = 'The request could not be verified. Reload the page and try again.';
    } elseif ($action === 'save' || $action === 'newversion') {
        $changes = (string) ($_POST['changes'] ?? '');
        $saved   = 0;
        if ($changes !== '' && $changes !== '{}') {
            if (permission_group_manager_snapshot() === false) { // keep the previous permissions in the history
                $errors[] = 'The previous permissions could not be stored in the version history (check write access to ' . permission_group_manager_versions_dir() . ').';
            } else {
                $saved = permission_group_manager_apply($changes, $own_group, $errors);
            }
        }
        // list of custom permission rows (code => description)
        $defs = json_decode((string) ($_POST['custom_defs'] ?? ''), true);
        if (is_array($defs) && !$errors && !permission_group_manager_custom_save($defs, permission_group_manager_known_codes(permission_group_manager_catalogue()))) {
            $errors[] = 'The custom permissions could not be stored (check write access to ' . permission_group_manager_versions_dir() . ').';
        }
        if (!$errors) {
            if ($action === 'newversion') {
                $name = permission_group_manager_new_version();
                if ($name === false) {
                    $errors[] = 'The new version could not be stored (check write access to ' . permission_group_manager_versions_dir() . ').';
                } else {
                    redirect($self . '?done=newversion&n=' . $saved . '&v=' . rawurlencode($name));
                    exit;
                }
            } else {
                redirect($self . '?done=saved&n=' . $saved);
                exit;
            }
        }
        $message = $saved . ' group(s) saved.';
    } elseif ($action === 'restore_version' || $action === 'delete_version' || $action === 'clear_history') {
        $vname = (string) ($_POST['version_name'] ?? '');
        $vp    = permission_group_manager_version_parse($vname);
        if ($action !== 'clear_history' && ($vp === null || !in_array($vname, permission_group_manager_versions(), true))) {
            $errors[] = 'Select a version.';
        } elseif ($action === 'restore_version') {
            if (permission_group_manager_snapshot() === false) {
                $errors[] = 'The current permissions could not be stored in the version history; nothing was restored.';
            } else {
                $n = permission_group_manager_restore_version($vname, $own_group, $errors);
                if (!$errors) {
                    redirect($self . '?done=restored&n=' . $n . '&v=' . rawurlencode($vname));
                    exit;
                }
                $message = $n . ' group(s) restored.';
            }
        } elseif ($action === 'delete_version') {
            $n = permission_group_manager_delete_version($vp['num']);
            redirect($self . '?done=deleted&n=' . $n . '&v=' . rawurlencode($vname));
            exit;
        } else {
            $n = permission_group_manager_clear_history();
            redirect($self . '?done=cleared&n=' . $n);
            exit;
        }
    } elseif ($action === 'reset') {
        $reset_open = true; // stays open when the reset fails (a successful reset redirects)
        if (trim((string) ($_POST['reset_confirm'] ?? '')) !== 'RESET') {
            $errors[] = 'Not reset: type RESET (capital letters) to confirm.';
        } elseif (!permission_group_manager_reset()) {
            $errors[] = 'The plugin could not be reset (check permissions of ' . permission_group_manager_base_dir() . ').';
        } else {
            redirect($self . '?done=reset');
            exit;
        }
    }
}

// Messages after a redirect
$done  = (string) ($_GET['done'] ?? '');
$n     = (int) ($_GET['n'] ?? 0);
$vdone = permission_group_manager_version_parse((string) ($_GET['v'] ?? ''));
switch ($done) {
    case 'saved':
        $message = 'Permissions saved for ' . $n . ' user group(s). The previous permissions are in the version history.';
        break;
    case 'newversion':
        $message = ($n ? 'Permissions saved for ' . $n . ' user group(s) and stored' : 'Current permissions stored') . ' as new version v' . ($vdone['num'] ?? '?') . '.';
        break;
    case 'restored':
        $message = 'Version v' . ($vdone['num'] ?? '?') . ' restored: ' . $n . ' user group(s) changed. The permissions before the restore are in the version history.';
        break;
    case 'deleted':
        $message = 'Version v' . ($vdone['num'] ?? '?') . ' deleted (' . $n . ' file(s)).';
        break;
    case 'cleared':
        $message = 'Version history cleared (' . $n . ' file(s) deleted, the newest entry is kept).';
        break;
    case 'reset':
        $message = 'The plugin was reset: the version history and the descriptions of custom permissions were deleted. The permissions of the user groups were not changed; the current state is version v1 again.';
        break;
}
if ($relocated !== '') {
    $message = trim($relocated . ' ' . $message);
}
if (in_array('groupperms', (array) ($GLOBALS['plugins'] ?? array()), true)) {
    $message = trim($message . ' The old version of this plugin ("groupperms", renamed in 1.22) is still active - deactivate it in System > Plugins and delete its folder; its data was taken over.');
}

// ---- Prepare the page ----
$catalogue = permission_group_manager_catalogue();
$known     = permission_group_manager_known_codes($catalogue);
$groups    = permission_group_manager_groups();
$parents   = array();
foreach ($groups as $g) {
    if ($g['parent'] > 0 && isset($groups[$g['parent']])) {
        $parents[$g['parent']] = $groups[$g['parent']]['name'];
    }
}
asort($parents, SORT_NATURAL | SORT_FLAG_CASE);

// Current permissions (what is stored) and shown permissions (a loaded version, if any)
$eff  = array();
$show = array();
foreach ($groups as $ref => $g) {
    $eff[$ref]  = array_flip(permission_group_manager_effective($groups, $ref));
    $show[$ref] = $eff[$ref];
}
$loaded = '';
$vparam = (string) ($_GET['version'] ?? '');
if ($vparam !== '' && ($vdata = permission_group_manager_version_read($vparam)) !== null) {
    $loaded  = $vparam;
    $missing = 0;
    foreach ($vdata['groups'] as $ref => $vg) {
        $ref = (int) $ref;
        if (!isset($groups[$ref])) {
            $missing++;
        } elseif (!$groups[$ref]['inherits'] && is_array($vg)) {
            $show[$ref] = array_flip(permission_group_manager_parse($vg['permissions'] ?? ''));
        }
    }
    $message = 'Version ' . permission_group_manager_version_label($loaded) . ' loaded into the table. Differences to the current permissions are framed blue - click "Save changes" to apply them.'
        . ($missing ? ' ' . $missing . ' group(s) of that version no longer exist.' : '');
}

// Custom permission rows: saved ones first, then other unknown codes found in the groups
$cdefs  = permission_group_manager_custom_defs();
$ccodes = array_map('strval', array_keys($cdefs));
$extra  = array();
foreach ($groups as $ref => $g) {
    foreach (array_merge(array_keys($show[$ref]), array_keys($eff[$ref])) as $c) {
        $c = (string) $c;
        if (!isset($known[$c]) && !in_array($c, $ccodes, true) && !in_array($c, $extra, true)) {
            $extra[] = $c;
        }
    }
}
natcasesort($extra);
$ccodes = array_merge($ccodes, array_values($extra));
$jrefs  = array();
foreach ($ccodes as $c) {
    if (preg_match('/^-?j(\d+)\z/', $c, $m)) {
        $jrefs[] = (int) $m[1];
    }
}
$colls = permission_group_manager_collection_names($jrefs);

$versions    = permission_group_manager_versions();
$current_num = permission_group_manager_current_version();
$version_sel = $loaded !== '' ? $loaded : (string) end($versions);
$nonce       = isset($CSP_nonce) ? ' nonce="' . htmlspecialchars($CSP_nonce) . '"' : '';
$h           = function ($s) { return htmlspecialchars((string) $s); };
$lower       = function ($s) { return function_exists('mb_strtolower') ? mb_strtolower((string) $s) : strtolower((string) $s); };
// group names in the column headers: at most 20 characters (full name in the tooltip)
$short       = function ($s, $max = 20) {
    $s = (string) $s;
    $len = function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
    return $len <= $max ? $s : rtrim(function_exists('mb_substr') ? mb_substr($s, 0, $max - 1) : substr($s, 0, $max - 1)) . "\u{2026}";
};
$colspan_all = count($groups) + 3;

include '../../../include/header.php';
?>
<div class="BasicsBox gp-page">
    <h1>Permission Group Manager</h1>
    <?php
    renderBreadcrumbs([
        ['title' => $lang['systemsetup'],   'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
        ['title' => $lang['pluginmanager'], 'href' => $plugins_url],
        ['title' => 'Permission Group Manager'],
    ]);
    ?>

    <?php if ($message) { ?><div class="PageInformal"><?php echo $h($message); ?></div><?php } ?>
    <?php foreach ($errors as $e) { ?><div class="FormError"><?php echo $h($e); ?></div><?php } ?>

    <style<?php echo $nonce; ?>>
        .gp-page { --gp-bg: #fff; }
        .gp-legend { opacity: 0.8; font-size: 0.9em; }
        .gp-wrap { overflow: auto; max-height: 72vh; border: 1px solid rgba(128, 128, 128, 0.45); border-top: 0; border-radius: 0 0 6px 6px; }
        .gp-table { border-collapse: separate; border-spacing: 0; min-width: 100%; }
        /* normal letters (some themes use upper case / small caps in table headers) */
        .gp-table th, .gp-table td, .gp-table button, .gp-table input, .gp-table select { text-transform: none; font-variant: normal; letter-spacing: normal; }
        .gp-table th, .gp-table td { padding: 4px 6px; border-bottom: 1px solid rgba(128, 128, 128, 0.25); background-color: var(--gp-bg); color: inherit; }
        /* filters above the table: two panels - permissions (rows) and user groups (columns).
           The accent colours match the header of the part of the table they filter.
           The permissions panel is exactly as wide as the "Permission" + "Code" columns (--gp-left),
           so the groups panel starts where the group columns start. */
        .gp-page { --gp-perm: #1e88e5; --gp-grp: #8a8a8a; --gp-tint: rgba(128, 128, 128, 0.09); --gp-lw: 300px; --gp-cw: 80px; --gp-left: calc(var(--gp-lw) + var(--gp-cw) + 1px); }
        .gp-filters { display: grid; grid-template-columns: var(--gp-left) 1fr; gap: 0; margin: 10px 0 0; }
        @media (max-width: 900px) { .gp-filters { grid-template-columns: 1fr; } }
        .gp-fbox { margin: 0; padding: 8px 12px 10px; border: 1px solid rgba(128, 128, 128, 0.45); border-top-width: 4px; border-radius: 6px; min-width: 0; box-sizing: border-box; }
        .gp-fbox-perm { border-top-color: var(--gp-perm); background-color: var(--gp-tint); }
        .gp-fbox-grp { border-top-color: var(--gp-grp); }
        /* the filter panels sit directly on the table (no gap, no rounded bottom corners) */
        .gp-fbox { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        @media (min-width: 901px) {
            .gp-fbox-perm { border-top-right-radius: 0; }
            .gp-fbox-grp { border-left: 0; border-top-left-radius: 0; padding-left: 22px; }
        }
        @media (max-width: 900px) { .gp-fbox-perm { border-bottom: 0; } }
        .gp-fbox legend { padding: 0 6px; font-weight: bold; }
        .gp-fbox legend small { font-weight: normal; opacity: 0.75; }
        .gp-fbox-perm legend { color: var(--gp-perm); }
        .gp-fbox-grp legend { color: var(--gp-grp); }
        .gp-fstat { font-weight: normal; font-size: 0.85em; opacity: 0.8; margin-left: 6px; color: inherit; }
        /* fields: label above, input / select styled by the ResourceSpace theme */
        .gp-fgrid { display: grid; gap: 8px 12px; align-items: end; }
        .gp-fbox-perm .gp-fgrid { grid-template-columns: 1fr; }
        .gp-checks { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 14px; font-size: 0.9em; }
        .gp-checks label { display: inline-flex; gap: 5px; align-items: center; }
        .gp-checks .gp-clabel { opacity: 0.85; }
        .gp-fbox-grp .gp-fgrid { grid-template-columns: minmax(180px, 2.5fr) minmax(150px, 2fr) minmax(120px, 130px) minmax(150px, 170px); gap: 8px 18px; }
        /* clear break before the page controls (Columns + Page) */
        @media (min-width: 1201px) { .gp-cols { padding-left: 22px; margin-left: 14px; border-left: 1px solid rgba(128, 128, 128, 0.45); } }
        @media (max-width: 1200px) { .gp-fbox-grp .gp-fgrid { grid-template-columns: 1fr 1fr; } }
        .gp-field { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
        .gp-field.gp-wide { grid-column: 1 / -1; }
        .gp-pager { display: flex; gap: 4px; align-items: center; }
        .gp-pager select { flex: 1 1 auto; min-width: 0; }
        .gp-pager button { flex: 0 0 auto; margin: 0; padding: 2px 8px; }
        .gp-field > label { font-size: 0.9em; opacity: 0.85; }
        .gp-field select, .gp-field input[type=text] { width: 100%; max-width: 100%; box-sizing: border-box; margin: 0; }
        /* text fields with a small clear icon (also: Esc key) */
        .gp-sfield { position: relative; display: block; }
        .gp-sfield input[type=text] { padding-right: 28px; }
        .gp-x { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); border: 0; background: none; padding: 0 4px; margin: 0;
                cursor: pointer; color: inherit; opacity: 0.55; font-size: 1.2em; line-height: 1; box-shadow: none; min-width: 0; }
        .gp-x:hover, .gp-x:focus { opacity: 1; }
        .gp-x[hidden] { display: none; }
        /* open / close all sections: under "Permission" in the header */
        .gp-head .gp-checks { font-weight: normal; font-size: 0.8em; margin-top: 4px; }
        .gp-head .gp-label, .gp-head .gp-code { background-image: linear-gradient(var(--gp-tint), var(--gp-tint)); }
        .gp-link { background: none; border: 0; padding: 0; margin: 0; color: inherit; text-decoration: underline; cursor: pointer; font: inherit; font-size: 0.9em; box-shadow: none; min-width: 0; }
        .gp-link:hover, .gp-link:focus { opacity: 0.75; }
        /* column headers */
        .gp-head th { position: sticky; top: 0; z-index: 2; vertical-align: bottom; font-weight: normal; border-bottom: 1px solid rgba(128, 128, 128, 0.45); }
        /* group columns: width follows the group name (long names wrap at 200px) */
        .gp-head .gp-gh { text-align: center; font-size: 0.9em; padding: 4px 8px; }
        .gp-gh b, .gp-gh small { display: block; max-width: 200px; min-width: 48px; margin: 0 auto; overflow-wrap: anywhere; }
        .gp-gh b { font-weight: bold; }
        .gp-gh small { opacity: 0.7; }
        .gp-own { box-shadow: inset 0 3px 0 #2e7d32; }
        /* fixed columns: permission + code on the left, select buttons on the right */
        .gp-table .gp-label { position: sticky; left: 0; z-index: 1; width: var(--gp-lw); min-width: var(--gp-lw); max-width: var(--gp-lw); box-sizing: border-box; text-align: left; font-weight: normal; }
        .gp-table .gp-code { position: sticky; left: var(--gp-lw); z-index: 1; width: var(--gp-cw); min-width: var(--gp-cw); max-width: var(--gp-cw); box-sizing: border-box; text-align: left; border-right: 2px solid rgba(128, 128, 128, 0.5); }
        /* "Select" column (tick / untick the row for the shown groups): fixed width */
        .gp-table .gp-act { position: sticky; right: 0; z-index: 1; width: 96px; min-width: 96px; max-width: 96px; box-sizing: border-box; white-space: nowrap; text-align: center; border-left: 2px solid rgba(128, 128, 128, 0.5); }
        .gp-head .gp-act { white-space: normal; }
        .gp-head .gp-label, .gp-head .gp-code, .gp-head .gp-act { z-index: 3; font-weight: bold; }
        .gp-head .gp-act { font-size: 0.85em; }
        .gp-code code { font-size: 0.9em; word-break: break-all; }
        .gp-act button { background: transparent; border: 1px solid rgba(128, 128, 128, 0.7); border-radius: 3px; box-shadow: none;
                         color: inherit; padding: 1px 6px; margin: 0; min-width: 0; font-size: 0.75em; line-height: 1.3; cursor: pointer; text-transform: none; }
        .gp-act button:hover, .gp-act button:focus { background: rgba(128, 128, 128, 0.18); border-color: currentColor; }
        .gp-act button + button { margin-left: 4px; }
        .gp-table td { text-align: center; }
        .gp-table td input[type=checkbox] { cursor: pointer; width: 16px; height: 16px; margin: 0; }
        /* permission + code columns (and the permissions filter above): slightly different background */
        .gp-table tbody .gp-label, .gp-table tbody .gp-code { background-image: linear-gradient(var(--gp-tint), var(--gp-tint)); }
        .gp-row:hover th, .gp-row:hover td { background-image: linear-gradient(rgba(128, 128, 128, 0.12), rgba(128, 128, 128, 0.12)); }
        .gp-row:hover .gp-label, .gp-row:hover .gp-code { background-image: linear-gradient(rgba(128, 128, 128, 0.2), rgba(128, 128, 128, 0.2)); }
        .gp-rev { color: #b26a00; cursor: help; margin-left: 4px; }
        .gp-chg { box-shadow: inset 0 0 0 2px #1e88e5; }
        .gp-na input { opacity: 0.35; }
        .gp-inh { opacity: 0.55; }
        .gp-hide { display: none; }
        /* section headers: a full-width row with an arrow, click to open / close */
        .gp-sh td { padding: 0; text-align: left; border-bottom: 1px solid rgba(128, 128, 128, 0.45); }
        .gp-sh-btn { position: sticky; left: 0; display: inline-flex; align-items: center; gap: 8px; padding: 8px 10px; cursor: pointer; font-weight: bold; user-select: none; }
        .gp-sh-btn::before { content: "\25BE"; display: inline-block; width: 1em; transition: transform 0.15s; }
        .gp-collapsed .gp-sh-btn::before { transform: rotate(-90deg); }
        .gp-sh-btn:hover, .gp-sh-btn:focus { text-decoration: underline; outline: none; }
        .gp-sh-count { font-weight: normal; opacity: 0.7; }
        .gp-sh-hint { font-weight: normal; opacity: 0.6; font-size: 0.85em; }
        .gp-sh td { background-image: linear-gradient(rgba(128, 128, 128, 0.1), rgba(128, 128, 128, 0.1)); }
        .gp-collapsed tr.gp-row { display: none; }
        .gp-filtering .gp-collapsed tr.gp-row:not(.gp-hide) { display: table-row; }
        /* custom permission rows: editable description, remove link; last row adds a new one */
        .gp-cline { display: flex; gap: 6px; align-items: center; }
        .gp-cline input[type=text] { flex: 1 1 auto; min-width: 0; width: 100%; box-sizing: border-box; margin: 0; }
        .gp-cdesc.gp-dchg { outline: 2px solid #1e88e5; }
        .gp-crow.gp-removed { display: none !important; }
        .gp-cadd th, .gp-cadd td { vertical-align: middle; text-align: left; }
        .gp-cadd input[type=text] { width: 100%; box-sizing: border-box; margin: 0; }
        .gp-cadd-cell { white-space: nowrap; }
        .gp-cadd-msg { margin-left: 10px; font-size: 0.9em; }
        .gp-cadd-msg.gp-err { color: #c62828; }
        .gp-collapsed tr.gp-cadd { display: none; }
        /* below the table */
        .gp-save { position: sticky; bottom: 0; z-index: 4; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 8px 0; background-color: var(--gp-bg); }
        #gp-count.gp-has { font-weight: bold; color: #1e88e5; }
        .gp-box { margin: 14px 0; padding: 10px 14px; border: 1px solid rgba(128, 128, 128, 0.45); border-radius: 6px; }
        .gp-box h3 { margin: 0 0 8px; font-size: 1.05em; }
        details.gp-box > summary { cursor: pointer; list-style: none; display: flex; align-items: center; gap: 8px; }
        details.gp-box > summary::-webkit-details-marker { display: none; }
        details.gp-box > summary::before { content: "\25B8"; display: inline-block; transition: transform 0.15s; }
        details.gp-box[open] > summary::before { transform: rotate(90deg); }
        details.gp-box > summary h3 { margin: 0; display: inline; }
        details.gp-box[open] > summary { margin-bottom: 8px; }
        .gp-box p { margin: 0.5em 0; }
        .gp-row-ctl { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0.6em 0; }
        #gp-version { max-width: 100%; }
    </style>

    <?php if (!$groups) { ?>
        <p><i>No user groups found.</i></p>
    <?php } else { ?>

    <p class="gp-legend">Ticked = the group has the permission. <span class="gp-rev">&#8634;</span> marks "reverse" options (as in ResourceSpace: ticked means the code is <b>not</b> set).
        Greyed cells do not apply to that group (for example they need Admin access first). Blue frame = changed, not saved yet.
        Groups that inherit permissions from their parent are read-only. Click a section name to open or close it.
        Sections marked "plugin" belong to active plugins. Other codes are rows in "Custom permissions" at the end of the table - add new ones there (description + code).</p>

    <div class="gp-filters">
        <fieldset class="gp-fbox gp-fbox-perm">
            <legend>Permissions <small>(table rows)</small><span class="gp-fstat" id="gp-qstat"></span></legend>
            <div class="gp-fgrid">
                <div class="gp-field gp-wide">
                    <label for="gp-q">Search permissions</label>
                    <span class="gp-sfield">
                        <input type="text" id="gp-q" class="stdwidth" placeholder="Name or code, e.g. upload or XU" autocomplete="off">
                        <button type="button" class="gp-x" data-for="gp-q" title="Clear (Esc)" aria-label="Clear" hidden>&times;</button>
                    </span>
                </div>
                <div class="gp-checks">
                    <span class="gp-clabel">Show only:</span>
                    <label title="Rows where the shown groups have different settings"><input type="checkbox" id="gp-diff"> differing</label>
                    <label title="Rows with changes that are not saved yet"><input type="checkbox" id="gp-changed"> unsaved</label>
                </div>
            </div>
        </fieldset>
        <fieldset class="gp-fbox gp-fbox-grp">
            <legend>User groups <small>(table columns)</small><span class="gp-fstat" id="gp-gstat"></span></legend>
            <div class="gp-fgrid">
                <div class="gp-field">
                    <label for="gp-gq">Search groups</label>
                    <span class="gp-sfield">
                        <input type="text" id="gp-gq" class="stdwidth" placeholder="Name or #number" autocomplete="off">
                        <button type="button" class="gp-x" data-for="gp-gq" title="Clear (Esc)" aria-label="Clear" hidden>&times;</button>
                    </span>
                </div>
                <div class="gp-field">
                    <label for="gp-parent">Parent group</label>
                    <select id="gp-parent">
                        <option value="">all groups</option>
                        <option value="top">top level only</option>
                        <?php foreach ($parents as $ref => $name) { ?>
                            <option value="<?php echo (int) $ref; ?>"><?php echo $h($name); ?> + subgroups</option>
                        <?php } ?>
                    </select>
                </div>
                <div class="gp-field gp-cols">
                    <label for="gp-size">Columns</label>
                    <select id="gp-size">
                        <?php foreach (array(10, 15, 20) as $c) { ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php } ?>
                    </select>
                </div>
                <div class="gp-field">
                    <label for="gp-page">Page</label>
                    <span class="gp-pager">
                        <button type="button" id="gp-prev" title="Previous groups">&laquo;</button>
                        <select id="gp-page"></select>
                        <button type="button" id="gp-next" title="Next groups">&raquo;</button>
                    </span>
                </div>
            </div>
        </fieldset>
    </div>

    <div class="gp-wrap">
        <table class="gp-table" id="gp-table">
            <thead>
                <tr class="gp-head">
                    <th class="gp-label">
                        <div>Permission</div>
                        <div class="gp-checks">
                            <span class="gp-clabel">Sections:</span>
                            <label><input type="checkbox" id="gp-open-all"> open all</label>
                            <label><input type="checkbox" id="gp-close-all"> close all</label>
                        </div>
                    </th>
                    <th class="gp-code">Code</th>
                    <?php foreach ($groups as $ref => $g) { ?>
                        <th class="gp-gh<?php echo $ref === $own_group ? ' gp-own' : ''; ?>" data-g="<?php echo (int) $ref; ?>" data-parent="<?php echo (int) $g['parent']; ?>"
                            data-name="<?php echo $h($lower($g['name'])); ?>"<?php echo $g['inherits'] ? ' data-inh="1"' : ''; ?><?php echo $ref === $own_group ? ' data-own="1"' : ''; ?>
                            title="<?php echo $h($g['name'] . ($ref === $own_group ? ' (your group)' : '')); ?>">
                            <b><?php echo $h($short($g['name'])); ?></b>
                            <small>#<?php echo (int) $ref; ?><?php echo $ref === $own_group ? ' &middot; you' : ''; ?></small>
                            <?php if ($g['inherits']) { ?><small>inherits from <?php echo $h($short($groups[$g['parent']]['name'] ?? '#' . $g['parent'])); ?></small><?php } ?>
                        </th>
                    <?php } ?>
                    <th class="gp-act" title="All / None: tick / untick the row for all groups shown in the table">Select</th>
                </tr>
            </thead>
            <?php foreach ($catalogue as $s) { ?>
                <tbody class="gp-sec<?php echo !empty($s['collapsed']) ? ' gp-collapsed' : ''; ?>">
                    <tr class="gp-sh"><td colspan="<?php echo $colspan_all; ?>"><span class="gp-sh-btn" role="button" tabindex="0" aria-expanded="<?php echo !empty($s['collapsed']) ? 'false' : 'true'; ?>">
                        <?php echo $h($s['title']); ?> <span class="gp-sh-count">(<span class="gp-n"><?php echo count($s['rows']); ?></span>)</span>
                        <?php if (!empty($s['plugin'])) { ?><span class="gp-sh-hint">- plugin <?php echo $h($s['plugin']); ?></span><?php } ?>
                    </span></td></tr>
                    <?php foreach ($s['rows'] as $r) { ?>
                        <tr class="gp-row" data-code="<?php echo $h($r['code']); ?>"<?php echo $r['reverse'] ? ' data-rev="1"' : ''; ?><?php echo $r['requires'] ? ' data-req="' . $h(implode(',', $r['requires'])) . '"' : ''; ?><?php echo !empty($r['implied']) ? ' data-imp="' . $h(implode(',', $r['implied'])) . '"' : ''; ?>>
                            <th class="gp-label"><?php echo $h($r['label']); ?></th>
                            <td class="gp-code"><code><?php echo $h($r['code']); ?></code><?php if ($r['reverse']) { ?><span class="gp-rev" title="Reverse option: ticked = code &quot;<?php echo $h($r['code']); ?>&quot; is NOT set">&#8634;</span><?php } ?></td>
                            <?php foreach ($groups as $ref => $g) {
                                $code = $r['code'];
                                $on   = isset($show[$ref][$code]) !== $r['reverse'];
                                $was  = isset($eff[$ref][$code]) !== $r['reverse'];
                                $cls  = $g['inherits'] ? ' class="gp-inh"' : '';
                                ?><td<?php echo $cls; ?>><input type="checkbox"<?php echo $on ? ' checked' : ''; ?><?php echo $g['inherits'] ? ' disabled' : ''; ?><?php echo $on !== $was ? ' data-o="' . ($was ? 1 : 0) . '"' : ''; ?>></td><?php
                            } ?>
                            <td class="gp-act"><button type="button" class="gp-all" title="Tick for all shown groups">All</button><button type="button" class="gp-none" title="Untick for all shown groups">None</button></td>
                        </tr>
                    <?php } ?>
                </tbody>
            <?php } ?>
            <tbody class="gp-sec gp-csec">
                <tr class="gp-sh"><td colspan="<?php echo $colspan_all; ?>"><span class="gp-sh-btn" role="button" tabindex="0" aria-expanded="true">
                    Custom permissions <span class="gp-sh-count">(<span class="gp-n"><?php echo count($ccodes); ?></span>)</span>
                    <span class="gp-sh-hint">- codes not listed above (featured collection categories, other plugins ...), one row each</span>
                </span></td></tr>
                <?php foreach ($ccodes as $code) {
                    $desc = isset($cdefs[$code]) && $cdefs[$code] !== '' ? $cdefs[$code] : permission_group_manager_custom_default_desc($code, $colls); ?>
                    <tr class="gp-row gp-crow" data-code="<?php echo $h($code); ?>" data-custom="1" data-saved="<?php echo isset($cdefs[$code]) ? '1' : '0'; ?>">
                        <th class="gp-label"><span class="gp-cline">
                            <input type="text" class="gp-cdesc" value="<?php echo $h($desc); ?>" data-o="<?php echo $h($desc); ?>" placeholder="Description" maxlength="200" aria-label="<?php echo $h('Description of ' . $code); ?>">
                            <button type="button" class="gp-link gp-cdel" title="Remove this row (unticks the code in all groups)">remove</button>
                        </span></th>
                        <td class="gp-code"><code><?php echo $h($code); ?></code></td>
                        <?php foreach ($groups as $ref => $g) {
                            $on  = isset($show[$ref][$code]);
                            $was = isset($eff[$ref][$code]);
                            ?><td<?php echo $g['inherits'] ? ' class="gp-inh"' : ''; ?>><input type="checkbox"<?php echo $on ? ' checked' : ''; ?><?php echo $g['inherits'] ? ' disabled' : ''; ?><?php echo $on !== $was ? ' data-o="' . ($was ? 1 : 0) . '"' : ''; ?>></td><?php
                        } ?>
                        <td class="gp-act"><button type="button" class="gp-all" title="Tick for all shown groups">All</button><button type="button" class="gp-none" title="Untick for all shown groups">None</button></td>
                    </tr>
                <?php } ?>
                <tr class="gp-cadd">
                    <th class="gp-label"><input type="text" id="gp-new-desc" placeholder="Description of the new permission" maxlength="200"></th>
                    <td class="gp-code"><input type="text" id="gp-new-code" placeholder="Code" maxlength="100" spellcheck="false"></td>
                    <td class="gp-cadd-cell" colspan="<?php echo count($groups); ?>"><button type="button" id="gp-cadd">Add permission row</button><span class="gp-cadd-msg" id="gp-cadd-msg"></span></td>
                    <td class="gp-act"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Save -->
    <form method="post" action="<?php echo $h($self); ?>" id="gp-form" class="gp-save">
        <?php generateFormToken('permission_group_manager'); ?>
        <input type="hidden" name="changes" id="gp-changes" value="">
        <input type="hidden" name="custom_defs" id="gp-cdefs" value="">
        <button type="submit" name="action" value="save" id="gp-savebtn" disabled>Save changes</button>
        <button type="submit" name="action" value="newversion" id="gp-newversion" title="Saves the changes (if any) and stores the permissions as version v<?php echo (int) $current_num + 1; ?>">Save as new version</button>
        <button type="button" id="gp-discard" disabled>Discard changes</button>
        <span id="gp-count">No changes.</span>
    </form>

    <!-- Copy all permissions of one group to another -->
    <div class="gp-box">
        <h3>Copy permissions</h3>
        <div class="gp-row-ctl">
            <label>From
                <select id="gp-copy-from">
                    <?php foreach ($groups as $ref => $g) { ?><option value="<?php echo (int) $ref; ?>"><?php echo $h($g['name']); ?></option><?php } ?>
                </select>
            </label>
            <label>to
                <select id="gp-copy-to">
                    <?php foreach ($groups as $ref => $g) { if ($g['inherits']) { continue; } ?><option value="<?php echo (int) $ref; ?>"><?php echo $h($g['name']); ?></option><?php } ?>
                </select>
            </label>
            <button type="button" id="gp-copy">Copy</button>
        </div>
        <p class="gp-legend">Copies all permissions of the table, including the custom permission rows. Nothing is saved until you click "Save changes".</p>
    </div>

    <!-- Export / import of user groups -->
    <div class="gp-box">
        <h3>Export / import user groups</h3>
        <p>Save user groups with their permissions to a file, or import groups from such a file (for example from another ResourceSpace installation).
            Before an import you choose which new groups to add and how to handle groups whose permissions differ.</p>
        <div class="gp-row-ctl"><a href="transfer.php">Export / import user groups &rarr;</a></div>
    </div>

    <!-- Version history -->
    <div class="gp-box">
        <h3>Version history</h3>
        <p>Current version: <b>v<?php echo (int) $current_num; ?></b></p>
        <p>Every "Save changes" stores the previous permissions of all groups under the current version number. "Save as new version" starts the next number.
            The last <?php echo (int) PERMISSION_GROUP_MANAGER_KEEP_VERSIONS; ?> entries are kept. The first version was created when the plugin was activated.</p>
        <?php if ($versions) { ?>
            <form method="post" action="<?php echo $h($self); ?>" id="gp-vform">
                <?php generateFormToken('permission_group_manager'); ?>
                <select name="version_name" id="gp-version">
                    <?php foreach ($versions as $v) { ?>
                        <option value="<?php echo $h($v); ?>" data-num="<?php echo (int) permission_group_manager_version_parse($v)['num']; ?>"<?php echo $v === $version_sel ? ' selected' : ''; ?>><?php echo $h(permission_group_manager_version_label($v)); ?></option>
                    <?php } ?>
                </select>
                <div class="gp-row-ctl">
                    <button type="button" id="gp-vload">Load into table</button>
                    <button type="submit" name="action" value="restore_version">Restore this version</button>
                    <button type="submit" name="action" value="delete_version">Delete this version</button>
                    <button type="submit" name="action" value="clear_history"<?php echo count($versions) < 2 ? ' disabled' : ''; ?>>Delete all history</button>
                </div>
            </form>
            <p class="gp-legend">"Load into table" only shows the version in the table (differences are framed blue) - click "Save changes" to apply it.
                "Restore this version" writes it back at once, including the other codes. In both cases the current permissions stay in the history.
                Your own group never loses Admin / System setup access.</p>
        <?php } else { ?>
            <p><i>No versions yet. They are stored in <code><?php echo $h(permission_group_manager_versions_dir()); ?></code> - check that the web server can write there.</i></p>
        <?php } ?>
    </div>

    <!-- Reset (collapsible) -->
    <details class="gp-box"<?php echo $reset_open ? ' open' : ''; ?>>
        <summary><h3>Reset plugin</h3></summary>
        <p>Deletes everything the plugin stored in <code><?php echo $h(permission_group_manager_base_dir()); ?></code>: the version history and the descriptions
            of custom permissions. <b>The permissions of the user groups are not changed.</b> This cannot be undone.</p>
        <form method="post" action="<?php echo $h($self); ?>" id="gp-reset">
            <?php generateFormToken('permission_group_manager'); ?>
            <div class="gp-row-ctl">
                <label>To confirm, type <b>RESET</b> <input type="text" name="reset_confirm" autocomplete="off" spellcheck="false"></label>
                <button type="submit" name="action" value="reset">Reset plugin</button>
            </div>
        </form>
    </details>

    <script<?php echo $nonce; ?>>
    (function () {
        var table = document.getElementById('gp-table');
        if (!table) { return; }
        var $ = function (id) { return document.getElementById(id); };
        var page = document.querySelector('.gp-page');
        var gq = $('gp-gq'), parentSel = $('gp-parent'), sizeSel = $('gp-size'), pq = $('gp-q');
        var diffBox = $('gp-diff'), chgBox = $('gp-changed'), pageSel = $('gp-page'), openAll = $('gp-open-all'), closeAll = $('gp-close-all'), countEl = $('gp-count');
        var saveBtn = $('gp-savebtn'), discardBtn = $('gp-discard');
        var leaving = false, pageNo = 0, shown = [];
        var selfUrl = <?php echo json_encode($self); ?>;

        // ---- Background of the fixed header / columns: follows light and dark themes ----
        // Re-checked when stylesheets change (e.g. the light / dark mode button) or the device setting changes.
        function updateBg() {
            var el = page.parentNode, bg = '';
            while (el && el.nodeType === 1) {
                bg = window.getComputedStyle(el).backgroundColor;
                if (bg && bg !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(bg)) { break; }
                bg = '';
                el = el.parentNode;
            }
            page.style.setProperty('--gp-bg', bg || '#fff');
        }
        var bgTimer = null;
        function updateBgSoon() {
            clearTimeout(bgTimer);
            bgTimer = setTimeout(updateBg, 50);
        }
        updateBg();
        window.addEventListener('load', updateBg);
        document.addEventListener('load', function (e) {
            if (e.target && e.target.tagName === 'LINK') { updateBgSoon(); }
        }, true);
        if (window.MutationObserver) {
            var mo = new MutationObserver(updateBgSoon);
            mo.observe(document.head, { childList: true, subtree: true, attributes: true, attributeFilter: ['href', 'media', 'disabled'] });
            mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'style', 'data-theme'] });
            if (document.body) { mo.observe(document.body, { attributes: true, attributeFilter: ['class', 'style'] }); }
        }
        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            if (mq.addEventListener) { mq.addEventListener('change', updateBgSoon); }
        }

        // ---- Model ----
        var FIRST = 2; // cells before the group columns: permission, code
        var groups = [].slice.call(table.querySelectorAll('tr.gp-head th.gp-gh')).map(function (th, i) {
            return { i: i, th: th, ref: th.getAttribute('data-g'), parent: th.getAttribute('data-parent'), name: th.getAttribute('data-name'),
                     inh: th.hasAttribute('data-inh'), own: th.hasAttribute('data-own'), label: th.getAttribute('title'),
                     disp: (th.querySelector('b') || th).textContent.trim() };
        });
        var byCode = {}, byTr = new Map(), isPrereq = {};
        function rowText(r) {
            return ((r.desc ? r.desc.value : (r.tr.cells[0].textContent || '')) + ' ' + r.code).toLowerCase();
        }
        function makeRow(tr) {
            var cells = [].slice.call(tr.cells, FIRST, FIRST + groups.length);
            var r = { tr: tr, code: tr.getAttribute('data-code'), rev: tr.getAttribute('data-rev') === '1',
                      custom: tr.hasAttribute('data-custom'), saved: tr.getAttribute('data-saved') === '1',
                      req: (tr.getAttribute('data-req') || '').split(',').filter(Boolean),
                      imp: (tr.getAttribute('data-imp') || '').split(',').filter(Boolean),
                      boxes: cells.map(function (td) { return td.firstElementChild; }),
                      desc: tr.querySelector('.gp-cdesc') };
            // Initial state = what is stored (data-o is set where a loaded version differs)
            r.init = r.boxes.map(function (b) {
                var checked = b.hasAttribute('data-o') ? b.getAttribute('data-o') === '1' : b.checked;
                return r.rev ? !checked : checked;
            });
            r.text = rowText(r);
            byCode[r.code] = r;
            byTr.set(tr, r);
            r.req.concat(r.imp).forEach(function (q) { isPrereq[q.replace(/^!/, '')] = true; });
            return r;
        }
        var rows = [].slice.call(table.querySelectorAll('tr.gp-row')).map(makeRow);
        function has(r, gi) { return r.rev ? !r.boxes[gi].checked : r.boxes[gi].checked; }
        function hasCode(code, gi) { var r = byCode[code]; return r ? has(r, gi) : false; }
        function applies(r, gi) {
            return r.req.every(function (q) { return q.charAt(0) === '!' ? !hasCode(q.slice(1), gi) : hasCode(q, gi); });
        }
        // Cells whose prerequisites are not met, or that are already included in another
        // permission (e.g. "a"), are greyed out (their value is kept)
        function refreshReq(gi) {
            rows.forEach(function (r) {
                if (!r.req.length && !r.imp.length) { return; }
                var ok = applies(r, gi), b = r.boxes[gi], td = b.parentNode;
                var inc = r.imp.filter(function (c) { return hasCode(c, gi); });
                td.classList.toggle('gp-na', !ok || inc.length > 0);
                td.title = !ok ? 'Does not apply: needs ' + r.req.map(function (q) { return q.charAt(0) === '!' ? 'no ' + q.slice(1) : q; }).join(' + ')
                    : inc.length ? 'Already included in permission ' + inc.join(' + ') : '';
                if (!groups[gi].inh) { b.disabled = !ok || inc.length > 0; }
            });
        }
        function markCell(r, gi) { r.boxes[gi].parentNode.classList.toggle('gp-chg', has(r, gi) !== r.init[gi]); }

        // ---- Custom permission rows ----
        var CODE_RE = /^[^\s,'"<>]{1,100}$/;
        var csec = table.querySelector('tbody.gp-csec'), caddTr = table.querySelector('tr.gp-cadd');
        // code => description of all custom rows that are kept (sent with "Save")
        function customDefs() {
            var d = {};
            rows.forEach(function (r) { if (r.custom && !r.removed) { d[r.code] = r.desc.value.trim(); } });
            return d;
        }
        // Rows / descriptions that differ from what is saved
        function defChanges() {
            var n = 0;
            rows.forEach(function (r) {
                if (!r.custom) { return; }
                if (r.added ? !r.removed : (r.removed || r.desc.value.trim() !== r.desc.getAttribute('data-o'))) { n++; }
            });
            return n;
        }
        function markDesc(r) { if (r.desc) { r.desc.classList.toggle('gp-dchg', !r.added && r.desc.value.trim() !== r.desc.getAttribute('data-o')); } }
        function caddMsg(text, err) { var m = $('gp-cadd-msg'); m.textContent = text; m.classList.toggle('gp-err', !!err); }
        function addCustomRow(code, desc) {
            var tr = document.createElement('tr');
            tr.className = 'gp-row gp-crow';
            tr.setAttribute('data-code', code);
            tr.setAttribute('data-custom', '1');
            tr.setAttribute('data-saved', '0');
            var th = document.createElement('th');
            th.className = 'gp-label';
            th.innerHTML = '<span class="gp-cline"><input type="text" class="gp-cdesc" placeholder="Description" maxlength="200">'
                + '<button type="button" class="gp-link gp-cdel" title="Remove this row">remove</button></span>';
            th.querySelector('input').value = desc;
            th.querySelector('input').setAttribute('data-o', desc);
            tr.appendChild(th);
            var tdc = document.createElement('td');
            tdc.className = 'gp-code';
            tdc.innerHTML = '<code></code>';
            tdc.firstChild.textContent = code;
            tr.appendChild(tdc);
            groups.forEach(function (g) {
                var td = document.createElement('td'), cb = document.createElement('input');
                cb.type = 'checkbox';
                if (g.inh) { td.className = 'gp-inh'; cb.disabled = true; }
                if (shown.indexOf(g.i) === -1) { td.classList.add('gp-hide'); }
                td.appendChild(cb);
                tr.appendChild(td);
            });
            var act = document.createElement('td');
            act.className = 'gp-act';
            act.innerHTML = '<button type="button" class="gp-all" title="Tick for all shown groups">All</button><button type="button" class="gp-none" title="Untick for all shown groups">None</button>';
            tr.appendChild(act);
            csec.insertBefore(tr, caddTr);
            var r = makeRow(tr);
            r.added = true;
            rows.push(r);
            bodyRows.push(tr);
            return r;
        }
        $('gp-cadd').addEventListener('click', function () {
            var code = $('gp-new-code').value.trim(), desc = $('gp-new-desc').value.trim(), ex = byCode[code];
            if (!code) { caddMsg('Enter a code.', true); $('gp-new-code').focus(); return; }
            if (!CODE_RE.test(code)) { caddMsg('A code cannot contain spaces, commas, quotes or < >.', true); return; }
            if (ex && !(ex.custom && ex.removed)) {
                caddMsg(ex.custom ? 'This code already has a row.' : 'This code is already in the table (' + (ex.tr.cells[0].textContent || '').trim() + ').', true);
                return;
            }
            if (ex) {
                ex.removed = false;
                ex.tr.classList.remove('gp-removed');
                if (desc) { ex.desc.value = desc; }
                markDesc(ex);
            } else {
                addCustomRow(code, desc);
            }
            $('gp-new-code').value = '';
            $('gp-new-desc').value = '';
            caddMsg('Row "' + code + '" added - tick the groups, then save.', false);
            updateCount();
            renderRows();
        });
        [$('gp-new-code'), $('gp-new-desc')].forEach(function (inp) {
            inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('gp-cadd').click(); } });
        });
        // description edits and "remove"
        table.addEventListener('input', function (e) {
            var t = e.target;
            if (!t.classList || !t.classList.contains('gp-cdesc')) { return; }
            var r = byTr.get(t.closest('tr'));
            if (r) { r.text = rowText(r); markDesc(r); updateCount(); }
        });
        table.addEventListener('click', function (e) {
            var del = e.target.closest ? e.target.closest('.gp-cdel') : null;
            if (!del) { return; }
            var r = byTr.get(del.closest('tr'));
            if (!r) { return; }
            var used = groups.some(function (g) { return !g.inh && has(r, g.i); });
            if (used && !confirm('Remove the row "' + r.code + '"? The code is unticked in all groups (when you save).')) { return; }
            groups.forEach(function (g) { if (!g.inh && r.boxes[g.i].checked) { r.boxes[g.i].checked = false; markCell(r, g.i); } });
            r.removed = true;
            r.tr.classList.add('gp-removed');
            updateCount();
            renderRows();
        });

        // ---- Changes ----
        function collect() {
            var out = {}, cells = 0;
            groups.forEach(function (g) {
                if (g.inh) { return; }
                var add = [], remove = [], cadd = [], cremove = [];
                rows.forEach(function (r) {
                    var now = has(r, g.i);
                    if (now !== r.init[g.i]) { (now ? (r.custom ? cadd : add) : (r.custom ? cremove : remove)).push(r.code); }
                });
                var n = add.length + remove.length + cadd.length + cremove.length;
                if (n) {
                    out[g.ref] = { add: add, remove: remove, custom_add: cadd, custom_remove: cremove };
                    cells += n;
                }
            });
            var defs = defChanges();
            return { data: out, cells: cells, groups: Object.keys(out).length, defs: defs, any: cells + defs > 0 };
        }
        function updateCount() {
            var c = collect();
            var parts = [];
            if (c.cells) { parts.push(c.cells + ' change(s) in ' + c.groups + ' group(s)'); }
            if (c.defs) { parts.push(c.defs + ' custom row change(s)'); }
            countEl.textContent = parts.length ? parts.join(', ') + ' - not saved yet.' : 'No changes.';
            countEl.classList.toggle('gp-has', c.any);
            saveBtn.disabled = discardBtn.disabled = !c.any;
            return c;
        }
        function changed(r, gi) {
            markCell(r, gi);
            if (isPrereq[r.code]) { refreshReq(gi); }
        }

        table.addEventListener('change', function (e) {
            var b = e.target;
            if (!b || b.type !== 'checkbox' || !b.closest('tr.gp-row')) { return; }
            var r = byTr.get(b.closest('tr')), gi = b.parentNode.cellIndex - FIRST;
            if (r) { changed(r, gi); updateCount(); }
        });

        // Section headers (click or Enter / Space) and the All / None buttons
        function toggleSection(btn) {
            var tb = btn.closest('tbody');
            tb.classList.toggle('gp-collapsed');
            btn.setAttribute('aria-expanded', tb.classList.contains('gp-collapsed') ? 'false' : 'true');
            syncSections();
        }
        table.addEventListener('click', function (e) {
            var sh = e.target.closest ? e.target.closest('.gp-sh-btn') : null;
            if (sh) { toggleSection(sh); return; }
            var btn = e.target.closest ? e.target.closest('button.gp-all, button.gp-none') : null;
            if (!btn) { return; }
            var r = byTr.get(btn.closest('tr')), val = btn.classList.contains('gp-all');
            if (!r) { return; }
            shown.forEach(function (gi) {
                var b = r.boxes[gi];
                if (!b.disabled && b.checked !== val) { b.checked = val; changed(r, gi); }
            });
            updateCount();
        });
        table.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('gp-sh-btn')) {
                e.preventDefault();
                toggleSection(e.target);
            }
        });

        // ---- Columns: search + pages ----
        function filteredGroups() {
            var q = gq.value.trim().toLowerCase().replace(/^#/, ''), p = parentSel.value;
            return groups.filter(function (g) {
                if (q && g.name.indexOf(q) === -1 && g.ref !== q) { return false; }
                if (p === 'top') { return g.parent === '0' || !groups.some(function (x) { return x.ref === g.parent; }); }
                if (p !== '') { return g.parent === p || g.ref === p; }
                return true;
            });
        }
        var bodyRows = [].slice.call(table.querySelectorAll('tbody tr')).filter(function (tr) { return !tr.classList.contains('gp-sh') && !tr.classList.contains('gp-cadd'); });
        var spanCells = [].slice.call(table.querySelectorAll('tr.gp-sh > td'));
        function renderColumns() {
            var list = filteredGroups();
            var size = parseInt(sizeSel.value, 10) || 10;
            var pages = Math.max(1, Math.ceil(list.length / size));
            pageNo = Math.min(Math.max(pageNo, 0), pages - 1);
            var part = list.slice(pageNo * size, pageNo * size + size), vis = {};
            shown = part.map(function (g) { vis[g.i] = true; return g.i; });
            groups.forEach(function (g) { g.th.classList.toggle('gp-hide', !vis[g.i]); });
            bodyRows.forEach(function (tr) {
                for (var i = 0; i < groups.length; i++) { tr.cells[FIRST + i].classList.toggle('gp-hide', !vis[i]); }
            });
            // colspans follow the number of shown columns
            spanCells.forEach(function (td) { td.colSpan = shown.length + 3; });
            var caddCell = table.querySelector('.gp-cadd-cell');
            if (caddCell) { caddCell.colSpan = Math.max(shown.length, 1); }
            // page list: "1-10", "11-20", ...
            pageSel.innerHTML = '';
            for (var p = 0; p < pages; p++) {
                var cnt = Math.min(size, list.length - p * size), o = document.createElement('option');
                o.value = p;
                o.textContent = list.length ? (p * size + 1) + '-' + (p * size + cnt) : '-';
                pageSel.appendChild(o);
            }
            pageSel.value = String(pageNo);
            pageSel.disabled = pages < 2;
            $('gp-prev').disabled = pageNo === 0;
            $('gp-next').disabled = pageNo >= pages - 1;
            $('gp-gstat').textContent = list.length
                ? '- showing ' + (pageNo * size + 1) + '-' + (pageNo * size + part.length) + ' of ' + list.length + (list.length < groups.length ? ' matching (' + groups.length + ' groups)' : ' groups')
                : '- no matching groups';
            renderRows();
        }

        // ---- Rows: search ----
        function renderRows() {
            var q = pq.value.trim().toLowerCase(), onlyDiff = diffBox.checked, onlyChg = chgBox.checked;
            table.classList.toggle('gp-filtering', !!(q || onlyDiff || onlyChg));
            rows.forEach(function (r) {
                var show = !q || r.text.indexOf(q) !== -1;
                if (show && onlyDiff) {
                    var seen = {};
                    shown.forEach(function (gi) { seen[has(r, gi) ? 1 : 0] = true; });
                    show = Object.keys(seen).length > 1;
                }
                if (show && onlyChg) {
                    show = r.boxes.some(function (b, gi) { return has(r, gi) !== r.init[gi]; });
                }
                r.tr.classList.toggle('gp-hide', !show);
            });
            var live = rows.filter(function (r) { return !r.removed; });
            var vis = live.filter(function (r) { return !r.tr.classList.contains('gp-hide'); }).length;
            $('gp-qstat').textContent = vis === live.length ? '- all ' + live.length + ' shown' : '- ' + vis + ' of ' + live.length + ' shown';
            [].forEach.call(table.tBodies, function (tb) {
                var all = tb.querySelectorAll('tr.gp-row:not(.gp-removed)');
                var n = tb.querySelectorAll('tr.gp-row:not(.gp-hide):not(.gp-removed)').length;
                var nEl = tb.querySelector('.gp-n');
                if (tb.classList.contains('gp-csec')) { // always shown: holds the "add" row
                    if (nEl) { nEl.textContent = n === all.length ? n : n + ' of ' + all.length; }
                    return;
                }
                if (!all.length) { return; }
                tb.classList.toggle('gp-hide', n === 0);
                if (nEl) { nEl.textContent = n === all.length ? n : n + ' of ' + all.length; }
            });
        }

        // ---- Controls ----
        var savedSize = '10';
        try { savedSize = window.localStorage.getItem('permission_group_manager_size') || '10'; } catch (e) { /* storage not available */ }
        sizeSel.value = savedSize;
        if (sizeSel.value !== savedSize) { sizeSel.value = '10'; }
        sizeSel.addEventListener('change', function () {
            try { window.localStorage.setItem('permission_group_manager_size', sizeSel.value); } catch (e) { /* ignore */ }
            pageNo = 0;
            renderColumns();
        });
        parentSel.addEventListener('change', function () { pageNo = 0; renderColumns(); });
        pageSel.addEventListener('change', function () { pageNo = parseInt(pageSel.value, 10) || 0; renderColumns(); });
        $('gp-prev').addEventListener('click', function () { pageNo--; renderColumns(); });
        $('gp-next').addEventListener('click', function () { pageNo++; renderColumns(); });
        diffBox.addEventListener('change', renderRows);
        chgBox.addEventListener('change', renderRows);
        // Text fields: filter while typing, small "x" (and Esc) clears the field
        function textFilter(inp, run) {
            var x = document.querySelector('.gp-x[data-for="' + inp.id + '"]');
            function upd() { if (x) { x.hidden = inp.value === ''; } run(); }
            inp.addEventListener('input', upd);
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); }
                if (e.key === 'Escape' && inp.value !== '') { e.preventDefault(); inp.value = ''; upd(); }
            });
            if (x) { x.addEventListener('click', function () { inp.value = ''; upd(); inp.focus(); }); }
        }
        textFilter(pq, renderRows);
        textFilter(gq, function () { pageNo = 0; renderColumns(); });
        // "Sections: open all / close all" - ticked when all sections are open / closed
        function syncSections() {
            var tbs = [].slice.call(table.tBodies);
            openAll.checked = tbs.every(function (tb) { return !tb.classList.contains('gp-collapsed'); });
            closeAll.checked = tbs.every(function (tb) { return tb.classList.contains('gp-collapsed'); });
        }
        function setAllSections(open) {
            [].forEach.call(table.tBodies, function (tb) {
                tb.classList.toggle('gp-collapsed', !open);
                var b = tb.querySelector('.gp-sh-btn');
                if (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); }
            });
            syncSections();
        }
        openAll.addEventListener('change', function () { if (openAll.checked) { setAllSections(true); } else { syncSections(); } });
        closeAll.addEventListener('change', function () { if (closeAll.checked) { setAllSections(false); } else { syncSections(); } });

        $('gp-copy').addEventListener('click', function () {
            var from = groups.filter(function (g) { return g.ref === $('gp-copy-from').value; })[0];
            var to = groups.filter(function (g) { return g.ref === $('gp-copy-to').value; })[0];
            if (!from || !to || from === to) { alert('Choose two different groups.'); return; }
            if (!confirm('Replace the permissions of "' + to.label + '" in the table with those of "' + from.label + '"? (Not saved until you click Save changes.)')) { return; }
            rows.forEach(function (r) { if (!r.removed) { r.boxes[to.i].checked = r.boxes[from.i].checked; markCell(r, to.i); } });
            refreshReq(to.i);
            updateCount();
            renderRows();
        });

        discardBtn.addEventListener('click', function () {
            if (!confirm('Discard all changes that are not saved?')) { return; }
            rows = rows.filter(function (r) {
                if (r.added) { // rows added on the page are dropped again
                    r.tr.parentNode.removeChild(r.tr);
                    delete byCode[r.code];
                    bodyRows.splice(bodyRows.indexOf(r.tr), 1);
                    return false;
                }
                r.boxes.forEach(function (b, gi) { b.checked = r.rev ? !r.init[gi] : r.init[gi]; markCell(r, gi); });
                if (r.custom) {
                    r.removed = false;
                    r.tr.classList.remove('gp-removed');
                    r.desc.value = r.desc.getAttribute('data-o');
                    r.text = rowText(r);
                    markDesc(r);
                }
                return true;
            });
            caddMsg('', false);
            groups.forEach(function (g) { refreshReq(g.i); });
            updateCount();
            renderRows();
        });

        $('gp-form').addEventListener('submit', function (e) {
            var act = (e.submitter && e.submitter.value) || 'save';
            var c = collect();
            if (act === 'save' && !c.any) { e.preventDefault(); return; }
            var own = groups.filter(function (g) { return g.own; })[0];
            if (own && c.data[own.ref] && c.data[own.ref].remove.some(function (x) { return x === 't' || x === 'a'; })) {
                alert('You cannot remove Admin / System setup access from your own user group.');
                e.preventDefault();
                return;
            }
            var q = act === 'newversion'
                ? (c.any ? 'Save the changes and store the result as a new version?' : 'Store the current permissions as a new version?')
                : 'Save ' + countEl.textContent.replace(/ - not saved yet\.$/, '') + '?';
            if (!confirm(q)) { e.preventDefault(); return; }
            $('gp-changes').value = c.cells ? JSON.stringify(c.data) : '';
            $('gp-cdefs').value = c.defs ? JSON.stringify(customDefs()) : '';
            leaving = true;
        });

        // ---- Version history ----
        var vsel = $('gp-version');
        if (vsel) {
            $('gp-vload').addEventListener('click', function () {
                if (collect().any && !confirm('You have unsaved changes. Replace them with the selected version?')) { return; }
                leaving = true;
                window.location.href = selfUrl + '?version=' + encodeURIComponent(vsel.value);
            });
            $('gp-vform').addEventListener('submit', function (e) {
                var act = (e.submitter && e.submitter.value) || '';
                var opt = vsel.selectedOptions[0], label = opt ? opt.textContent : '';
                var q = act === 'restore_version' ? 'Restore "' + label + '" for all user groups now? The current permissions are kept in the history.'
                    : act === 'delete_version' ? 'Delete ALL stored files of version v' + (opt ? opt.getAttribute('data-num') : '') + '? This cannot be undone.'
                    : 'Delete the whole version history except the newest entry? This cannot be undone.';
                if (!confirm(q)) { e.preventDefault(); return; }
                leaving = true;
            });
        }

        window.addEventListener('beforeunload', function (e) {
            if (!leaving && collect().any) { e.preventDefault(); e.returnValue = ''; }
        });

        groups.forEach(function (g) { refreshReq(g.i); markCellsInit(g.i); });
        function markCellsInit(gi) { rows.forEach(function (r) { markCell(r, gi); }); }
        renderColumns();
        syncSections();
        updateCount();
        var resetForm = document.getElementById('gp-reset');
        if (resetForm) {
            resetForm.addEventListener('submit', function (e) {
                if (resetForm.reset_confirm.value.trim() !== 'RESET') { alert('Type RESET to confirm.'); e.preventDefault(); return; }
                if (!confirm('Delete the version history and the custom permission descriptions of Permission Group Manager? The group permissions stay as they are.')) { e.preventDefault(); return; }
                leaving = true;
            });
        }
    })();
    </script>
    <?php } ?>
</div>
<?php
include '../../../include/footer.php';

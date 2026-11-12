<?php
include '../../../include/db.php';
include '../../../include/authenticate.php';
require_once dirname(__FILE__, 2) . '/include/transfer.php';

// Admins only
if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

$self        = $_SERVER['PHP_SELF'];
$setup_url   = 'setup.php'; // same folder (works also in filestore/plugins)
$plugins_url = $baseurl_short . 'pages/team/team_plugins.php';
$own_group   = (int) ($usergroup ?? 0);
$user        = (int) ($userref ?? 0);
$action      = (string) ($_POST['action'] ?? '');
$message     = '';
$errors      = array();
$token       = (string) ($_GET['import'] ?? $_POST['token'] ?? '');

permission_group_manager_import_cleanup();

// ---- Handle form actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {
    if (function_exists('enforcePostRequest') && !enforcePostRequest(false)) {
        $errors[] = 'The request could not be verified. Reload the page and try again.';
    } elseif ($action === 'export') {
        $refs = array_map('intval', (array) ($_POST['export'] ?? array()));
        if (!$refs) {
            $errors[] = 'Select at least one user group to export.';
        } else {
            $json = json_encode(permission_group_manager_export_data($refs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="permission_group_manager-groups-' . date('Ymd-His') . '.json"');
            header('Content-Length: ' . strlen($json));
            header('Cache-Control: no-store');
            echo $json;
            exit;
        }
    } elseif ($action === 'upload') {
        $f = $_FILES['import_file'] ?? null;
        if (!is_array($f) || !isset($f['error']) || is_array($f['error']) || (int) $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $errors[] = 'Choose an export file (.json) to import.';
        } elseif ((int) $f['size'] > PERMISSION_GROUP_MANAGER_IMPORT_MAX) {
            $errors[] = 'The file is too large (max. 2 MB).';
        } else {
            $notes  = array();
            $perr   = '';
            $groups = permission_group_manager_import_parse(file_get_contents($f['tmp_name']), $perr, $notes);
            if ($groups === null) {
                $errors[] = $perr;
            } else {
                $token = permission_group_manager_import_save($groups, $notes, $user, (string) $f['name']);
                if ($token === '') {
                    $errors[] = 'The file could not be kept for the next step (temporary folder ' . permission_group_manager_import_dir() . ' not writable).';
                } else {
                    redirect($self . '?import=' . $token);
                    exit;
                }
            }
        }
    } elseif ($action === 'import_cancel') {
        permission_group_manager_import_drop($token);
        redirect($self . '?done=cancelled');
        exit;
    } elseif ($action === 'import_apply') {
        $pending = permission_group_manager_import_get($token, $user);
        if ($pending === null) {
            $errors[] = 'The import has expired or was already done. Upload the file again.';
        } elseif (permission_group_manager_snapshot() === false) {
            $errors[] = 'The current permissions could not be stored in the version history (check write access to ' . permission_group_manager_versions_dir() . '); nothing was imported.';
        } else {
            $choices = array(
                'new'    => array_map('intval', (array) ($_POST['new'] ?? array())),
                'mode'   => (array) ($_POST['mode'] ?? array()),
                'custom' => (array) ($_POST['custom'] ?? array()),
            );
            list($created, $updated) = permission_group_manager_import_apply($pending['groups'], $choices, $own_group, $errors);
            permission_group_manager_import_drop($token);
            $msg = 'Import finished: ' . $created . ' group(s) created, ' . $updated . ' group(s) updated. The permissions before the import are in the version history.';
            if (!$errors) {
                redirect($self . '?done=imported&c=' . $created . '&u=' . $updated);
                exit;
            }
            $message = $msg;
            $token   = '';
        }
    }
}

switch ((string) ($_GET['done'] ?? '')) {
    case 'imported':
        $message = 'Import finished: ' . (int) ($_GET['c'] ?? 0) . ' group(s) created, ' . (int) ($_GET['u'] ?? 0)
            . ' group(s) updated. The permissions before the import are in the version history of the plugin.';
        break;
    case 'cancelled':
        $message = 'Import cancelled. Nothing was changed.';
        break;
}

// ---- Prepare the page ----
$pending = $token !== '' ? permission_group_manager_import_get($token, $user) : null;
if ($token !== '' && $pending === null && !$errors && $message === '') {
    $errors[] = 'The import has expired or was already done. Upload the file again.';
}
$plan   = $pending !== null ? permission_group_manager_import_plan($pending['groups']) : null;
$labels = $plan !== null ? permission_group_manager_code_labels() : array();
$groups = permission_group_manager_raw_groups();
$h      = function ($s) { return htmlspecialchars((string) $s); };
$nonce  = isset($CSP_nonce) ? ' nonce="' . htmlspecialchars($CSP_nonce) . '"' : '';

include '../../../include/header.php';
?>
<div class="BasicsBox gt-page">
    <h1>Permission Group Manager &ndash; export / import</h1>
    <?php
    renderBreadcrumbs([
        ['title' => $lang['systemsetup'],   'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
        ['title' => $lang['pluginmanager'], 'href' => $plugins_url],
        ['title' => 'Permission Group Manager',    'href' => $setup_url],
        ['title' => 'Export / import'],
    ]);
    ?>

    <?php if ($message) { ?><div class="PageInformal"><?php echo $h($message); ?></div><?php } ?>
    <?php foreach ($errors as $e) { ?><div class="FormError"><?php echo $h($e); ?></div><?php } ?>

    <style<?php echo $nonce; ?>>
        .gt-box { margin: 14px 0; padding: 10px 14px; border: 1px solid rgba(128, 128, 128, 0.45); border-radius: 6px; }
        .gt-box h3 { margin: 0 0 8px; font-size: 1.05em; }
        .gt-box p { margin: 0.5em 0; }
        .gt-hint { opacity: 0.8; font-size: 0.9em; }
        .gt-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0.6em 0; }
        .gt-list { columns: 3 220px; margin: 8px 0; }
        .gt-list label { display: block; break-inside: avoid; padding: 2px 0; }
        .gt-table { border-collapse: collapse; width: 100%; margin: 6px 0; }
        .gt-table th, .gt-table td { text-align: left; padding: 4px 8px; border-bottom: 1px solid rgba(128, 128, 128, 0.3); vertical-align: top; }
        .gt-table th { font-weight: bold; }
        .gt-table td.gt-c { text-align: center; width: 90px; }
        .gt-table code { font-size: 0.9em; }
        .gt-diff td { background-image: linear-gradient(rgba(255, 179, 0, 0.18), rgba(255, 179, 0, 0.18)); }
        .gt-diff td.gt-res { box-shadow: inset 0 0 0 2px #e69500; }
        .gt-yes { color: #2e7d32; font-weight: bold; }
        .gt-no { opacity: 0.45; }
        .gt-rev { color: #b26a00; cursor: help; margin-left: 4px; }
        .gt-group { margin: 12px 0; padding: 8px 12px; border: 1px solid rgba(128, 128, 128, 0.35); border-radius: 6px; }
        .gt-group h4 { margin: 0 0 6px; }
        .gt-modes label { display: block; margin: 3px 0; }
        .gt-detail[hidden] { display: none; }
        .gt-link { background: none; border: 0; padding: 0; margin: 0; color: inherit; text-decoration: underline; cursor: pointer; font: inherit; font-size: 0.9em; box-shadow: none; min-width: 0; }
        .gt-warn { color: #b26a00; }
    </style>

    <p><a href="<?php echo $h($setup_url); ?>">&larr; Back to the permission table</a></p>

<?php if ($plan !== null) { ?>
    <!-- ================= Import: review ================= -->
    <form method="post" action="<?php echo $h($self); ?>" id="gt-import">
        <?php generateFormToken('permission_group_manager'); ?>
        <input type="hidden" name="token" value="<?php echo $h($token); ?>">

        <div class="gt-box">
            <h3>Import from <?php echo $h($pending['file']); ?></h3>
            <p><?php echo count($pending['groups']); ?> group(s) in the file:
                <?php echo count($plan['new']); ?> new, <?php echo count($plan['diff']); ?> with different permissions,
                <?php echo count($plan['same']); ?> identical. Groups are matched by name.</p>
            <?php foreach ((array) ($pending['notes'] ?? array()) as $n) { ?><p class="gt-warn"><?php echo $h($n); ?></p><?php } ?>
        </div>

        <!-- New groups -->
        <div class="gt-box">
            <h3>New groups (not in the system)</h3>
            <?php if (!$plan['new']) { ?>
                <p><i>None.</i></p>
            <?php } else { ?>
                <p><?php echo count($plan['new']) === 1 ? 'This group does not exist in the system. Add it?' : 'These groups do not exist in the system. Choose which ones to add:'; ?></p>
                <?php if (count($plan['new']) > 1) { ?>
                    <p><button type="button" class="gt-link" id="gt-new-all">select all</button> &middot; <button type="button" class="gt-link" id="gt-new-none">select none</button></p>
                <?php } ?>
                <table class="gt-table">
                    <tr><th>Add</th><th>Group</th><th>Parent</th><th>Permissions</th></tr>
                    <?php foreach ($plan['new'] as $i => $g) { ?>
                        <tr>
                            <td class="gt-c"><input type="checkbox" name="new[]" value="<?php echo (int) $i; ?>" class="gt-new" checked aria-label="Add <?php echo $h($g['name']); ?>"></td>
                            <td><b><?php echo $h(permission_group_manager_tr($g['name'])); ?></b></td>
                            <td><?php echo $h($g['parent_info']); ?><?php echo $g['inherit_flags'] ? '<br><span class="gt-hint">inherits: ' . $h(implode(', ', $g['inherit_flags'])) . '</span>' : ''; ?></td>
                            <td><?php echo count($g['permissions']); ?> <button type="button" class="gt-link gt-show" data-target="gt-np-<?php echo (int) $i; ?>">show</button>
                                <div class="gt-detail gt-hint" id="gt-np-<?php echo (int) $i; ?>" hidden><code><?php echo $h(implode(', ', $g['permissions'])); ?></code></div></td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } ?>
        </div>

        <!-- Existing groups with different permissions -->
        <div class="gt-box">
            <h3>Existing groups with different permissions</h3>
            <?php if (!$plan['diff']) { ?>
                <p><i>None.</i></p>
            <?php } else { ?>
                <p>Choose for each group which permissions it should have after the import.</p>
                <?php foreach ($plan['diff'] as $i => $g) {
                    $rows   = permission_group_manager_diff_rows($g['current'], $g['permissions'], $labels);
                    $differ = count(array_filter($rows, function ($r) { return $r['system'] !== $r['import']; })); ?>
                    <div class="gt-group">
                        <h4><?php echo $h(permission_group_manager_tr($g['name'])); ?> <span class="gt-hint">#<?php echo (int) $g['ref']; ?> &ndash; <?php echo (int) $differ; ?> difference(s)</span></h4>
                        <?php if ($g['inherits']) { ?><p class="gt-warn">This group inherits its permissions from its parent group - its own permissions are stored, but not used while inheritance is on.</p><?php } ?>
                        <?php if ((int) $g['ref'] === $own_group) { ?><p class="gt-warn">This is your own user group: Admin (t) and System setup (a) access are always kept.</p><?php } ?>
                        <div class="gt-modes">
                            <label><input type="radio" name="mode[<?php echo (int) $i; ?>]" value="keep" checked data-group="<?php echo (int) $i; ?>"> Keep the existing permissions</label>
                            <label><input type="radio" name="mode[<?php echo (int) $i; ?>]" value="import" data-group="<?php echo (int) $i; ?>"> Use the permissions from the import</label>
                            <label><input type="radio" name="mode[<?php echo (int) $i; ?>]" value="custom" data-group="<?php echo (int) $i; ?>"> Show the differences and choose</label>
                        </div>
                        <div class="gt-detail" id="gt-diff-<?php echo (int) $i; ?>" hidden>
                            <p class="gt-hint">"Result" = the permissions the group gets. It is ticked where the system and the import agree.
                                Rows with a difference are highlighted and start unticked (no permission) - tick them to give the permission.
                                <button type="button" class="gt-link gt-res-set" data-group="<?php echo (int) $i; ?>" data-from="import">use all from import</button> &middot;
                                <button type="button" class="gt-link gt-res-set" data-group="<?php echo (int) $i; ?>" data-from="system">use all from system</button> &middot;
                                <button type="button" class="gt-link gt-diff-only" data-group="<?php echo (int) $i; ?>">show only differences</button></p>
                            <table class="gt-table" id="gt-tab-<?php echo (int) $i; ?>">
                                <tr><th>Permission</th><th>Code</th><th class="gt-c">In the system</th><th class="gt-c">In the import</th><th class="gt-c">Result</th></tr>
                                <?php foreach ($rows as $r) {
                                    $diff = $r['system'] !== $r['import']; ?>
                                    <tr class="<?php echo $diff ? 'gt-diff' : 'gt-same'; ?>" data-system="<?php echo $r['system'] ? 1 : 0; ?>" data-import="<?php echo $r['import'] ? 1 : 0; ?>">
                                        <td><?php echo $h($r['label']); ?><?php echo $r['reverse'] ? '<span class="gt-rev" title="Reverse option in ResourceSpace: the code switches the option OFF">&#8634;</span>' : ''; ?></td>
                                        <td><code><?php echo $h($r['code']); ?></code></td>
                                        <td class="gt-c"><?php echo $r['system'] ? '<span class="gt-yes">&#10003;</span>' : '<span class="gt-no">&ndash;</span>'; ?></td>
                                        <td class="gt-c"><?php echo $r['import'] ? '<span class="gt-yes">&#10003;</span>' : '<span class="gt-no">&ndash;</span>'; ?></td>
                                        <td class="gt-c gt-res"><input type="checkbox" name="custom[<?php echo (int) $i; ?>][]" value="<?php echo $h($r['code']); ?>"<?php echo (!$diff && $r['system']) ? ' checked' : ''; ?> aria-label="Result <?php echo $h($r['code']); ?>"></td>
                                    </tr>
                                <?php } ?>
                            </table>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>

        <!-- Identical groups -->
        <div class="gt-box">
            <h3>Existing groups with the same permissions</h3>
            <?php if (!$plan['same']) { ?>
                <p><i>None.</i></p>
            <?php } else { ?>
                <p>No change needed: <?php echo $h(implode(', ', array_map(function ($g) { return permission_group_manager_tr($g['name']); }, $plan['same']))); ?>.</p>
            <?php } ?>
        </div>

        <div class="gt-row">
            <button type="submit" name="action" value="import_apply" id="gt-apply">Import</button>
            <button type="submit" name="action" value="import_cancel" formnovalidate>Cancel</button>
        </div>
        <p class="gt-hint">The permissions of all groups are stored in the version history of the plugin before the import ("Restore this version" on the main page brings them back; groups created by the import are not deleted by a restore).</p>
    </form>

<?php } else { ?>
    <!-- ================= Export ================= -->
    <div class="gt-box">
        <h3>Export user groups</h3>
        <p>Saves the selected groups to a JSON file: name, parent group, inherited settings and permissions.
            The file can be imported here on another ResourceSpace installation (groups are matched by name).</p>
        <form method="post" action="<?php echo $h($self); ?>" id="gt-export">
            <?php generateFormToken('permission_group_manager'); ?>
            <p><button type="button" class="gt-link" id="gt-exp-all">select all</button> &middot; <button type="button" class="gt-link" id="gt-exp-none">select none</button></p>
            <div class="gt-list">
                <?php foreach ($groups as $ref => $g) { ?>
                    <label><input type="checkbox" name="export[]" value="<?php echo (int) $ref; ?>" class="gt-exp" checked> <?php echo $h($g['name']); ?> <span class="gt-hint">#<?php echo (int) $ref; ?></span></label>
                <?php } ?>
            </div>
            <div class="gt-row"><button type="submit" name="action" value="export">Export</button></div>
        </form>
    </div>

    <!-- ================= Import: upload ================= -->
    <div class="gt-box">
        <h3>Import user groups</h3>
        <p>Choose an export file of this plugin. In the next step you see which groups are new and which differ from the groups in the system,
            and choose what to import. Nothing is changed before you confirm.</p>
        <form method="post" action="<?php echo $h($self); ?>" enctype="multipart/form-data">
            <?php generateFormToken('permission_group_manager'); ?>
            <div class="gt-row">
                <input type="file" name="import_file" accept=".json,application/json" required>
                <button type="submit" name="action" value="upload">Check file</button>
            </div>
        </form>
    </div>
<?php } ?>

    <script<?php echo $nonce; ?>>
    (function () {
        function all(sel) { return [].slice.call(document.querySelectorAll(sel)); }
        function on(id, fn) { var el = document.getElementById(id); if (el) { el.addEventListener('click', fn); } }
        on('gt-exp-all', function () { all('.gt-exp').forEach(function (c) { c.checked = true; }); });
        on('gt-exp-none', function () { all('.gt-exp').forEach(function (c) { c.checked = false; }); });
        on('gt-new-all', function () { all('.gt-new').forEach(function (c) { c.checked = true; }); });
        on('gt-new-none', function () { all('.gt-new').forEach(function (c) { c.checked = false; }); });

        // Show / hide permission lists and difference tables
        all('.gt-show').forEach(function (b) {
            b.addEventListener('click', function () {
                var d = document.getElementById(b.getAttribute('data-target'));
                d.hidden = !d.hidden;
                b.textContent = d.hidden ? 'show' : 'hide';
            });
        });
        all('.gt-modes input[type=radio]').forEach(function (r) {
            r.addEventListener('change', function () {
                var d = document.getElementById('gt-diff-' + r.getAttribute('data-group'));
                if (d) { d.hidden = r.value !== 'custom'; }
            });
        });
        all('.gt-res-set').forEach(function (b) {
            b.addEventListener('click', function () {
                var from = b.getAttribute('data-from');
                all('#gt-tab-' + b.getAttribute('data-group') + ' tr[data-system]').forEach(function (tr) {
                    tr.querySelector('input').checked = tr.getAttribute('data-' + from) === '1';
                });
            });
        });
        all('.gt-diff-only').forEach(function (b) {
            b.addEventListener('click', function () {
                var rows = all('#gt-tab-' + b.getAttribute('data-group') + ' tr.gt-same');
                var hide = b.textContent.indexOf('only') !== -1;
                rows.forEach(function (tr) { tr.hidden = hide; });
                b.textContent = hide ? 'show all permissions' : 'show only differences';
            });
        });

        var form = document.getElementById('gt-import');
        if (form) {
            form.addEventListener('submit', function (e) {
                if ((e.submitter && e.submitter.value) !== 'import_apply') { return; }
                var adds = all('.gt-new:checked').length;
                var changes = all('.gt-modes input:checked').filter(function (r) { return r.value !== 'keep'; }).length;
                if (!adds && !changes) {
                    if (!confirm('Nothing is selected for import. Close the import without changes?')) { e.preventDefault(); }
                    return;
                }
                if (!confirm('Import now: ' + adds + ' new group(s), ' + changes + ' existing group(s) changed?')) { e.preventDefault(); }
            });
        }
    })();
    </script>
</div>
<?php
include '../../../include/footer.php';

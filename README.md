# Permission Group Manager – documentation

**English** | [Polski](README.pl.md)

Documentation for plugin version **1.23** (ResourceSpace 11).
*(since X.YY)* marks the plugin version in which a feature first appeared. Features without
a mark have been available since 1.00. Change history (in Polish): [CHANGELOG.md](CHANGELOG.md).

The plugin shows the permissions of **all user groups in one table**. Rows are permissions and
columns are groups. Instead of opening the permission page of each group, you tick boxes in the
table, compare groups, copy permissions between them and return to earlier versions.

**Where:** System > Plugins > Permission Group Manager > **Options**. Only administrators
(permission `a`) can open this page.


**Name** *(since 1.22)*: up to version 1.21 the plugin was called **Group Permissions** (folder
`groupperms`). For ResourceSpace it is a different plugin: install `permission_group_manager`,
activate it, then deactivate and delete `groupperms` (the options page reminds you while it is
still active). On first use the plugin copies the data of the old one (version history and custom
permission descriptions); the old folder `<filestore>/groupperms` is kept. The permissions of the
user groups are stored by ResourceSpace and do not change.

---

## Permission table

- **Rows** use the same sections and order as the group permission page in ResourceSpace 11.
  The labels come from ResourceSpace's translations, so they follow the user's language.
- **Code** *(separate column: since 1.01)* – the permission code stored in the database
  (e.g. `t`, `a`, `e0`).
- **Ticked** means the group has the permission.
- **↺ (reverse options)** – as in ResourceSpace: ticked means the code is **not** set.
- **Greyed cells** do not apply to that group, because they need another permission first
  (e.g. Admin access `t`).
- **Blue frame** means a change that is not saved yet.
- **Groups that inherit permissions** from their parent group are read-only (greyed).
- **Sections** open and close when you click their name *(since 1.01)*. Below the "Permission"
  header there are "Sections: open all / close all" switches *(since 1.09)*.
- **Select > All / None** at the end of a row ticks or unticks the permission for all shown
  groups *(since 1.01; "Select" column since 1.11)*.
- **Group names** in the headers are shortened to 20 characters; the full name is in the tooltip
  *(since 1.11)*.

### Plugin sections *(since 1.03)*

Permissions of active ResourceSpace plugins have their own sections marked "plugin", with the
same codes and conditions as in the plugins themselves:

- Workflow actions (`wf<id>`),
- Brand guidelines (`bgv`, `bge`),
- AI smart search (`clip-sb`, `clip-v`),
- Consent management (`cm`),
- AI faces (`faces-v`),
- License management (`lm`).

### Custom permissions *(since 1.09)*

At the end of the table there are rows for codes outside the catalogue, e.g. permissions of
other plugins or access to featured collection categories (`j<ref>`, `-j<ref>`).

- Each such code is its own row with checkboxes, an **editable description** and a **remove**
  link.
- **To add one:** type a description and a code in the last row, then click **Add permission
  row**.
- **remove** deletes the row and unticks the code in all groups (when you save).
- `j<ref>` / `-j<ref>` codes get a default description with the collection name.
- In versions 1.03–1.08 custom codes were typed into one text field per group.

## Filters above the table

The filters are in two labelled boxes with counters *(since 1.04)*.

**Permissions (table rows)** – which rows are shown:

- **Search permissions** – search by name or code. × or Esc clears the field *(since 1.06)*.
- **Show only: differing** – only rows where the shown groups have different settings
  *(as a checkbox: since 1.07)*.
- **Show only: unsaved** – only rows with unsaved changes *(as a checkbox: since 1.07)*.

**User groups (table columns)** – which groups are shown:

- **Search groups** – search by group name,
- **Parent group** – only the subgroups of the selected group,
- **Columns** – 10, 15 or 20 groups per page *(since 1.11)*,
- **Page** – range of shown groups: 1-10, 11-20 … *(since 1.11)*, with « » buttons
  *(since 1.08)*.

## Saving

| Button | Action |
|---|---|
| **Save changes** | Saves the changed groups. Since 1.02 the previous permissions of all groups go to the version history. |
| **Save as new version** *(since 1.02)* | Saves the changes (if any) and starts the next version number. |
| **Discard changes** | Reverts all unsaved changes in the table. |

- Saving uses the same functions as ResourceSpace (`save_usergroup`), and each change is
  recorded in the activity log.
- Changes are applied to the **current** value in the database. Codes that are not in the table
  are kept.
- **Your own group never loses** Admin access (`t`) or System setup access (`a`). This stops
  you from locking yourself out.
- Leaving the page with unsaved changes asks for confirmation.

## Copy permissions

Choose **From** (source group) and **to** (target group), then click **Copy**. All permissions of
the table are copied, including the custom permission rows. The change appears in the table and
is saved only after **Save changes**. Inheriting groups cannot be selected as the target.

## Version history *(since 1.02)*

- **The first version (v1)** is created automatically after the plugin is activated and holds
  the permissions of all groups.
- Every **Save changes** stores the previous state under the current number. **Save as new
  version** starts the next number. The last 50 entries are kept.
- **Load into table** – shows the selected version in the table (differences framed blue). It is
  applied only after **Save changes**.
- **Restore this version** – restores the whole version at once, including codes outside the
  table.
- **Delete this version** – deletes all files with that number.
- **Delete all history** – deletes the history except the newest entry.

With Load and Restore the current state stays in the history, so every operation can be undone.

## Export and import of user groups *(since 1.18)*

**Where:** plugin page > **Export / import user groups** (below the table). The page has its own
address, `pages/transfer.php`.

### Export

Tick the groups (all by default) and click **Export**. This creates the file
`permission_group_manager-groups-<date>-<time>.json`, which stores for each group:
- its name,
- its parent group (by name),
- the settings inherited from the parent group (`inherit_flags`),
- its list of permission codes.

The file can be imported into another ResourceSpace installation with this plugin. **Groups are
matched by name** (case-insensitive), because group numbers differ between installations.

### Import

1. Choose an export file and click **Check file**. Nothing is changed at this point.
2. The review page sorts the groups of the file into three parts:
   - **New groups** (not in the system):
     - one new group: the question "Add it?" with a checkbox,
     - several new groups: a list with a checkbox for each (ticked by default) and "select all /
       none".

     You also see the parent group, inheritance and the number of permissions ("show" lists the
     codes).
   - **Existing groups with different permissions** – choose for each:
     - *Keep the existing permissions* – keep the system's permissions (default),
     - *Use the permissions from the import* – use the permissions from the file,
     - *Show the differences and choose* – shows a permission table with the columns permission
       name, code, **In the system**, **In the import** and **Result** (checkbox).
       - **Result** starts ticked where the system and the import agree.
       - **Rows with a difference are highlighted and their Result box starts unticked** (no
         permission). Tick it to give the permission.
       - Shortcuts: "use all from import", "use all from system", "show only differences".
   - **Existing groups with the same permissions** – just listed, nothing to do.
3. **Import** applies the chosen changes (after a confirmation); **Cancel** ends without changes.
   The uploaded file waits for the decision for up to one hour.

**Import details:**
- Before the import, the permissions of all groups are stored in the plugin's **version
  history**. "Restore this version" brings them back, but does not delete groups created by the
  import.
- New groups are created with the same function as in ResourceSpace (`save_usergroup`) and
  recorded in the activity log.
- **Parent group:** if it exists in the system or is imported from the same file, the new group
  becomes its subgroup (the parent is created first). Otherwise the new group is created at the
  top level, without inheritance.
- **Your own group** never loses Admin (`t`) or System setup (`a`) access.
- Groups that inherit permissions are marked: their own permissions are stored, but are not used
  while inheritance is on.
- Invalid codes and repeated group names in the file are skipped and shown on the review page.

## Rules of use

1. **Before larger changes, click "Save as new version".** This gives you a clear restore point
   with its own number.
2. **When comparing groups, tick "differing".** You see only the rows where the groups differ.
3. **Before saving, tick "unsaved"** to check exactly what will change. The counter next to the
   buttons shows the number of changes.
4. **Inheriting groups are not changed in this table.** Change the parent group, or turn off
   inheritance on the group page in ResourceSpace.
5. **Type custom codes exactly as ResourceSpace or the plugin expects them.** Only the format of
   a code is checked, not its meaning.

## Installation and files on the server

### Download and installation

The plugin is distributed as a `permission_group_manager-<version>.zip` archive on the releases page:
<https://github.com/s29829/permission_group_manager/releases>. The archive contains the folder `permission_group_manager/` with the code,
license and documentation. Below, `<RS_ROOT>` is the ResourceSpace installation folder (e.g.
`/var/www/resourcespace`) and `www-data` is the web server user (e.g. `apache` on other systems).

**In the browser:** Plugin Manager > Upload a plugin > choose the `.zip` file > Upload (when
updating: **Overwrite**).

**From the command line (CLI)** – download, unpack and set the permissions:

```bash
VER=<version>                    # version number from the releases page, e.g. 1.00
cd /tmp
curl -fLO https://github.com/s29829/permission_group_manager/releases/download/v$VER/permission_group_manager-$VER.zip
sudo rm -rf <RS_ROOT>/plugins/permission_group_manager        # only when updating (the plugin data is in the filestore)
sudo unzip -q permission_group_manager-$VER.zip -d <RS_ROOT>/plugins/
sudo chown -R www-data:www-data <RS_ROOT>/plugins/permission_group_manager
sudo find <RS_ROOT>/plugins/permission_group_manager -type d -exec chmod 755 {} \;
sudo find <RS_ROOT>/plugins/permission_group_manager -type f -exec chmod 644 {} \;
```

**With git:** the repository <https://github.com/s29829/permission_group_manager> contains the complete plugin folder:

```bash
sudo git clone https://github.com/s29829/permission_group_manager <RS_ROOT>/plugins/permission_group_manager
sudo chown -R www-data:www-data <RS_ROOT>/plugins/permission_group_manager
```

Update: `sudo git -C <RS_ROOT>/plugins/permission_group_manager pull`.

Finally: System > Plugins, activate "Permission Group Manager" and click **Options**. The plugin creates its
data folder `<filestore>/permission_group_manager` itself – it only needs write access to the filestore, which
ResourceSpace requires anyway.

### Files on the server

Everything the plugin stores is in `<filestore>/permission_group_manager/` *(since 1.22)*, in the subfolder
`data_<key>` (the key derives from `scramble_key`, so the files cannot be read by guessing the
address):

- `v<N>_<date>_<time>.json` – permissions of all groups,
- `current_version.txt` – current version number,
- `custom_codes.json` – descriptions of the "Custom permissions" rows *(since 1.09)*.

`<filestore>` is ResourceSpace's `$storagedir` (default `<RS_ROOT>/filestore`). Updating or removing the plugin code never deletes these files. Versions 1.19–1.21 (`groupperms`) stored the data in
`<filestore>/groupperms/`, versions 1.02–1.18 in `<filestore>/groupperms_versions_<key>/` – version 1.22 copies it automatically.

**The plugin creates its folders itself** on first use (activation, opening the options page).
It only needs write access to the filestore, which ResourceSpace requires anyway.

## Reset plugin *(since 1.19)*

The box is collapsed – click its title to open it *(since 1.23)*; it opens by itself after a failed reset.

The **Reset plugin** box on the plugin page (after typing `RESET`) deletes everything the plugin
stored in `<filestore>/permission_group_manager`: the version history and the custom permission descriptions.
**The permissions of the user groups are not changed** – the current state becomes version v1
again. This cannot be undone.

## License *(since 1.14)*

- The plugin is licensed under the **PolyForm Noncommercial License 1.0.0** (file
  [LICENSE](LICENSE)).
- It is **free** for private and noncommercial use, and for noncommercial organizations
  (education, public institutions, charities). You may change and share it for these purposes,
  keeping the license text.
- **Commercial use** (a company, services for clients, a paid product) requires the author's
  written permission: <https://github.com/s29829>. Details: `LICENSE-COMMERCIAL.md` in the
  repository.

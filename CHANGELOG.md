# Changelog - Permission Group Manager

Plugin version numbers: 1.00, 1.01, 1.02 … (+0.01 for every change). The current version is in `permission_group_manager.yaml`.

## [1.25] - 2026-11-13
- Documentation: English is the main language - change history translated to English.

## [1.24] - 2026-11-13
- Documentation: installation on Apache (Ubuntu/Debian and the RHEL family) – user and group that run PHP (mod_php / PHP-FPM), permissions, no directory listings (Options -Indexes), SELinux.

## [1.23] - 2026-11-12
- Collapsible "Reset plugin" box (as in Custom CSS).

## [1.22] - 2026-11-12
- New name: Permission Group Manager (folder permission_group_manager, previously Group Permissions / groupperms); on first use it copies the data of the old plugin (version history, custom permission descriptions) without deleting the old folder; the options page reminds you to deactivate groupperms; new repository s29829/permission_group_manager.

## [1.21] - 2026-11-11
- Clean-up of the change history (no change in the plugin).

## [1.20] - 2026-11-11
- Documentation: installation with Plugin Manager or on the command line (curl, unzip, chown) without bash scripts; releases contain only the zip archive.

## [1.19] - 2026-11-11
- Plugin data (version history, custom permission descriptions) in <filestore>/permission_group_manager/data_<key>/; automatic move from <filestore>/permission_group_manager_versions_<key>/; "Reset plugin" (deletes the plugin data, does not change the group permissions).

## [1.18] - 2026-11-11
- Export and import of user groups (page pages/transfer.php): export of selected groups to JSON (name, parent group, inheritance, permissions); import with a review – choose the new groups to add; for existing groups with other permissions choose: keep existing / use the import / show differences (table: in the system, in the import, result – matching permissions preselected, differences highlighted and not selected); the state before the import is stored in the version history.

## [1.17] - 2026-11-11
- Changes in the documentation and release tools (no change in the plugin).

## [1.16] - 2026-11-11
- Changes in the documentation and release tools (no change in the plugin).

## [1.15] - 2026-11-11
- Self-contained zip package: LICENSE-COMMERCIAL.md in the plugin folder, license and documentation in the package; installation instructions for the zip archive.

## [1.14] - 2026-11-11
- License PolyForm Noncommercial 1.0.0 (file LICENSE; commercial use only with the author's written permission); "License" section in the documentation.

## [1.13] - 2026-11-11
- Documentation in the plugin folder: README.md (EN) and README.pl.md (PL), with the version in which each feature appeared.

## [1.12] - 2026-11-10
- Clear gap (with a thin line) before the "Columns" / "Page" fields in the groups panel.
- "Select" column: small outline "All" / "None" buttons (grey border, smaller font, not blue).

## [1.11] - 2026-11-10
- Filter panels joined to the table (no gap, no rounded corners); a thin line below the header row instead of a bold one.
- Group names in the headers shortened to 20 characters (full name in the tooltip); 10, 15 or 20 groups per page.
- "Page" list only with number ranges (1-10, 11-20 …); more space in the groups panel.
- Column "Row" → "Select" (icons instead of "All" / "None" buttons).

## [1.10] - 2026-11-10
- Fix for "Sorry, an error has occurred": reading the not yet existing file `custom_codes.json` with `@file_get_contents()` triggered the ResourceSpace error page (its error handler also reacts to warnings silenced with `@`). All `@` calls removed – files and folders are checked before use.

## [1.09] - 2026-11-10
- Custom permissions as table rows: every code outside the catalogue is a row with checkboxes, an editable description and a "remove" link; a new row is added at the end of the "Custom permissions" section (description + code). Descriptions in `custom_codes.json`, stored in the version history. Codes `j<ref>` / `-j<ref>` get a default description with the featured collection name.
- Darker background also in the "Permission" and "Code" headers; "Sections: open all / close all" below "Permission".

## [1.08] - 2026-11-10
- Darker background in the "Code" column and the permission search panel; « » buttons next to the "Page" list are back; "Show only: differing / unsaved" on one line.

## [1.07] - 2026-11-10
- Slightly darker background of the permission name column; "Show rows" as checkboxes; "Open / Close all sections" link in the "Permission" header.

## [1.06] - 2026-11-10
- Filter fields like in other ResourceSpace forms (`input type="text"`, theme style), label above each field.
- Fewer buttons: "Show rows" list, one link to open / close sections, × icon in the fields (and Esc), "Page" list instead of « ».

## [1.05] - 2026-11-10
- Colours of the filter panels: blue (permissions) and grey (groups); the permissions panel is exactly as wide as the "Permission" + "Code" columns; column "Shown groups" → "Row".

## [1.04] - 2026-11-10
- Filters above the table in two labelled panels: "Filter permissions (table rows)" and "Filter user groups (table columns)", with counters of the shown rows / groups.
- Group columns as wide as the name, "Shown groups" column with a fixed width.

## [1.03] - 2026-11-10
- Permissions of ResourceSpace plugins as separate sections (only for active plugins; codes and conditions as in the `additionalperms` hooks): Workflow actions (`wf<id>`), Brand guidelines (`bgv`, `bge`), AI smart search (`clip-sb`, `clip-v`), Consent management (`cm`), AI faces (`faces-v`), License management (`lm`).
- "Custom permissions" text field for each group (instead of a read-only section).

## [1.02] - 2026-11-09
- Version history (as in Custom CSS): "Save changes" stores the previous state of the permissions, "Save as new version" creates the next number; "Load into table", "Restore this version", "Delete this version", "Delete all history"; the last 50 entries as JSON files in `<filestore>/permission_group_manager_versions_<key>`. The first version (v1) is created after the plugin is activated (hook `initialise`).

## [1.01] - 2026-11-09
- Layout fixes: the table background follows the light / dark mode switch; normal spelling of names (no small caps); codes in a separate "Code" column; "All" / "None" buttons at the end of the row; search above the columns; copying permissions below the table; sections open and close by clicking the header.

## [1.00] - 2026-11-09
- First version: permission matrix – rows are permissions (sections as on the group permission page of ResourceSpace 11, labels from `$lang`), columns are user groups; column paging, filters, copying permissions between groups; saving with `save_usergroup()` + `log_activity()`; your own group cannot lose `t` / `a`.

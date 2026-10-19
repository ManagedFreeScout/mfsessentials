<?php

namespace Modules\MFSEssentials\Services;

/**
 * Feature switches (admin, Manage → Settings → MFS Essentials) and per-agent preferences
 * (1.4.0, card freescout-modules #340).
 *
 * Every feature needs a valid licence (LicenseService::isLicensed()) AND its switch on.
 * All switches default to on, also for installations updating from 1.3.x (Rutger, 19 Oct 2026).
 * The switches are plain FreeScout options, saved by core's own settings form.
 */
class Features
{
    // Option name => label shown on the settings page. Order = order on the page.
    const SWITCHES = [
        'mfsessentials_fixed_panels'  => 'Fixed panels',
        'mfsessentials_split_view'    => 'Split view',
        'mfsessentials_status_filters' => 'Status filters',
        'mfsessentials_list_columns'  => 'Sortable and adjustable columns',
        'mfsessentials_editor_extras' => 'Editor extras',
        'mfsessentials_emoji'         => 'Emoji and symbol picker',
        'mfsessentials_reactions'     => 'Note reactions',
        'mfsessentials_convert'       => 'Convert an email to a note',
    ];

    // The features that change the layout of the folder lists and conversation pages.
    const LAYOUT = [
        'mfsessentials_fixed_panels',
        'mfsessentials_split_view',
        'mfsessentials_status_filters',
        'mfsessentials_list_columns',
    ];

    /** @var array|null Per request: switches are read once. */
    protected static $cache = null;

    /**
     * Short name (fixed_panels) or option name (mfsessentials_fixed_panels).
     */
    public static function enabled($name)
    {
        if (strpos($name, 'mfsessentials_') !== 0) {
            $name = 'mfsessentials_'.$name;
        }
        if (!LicenseService::isLicensed()) {
            return false;
        }
        $all = self::switches();

        return !empty($all[$name]);
    }

    /**
     * All switches as option name => bool (without the licence check), for the settings page.
     */
    public static function switches()
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (array_keys(self::SWITCHES) as $name) {
                self::$cache[$name] = (bool) \Option::get($name, true);
            }
        }

        return self::$cache;
    }

    public static function anyLayout()
    {
        foreach (self::LAYOUT as $name) {
            if (self::enabled($name)) {
                return true;
            }
        }

        return false;
    }

    // ---------- Per-agent preferences ----------
    // One JSON option per user: split view on/off, divider position, column widths, hidden
    // statuses. Stored in FreeScout (not the browser), so they follow the agent to every
    // computer and the Teams tab.

    const PREF_KEYS = ['split', 'listw', 'colw', 'hide_status'];

    const COLUMNS = ['customer', 'owner', 'number', 'date'];

    protected static function prefsOption($user_id)
    {
        return 'mfsessentials_prefs_'.(int) $user_id;
    }

    public static function prefs($user_id = null)
    {
        if ($user_id === null) {
            $user = auth()->user();
            if (!$user) {
                return [];
            }
            $user_id = $user->id;
        }
        $prefs = json_decode((string) \Option::get(self::prefsOption($user_id), ''), true);

        return is_array($prefs) ? $prefs : [];
    }

    /**
     * Validates one preference; returns [true, cleaned value] or [false, null].
     * Nothing from the request is stored unchecked.
     */
    public static function cleanPref($key, $value)
    {
        switch ($key) {
            case 'split':
                return [true, (bool) $value];

            case 'listw':
                // Width of the list in split view, % of the content area.
                if (!is_numeric($value)) {
                    return [false, null];
                }
                return [true, round(min(75, max(20, (float) $value)), 1)];

            case 'colw':
                // Column widths in px; an empty list = all default.
                if (!is_array($value)) {
                    return [false, null];
                }
                $clean = [];
                foreach (self::COLUMNS as $col) {
                    if (isset($value[$col]) && is_numeric($value[$col])) {
                        $clean[$col] = (int) min(2000, max(50, $value[$col]));
                    }
                }
                return [true, $clean];

            case 'hide_status':
                // Hidden statuses: a subset of active / pending / closed, never all three.
                if (!is_array($value)) {
                    return [false, null];
                }
                $allowed = [\App\Conversation::STATUS_ACTIVE, \App\Conversation::STATUS_PENDING, \App\Conversation::STATUS_CLOSED];
                $clean = array_values(array_unique(array_intersect($allowed, array_map('intval', $value))));
                sort($clean);
                if (count($clean) >= count($allowed)) {
                    return [false, null];
                }
                return [true, $clean];
        }

        return [false, null];
    }

    public static function setPref($user_id, $key, $value)
    {
        list($ok, $clean) = self::cleanPref($key, $value);
        if (!$ok) {
            return false;
        }
        $prefs = self::prefs($user_id);
        $prefs[$key] = $clean;
        \Option::set(self::prefsOption($user_id), json_encode($prefs));

        return true;
    }

    /**
     * Hidden statuses of the current agent (status filters), [] when none or switched off.
     */
    public static function hiddenStatuses()
    {
        if (\Helper::isConsole() || !self::enabled('status_filters')) {
            return [];
        }
        $prefs = self::prefs();

        return isset($prefs['hide_status']) && is_array($prefs['hide_status'])
            ? array_map('intval', $prefs['hide_status'])
            : [];
    }
}

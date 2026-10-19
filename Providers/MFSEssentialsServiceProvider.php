<?php

namespace Modules\MFSEssentials\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Modules\MFSEssentials\Entities\ThreadReaction;
use Modules\MFSEssentials\Services\ThreadConverter;
use Modules\MFSEssentials\Services\LicenseService;
use Modules\MFSEssentials\Services\Features;

defined('MFSESSENTIALS_MODULE') || define('MFSESSENTIALS_MODULE', 'mfsessentials');

class MFSEssentialsServiceProvider extends ServiceProvider
{
    const MODULE_ALIAS = 'mfsessentials';

    protected $defer = false;

    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerLicenseHooks();
        $this->registerAssets();
        $this->registerReactionsHook();
        $this->registerConvertHooks();
        $this->registerLayoutHooks();
    }

    public function register()
    {
        $this->app->singleton(LicenseService::class, function () {
            return new LicenseService();
        });
    }

    protected function registerConfig()
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', self::MODULE_ALIAS);
    }

    /**
     * Licence (card #194, 1.3.0): a settings section under Manage → Settings → MFSEssentials,
     * the licence shown on FreeScout's Manage → Modules page, and a re-check every 6 hours.
     * The modules.* filters below only affect what FreeScout shows; the real gate is
     * LicenseService::isLicensed(), checked by every feature hook and controller.
     * Rutger, 8 Oct 2026: no licence, an expired subscription or a licence that could not be
     * checked for 14 days = every feature off, also right after updating from a free version.
     */
    protected function registerLicenseHooks()
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections[self::MODULE_ALIAS] = [
                'title'       => __('MFS Essentials'),
                'icon'        => 'lock',
                'order'       => 310,
                'description' => __('Licence for the MFS Essentials module.'),
            ];
            return $sections;
        }, 15);

        // 1.4.0: the feature switches are plain options in this section, so core's own settings
        // form saves them (SettingsController::processSave). 'default' => true makes an unticked
        // switch save as false instead of falling back to "on".
        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ($section === self::MODULE_ALIAS) {
                $settings['license_status'] = app(LicenseService::class)->getLicenseStatus();
                $settings = array_merge($settings, Features::switches());
            }
            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ($section === self::MODULE_ALIAS) {
                $params['license_status'] = app(LicenseService::class)->getLicenseStatus();
                foreach (array_keys(Features::SWITCHES) as $name) {
                    $params['settings'][$name] = ['default' => true];
                }
            }
            return $params;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section === self::MODULE_ALIAS ? self::MODULE_ALIAS . '::settings.mfsessentials' : $view;
        }, 20, 2);

        \Eventy::addFilter('modules.show_license', function ($show, $module) {
            return (isset($module['alias']) && $module['alias'] === self::MODULE_ALIAS) ? true : $show;
        }, 20, 2);

        \Eventy::addFilter('modules.license_info', function ($license_info, $module_alias) {
            if ($module_alias !== self::MODULE_ALIAS) {
                return $license_info;
            }
            $status = app(LicenseService::class)->getLicenseStatus();
            return [
                'license'      => $status['license_key'] ?? '',
                'activated'    => $status['valid'] ?? false,
                'status'       => $status['status'] ?? 'inactive',
                'expires_at'   => $status['expires_at'] ?? null,
                'license_type' => $status['license_type'] ?? null,
            ];
        }, 20, 2);

        \Eventy::addFilter('module.requires_license', function ($requires, $module) {
            return (isset($module['alias']) && $module['alias'] === self::MODULE_ALIAS) ? true : $requires;
        }, 20, 2);

        // Re-check every 6 hours (same interval as MSTeamsFS, card #232 F5), so an expired or
        // revoked subscription switches the features off without anyone opening the settings.
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->call(function () {
                $service = app(LicenseService::class);
                $status  = $service->getLicenseStatus();
                if (!empty($status['license_key'])) {
                    $service->validateLicense($status['license_key']);
                }
            })->cron('0 */6 * * *');
            return $schedule;
        }, 20, 1);
    }

    protected function registerViews()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', self::MODULE_ALIAS);
    }

    protected function registerAssets()
    {
        // Mirrors CfsAssistServiceProvider::boot() exactly -- Module::getPublicPath(),
        // not asset() (asset() has no such helper, FreeScout_Development_Notes.md §3.4).
        // Licence gate (card #194): no licence = none of the CSS/JS is loaded, which also
        // switches off the code-block wrap fix (pure CSS, nothing else to gate).
        // 1.4.0: each feature's file only when its switch is on (Manage → Settings → MFS Essentials).
        // module.css stays with the licence: it holds the code-block wrap fix (no switch).
        \Eventy::addFilter('stylesheets', function ($items) {
            if (!LicenseService::isLicensed()) {
                return $items;
            }
            $path = \Module::getPublicPath(self::MODULE_ALIAS);
            $items[] = $path . '/css/module.css';
            if (Features::anyLayout() || Features::enabled('editor_extras')) {
                $items[] = $path . '/css/mfsessentials-layout.css';
            }
            return $items;
        }, 20, 1);

        \Eventy::addFilter('javascripts', function ($items) {
            if (!LicenseService::isLicensed()) {
                return $items;
            }
            $path = \Module::getPublicPath(self::MODULE_ALIAS);
            if (Features::enabled('emoji')) {
                $items[] = $path . '/js/mfsessentials-editor.js';
            }
            if (Features::enabled('reactions')) {
                $items[] = $path . '/js/mfsessentials-reactions.js';
            }
            if (Features::enabled('convert')) {
                $items[] = $path . '/js/mfsessentials-convert.js';
            }
            if (Features::anyLayout()) {
                $items[] = $path . '/js/mfsessentials-layout.js';
            }
            if (Features::enabled('editor_extras')) {
                $items[] = $path . '/js/mfsessentials-editor-extras.js';
            }
            return $items;
        }, 20, 1);
    }

    protected function registerReactionsHook()
    {
        // thread.meta fires unconditionally for every thread type
        // (resources/views/conversations/partials/thread.blade.php), so the
        // isNote() gate must be the first line -- same pattern already
        // proven in this project's own MSTeamsFSServiceProvider hooks.
        \Eventy::addAction('thread.meta', function ($thread, $loop, $threads, $conversation, $mailbox) {
            // Not on FreeScout's print view (?print=1): a printout shows no reaction buttons (card #306).
            if (!$thread->isNote() || request()->input('print') || !Features::enabled('reactions')) {
                return;
            }

            $userId  = Auth::check() ? Auth::user()->id : 0;
            $summary = ThreadReaction::summaryFor($thread->id, $userId);

            echo view(self::MODULE_ALIAS . '::partials.reactions-bar', [
                'thread'       => $thread,
                'counts'       => $summary['counts'],
                'active'       => $summary['active'],
                'reactorNames' => $summary['reactor_names'],
            ])->render();
        }, 20, 5);
    }

    /**
     * Convert a single email to a note and back (card freescout-modules #261).
     * The work itself is in Services/ThreadConverter.php.
     */
    protected function registerConvertHooks()
    {
        // Menu items in each thread's top-right dropdown (same hook as
        // TicketTranslator's "Translate"). Confirmation texts travel as data
        // attributes so they stay translatable; mfsessentials-convert.js
        // shows them and posts the conversion.
        \Eventy::addAction('thread.menu', function ($thread) {
            if (!Features::enabled('convert') || !Auth::check() || !ThreadConverter::userCanConvert(Auth::user(), $thread)) {
                return;
            }

            if (ThreadConverter::canConvertToNote($thread)) {
                $confirm = __('Convert this email to an internal note? Clients will no longer see it in the history of later replies or in the customer portal.');
                if ($thread->type == \App\Thread::TYPE_MESSAGE) {
                    $confirm .= ' '.__('This reply has already been sent; converting it cannot recall that email.');
                }
                if ($thread->first) {
                    $confirm .= ' '.__('Note: this is the first message of the conversation.');
                }
                $label = __('Convert to note');
                $direction = 'note';
            } elseif (ThreadConverter::isConverted($thread)) {
                $confirm = __('Convert this note back to an email? Clients will see it again in the history of later replies and in the customer portal.');
                $label = __('Convert back to email');
                $direction = 'email';
            } else {
                return;
            }

            echo '<li><a href="#" class="mfsessentials-convert-trigger" role="button"'
                .' data-direction="'.$direction.'"'
                .' data-confirm="'.e($confirm).'"'
                .' data-ok="'.e($label).'"'
                .' data-url="'.e(route('mfsessentials.thread.convert')).'">'
                .e($label).'</a></li>';
        }, 20, 1);

        // Banner on a converted note: who converted it, when, original sender.
        \Eventy::addAction('thread.before_body', function ($thread, $loop, $threads, $conversation, $mailbox) {
            if (!ThreadConverter::isConverted($thread) || !LicenseService::isLicensed()) {
                return;
            }

            echo view(self::MODULE_ALIAS . '::partials.convert-banner', [
                'banner' => ThreadConverter::bannerData($thread),
            ])->render();
        }, 20, 5);

        // Activity lines ("Rutger converted an email to a note").
        \Eventy::addFilter('thread.action_text', function ($did_this, $thread) {
            if ($thread->type != \App\Thread::TYPE_LINEITEM) {
                return $did_this;
            }
            if ($thread->action_type == ThreadConverter::ACTION_TYPE_CONVERTED_TO_NOTE) {
                return __(':person converted an email to a note');
            }
            if ($thread->action_type == ThreadConverter::ACTION_TYPE_CONVERTED_TO_EMAIL) {
                return __(':person converted a note back to an email');
            }

            return $did_this;
        }, 20, 2);
    }

    /** @var int|null Folder type of the list page being rendered (for body.class). */
    public static $folderType = null;

    /**
     * Layout features (1.4.0, card freescout-modules #340, tested as "MFS Layout (test)"):
     * fixed panels, split view, status filters, sortable + adjustable columns. Almost all of it
     * is CSS/JS behind body classes; the server part is below. Each one has its own switch.
     */
    protected function registerLayoutHooks()
    {
        // Body classes: mfsl-on = a layout feature or the editor extras are on (scopes the CSS),
        // mfsl-fixed = fixed panels, mfsl-editor = editor extras, mfsl-status-filters = the filters
        // apply to the folder shown. Not on FreeScout's print view (?print=1).
        \Eventy::addAction('body.class', function () {
            if (request()->input('print') || !LicenseService::isLicensed()) {
                return;
            }
            $editor = Features::enabled('editor_extras');
            if (!Features::anyLayout() && !$editor) {
                return;
            }
            echo ' mfsl-on';
            if (Features::enabled('fixed_panels')) {
                echo ' mfsl-fixed';
            }
            if ($editor) {
                echo ' mfsl-editor';
            }
            if (Features::enabled('status_filters') && self::$folderType !== null && self::statusFilterApplies(self::$folderType)) {
                echo ' mfsl-status-filters';
            }
        });

        // Which folder the list page shows (the body.class action has no view data).
        \View::composer('mailboxes.view', function ($view) {
            $folder = $view->getData()['folder'] ?? null;
            self::$folderType = $folder ? (int) $folder->type : null;
        });

        // Settings for mfsessentials-layout.js / -editor-extras.js: the switches, this agent's
        // preferences and where to save them. A JSON block, not a script (no CSP nonce needed).
        \Eventy::addAction('layout.head', function () {
            if (!LicenseService::isLicensed() || !Auth::check()) {
                return;
            }
            if (!Features::anyLayout() && !Features::enabled('editor_extras')) {
                return;
            }
            $config = [
                'features' => [
                    'fixed_panels'   => Features::enabled('fixed_panels'),
                    'split_view'     => Features::enabled('split_view'),
                    'status_filters' => Features::enabled('status_filters'),
                    'list_columns'   => Features::enabled('list_columns'),
                    'editor_extras'  => Features::enabled('editor_extras'),
                ],
                'prefs'    => (object) Features::prefs(),
                'prefsUrl' => route('mfsessentials.prefs'),
            ];
            echo '<script type="application/json" id="mfsessentials-config">'
                .json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
                .'</script>';
        });

        // Status filters (Active / Pending / Closed): leave this agent's hidden statuses out of the
        // folder query itself, so paging, totals and core's AJAX list refreshes stay correct.
        // Priority 100 = after other modules: Inbox and Custom Folders REPLACE the query at 20.
        \Eventy::addFilter('folder.conversations_query', function ($query, $folder, $user_id) {
            if (!$folder || !self::statusFilterApplies($folder->type)) {
                return $query;
            }
            $possible = [\App\Conversation::STATUS_ACTIVE, \App\Conversation::STATUS_PENDING, \App\Conversation::STATUS_CLOSED];
            $hidden = array_values(array_intersect($possible, Features::hiddenStatuses()));
            // Never everything this folder can show: an empty list would look like a bug.
            if ($hidden && count($hidden) < count($possible)) {
                $query->whereNotIn('conversations.status', $hidden);
            }
            return $query;
        }, 100, 3);

        // Extra sortable columns (Customer, Assigned To). Core only accepts subject/number/date
        // and ignores anything else, then appends its own order (status, last reply) after ours,
        // which becomes the tie-breaker. The sort comes in with core's own AJAX list request.
        \Eventy::addFilter('folder.conversations_query', function ($query, $folder, $user_id) {
            $sort = self::extraSort();
            if (!$sort) {
                return $query;
            }
            $p = \DB::getTablePrefix();
            if ($sort['sort_by'] == 'customer') {
                // Same as Customer::getFullName(true): name, else the e-mail address.
                $expr = "(select coalesce(nullif(trim(concat(coalesce(c.first_name, ''), ' ', coalesce(c.last_name, ''))), ''), {$p}conversations.customer_email)"
                    ." from {$p}customers c where c.id = {$p}conversations.customer_id)";
            } else {
                $expr = "(select nullif(trim(concat(coalesce(u.first_name, ''), ' ', coalesce(u.last_name, ''))), '')"
                    ." from {$p}users u where u.id = {$p}conversations.user_id)";
            }
            // Empty values (no customer, unassigned) always at the bottom.
            $query->orderByRaw("{$expr} is null asc")->orderByRaw("{$expr} ".$sort['order']);
            return $query;
        }, 110, 3);
    }

    /**
     * Spam, Deleted and Drafts are their own world; the Closed folder only holds closed ones.
     * Closed is offered everywhere else: core moves a closed conversation to the Closed folder,
     * but imports, the API or other modules can leave one in Assigned.
     */
    public static function statusFilterApplies($folder_type)
    {
        return !in_array((int) $folder_type, [
            \App\Folder::TYPE_CLOSED,
            \App\Folder::TYPE_SPAM,
            \App\Folder::TYPE_DELETED,
            \App\Folder::TYPE_DRAFTS,
        ]);
    }

    /**
     * Sorting on a column core can't sort, from core's AJAX request (sorting[sort_by], sorting[order]).
     * The values are checked against fixed lists, so nothing from the request reaches the SQL.
     */
    public static function extraSort()
    {
        if (\Helper::isConsole() || !Features::enabled('list_columns')) {
            return null;
        }
        $sorting = request()->input('sorting');
        if (!is_array($sorting)
            || !in_array($sorting['sort_by'] ?? '', ['customer', 'assignee'], true)
            || !in_array($sorting['order'] ?? '', ['asc', 'desc'], true)
        ) {
            return null;
        }
        return ['sort_by' => $sorting['sort_by'], 'order' => $sorting['order']];
    }
}

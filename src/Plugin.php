<?php

namespace Gilbitron\WhatTheCron;

use Carbon\Carbon;

class Plugin
{
    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    private $timeFormat = 'Y-m-d H:i:s';

    /**
     * @var string
     */
    private $textDomain = 'what-the-cron';

    /**
     * Plugin constructor.
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->url = plugin_dir_url($path);
    }

    public function run()
    {
        add_filter('cron_schedules', [$this, 'add_cron_intervals']);
        add_action('wtc_cron_ping', [$this, 'cron_ping']);

        if (!wp_next_scheduled('wtc_cron_ping')) {
            wp_schedule_event(time(), 'everyminute', 'wtc_cron_ping');
        }

        register_deactivation_hook($this->path, [$this, 'deactivate']);

        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    public function add_cron_intervals($schedules)
    {
        $schedules['everyminute'] = [
            'interval' => 60,
            'display' => esc_html__('Every Minute'),
        ];

        return $schedules;
    }

    public function cron_ping()
    {
        // Do nothing
    }

    public function admin_menu()
    {
        $hook = add_management_page('What The Cron', 'What The Cron', 'install_plugins', 'what-the-cron', [$this, 'admin_page']);
    }

    public function admin_page()
    {
        ?>
        <div class="wrap">
            <div style="max-width: 1200px;">
                <h1 style="margin-bottom: 20px;"><?php _e('What The Cron', $this->textDomain) ?></h1>

                <div style="display: flex; margin-bottom: 30px;">
                    <div style="width: 50%; margin-right: 30px;">
                        <table class="wp-list-table widefat striped">
                            <thead>
                            <tr>
                                <th><?php _e('Config', $this->textDomain) ?></th>
                                <th style="text-align: right;"><?php _e('Value', $this->textDomain) ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td><code>DISABLE_WP_CRON</code></td>
                                <td style="text-align: right;">
                                    <?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? '<span style="color: orange;">true</span>' : 'false' ?>
                                </td>
                            </tr>
                            <tr>
                                <td><code>ALTERNATE_WP_CRON</code></td>
                                <td style="text-align: right;">
                                    <?php echo defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON ? '<span style="color: orange;">true</span>' : 'false' ?>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="width: 50%;">
                        <table class="wp-list-table widefat striped">
                            <thead>
                            <tr>
                                <th><?php _e('Schedule', $this->textDomain) ?></th>
                                <th style="text-align: right;"><?php _e('Interval (seconds)', $this->textDomain) ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this->getCronSchedules() as $schedule) { ?>
                                <tr>
                                    <td><?php echo $schedule['display'] ?></td>
                                    <td style="text-align: right;">
                                        <?php echo number_format($schedule['interval']) ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <table class="wp-list-table widefat striped">
                    <thead>
                    <tr>
                        <th><?php _e('Cron', $this->textDomain) ?></th>
                        <th><?php _e('Schedule', $this->textDomain) ?></th>
                        <th><?php _e('Next Run', $this->textDomain) ?></th>
                        <th><?php _e('Due', $this->textDomain) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->getCronEvents() as $event) { ?>
                        <tr>
                            <td><?php echo $event->hook ?></td>
                            <td><?php echo $event->schedule ?></td>
                            <td><?php echo $event->next_run_local ?></td>
                            <td>
                                <?php if ($event->is_due) { ?>
                                    <span style="color: red;"><?php echo $event->next_run_relative ?></span>
                                <?php } else { ?>
                                    <?php echo $event->next_run_relative ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php

    }

    public function admin_notices()
    {
        if (empty($_GET['page']) || $_GET['page'] !== 'what-the-cron') {
            return;
        }

        if ($this->cronIsOverdue()) {
            $class = 'notice notice-error';
            $message = __('The cron is not running!', $this->textDomain);

            if ($lastCronPing = $this->lastCronPing()) {
                $message .= ' ' . __('Last run:', $this->textDomain) . ' ' . $lastCronPing . ' (' . $this->lastCronPingRelative() . ')';
            }
        } else {
            $class = 'notice notice-success';
            $message = __('The cron is running.', $this->textDomain);

            if ($lastCronPing = $this->lastCronPing()) {
                $message .= ' ' . __('Last ping:', $this->textDomain) . ' ' . $lastCronPing . ' (' . $this->lastCronPingRelative() . ')';
            }
        }

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $class = 'notice notice-warning';
            $message = __('<strong>Warning:</strong> When <code>DISABLE_WP_CRON</code> is enabled you need to make sure your system\'s task scheduler is configured to run the cron.', $this->textDomain);

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        }
    }

    public function deactivate()
    {
        $timestamp = wp_next_scheduled('wtc_cron_ping');
        wp_unschedule_event($timestamp, 'wtc_cron_ping');
    }

    /**
     * @return array
     */
    protected function getCronSchedules()
    {
        return wp_get_schedules();
    }

    /**
     * @return array
     */
    protected function getCronEvents()
    {
        $cronTasks = _get_cron_array();
        $events = [];

        foreach ($cronTasks as $time => $cron) {
            foreach ($cron as $hook => $dings) {
                foreach ($dings as $sig => $data) {
                    $events["$hook-$sig-$time"] = (object)[
                        'hook' => $hook,
                        'time' => $time,
                        'sig' => $sig,
                        'args' => $data['args'],
                        'schedule' => $data['schedule'],
                        'interval' => isset($data['interval']) ? $data['interval'] : null,
                    ];
                }
            }
        }

        $events = array_map(
            function ($event) {
                $event->next_run = date($this->timeFormat, $event->time);
                $event->next_run_local = get_date_from_gmt(date('Y-m-d H:i:s', $event->time), $this->timeFormat);
                $event->next_run_relative = Carbon::parse($event->next_run_local)->diffForHumans();
                $event->is_due = $event->time < time();

                return $event;
            },
            $events
        );

        return $events;
    }

    /**
     * @return bool
     */
    protected function cronIsOverdue()
    {
        $events = $this->getCronEvents();

        return count(
                array_filter(
                    $events,
                    function ($event) {
                        return $event->is_due;
                    }
                )
            ) > 0;
    }

    /**
     * @return string
     */
    protected function lastCronPing()
    {
        $time = wp_next_scheduled('wtc_cron_ping');
        if ($time) {
            return get_date_from_gmt(date('Y-m-d H:i:s', ($time - 60)), $this->timeFormat);
        }

        return '';
    }

    /**
     * @return string
     */
    protected function lastCronPingRelative()
    {
        $lastCronPing = $this->lastCronPing();

        if ($lastCronPing) {
            return Carbon::parse($lastCronPing)->diffForHumans();
        }

        return '';
    }
}

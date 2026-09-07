<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.3.2
 * ---------------------------------------------------------------------------- */

use Jsvrcek\ICS\Exception\CalendarEventException;

require_once __DIR__ . '/Google.php';
require_once __DIR__ . '/Caldav.php';

/**
 * Console controller.
 *
 * Handles all the Console related operations.
 */
class Console extends EA_Controller
{
    /**
     * Console constructor.
     */
    public function __construct()
    {
        if (!is_cli()) {
            exit('No direct script access allowed');
        }

        parent::__construct();

        $this->load->dbutil();

        $this->load->library('instance');
        $this->load->library('cleanup');

        $this->load->model('admins_model');
        $this->load->model('appointments_model');
        $this->load->model('customers_model');
        $this->load->model('providers_model');
        $this->load->model('services_model');
        $this->load->model('settings_model');

        $this->load->library('notifications');
        $this->load->library('synchronization');
        $this->load->library('webhooks_client');
    }

    /**
     * Process the mail queue (send pending appointment notifications).
     *
     * Intended to run from cron every minute. Picks up pending mail_queue rows,
     * sends the notifications for each, and marks them sent. On transient
     * failure it retries up to MAX_ATTEMPTS times (with a delay) before giving up.
     *
     * Usage:
     *
     *   php index.php console mail_worker
     */
    public function mail_worker(): void
    {
        $max_attempts = (int) config('mail_queue_max_attempts') ?: 3;
        $retry_minutes = (int) config('mail_queue_retry_minutes') ?: 1;

        $pending = $this->db
            ->where('status', 'pending')
            ->limit(20)
            ->get('mail_queue')
            ->result_array();

        $sent = 0;

        if (empty($pending)) {
            response(PHP_EOL . 'No pending mail jobs.' . PHP_EOL);
            return;
        }

        foreach ($pending as $job) {
            // Only retry after the retry delay has elapsed (avoid hammering SMTP).
            $updated = new DateTime($job['updated_at']);
            if ($job['attempts'] > 0 && (time() - $updated->getTimestamp()) < $retry_minutes * 60) {
                continue;
            }

            try {
                $appointment_id = (int) $job['appointment_id'];

                $appointment = $this->appointments_model->find($appointment_id);

                if (empty($appointment)) {
                    // Appointment gone (e.g. cancelled) — nothing left to send.
                    $this->db->where('id', $job['id']);
                    $this->db->update('mail_queue', [
                        'status' => 'sent',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    continue;
                }

                $manage_mode = filter_var($job['manage_mode'], FILTER_VALIDATE_BOOLEAN);

                $provider = $this->providers_model->find($appointment['id_users_provider']);
                $service = $this->services_model->find($appointment['id_services']);
                $customer = $this->customers_model->find($appointment['id_users_customer']);

                // Stacked booking: load the WHOLE group so the email shows every
                // service, not just the last one whose id was queued.
                $appointment_group = [];
                $appointment_group_names = [];
                $appointment_real_end = $appointment['end_datetime'];

                if (!empty($appointment['booking_group'])) {
                    $appointment_group = $this->appointments_model->get([
                        'booking_group' => $appointment['booking_group'],
                    ]);

                    // Pre-compute the service names (in booking order) and the real
                    // end time (excluding the last service's slot_interval cooldown).
                    foreach ($appointment_group as $group_appointment) {
                        $group_service = $this->services_model->find($group_appointment['id_services']);
                        if (!empty($group_service['name'])) {
                            $appointment_group_names[] = $group_service['name'];
                        }
                    }

                    // Real end = last group record's end minus its slot_interval cooldown.
                    $last_group_appointment = $appointment;

                    foreach ($appointment_group as $group_appointment) {
                        if (strtotime($group_appointment['start_datetime']) >= strtotime($last_group_appointment['start_datetime'])) {
                            $last_group_appointment = $group_appointment;
                        }
                    }

                    $last_service = $this->services_model->find($last_group_appointment['id_services']);
                    $cooldown = !empty($last_service['slot_interval']) ? (int) $last_service['slot_interval'] : 0;

                    if ($cooldown > 0) {
                        $appointment_real_end = (new DateTime($last_group_appointment['end_datetime']))
                            ->sub(new DateInterval('PT' . $cooldown . 'M'))
                            ->format('Y-m-d H:i:s');
                    }
                }

                $company_color = setting('company_color');

                $settings = [
                    'company_name' => setting('company_name'),
                    'company_link' => setting('company_link'),
                    'company_email' => setting('company_email'),
                    'company_color' =>
                        !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                    'date_format' => setting('date_format'),
                    'time_format' => setting('time_format'),
                ];

                $this->notifications->notify_appointment_saved(
                    $appointment,
                    $service,
                    $provider,
                    $customer,
                    $settings,
                    $manage_mode,
                    $appointment_group,
                    $appointment_group_names,
                    $appointment_real_end,
                );

                $this->db->where('id', $job['id']);
                $this->db->update('mail_queue', [
                    'status' => 'sent',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $sent++;
            } catch (Throwable $e) {
                $attempts = (int) $job['attempts'] + 1;

                if ($attempts >= $max_attempts) {
                    $new_status = 'failed';
                } else {
                    $new_status = 'pending';
                }

                $this->db->where('id', $job['id']);
                $this->db->update('mail_queue', [
                    'status' => $new_status,
                    'attempts' => $attempts,
                    'last_error' => substr($e->getMessage(), 0, 2000),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                log_message('error', 'Mail worker failed for job ' . $job['id'] . ': ' . $e->getMessage());
            }
        }

        response(PHP_EOL . 'Mail worker done. Sent ' . $sent . ' of ' . count($pending) . ' jobs.' . PHP_EOL);
    }

    /**
     * Send the "appointment saved" notifications for a booking asynchronously.
     *
     * Fired as a detached CLI worker from Booking::register() so the web request
     * returns immediately (avoids the "frozen" feeling while SMTP runs inline).
     *
     * Usage:
     *
     *   php index.php console notify_appointment_saved <appointment_id> [manage_mode]
     *
     * @param string $appointment_id
     * @param string $manage_mode
     */
    public function notify_appointment_saved(string $appointment_id = '', string $manage_mode = 'false'): void
    {
        try {
            $appointment_id = (int) $appointment_id;

            if (empty($appointment_id)) {
                response(PHP_EOL . 'Error: an appointment ID is required.' . PHP_EOL);
                return;
            }

            $appointment = $this->appointments_model->find($appointment_id);

            if (empty($appointment)) {
                response(PHP_EOL . 'Error: appointment not found: ' . $appointment_id . PHP_EOL);
                return;
            }

            $manage_mode = filter_var($manage_mode, FILTER_VALIDATE_BOOLEAN);

            $provider = $this->providers_model->find($appointment['id_users_provider']);
            $service = $this->services_model->find($appointment['id_services']);
            $customer = $this->customers_model->find($appointment['id_users_customer']);

            // Stacked booking: load the WHOLE group so the email shows every service.
            $appointment_group = [];
            $appointment_group_names = [];
            $appointment_real_end = $appointment['end_datetime'];

            if (!empty($appointment['booking_group'])) {
                $appointment_group = $this->appointments_model->get([
                    'booking_group' => $appointment['booking_group'],
                ]);

                foreach ($appointment_group as $group_appointment) {
                    $group_service = $this->services_model->find($group_appointment['id_services']);
                    if (!empty($group_service['name'])) {
                        $appointment_group_names[] = $group_service['name'];
                    }
                }

                $last_group_appointment = $appointment;

                foreach ($appointment_group as $group_appointment) {
                    if (strtotime($group_appointment['start_datetime']) >= strtotime($last_group_appointment['start_datetime'])) {
                        $last_group_appointment = $group_appointment;
                    }
                }

                $last_service = $this->services_model->find($last_group_appointment['id_services']);
                $cooldown = !empty($last_service['slot_interval']) ? (int) $last_service['slot_interval'] : 0;

                if ($cooldown > 0) {
                    $appointment_real_end = (new DateTime($last_group_appointment['end_datetime']))
                        ->sub(new DateInterval('PT' . $cooldown . 'M'))
                        ->format('Y-m-d H:i:s');
                }
            }

            $company_color = setting('company_color');

            $settings = [
                'company_name' => setting('company_name'),
                'company_link' => setting('company_link'),
                'company_email' => setting('company_email'),
                'company_color' =>
                    !empty($company_color) && $company_color != DEFAULT_COMPANY_COLOR ? $company_color : null,
                'date_format' => setting('date_format'),
                'time_format' => setting('time_format'),
            ];

            $this->notifications->notify_appointment_saved(
                $appointment,
                $service,
                $provider,
                $customer,
                $settings,
                $manage_mode,
                $appointment_group,
                $appointment_group_names,
                $appointment_real_end,
            );

            response(PHP_EOL . 'Notifications sent for appointment #' . $appointment_id . PHP_EOL);
        } catch (Throwable $e) {
            // Log but never crash the worker.
            log_message('error', 'Async notify failed for appointment ' . $appointment_id . ': ' . $e->getMessage());
            response(PHP_EOL . 'Error: ' . $e->getMessage() . PHP_EOL);
        }
    }

    /**
     * Perform a console installation.
     *
     * Use this method to install Easy!Appointments directly from the terminal.
     *
     * Usage:
     *
     * php index.php console install
     *
     * @throws Exception
     */
    public function install(): void
    {
        $this->instance->migrate('fresh');

        $password = $this->instance->seed();

        response(
            PHP_EOL . '⇾ Installation completed, login with "administrator" / "' . $password . '".' . PHP_EOL . PHP_EOL,
        );
    }

    /**
     * Migrate the database to the latest state.
     *
     * Use this method to upgrade an Easy!Appointments instance to the latest database state.
     *
     * Notice:
     *
     * Do not use this method to install the app as it will not seed the database with the initial entries (admin,
     * provider, service, settings etc.).
     *
     * Usage:
     *
     * php index.php console migrate
     *
     * php index.php console migrate fresh
     *
     * @param string $type
     */
    public function migrate(string $type = ''): void
    {
        $this->instance->migrate($type);
    }

    /**
     * Seed the database with test data.
     *
     * Use this method to add test data to your database
     *
     * Usage:
     *
     * php index.php console seed
     * @throws Exception
     */
    public function seed(): void
    {
        $this->instance->seed();
    }

    /**
     * Create a database backup file.
     *
     * Use this method to back up your Easy!Appointments data.
     *
     * Usage:
     *
     * php index.php console backup
     *
     * php index.php console backup /path/to/backup/folder
     *
     * @throws Exception
     */
    public function backup(): void
    {
        $this->instance->backup($GLOBALS['argv'][3] ?? null);
    }

    /**
     * Trigger the synchronization of all provider calendars with Google Calendar.
     *
     * Use this method in a cronjob to automatically sync events between Easy!Appointments and Google Calendar.
     *
     * Notice:
     *
     * Google syncing must first be enabled for each individual provider from inside the backend calendar page.
     *
     * Usage:
     *
     * php index.php console sync
     *
     * @throws CalendarEventException
     * @throws Exception
     * @throws Throwable
     */
    public function sync(): void
    {
        $providers = $this->providers_model->get();

        foreach ($providers as $provider) {
            if (filter_var($provider['settings']['google_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Google::sync((string) $provider['id']);
            }

            if (filter_var($provider['settings']['caldav_sync'], FILTER_VALIDATE_BOOLEAN)) {
                Caldav::sync((string) $provider['id']);
            }
        }
    }

    /**
     * Clean up old customer data based on data retention settings.
     *
     * Use this method in a cronjob to automatically delete customer data older than the configured retention period.
     *
     * Usage:
     *
     * php index.php console cleanup
     *
     * @throws Exception
     */
    public function cleanup(): void
    {
        $this->cleanup->run();
    }

    /**
     * Show help information about the console capabilities.
     *
     * Use this method to see the available commands.
     *
     * Usage:
     *
     * php index.php console help
     */
    public function help(): void
    {
        $help = [
            '',
            'Easy!Appointments ' . config('version'),
            '',
            'Usage:',
            '',
            '⇾ php index.php console [command] [arguments]',
            '',
            'Commands:',
            '',
            '⇾ php index.php console migrate',
            '⇾ php index.php console migrate fresh',
            '⇾ php index.php console migrate up',
            '⇾ php index.php console migrate down',
            '⇾ php index.php console seed',
            '⇾ php index.php console install',
            '⇾ php index.php console backup',
            '⇾ php index.php console sync',
            '⇾ php index.php console cleanup    (cleans sessions, logs, cache, and customer data)',
            '⇾ php index.php console notify_appointment_saved <appointment_id> [manage_mode]',
            '',
            '',
        ];

        response(implode(PHP_EOL, $help));
    }
}

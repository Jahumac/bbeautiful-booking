<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Reports Controller
 *
 * Renders the reports/analytics page and serves the date-range aggregation
 * endpoint. This is a Bbeautiful customisation, not part of stock
 * Easy!Appointments.
 *
 * @package Controller
 * @author Bbeautiful
 */
class Reports extends EA_Controller
{
    /**
     * Reports constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('appointments_model');
        $this->load->model('services_model');
        $this->load->model('roles_model');
        $this->load->model('settings_model');
    }

    /**
     * Render the reports page (backend, admin only).
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('reports')]);

        $user_id = session('user_id');

        if (cannot('view', PRIV_APPOINTMENTS)) {
            if ($user_id) {
                abort(403, 'Forbidden');
            }

            redirect('login');

            return;
        }

        $role_slug = session('role_slug');

        html_vars([
            'page_title' => 'Reports',
            'active_menu' => PRIV_APPOINTMENTS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
        ]);

        $this->load->view('pages/reports');
    }

    /**
     * Aggregate appointment + revenue data for a given date range.
     *
     * GET endpoint. Query params:
     *   from = YYYY-MM-DD (inclusive start)
     *   to   = YYYY-MM-DD (inclusive end)
     *
     * Returns JSON:
     *   total_appointments, total_revenue, total_cancelled,
     *   per_service: [ {name, count, revenue, duration} ],
     *   by_month:    [ {month, count, revenue} ]
     */
    public function data(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            $range = $this->parse_range();

            if (!$range) {
                json_response([
                    'success' => false,
                    'message' => 'Both "from" and "to" parameters are required (YYYY-MM-DD).',
                ]);
                return;
            }

            $report = $this->aggregate($range['from'], $range['to']);

            json_response([
                'success' => true,
                'from' => $report['from'],
                'to' => $report['to'],
                'total_appointments' => $report['total_appointments'],
                'total_revenue' => $report['total_revenue'],
                'total_cancelled' => $report['total_cancelled'],
                'per_service' => $report['per_service'],
                'by_month' => $report['by_month'],
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Export the report data as a CSV download (Excel-compatible).
     *
     * GET endpoint. Query params:
     *   from = YYYY-MM-DD
     *   to   = YYYY-MM-DD
     *
     * Streams a CSV file (UTF-8 BOM) with:
     *   - a summary block (appointments, revenue, cancelled)
     *   - a per-treatment breakdown
     *   - a by-month breakdown
     */
    public function csv(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            $range = $this->parse_range();

            if (!$range) {
                show_error('Both "from" and "to" parameters are required (YYYY-MM-DD).');
                return;
            }

            $report = $this->aggregate($range['from'], $range['to']);

            // UTF-8 BOM so Excel opens £ and accented characters correctly.
            $csv = "\xEF\xBB\xBF";

            $csv .= 'Bbeautiful — Report,' . $report['from'] . ' to ' . $report['to'] . "\n\n";

            $csv .= $this->csv_line(['Summary', '', '']);
            $csv .= $this->csv_line(['Appointments', $report['total_appointments'], '']);
            $csv .= $this->csv_line(['Revenue', $report['total_revenue'], '']);
            $csv .= $this->csv_line(['Cancelled', $report['total_cancelled'], '']);
            $csv .= "\n";

            $csv .= $this->csv_line(['By treatment', 'Count', 'Revenue (£)', 'Duration (min)']);
            foreach ($report['per_service'] as $row) {
                $csv .= $this->csv_line([$row['name'], $row['count'], $row['revenue'], $row['duration']]);
            }
            $csv .= "\n";

            $csv .= $this->csv_line(['By month', 'Appointments', 'Revenue (£)']);
            foreach ($report['by_month'] as $row) {
                $csv .= $this->csv_line([$row['month'], $row['count'], $row['revenue']]);
            }

            $filename = 'bbeautiful-report-' . $report['from'] . '_' . $report['to'] . '.csv';

            // Send as a CSV download.
            $this->output
                ->set_content_type('text/csv')
                ->set_header('Content-Disposition: attachment; filename="' . $filename . '"')
                ->set_output($csv);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Validate and parse the from/to range from the query string.
     *
     * @return array|null Associative array with 'from' and 'to' DateTime, or null when invalid.
     */
    private function parse_range(): ?array
    {
        $from = request('from');
        $to = request('to');

        if (!$from || !$to) {
            return null;
        }

        $from_date = new DateTime($from);
        $to_date = new DateTime($to);
        $to_date->setTime(23, 59, 59); // inclusive end of day

        return [
            'from' => $from_date,
            'to' => $to_date,
            'from_label' => $from,
            'to_label' => $to,
        ];
    }

    /**
     * Build a report for the given range.
     *
     * @param DateTime $from Inclusive start.
     * @param DateTime $to Inclusive end (end of day).
     *
     * @return array Report data.
     */
    private function aggregate(DateTime $from, DateTime $to): array
    {
        $appointments = $this->appointments_model->get();
        $filtered = [];

        foreach ($appointments as $appointment) {
            if (!empty($appointment['is_unavailability'])) {
                continue;
            }

            if (empty($appointment['id_services'])) {
                continue;
            }

            $start_dt = new DateTime($appointment['start_datetime']);

            if ($start_dt >= $from && $start_dt <= $to) {
                $filtered[] = $appointment;
            }
        }

        // Look up service metadata (price, duration, name) once.
        $services_cache = [];
        $services_list = $this->services_model->get();

        foreach ($services_list as $service) {
            $services_cache[(int) $service['id']] = $service;
        }

        $total_appointments = 0;
        $total_revenue = 0.0;
        $total_cancelled = 0;
        $per_service = [];
        $by_month = [];

        foreach ($filtered as $appointment) {
            $service_id = (int) $appointment['id_services'];
            $service = $services_cache[$service_id] ?? null;

            if (!$service) {
                continue;
            }

            $status = strtolower((string) ($appointment['status'] ?? ''));
            $is_cancelled = in_array($status, ['cancelled', 'canceled', 'deleted'], true);

            $appointment_count = $is_cancelled ? 0 : 1;
            $revenue = $is_cancelled ? 0.0 : (float) $service['price'];

            $total_appointments += $appointment_count;
            $total_revenue += $revenue;

            if ($is_cancelled) {
                $total_cancelled++;
            }

            // Per-service breakdown.
            if (!isset($per_service[$service_id])) {
                $per_service[$service_id] = [
                    'name' => $service['name'],
                    'count' => 0,
                    'revenue' => 0.0,
                    'duration' => (int) $service['duration'],
                    'cancelled' => 0,
                ];
            }

            $per_service[$service_id]['count'] += $appointment_count;
            $per_service[$service_id]['revenue'] += $revenue;

            if ($is_cancelled) {
                $per_service[$service_id]['cancelled']++;
            }

            // By-month breakdown.
            $month_key = $start_dt->format('Y-m');

            if (!isset($by_month[$month_key])) {
                $by_month[$month_key] = [
                    'month' => $month_key,
                    'count' => 0,
                    'revenue' => 0.0,
                ];
            }

            $by_month[$month_key]['count'] += $appointment_count;
            $by_month[$month_key]['revenue'] += $revenue;
        }

        // Sort per-service by count desc, by-month ascending.
        usort($per_service, fn ($a, $b) => $b['count'] <=> $a['count']);

        $by_month = array_values($by_month);
        usort($by_month, fn ($a, $b) => strcmp($a['month'], $b['month']));

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'total_appointments' => $total_appointments,
            'total_revenue' => round($total_revenue, 2),
            'total_cancelled' => $total_cancelled,
            'per_service' => array_values($per_service),
            'by_month' => $by_month,
        ];
    }

    /**
     * Quote a value as a single CSV line (RFC-4180-ish, with CRLF terminators
     * so Excel reads it cleanly). Values containing a comma, quote, or newline
     * are wrapped in quotes with doubled inner quotes.
     *
     * @param array $fields
     *
     * @return string
     */
    private function csv_line(array $fields): string
    {
        $escaped = array_map(function ($field) {
            $field = (string) $field;

            if (preg_match('/[",\r\n]/', $field)) {
                return '"' . str_replace('"', '""', $field) . '"';
            }

            return $field;
        }, $fields);

        return implode(',', $escaped) . "\r\n";
    }
}

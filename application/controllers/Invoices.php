<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Invoices Controller
 *
 * Bbeautiful customisation: generate, view, and manage invoices for customer
 * visits (HMRC / customer evidence). Not part of stock Easy!Appointments.
 *
 * @package Controller
 * @author Bbeautiful
 */
class Invoices extends EA_Controller
{
    /**
     * Invoices constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('invoices_model');
        $this->load->model('appointments_model');
        $this->load->model('services_model');
        $this->load->model('customers_model');
        $this->load->model('roles_model');
    }

    /**
     * Render the invoices list page (backend, admin only).
     */
    public function index(): void
    {
        method('get');

        session(['dest_url' => site_url('invoices')]);

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
            'page_title' => 'Invoices',
            'active_menu' => PRIV_APPOINTMENTS,
            'user_display_name' => $this->accounts->get_user_display_name($user_id),
            'privileges' => $this->roles_model->get_permissions_by_slug($role_slug),
            'date_format' => setting('date_format'),
            'time_format' => setting('time_format'),
        ]);

        $this->load->view('pages/invoices');
    }

    /**
     * Return the list of invoices as JSON.
     */
    public function list(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            $invoices = $this->invoices_model->get(null, null, null, 'create_datetime DESC');

            // Enrich with customer name + appointment date for display.
            $customers_cache = [];
            $appointments_cache = [];

            foreach ($invoices as &$invoice) {
                $customer_id = (int) $invoice['id_users_customer'];
                $appointment_id = (int) $invoice['id_appointments'];

                if (!isset($customers_cache[$customer_id])) {
                    try {
                        $customers_cache[$customer_id] = $this->customers_model->find($customer_id);
                    } catch (Throwable $e) {
                        $customers_cache[$customer_id] = null;
                    }
                }

                if (!isset($appointments_cache[$appointment_id])) {
                    try {
                        $appointments_cache[$appointment_id] = $this->appointments_model->find($appointment_id);
                    } catch (Throwable $e) {
                        $appointments_cache[$appointment_id] = null;
                    }
                }

                $customer = $customers_cache[$customer_id];
                $appointment = $appointments_cache[$appointment_id];

                $invoice['customer_name'] = $customer
                    ? trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))
                    : '—';

                $invoice['appointment_date'] = $appointment
                    ? $appointment['start_datetime']
                    : null;
            }

            json_response([
                'success' => true,
                'invoices' => $invoices,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Generate an invoice from an appointment (or a stacked booking group).
     *
     * POST. Body: appointment_id
     */
    public function generate(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('appointment_id', 'numeric');

            $appointment_id = (int) request('appointment_id');

            if (empty($appointment_id)) {
                throw new InvalidArgumentException('A valid appointment ID is required.');
            }

            $appointment = $this->appointments_model->find($appointment_id);

            // Build the services cache.
            $services_cache = [];
            foreach ($this->services_model->get() as $service) {
                $services_cache[(int) $service['id']] = $service;
            }

            $line_items = $this->invoices_model->build_line_items($appointment, $services_cache);

            if (empty($line_items['line_items'])) {
                throw new RuntimeException('No services found for this appointment.');
            }

            $customer_id = (int) $appointment['id_users_customer'];

            $invoice = [
                'number' => $this->invoices_model->next_number((int) date('Y')),
                'id_appointments' => $appointment_id,
                'id_users_customer' => $customer_id,
                'invoice_date' => date('Y-m-d H:i:s'),
                'total' => $line_items['total'],
                'status' => 'unpaid',
                'notes' => null,
            ];

            $invoice_id = $this->invoices_model->save($invoice);

            json_response([
                'success' => true,
                'invoice_id' => $invoice_id,
                'number' => $invoice['number'],
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Render a printable invoice document (HTML).
     *
     * GET. Query: id
     */
    public function view(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('id', 'numeric');

            $invoice_id = (int) request('id');

            $invoice = $this->invoices_model->find($invoice_id);

            $appointment = $this->appointments_model->find((int) $invoice['id_appointments']);
            $customer = $this->customers_model->find((int) $invoice['id_users_customer']);

            // Build line items (recompute from the appointment/group so the
            // document always reflects the current services).
            $services_cache = [];
            foreach ($this->services_model->get() as $service) {
                $services_cache[(int) $service['id']] = $service;
            }

            $line_items = $this->invoices_model->build_line_items($appointment, $services_cache);

            $company_name = setting('company_name');
            $company_email = setting('company_email');
            $company_link = setting('company_link');
            $company_logo = setting('company_logo');
            $company_color = setting('company_color');

            $this->load->view('pages/invoice_document', [
                'invoice' => $invoice,
                'appointment' => $appointment,
                'customer' => $customer,
                'line_items' => $line_items['line_items'],
                'total' => $line_items['total'],
                'company_name' => $company_name,
                'company_email' => $company_email,
                'company_link' => $company_link,
                'company_logo' => $company_logo,
                'company_color' => $company_color,
                'date_format' => setting('date_format'),
            ]);
        } catch (Throwable $e) {
            show_error($e->getMessage());
        }
    }

    /**
     * Mark an invoice as paid / unpaid.
     *
     * POST. Body: id, status ('paid' | 'unpaid')
     */
    public function mark_paid(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('id', 'numeric');
            check('status', 'string');

            $invoice_id = (int) request('id');
            $status = request('status');

            if (!in_array($status, ['paid', 'unpaid'], true)) {
                throw new InvalidArgumentException('Invalid invoice status.');
            }

            $invoice = $this->invoices_model->find($invoice_id);
            $invoice['status'] = $status;

            $this->invoices_model->save($invoice);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }

    /**
     * Delete an invoice.
     *
     * POST. Body: id
     */
    public function destroy(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('id', 'numeric');

            $invoice_id = (int) request('id');

            $this->invoices_model->delete($invoice_id);

            json_response([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            json_exception($e);
        }
    }
}

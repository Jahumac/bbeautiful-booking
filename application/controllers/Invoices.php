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

            // Snapshot the line items for this invoice.
            $this->invoices_model->save_line_items($invoice_id, $line_items['line_items']);

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

            $data = $this->prepare_invoice_data($invoice_id);

            $this->load->view('pages/invoice_document', $data);
        } catch (Throwable $e) {
            show_error($e->getMessage());
        }
    }

    /**
     * Stream a clean PDF download of an invoice via dompdf.
     *
     * GET. Query: id
     */
    public function pdf(): void
    {
        try {
            method('get');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            check('id', 'numeric');

            $invoice_id = (int) request('id');

            $data = $this->prepare_invoice_data($invoice_id);
            $data['is_pdf'] = true; // hides the on-page print button in the template

            // dompdf library (mounted into the fork's application/libraries/dompdf).
            $dompdf_lib = FCPATH . 'application/libraries/dompdf/autoload.inc.php';

            if (!file_exists($dompdf_lib)) {
                throw new RuntimeException('PDF library is not installed.');
            }

            require_once $dompdf_lib;

            $html = $this->load->view('pages/invoice_document', $data, true);

            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $filename = 'invoice-' . $data['invoice']['number'] . '.pdf';

            $dompdf->stream($filename, ['Attachment' => true]);
        } catch (Throwable $e) {
            show_error($e->getMessage());
        }
    }

    /**
     * Gather the shared invoice data for rendering (HTML view or PDF).
     *
     * @param int $invoice_id
     *
     * @return array
     */
    private function prepare_invoice_data(int $invoice_id): array
    {
        $invoice = $this->invoices_model->find($invoice_id);

        $appointment = null;
        $customer = [];

        try {
            $appointment = $this->appointments_model->find((int) $invoice['id_appointments']);
        } catch (Throwable $e) {
            $appointment = null;
        }

        try {
            $customer = $this->customers_model->find((int) $invoice['id_users_customer']);
        } catch (Throwable $e) {
            $customer = [];
        }

        // Load stored line items (works for both appointment-based and manual invoices).
        $line_items = $this->invoices_model->get_line_items($invoice_id);

        if (empty($line_items) && !empty($appointment)) {
            // Backwards-compatible fallback: recompute from the appointment.
            $services_cache = [];
            foreach ($this->services_model->get() as $service) {
                $services_cache[(int) $service['id']] = $service;
            }

            $computed = $this->invoices_model->build_line_items($appointment, $services_cache);
            $line_items = $computed['line_items'];
            $this->invoices_model->save_line_items($invoice_id, $line_items);
        }

        $total = $this->invoices_model->total_from_line_items($line_items);

        // VAT: read the configured rate (0% unless the salon is registered).
        // When 0%, no VAT lines appear and the total is the sum of prices.
        $vat_rate = (float) (setting('vat_rate') ?? 0);
        $vat_amount = round($total * $vat_rate / 100, 2);
        $grand_total = $vat_amount > 0 ? round($total + $vat_amount, 2) : $total;

        return [
            'invoice' => $invoice,
            'appointment' => $appointment,
            'customer' => $customer,
            'line_items' => $line_items,
            'total' => $total,
            'vat_rate' => $vat_rate,
            'vat_amount' => $vat_amount,
            'grand_total' => $grand_total,
            'company_name' => setting('company_name'),
            'company_email' => setting('company_email'),
            'company_link' => setting('company_link'),
            'company_logo' => setting('company_logo'),
            'company_color' => setting('company_color'),
            'date_format' => setting('date_format'),
            'is_pdf' => false,
        ];
    }

    /**
     * Create a manual invoice (no appointment backing — e.g. walk-in / salon sales).
     *
     * POST. Body: customer_name, customer_email?, customer_phone?, items (JSON
     * array of {description, quantity, duration, price}).
     */
    public function store_manual(): void
    {
        try {
            method('post');

            if (cannot('view', PRIV_APPOINTMENTS)) {
                abort(403, 'Forbidden');
            }

            $customer_name = (string) request('customer_name');
            $customer_email = (string) request('customer_email');
            $customer_phone = (string) request('customer_phone');
            $items_json = (string) request('items');

            if (empty(trim($customer_name))) {
                throw new InvalidArgumentException('A customer name is required.');
            }

            $items = json_decode($items_json, true);

            if (!is_array($items) || empty($items)) {
                throw new InvalidArgumentException('At least one line item is required.');
            }

            // Normalise line items.
            $normalised = [];

            foreach ($items as $item) {
                $description = trim((string) ($item['description'] ?? ''));

                if ($description === '') {
                    continue;
                }

                $normalised[] = [
                    'description' => $description,
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'duration' => !empty($item['duration']) ? (int) $item['duration'] : null,
                    'price' => round((float) ($item['price'] ?? 0), 2),
                ];
            }

            if (empty($normalised)) {
                throw new InvalidArgumentException('At least one line item with a description is required.');
            }

            // Find or create a customer by name/email (best-effort).
            $customer_id = null;

            // The app requires email + phone on customers; generate safe
            // placeholders when a walk-in manual invoice doesn't supply them.
            if (empty($customer_email)) {
                $customer_email = 'walkin-' . uniqid() . '@bbeautiful.local';
            }

            if (empty($customer_phone)) {
                $customer_phone = '00000000000';
            }

            $existing = $this->customers_model->get(['email' => $customer_email]);

            if (!empty($existing)) {
                $customer_id = (int) $existing[0]['id'];
            }

            if (!$customer_id) {
                $name_parts = preg_split('/\s+/', trim($customer_name), 2);
                $customer_id = $this->customers_model->save([
                    'first_name' => $name_parts[0] ?? $customer_name,
                    'last_name' => $name_parts[1] ?? '',
                    'email' => $customer_email,
                    'phone_number' => $customer_phone,
                ]);
            }

            $total = $this->invoices_model->total_from_line_items($normalised);

            $invoice = [
                'number' => $this->invoices_model->next_number((int) date('Y')),
                'id_appointments' => null,
                'id_users_customer' => $customer_id,
                'invoice_date' => date('Y-m-d H:i:s'),
                'total' => $total,
                'status' => 'unpaid',
                'notes' => 'Manual invoice',
            ];

            $invoice_id = $this->invoices_model->save($invoice);

            // Store line items as description/quantity/duration/price.
            $stored = [];

            foreach ($normalised as $item) {
                $stored[] = [
                    'name' => $item['description'],
                    'quantity' => $item['quantity'],
                    'duration' => $item['duration'],
                    'price' => $item['price'],
                ];
            }

            $this->invoices_model->save_line_items($invoice_id, $stored);

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

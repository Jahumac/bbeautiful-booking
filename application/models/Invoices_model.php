<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.0.0
 * ---------------------------------------------------------------------------- */

/**
 * Invoices model.
 *
 * Bbeautiful customisation: stores generated invoices for HMRC / customer
 * evidence. Not part of stock Easy!Appointments.
 *
 * @package Models
 */
class Invoices_model extends EA_Model
{
    /**
     * @var array
     */
    protected array $casts = [
        'id' => 'integer',
        'id_appointments' => 'integer',
        'id_users_customer' => 'integer',
        'total' => 'float',
    ];

    /**
     * Save (insert or update) an invoice.
     *
     * @param array $invoice Associative array with the invoice data.
     *
     * @return int Returns the invoice ID.
     */
    public function save(array $invoice): int
    {
        $this->validate($invoice);

        if (empty($invoice['id'])) {
            return $this->insert($invoice);
        }

        return $this->update($invoice);
    }

    /**
     * Validate the invoice data.
     *
     * @param array $invoice Associative array with the invoice data.
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $invoice): void
    {
        if (!empty($invoice['id'])) {
            $count = $this->db->get_where('invoices', ['id' => $invoice['id']])->num_rows();

            if (!$count) {
                throw new InvalidArgumentException(
                    'The provided invoice ID does not exist in the database: ' . $invoice['id'],
                );
            }
        }

        if (empty($invoice['number']) || !array_key_exists('id_appointments', $invoice) || empty($invoice['id_users_customer'])) {
            throw new InvalidArgumentException('Not all required invoice fields are provided.');
        }
    }

    /**
     * Insert a new invoice.
     *
     * @param array $invoice Associative array with the invoice data.
     *
     * @return int Returns the invoice ID.
     */
    protected function insert(array $invoice): int
    {
        $invoice['create_datetime'] = date('Y-m-d H:i:s');
        $invoice['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->insert('invoices', $invoice)) {
            throw new RuntimeException('Could not insert invoice.');
        }

        return $this->db->insert_id();
    }

    /**
     * Update an existing invoice.
     *
     * @param array $invoice Associative array with the invoice data.
     *
     * @return int Returns the invoice ID.
     */
    protected function update(array $invoice): int
    {
        $invoice['update_datetime'] = date('Y-m-d H:i:s');

        if (!$this->db->update('invoices', $invoice, ['id' => $invoice['id']])) {
            throw new RuntimeException('Could not update invoice record.');
        }

        return $invoice['id'];
    }

    /**
     * Get a specific invoice from the database.
     *
     * @param int $invoice_id The ID of the record to be returned.
     *
     * @return array Returns an array with the invoice data.
     */
    public function find(int $invoice_id): array
    {
        $invoice = $this->db->get_where('invoices', ['id' => $invoice_id])->row_array();

        if (!$invoice) {
            throw new InvalidArgumentException(
                'The provided invoice ID was not found in the database: ' . $invoice_id,
            );
        }

        $this->cast($invoice);

        return $invoice;
    }

    /**
     * Get invoices, optionally filtered.
     *
     * @param array|null $where
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $order_by
     *
     * @return array
     */
    public function get(
        array|string|null $where = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $order_by = null,
    ): array {
        if ($where !== null) {
            $this->db->where($where);
        }

        if ($order_by) {
            $this->db->order_by($this->quote_order_by($order_by));
        }

        $invoices = $this->db->get('invoices', $limit, $offset)->result_array();

        foreach ($invoices as &$invoice) {
            $this->cast($invoice);
        }

        return $invoices;
    }

    /**
     * Delete an invoice.
     *
     * @param int $invoice_id
     */
    public function delete(int $invoice_id): void
    {
        $this->db->delete('invoices', ['id' => $invoice_id]);
    }

    /**
     * Save the line items for an invoice (replacing any existing ones).
     *
     * @param int $invoice_id
     * @param array $line_items Each item: ['description', 'quantity', 'duration', 'price'].
     */
    public function save_line_items(int $invoice_id, array $line_items): void
    {
        $this->db->delete('invoice_items', ['id_invoices' => $invoice_id]);

        foreach ($line_items as $item) {
            $this->db->insert('invoice_items', [
                'id_invoices' => $invoice_id,
                'description' => (string) ($item['description'] ?? $item['name'] ?? ''),
                'quantity' => (int) ($item['quantity'] ?? 1),
                'duration' => !empty($item['duration']) ? (int) $item['duration'] : null,
                'price' => (float) ($item['price'] ?? 0),
                'create_datetime' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Load the stored line items for an invoice.
     *
     * @param int $invoice_id
     *
     * @return array
     */
    public function get_line_items(int $invoice_id): array
    {
        $items = $this->db
            ->order_by('id', 'ASC')
            ->get_where('invoice_items', ['id_invoices' => $invoice_id])
            ->result_array();

        $result = [];

        foreach ($items as $item) {
            $result[] = [
                'name' => $item['description'],
                'quantity' => (int) $item['quantity'],
                'duration' => $item['duration'] !== null ? (int) $item['duration'] : null,
                'price' => (float) $item['price'],
            ];
        }

        return $result;
    }

    /**
     * Compute the total from stored line items.
     *
     * @param array $line_items
     *
     * @return float
     */
    public function total_from_line_items(array $line_items): float
    {
        $total = 0.0;

        foreach ($line_items as $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $total += (float) ($item['price'] ?? 0) * $qty;
        }

        return round($total, 2);
    }

    /**
     * Find an existing invoice for an appointment, if any.
     *
     * @param int $appointment_id
     *
     * @return array|null
     */
    public function find_by_appointment(int $appointment_id): ?array
    {
        $invoice = $this->db->get_where('invoices', ['id_appointments' => $appointment_id])->row_array();

        if (!$invoice) {
            return null;
        }

        $this->cast($invoice);

        return $invoice;
    }

    /**
     * Generate the next sequential invoice number for the given year.
     *
     * Format: INV-YYYY-NNN (e.g. INV-2026-001).
     *
     * @param int $year
     *
     * @return string
     */
    public function next_number(int $year): string
    {
        $prefix = 'INV-' . $year . '-';

        $this->db->select('number');
        $this->db->from('invoices');
        $this->db->like('number', $prefix, 'after');
        $this->db->order_by('number', 'DESC');
        $this->db->limit(1);

        $row = $this->db->get()->row_array();

        if (!$row) {
            return $prefix . '001';
        }

        $last = (int) substr($row['number'], strlen($prefix));

        return $prefix . str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Build the line items for an appointment (or a stacked booking group).
     *
     * For a stacked booking, all records sharing the same booking_group are
     * aggregated into one invoice with one line item per service.
     *
     * @param array $appointment The primary appointment record.
     * @param array $services_cache Service metadata keyed by id.
     *
     * @return array [ 'line_items' => [...], 'total' => float ]
     */
    public function build_line_items(array $appointment, array $services_cache): array
    {
        $line_items = [];
        $total = 0.0;

        $appointments = [$appointment];

        // If this appointment is part of a stacked booking, pull in the siblings.
        if (!empty($appointment['booking_group'])) {
            $siblings = $this->db
                ->get_where('appointments', ['booking_group' => $appointment['booking_group']])
                ->result_array();

            if (count($siblings) > 1) {
                $appointments = $siblings;
            }
        }

        foreach ($appointments as $appt) {
            $service_id = (int) ($appt['id_services'] ?? 0);
            $service = $services_cache[$service_id] ?? null;

            if (!$service) {
                continue;
            }

            $line_items[] = [
                'name' => $service['name'],
                'duration' => (int) $service['duration'],
                'price' => (float) $service['price'],
            ];

            $total += (float) $service['price'];
        }

        return [
            'line_items' => $line_items,
            'total' => round($total, 2),
        ];
    }
}

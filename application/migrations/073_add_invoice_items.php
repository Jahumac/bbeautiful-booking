<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package    EasyAppointments
 * @author     A.Tselegidis <alextselegidis@gmail.com>
 * @copyright  Copyright (c) Alex Tselegidis
 * @license    https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link       https://easyappointments.org
 * @since      v1.5.0
 * ---------------------------------------------------------------------------- */

class Migration_Add_invoice_items extends CI_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('invoice_items')) {
            $this->db->query(
                'CREATE TABLE `ea_invoice_items` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `id_invoices` INT(11) NOT NULL,
                    `description` VARCHAR(255) NOT NULL,
                    `quantity` INT(11) NOT NULL DEFAULT 1,
                    `duration` INT(11) NULL,
                    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `create_datetime` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `id_invoices` (`id_invoices`),
                    CONSTRAINT `ea_invoice_items_ibfk_1` FOREIGN KEY (`id_invoices`) REFERENCES `ea_invoices` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
            );
        }

        // Allow manual invoices: id_appointments must be nullable (walk-in sales
        // have no backing appointment).
        if ($this->db->field_exists('id_appointments', 'invoices')) {
            $this->db->query(
                'ALTER TABLE `ea_invoices` MODIFY COLUMN `id_appointments` INT(11) NULL DEFAULT NULL'
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('invoice_items')) {
            $this->db->query('DROP TABLE `ea_invoice_items`');
        }
    }
}

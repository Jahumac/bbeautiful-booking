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

class Migration_Add_invoices extends CI_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        if (!$this->db->table_exists('invoices')) {
            $this->db->query(
                'CREATE TABLE `ea_invoices` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `number` VARCHAR(32) NOT NULL,
                    `id_appointments` INT(11) NOT NULL,
                    `id_users_customer` INT(11) NOT NULL,
                    `invoice_date` DATETIME NOT NULL,
                    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `status` VARCHAR(32) NOT NULL DEFAULT \'unpaid\',
                    `notes` TEXT NULL,
                    `create_datetime` DATETIME NOT NULL,
                    `update_datetime` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `number` (`number`),
                    KEY `id_appointments` (`id_appointments`),
                    KEY `id_users_customer` (`id_users_customer`),
                    CONSTRAINT `ea_invoices_ibfk_1` FOREIGN KEY (`id_appointments`) REFERENCES `ea_appointments` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `ea_invoices_ibfk_2` FOREIGN KEY (`id_users_customer`) REFERENCES `ea_users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
            );
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->table_exists('invoices')) {
            $this->db->query('DROP TABLE `ea_invoices`');
        }
    }
}

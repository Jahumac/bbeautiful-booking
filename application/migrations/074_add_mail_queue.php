<?php defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Add_mail_queue extends CI_Migration
{
    public function up(): void
    {
        if (!$this->db->table_exists('mail_queue')) {
            $this->db->query(
                'CREATE TABLE `ea_mail_queue` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `appointment_id` int(11) NOT NULL,
                    `manage_mode` tinyint(1) NOT NULL DEFAULT 0,
                    `status` varchar(20) NOT NULL DEFAULT \'pending\',
                    `attempts` int(11) NOT NULL DEFAULT 0,
                    `last_error` text NULL,
                    `created_at` datetime NOT NULL,
                    `updated_at` datetime NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `status_idx` (`status`),
                    KEY `appointment_id_idx` (`appointment_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
            );
        }
    }

    public function down(): void
    {
        if ($this->db->table_exists('mail_queue')) {
            $this->db->query('DROP TABLE `ea_mail_queue`');
        }
    }
}

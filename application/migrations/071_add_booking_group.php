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

class Migration_Add_booking_group extends CI_Migration
{
    /**
     * Upgrade method.
     */
    public function up(): void
    {
        // Add the booking_group column. This links separate appointment records that
        // belong to the same stacked booking (one customer, multiple back-to-back services).
        if (!$this->db->field_exists('booking_group', 'appointments')) {
            $this->db->query('ALTER TABLE `ea_appointments` ADD COLUMN `booking_group` VARCHAR(64) NULL AFTER `notes`');
        }
    }

    /**
     * Downgrade method.
     */
    public function down(): void
    {
        if ($this->db->field_exists('booking_group', 'appointments')) {
            $this->db->query('ALTER TABLE `ea_appointments` DROP COLUMN `booking_group`');
        }
    }
}

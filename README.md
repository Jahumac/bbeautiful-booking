<h1 align="center">
    <br>
    Bbeautiful — Booking System
    <br>
</h1>

<h4 align="center">
    A customised, self-hosted booking platform for <strong>Bbeautiful Beauty &amp; Nails</strong>.
</h4>

<p align="center">
  Built on <a href="https://easyappointments.org">Easy!Appointments</a> (GPL-3.0) and
  customised to fit the needs of a single-salon beauty business.
</p>

---

## What this is

This repository is a **customised fork of Easy!Appointments**, developed and maintained
for the **Bbeautiful** salon so it can accept online bookings. It is not the stock
Easy!Appointments — it adds features and a brand layer tailored to a single
beautician who offers hair, waxing, massage, lash/brow, and nail treatments.

It is **self-hosted**: all appointment and customer data stays under the salon's control
on the owner's own server. No third party sees or sells the data.

---

## Customisations (what we changed)

### Multi-service "stacked" booking

The headline feature. A customer can book **more than one treatment in a single booking**
with no rebooking and no repeated form-filling:

- The customer picks a first treatment, then clicks **"Add another treatment"** to stack
  more (e.g. *Back Wax + Eyebrow Tint + Express Gels*).
- Each stacked treatment is stored as **its own separate appointment record**, placed
  **back-to-back** with the others (same person, same booking) so the provider sees a
  clear timeline of what is happening in that visit.
- Because each treatment is its own record, **each one can be edited or removed
  independently** from the calendar using the app's normal edit flow (e.g. "I changed my
  mind on the third one").
- A shared **`booking_group`** value links the separate records together so they can still
  be managed as one customer booking.

### Clean-up / cooldown block

After a **stacked** booking, the **last** treatment inherits its own **slot interval**
(e.g. 15–30 minutes) as a cooldown block, so no other customer can book during the time
the beautician needs to clean and reset. This cooldown is **calendar-only** — it does not
inflate the time or price shown to the customer when they book.

### Branding

The whole customer-facing and admin experience is branded for **Bbeautiful**:

- Round **Bbeautiful** logo embedded across the booking page, login, logout, admin header,
  and browser tab (replacing the stock Easy!Appointments branding).
- Company name **Bbeautiful** and colour applied app-wide.
- Friendly copy:
  - Banner tagline: **"Beauty, nails &amp; more"**
  - Booking heading: **"Book your beauty treatment"**
  - Service label: **"Service — what would you like to book?"**
- Clean footers: **&copy; Bbeautiful** (years), no stock "Powered by Easy!Appointments"
  sponsor clutter on customer pages.

### Booking experience

- **Receipt-style breakdown** — as the customer stacks treatments, they see each service
  with its own duration and price, then a clean total (time + cost).
- **Categories in every dropdown** — the "Add another treatment" dropdown groups services
  by category (Waxing, Massage, Lashes/Brows, Nails) just like the main dropdown.

---

## Tech stack

- **PHP 8** (CodeIgniter) — application framework (as in Easy!Appointments)
- **MySQL 8** — data storage
- **Docker / Docker Compose** — deployment on the owner's server (Unraid)
- Served behind a reverse proxy

---

## Deployment

The app runs in Docker. The deployment on the owner's server uses the standard
Easy!Appointments Docker image with this repo's `application/`, `assets/`, and `config.php`
mounted over the image so the customisations take effect.

> The `application/migrations/` folder adds the schema changes (e.g. the `booking_group`
> column) on top of Easy!Appointments' own migrations.

---

## License

This project is a modified version of **Easy!Appointments**, which is licensed under the
**GPL-3.0** license. Those terms continue to apply to this fork. See the
[Easy!Appointments repository](https://github.com/alextselegidis/easyappointments) for
the original source and license.

This fork is maintained for **Bbeautiful** and is self-hosted and self-controlled.

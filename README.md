# Ruth Estates - Real Estate Management System

A small property listing and viewing-request system built with plain PHP, MySQL, HTML, CSS and a little JavaScript. It has three kinds of users:

| Role | Who | What they can do |
|---|---|---|
| **Landlord (super admin)** | The owner of the agency | Everything: overview stats, properties, rooms, viewing requests, room bookings, payments, reports, agency settings, all messages, plus a **People** page to create manager/customer accounts, block accounts and reset passwords |
| **Manager (admin)** | Office staff | List/edit/delete properties, add and manage rooms/units, approve or decline viewing requests and room bookings, record payments, view reports, manage their account settings, chat with customers and the landlord |
| **Customer** | Anyone | Browse properties, request a viewing, book an available room, withdraw requests, raise maintenance requests, pay invoices online, chat with the office, update own profile |

The parts talk to each other: a customer sends a viewing request or books a room -> the office approves/declines it and writes a short reply -> the customer sees the reply and becomes an accepted customer on their dashboard. Anyone can also send direct messages to any other user, with unread badges everywhere.

## Folder layout

```
realestate/
  database.sql        -> create and seed the database (users, properties, rooms, bookings)
  config/db.php       -> MySQL settings (edit if needed)
  includes/           -> shared header, footer, helper functions
  assets/             -> css, js, real property photos and placeholder images
  index.php           -> public home page
  properties.php      -> searchable property list with pagination
  property.php        -> property detail + available rooms/units
  book.php            -> room booking form (guests are asked to create an account first)
  customer/           -> customer dashboard, inquiries, bookings, payments, maintenance, messages, profile
  manager/            -> staff dashboard, properties, rooms, inquiries, bookings, payments, reports, settings, messages
  landlord/           -> super admin dashboard (overview + people management)
  uploads/            -> property and room photos uploaded by staff (PHP execution blocked)
```

## Setting it up

1. **Database.** Start MySQL, then import `database.sql` one of these ways:
   - phpMyAdmin: open it, go to Import, choose `database.sql`
   - Command line: `mysql -u root < database.sql`
2. **Config.** Open `config/db.php` and check the MySQL username/password (`root` with no password by default, as on XAMPP/WAMP). The currency label (`UGX`) is also set here.
3. **Serve.** Put the `realestate` folder inside `htdocs` (XAMPP) or `www` (WAMP), then visit:
   `http://localhost/ruth%20%26%20Group/realestate/`

For a very quick preview without Apache: `php -S localhost:8090` inside the `realestate` folder, then visit `http://localhost:8090`.

## Demo logins (created by the SQL file)

| Role | Email | Password |
|---|---|---|
| Landlord | `landlord@ruth-estates.com` | `landlord123` |
| Manager | `manager@ruth-estates.com` | `manager123` |
| Customer | `customer@ruth-estates.com` | `customer123` |

Please change the passwords after the demo (Landlord -> People -> Reset, customers can change their own under My profile).

## How a full cycle looks

1. A customer opens a property, sees its rooms and clicks **Book this room** (new visitors are asked to create an account first and are returned straight to the booking form).
2. The manager or landlord sees the request under **Room bookings**, picks Approve/Decline and types a reply. Approving marks the room occupied everywhere (room card, public listing and property status), makes the customer an accepted customer, notifies anyone else waiting for that room and creates a pending payment invoice.
3. The customer sees the confirmation, the office reply and the "accepted customer" note on their bookings page and dashboard.
4. Viewing requests work the same way under **Viewing requests**.
5. The customer pays the invoice from **My payments -> Pay online**, and raises any repair issue from **Maintenance**. Staff handle repairs from **Maintenance** and the customer sees the reply in their portal.
6. The landlord watches the whole thing from the overview and manages accounts from **People**.

## Good to know

- Properties come seeded with real photos and each property has rooms/units. Staff manage rooms from **Properties -> Rooms -> Manage** (add, edit, delete, upload a room photo).
- Approving a booking marks the room occupied, updates the property status when all its rooms are taken, and creates a pending invoice under **Payments**. Staff can reconcile payments there, review **Reports** and manage contact details or their own account under **Settings**.
- Customers pay invoices online from **My payments** (mobile money or card). The included checkout confirms payment instantly for the demo and can be replaced with a Flutterwave/Pesapal/MTN MoMo API call in production.
- Maintenance requests are submitted from the customer portal and managed by staff under **Maintenance**, with replies sent back to the customer.
- The site currency is `UGX` and is set once in `config/db.php`.
- Photos are saved to `uploads/` (JPG/PNG/WebP up to 3MB); sample listings use the bundled photos in `assets/img/`.
- Blocked users cannot log in; the landlord's own and other landlord accounts cannot be blocked or edited by mistake.
- Passwords are hashed with `password_hash`. All database queries use prepared statements, forms use CSRF tokens and session cookies are HttpOnly + SameSite.
- For a public deployment, add HTTPS and a proper MySQL user (avoid root) before going live.

<?php

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input()
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '">';
}

function verify_csrf()
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        flash('error', 'Your session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

function redirect($path)
{
    header('Location: ' . $path);
    exit;
}

function auth_user()
{
    return $_SESSION['user'] ?? null;
}

function is_role($roles)
{
    $user = auth_user();
    return $user && in_array($user['role'], (array) $roles, true);
}

function require_login()
{
    if (!auth_user()) {
        flash('error', 'Please log in to continue.');
        redirect(ROOT_URL . 'login.php');
    }
}

function require_role($roles)
{
    require_login();
    if (!is_role($roles)) {
        flash('error', 'You do not have permission to view that page.');
        redirect(dashboard_url());
    }
}

function dashboard_url()
{
    $user = auth_user();
    if (!$user) {
        return ROOT_URL . 'index.php';
    }
    return ROOT_URL . $user['role'] . '/dashboard.php';
}

function messages_url()
{
    $user = auth_user();
    if (!$user) {
        return ROOT_URL . 'login.php';
    }
    $folder = $user['role'] === 'customer' ? 'customer' : 'manager';
    return ROOT_URL . $folder . '/messages.php';
}

function flash($type, $text)
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function get_flashes()
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function property_price($row)
{
    $label = CURRENCY . ' ' . number_format((float) $row['price']);
    return $row['listed_for'] === 'rent' ? $label . ' / month' : $label;
}

function money($amount)
{
    return CURRENCY . ' ' . number_format((float) $amount);
}

function ensure_app_schema($pdo)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT DEFAULT NULL,
            customer_id INT NOT NULL,
            property_id INT DEFAULT NULL,
            room_id INT DEFAULT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            method ENUM(\'cash\',\'mobile_money\',\'bank\',\'card\') NOT NULL DEFAULT \'cash\',
            reference VARCHAR(80) DEFAULT NULL,
            status ENUM(\'pending\',\'paid\',\'cancelled\') NOT NULL DEFAULT \'pending\',
            note VARCHAR(255) DEFAULT NULL,
            recorded_by INT DEFAULT NULL,
            paid_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_payments_booking (booking_id),
            CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
            CONSTRAINT fk_payment_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
            CONSTRAINT fk_payment_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
        ) ENGINE=InnoDB');

        $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(60) PRIMARY KEY,
            setting_value TEXT
        ) ENGINE=InnoDB');

        $pdo->exec('CREATE TABLE IF NOT EXISTS maintenance_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            property_id INT DEFAULT NULL,
            room_id INT DEFAULT NULL,
            booking_id INT DEFAULT NULL,
            subject VARCHAR(150) NOT NULL,
            description TEXT NOT NULL,
            priority ENUM(\'low\',\'normal\',\'high\',\'urgent\') NOT NULL DEFAULT \'normal\',
            status ENUM(\'open\',\'in_progress\',\'resolved\',\'closed\') NOT NULL DEFAULT \'open\',
            reply TEXT DEFAULT NULL,
            handled_by INT DEFAULT NULL,
            responded_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_maintenance_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_maintenance_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
            CONSTRAINT fk_maintenance_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
            CONSTRAINT fk_maintenance_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
        ) ENGINE=InnoDB');

        $pdo->exec('CREATE TABLE IF NOT EXISTS maintenance_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            photo VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_maintenance_photo_request FOREIGN KEY (request_id) REFERENCES maintenance_requests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');

        $defaults = [
            'contact_address' => 'Tuskle Road, Kampala',
            'contact_email' => 'info@ruth-estates.com',
            'contact_phone' => '0700 000 000',
            'payment_instructions' => 'Pay by mobile money or bank transfer and quote your booking reference.',
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        // Backfill invoices for bookings that were approved before payments existed.
        $pdo->exec('INSERT INTO payments (booking_id, customer_id, property_id, room_id, amount, status, note)
            SELECT b.id, b.customer_id, b.property_id, b.room_id, r.price, "pending", CONCAT("Invoice for ", r.name)
            FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            LEFT JOIN payments pay ON pay.booking_id = b.id
            WHERE b.status = "approved" AND pay.id IS NULL AND r.price > 0');
    } catch (Throwable $e) {
        // database.sql creates these tables; skip silently if the user lacks DDL rights.
    }
}

function app_setting($key, $default = '')
{
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
            foreach ($stmt->fetchAll() as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function set_app_setting(PDO $pdo, $key, $value)
{
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute([$key, $value, $value]);
}

function sync_property_status(PDO $pdo, $propertyId)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(status = "available"), 0) AS available FROM rooms WHERE property_id = ?');
    $stmt->execute([(int) $propertyId]);
    $row = $stmt->fetch();
    if ($row && (int) $row['total'] > 0) {
        $status = (int) $row['available'] > 0 ? 'available' : 'taken';
        $pdo->prepare('UPDATE properties SET status = ? WHERE id = ?')->execute([$status, (int) $propertyId]);
    }
}

function create_booking_invoice(PDO $pdo, array $booking)
{
    $stmt = $pdo->prepare('SELECT id FROM payments WHERE booking_id = ? LIMIT 1');
    $stmt->execute([(int) $booking['id']]);
    if ($stmt->fetch()) {
        return;
    }

    $amount = (float) ($booking['room_price'] ?? 0);
    if ($amount <= 0) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO payments (booking_id, customer_id, property_id, room_id, amount, status, note) VALUES (?, ?, ?, ?, ?, "pending", ?)');
    $stmt->execute([
        (int) $booking['id'],
        (int) $booking['customer_id'],
        (int) $booking['property_id'],
        (int) $booking['room_id'],
        $amount,
        'Invoice for ' . ($booking['room_name'] ?? 'room'),
    ]);
}

function approve_booking(PDO $pdo, $bookingId, $reply, $staffId)
{
    $stmt = $pdo->prepare('SELECT b.*, r.status AS room_status, r.name AS room_name, r.price AS room_price FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE b.id = ?');
    $stmt->execute([(int) $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        return ['ok' => false, 'message' => 'That booking request no longer exists.'];
    }
    if ($booking['status'] !== 'pending') {
        return ['ok' => false, 'message' => 'That booking request was already handled.'];
    }
    if ($booking['room_status'] !== 'available') {
        return ['ok' => false, 'message' => 'That room is no longer available. Decline the request instead.'];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE bookings SET status = "approved", reply = ?, responded_at = NOW() WHERE id = ? AND status = "pending"');
        $stmt->execute([$reply, (int) $bookingId]);
        if (!$stmt->rowCount()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'That booking request was already handled.'];
        }

        $pdo->prepare('UPDATE rooms SET status = "occupied" WHERE id = ?')->execute([(int) $booking['room_id']]);

        $others = $pdo->prepare('SELECT id, customer_id FROM bookings WHERE room_id = ? AND id != ? AND status = "pending"');
        $others->execute([(int) $booking['room_id'], (int) $bookingId]);
        $others = $others->fetchAll();
        if ($others) {
            $pdo->prepare('UPDATE bookings SET status = "rejected", reply = ?, responded_at = NOW() WHERE room_id = ? AND id != ? AND status = "pending"')
                ->execute(['The room was let to another customer.', (int) $booking['room_id'], (int) $bookingId]);
            $msg = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)');
            foreach ($others as $other) {
                $msg->execute([(int) $staffId, (int) $other['customer_id'], 'We are sorry, ' . $booking['room_name'] . ' has been let to another customer. Your booking request was not successful.']);
            }
        }

        $body = 'Regarding your booking for ' . $booking['room_name'] . ': ' . ($reply !== '' ? $reply : 'Your booking has been approved. Welcome!');
        $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)')->execute([(int) $staffId, (int) $booking['customer_id'], $body]);

        create_booking_invoice($pdo, $booking);
        sync_property_status($pdo, (int) $booking['property_id']);

        $pdo->commit();
        return ['ok' => true, 'message' => 'The booking was approved, the room was marked occupied and the customer has been notified.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => 'Could not approve the booking. Please try again.'];
    }
}

function payment_method_label($method)
{
    return ucwords(str_replace('_', ' ', (string) $method));
}

function safe_next()
{
    $next = $_REQUEST['next'] ?? '';
    if (!is_string($next) || $next === '') {
        return '';
    }
    if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $next) || strpos($next, '..') !== false) {
        return '';
    }
    if (!preg_match('~^[A-Za-z0-9_\-./?=&%#]+$~', $next)) {
        return '';
    }
    return $next;
}

function unread_total()
{
    global $pdo;
    $user = auth_user();
    if (!$user) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$user['id']]);
    return (int) $stmt->fetchColumn();
}

function property_thumb($row)
{
    return $row['photo'] ? ROOT_URL . $row['photo'] : ROOT_URL . 'assets/img/placeholder.svg';
}

function status_badge($status)
{
    $classes = ['pending' => 'badge badge-pending', 'approved' => 'badge badge-approved', 'rejected' => 'badge badge-rejected', 'available' => 'badge badge-approved', 'taken' => 'badge badge-muted', 'occupied' => 'badge badge-muted', 'maintenance' => 'badge badge-pending', 'active' => 'badge badge-approved', 'blocked' => 'badge badge-rejected', 'rent' => 'badge badge-muted', 'sale' => 'badge badge-muted', 'manager' => 'badge badge-admin', 'landlord' => 'badge badge-admin', 'customer' => 'badge badge-approved', 'paid' => 'badge badge-approved', 'cancelled' => 'badge badge-rejected', 'open' => 'badge badge-pending', 'in_progress' => 'badge badge-admin', 'resolved' => 'badge badge-approved', 'closed' => 'badge badge-muted', 'low' => 'badge badge-muted', 'normal' => 'badge badge-muted', 'high' => 'badge badge-pending', 'urgent' => 'badge badge-rejected'];
    $labels = ['in_progress' => 'In progress', 'mobile_money' => 'Mobile money'];
    return '<span class="' . ($classes[$status] ?? 'badge badge-muted') . '">' . esc($labels[$status] ?? ucfirst($status)) . '</span>';
}

function friendly_date($timestamp)
{
    $timestamp = trim((string) $timestamp);
    if ($timestamp === '' || !strtotime($timestamp)) {
        return 'not yet';
    }
    return date('j M Y', strtotime($timestamp));
}

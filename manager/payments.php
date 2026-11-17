<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role(['manager', 'landlord']);
$user = auth_user();

$methods = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'bank' => 'Bank transfer', 'card' => 'Card'];
$statuses = ['pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_paid') {
        $id = (int) ($_POST['id'] ?? 0);
        $method = $_POST['method'] ?? 'cash';
        $reference = trim($_POST['reference'] ?? '');
        if (!isset($methods[$method])) {
            $method = 'cash';
        }
        $stmt = $pdo->prepare('UPDATE payments SET status = "paid", method = ?, reference = ?, paid_at = NOW(), recorded_by = ? WHERE id = ? AND status = "pending"');
        $stmt->execute([$method, $reference !== '' ? $reference : null, $user['id'], $id]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Payment marked as paid.' : 'That payment could not be updated. Only pending payments can be marked paid.');
        redirect('payments.php');
    }

    if ($action === 'cancel') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE payments SET status = "cancelled" WHERE id = ? AND status = "pending"');
        $stmt->execute([$id]);
        flash($stmt->rowCount() ? 'success' : 'error', $stmt->rowCount() ? 'Payment cancelled.' : 'Only pending payments can be cancelled.');
        redirect('payments.php');
    }

    if ($action === 'create') {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $roomId = (int) ($_POST['room_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $method = $_POST['method'] ?? 'cash';
        $status = $_POST['status'] ?? 'pending';
        $reference = trim($_POST['reference'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $propertyId = null;
        $error = null;

        if (!isset($methods[$method])) {
            $method = 'cash';
        }
        if (!isset($statuses[$status])) {
            $status = 'pending';
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = "customer"');
        $stmt->execute([$customerId]);
        if (!$stmt->fetch()) {
            $error = 'Choose a valid customer.';
        } elseif ($amount <= 0) {
            $error = 'Enter an amount greater than zero.';
        }

        $roomName = 'Manual payment';
        if (!$error && $roomId) {
            $stmt = $pdo->prepare('SELECT id, property_id, name FROM rooms WHERE id = ?');
            $stmt->execute([$roomId]);
            $room = $stmt->fetch();
            if (!$room) {
                $error = 'Choose a valid room or leave it blank.';
            } else {
                $propertyId = (int) $room['property_id'];
                $roomName = $room['name'];
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare('INSERT INTO payments (customer_id, property_id, room_id, amount, method, reference, status, note, recorded_by, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $customerId,
                $propertyId,
                $roomId ?: null,
                $amount,
                $method,
                $reference !== '' ? $reference : null,
                $status,
                $note !== '' ? $note : 'Manual payment - ' . $roomName,
                $user['id'],
                $status === 'paid' ? date('Y-m-d H:i:s') : null,
            ]);
            flash('success', 'Payment recorded.');
            redirect('payments.php');
        }

        flash('error', $error);
        redirect('payments.php');
    }
}

$filter = $_GET['status'] ?? '';
$where = '';
$params = [];
if (isset($statuses[$filter])) {
    $where = ' WHERE pay.status = ?';
    $params[] = $filter;
}

$sql = 'SELECT pay.*, u.name AS customer_name, u.email AS customer_email, r.name AS room_name, p.title AS property_title
    FROM payments pay
    JOIN users u ON u.id = pay.customer_id
    LEFT JOIN rooms r ON r.id = pay.room_id
    LEFT JOIN properties p ON p.id = pay.property_id' . $where . '
    ORDER BY pay.created_at DESC, pay.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totals = $pdo->query('SELECT
    COALESCE(SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END), 0) AS collected,
    COALESCE(SUM(CASE WHEN status = "pending" THEN amount ELSE 0 END), 0) AS outstanding,
    COUNT(*) AS invoices
    FROM payments')->fetch();

$customers = $pdo->query('SELECT id, name, email FROM users WHERE role = "customer" AND status = "active" ORDER BY name')->fetchAll();
$rooms = $pdo->query('SELECT r.id, r.name, r.price, p.title FROM rooms r JOIN properties p ON p.id = r.property_id ORDER BY p.title, r.name')->fetchAll();

$page_title = 'Payments';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>Payments</h1>
  <p class="muted">Invoices are created automatically when a booking is approved. Record and reconcile rent payments here.</p>

  <div class="stat-grid">
    <div class="stat"><span><?php echo esc(money($totals['collected'])); ?></span>Collected</div>
    <div class="stat"><span><?php echo esc(money($totals['outstanding'])); ?></span>Outstanding</div>
    <div class="stat"><span><?php echo (int) $totals['invoices']; ?></span>Invoices</div>
  </div>

  <?php if (app_setting('payment_instructions') !== ''): ?>
    <div class="callout"><p><b>Payment instructions:</b> <?php echo esc(app_setting('payment_instructions')); ?></p></div>
  <?php endif; ?>

  <div class="panel">
    <h2>Record a manual payment</h2>
    <form method="post" class="field-row field-row-4">
      <?php echo csrf_input(); ?>
      <input type="hidden" name="action" value="create">
      <div>
        <label for="customer_id">Customer</label>
        <select id="customer_id" name="customer_id" required>
          <option value="">Choose customer</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>"><?php echo esc($c['name'] . ' (' . $c['email'] . ')'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="room_id">Room / unit <small class="muted">(optional)</small></label>
        <select id="room_id" name="room_id">
          <option value="">No specific room</option>
          <?php foreach ($rooms as $r): ?>
            <option value="<?php echo (int) $r['id']; ?>"><?php echo esc($r['title'] . ' - ' . $r['name'] . ' (' . money($r['price']) . ')'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="amount">Amount (<?php echo CURRENCY; ?>)</label>
        <input type="number" id="amount" name="amount" min="1" step="0.01" required>
      </div>
      <div>
        <label for="method">Method</label>
        <select id="method" name="method">
          <?php foreach ($methods as $value => $label): ?>
            <option value="<?php echo esc($value); ?>"><?php echo esc($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="reference">Reference <small class="muted">(optional)</small></label>
        <input type="text" id="reference" name="reference" placeholder="e.g. MoMo ref">
      </div>
      <div>
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="pending">Pending</option>
          <option value="paid">Paid</option>
        </select>
      </div>
      <div>
        <label for="note">Note <small class="muted">(optional)</small></label>
        <input type="text" id="note" name="note" maxlength="255">
      </div>
      <button class="btn" type="submit">Record payment</button>
    </form>
  </div>

  <div class="panel-head">
    <h2>All payments</h2>
    <form method="get" class="filter-bar slim">
      <select name="status" onchange="this.form.submit()">
        <option value="">Everyone</option>
        <?php foreach ($statuses as $value => $label): ?>
          <option value="<?php echo esc($value); ?>"<?php echo $filter === $value ? ' selected' : ''; ?>><?php echo esc($label); ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="panel">
    <?php if (!$payments): ?>
      <p class="muted">No payments yet. Approve a booking or record a manual payment above.</p>
    <?php else: ?>
      <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Invoice</th><th>Customer</th><th>Property / room</th><th>Amount</th><th>Status</th><th>Recorded</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $pay): ?>
          <tr>
            <td>
              <b>#<?php echo (int) $pay['id']; ?></b>
              <?php if ($pay['booking_id']): ?><br><small class="muted">Booking #<?php echo (int) $pay['booking_id']; ?></small><?php endif; ?>
            </td>
            <td>
              <?php echo esc($pay['customer_name']); ?><br>
              <small class="muted"><?php echo esc($pay['customer_email']); ?></small>
            </td>
            <td>
              <?php echo esc($pay['property_title'] ?: 'Manual'); ?>
              <?php if ($pay['room_name']): ?><br><small class="muted"><?php echo esc($pay['room_name']); ?></small><?php endif; ?>
            </td>
            <td><?php echo esc(money($pay['amount'])); ?></td>
            <td>
              <?php echo status_badge($pay['status']); ?><br>
              <small class="muted"><?php echo esc(payment_method_label($pay['method'])); ?><?php echo $pay['reference'] ? ' - ' . esc($pay['reference']) : ''; ?></small>
            </td>
            <td class="muted"><?php echo esc(friendly_date($pay['paid_at'] ?: $pay['created_at'])); ?></td>
            <td class="cell-actions">
              <?php if ($pay['status'] === 'pending'): ?>
                <form class="inline-actions" method="post">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="id" value="<?php echo (int) $pay['id']; ?>">
                  <select name="method">
                    <?php foreach ($methods as $value => $label): ?>
                      <option value="<?php echo esc($value); ?>"><?php echo esc($label); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input class="input-tiny" type="text" name="reference" placeholder="Reference">
                  <button class="btn btn-tiny" name="action" value="mark_paid" type="submit">Mark paid</button>
                  <button class="btn btn-tiny btn-danger" name="action" value="cancel" type="submit" data-confirm="Cancel this payment?">Cancel</button>
                </form>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

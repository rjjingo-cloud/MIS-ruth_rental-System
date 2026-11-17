<?php
require_once dirname(__DIR__) . '/config/db.php';
require_role('customer');
$user = auth_user();

$onlineMethods = ['mobile_money' => 'Mobile money', 'card' => 'Debit or credit card'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $method = $_POST['method'] ?? 'mobile_money';
    if (!isset($onlineMethods[$method])) {
        $method = 'mobile_money';
    }
    $stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? AND customer_id = ? AND status = "pending"');
    $stmt->execute([$paymentId, $user['id']]);
    $payment = $stmt->fetch();
    if (!$payment) {
        flash('error', 'That invoice is not available for payment.');
        redirect('payments.php');
    }

    $reference = 'ONLINE-' . strtoupper(bin2hex(random_bytes(4)));
    $note = trim((string) $payment['note']);
    $note = ($note !== '' ? $note . ' - ' : '') . 'Paid online by customer';

    $stmt = $pdo->prepare('UPDATE payments SET status = "paid", method = ?, reference = ?, paid_at = NOW(), recorded_by = ?, note = ? WHERE id = ? AND customer_id = ? AND status = "pending"');
    $stmt->execute([$method, $reference, $user['id'], $note, $paymentId, $user['id']]);
    if ($stmt->rowCount()) {
        flash('success', 'Payment successful. Your reference is ' . $reference . '.');
    } else {
        flash('error', 'That invoice has already been paid or cancelled.');
    }
    redirect('payments.php');
}

$stmt = $pdo->prepare('SELECT pay.*, r.name AS room_name, p.title AS property_title, p.location FROM payments pay LEFT JOIN rooms r ON r.id = pay.room_id LEFT JOIN properties p ON p.id = pay.property_id WHERE pay.customer_id = ? ORDER BY (pay.status = "pending") DESC, pay.created_at DESC');
$stmt->execute([$user['id']]);
$payments = $stmt->fetchAll();

$totalPaid = 0.0;
$totalPending = 0.0;
foreach ($payments as $payment) {
    if ($payment['status'] === 'paid') {
        $totalPaid += (float) $payment['amount'];
    } elseif ($payment['status'] === 'pending') {
        $totalPending += (float) $payment['amount'];
    }
}

$payId = (int) ($_GET['pay'] ?? 0);
$toPay = null;
foreach ($payments as $payment) {
    if ((int) $payment['id'] === $payId && $payment['status'] === 'pending') {
        $toPay = $payment;
        break;
    }
}

$page_title = 'My payments';
include dirname(__DIR__) . '/includes/header.php';
?>
<div class="dash-layout">
<?php include __DIR__ . '/menu.php'; ?>
<main class="dash-main">
  <h1>My payments</h1>
  <p class="muted">View your invoices and pay outstanding balances online.</p>

  <div class="stat-grid">
    <div class="stat"><span><?php echo esc(money($totalPending)); ?></span>Outstanding balance</div>
    <div class="stat"><span><?php echo esc(money($totalPaid)); ?></span>Paid to date</div>
    <div class="stat"><span><?php echo count($payments); ?></span>Invoices</div>
  </div>

  <?php if ($toPay): ?>
    <div class="panel">
      <h2>Pay invoice #<?php echo (int) $toPay['id']; ?></h2>
      <div class="note">
        <p><b>Property:</b> <?php echo esc($toPay['property_title'] ?: 'General payment'); ?></p>
        <p><b>Room:</b> <?php echo esc($toPay['room_name'] ?: 'N/A'); ?></p>
        <p><b>Amount due:</b> <span class="price"><?php echo esc(money($toPay['amount'])); ?></span></p>
      </div>
      <form method="post" class="field-row">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="pay">
        <input type="hidden" name="payment_id" value="<?php echo (int) $toPay['id']; ?>">
        <label>Payment method
          <select name="method" required>
            <?php foreach ($onlineMethods as $value => $label): ?>
              <option value="<?php echo esc($value); ?>"><?php echo esc($label); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn" type="submit" data-confirm="Pay <?php echo esc(money($toPay['amount'])); ?> now?">Pay now</button>
        <a class="btn btn-ghost" href="payments.php">Cancel</a>
      </form>
      <p class="muted small">Payments are confirmed instantly in this demo checkout. In production this step connects to your mobile money or card gateway.</p>
    </div>
  <?php endif; ?>

  <div class="panel">
    <?php if (!$payments): ?>
      <p class="muted">You have no invoices yet. An invoice is created automatically when a booking is approved.</p>
      <p><a class="btn btn-small" href="bookings.php">View my bookings</a></p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Invoice</th><th>Property / room</th><th>Amount</th><th>Status</th><th>Method</th><th>Date</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($payments as $payment): ?>
              <tr>
                <td>#<?php echo (int) $payment['id']; ?></td>
                <td>
                  <b><?php echo esc($payment['property_title'] ?: 'General payment'); ?></b>
                  <?php if ($payment['room_name']): ?><br><small class="muted"><?php echo esc($payment['room_name']); ?></small><?php endif; ?>
                </td>
                <td><?php echo esc(money($payment['amount'])); ?></td>
                <td><?php echo status_badge($payment['status']); ?></td>
                <td><?php echo esc(payment_method_label($payment['method'])); ?><?php if ($payment['reference']): ?><br><small class="muted"><?php echo esc($payment['reference']); ?></small><?php endif; ?></td>
                <td><?php echo esc(friendly_date($payment['paid_at'] ?: $payment['created_at'])); ?></td>
                <td>
                  <?php if ($payment['status'] === 'pending'): ?>
                    <a class="btn btn-tiny" href="payments.php?pay=<?php echo (int) $payment['id']; ?>">Pay online</a>
                  <?php else: ?>
                    <span class="muted small">&mdash;</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Payment instructions</h2>
    <p class="muted"><?php echo nl2br(esc(app_setting('payment_instructions'))); ?></p>
    <p class="muted small">Need help with an invoice? <a href="messages.php">Send the office a message</a>.</p>
  </div>
</main>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

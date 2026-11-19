<?php
$activeProducts = array_values(array_filter($products, static fn (array $p): bool => $p['status'] === 'active'));
$productMap = [];
foreach ($activeProducts as $product) {
    $productMap[(int)$product['id']] = [
        'name' => $product['name'],
        'price' => (float)$product['selling_price'],
        'stock' => (float)$product['current_stock'],
        'unit' => $product['unit'],
        'track' => (int)$product['track_stock'] === 1,
    ];
}
$categoryLabels = [
    'snack' => 'Snack', 'beverage' => 'Beverage', 'stationery' => 'Stationery',
    'uniform' => 'Uniform', 'scholastic' => 'Scholastic', 'textbook' => 'Textbook',
    'supplies' => 'Supplies', 'other' => 'Other',
];
$money = static fn ($value): string => number_format((float)$value, 0);
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Canteen &amp; shop</h1>
    <p class="text-muted small mb-0">Sell items, manage product stock and keep student wallets up to date.</p>
  </div>
  <div class="text-end">
    <div class="small text-muted mb-2">Today: <strong><?= (int)$today['sale_count'] ?></strong> sale<?= (int)$today['sale_count'] === 1 ? '' : 's' ?> · <?= $money($today['total']) ?> UGX</div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('canteen/wallet') ?>"><i class="bi bi-wallet2 me-1"></i>Student wallets</a>
  </div>
</div>

<ul class="nav nav-pills app-pills mb-3" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-sell" type="button" role="tab"><i class="bi bi-cart-plus me-1"></i>Sell</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-products" type="button" role="tab"><i class="bi bi-box-seam me-1"></i>Products <span class="badge text-bg-light ms-1"><?= count($products) ?></span></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sales" type="button" role="tab"><i class="bi bi-receipt me-1"></i>Sales <span class="badge text-bg-light ms-1"><?= count($sales) ?></span></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-till" type="button" role="tab"><i class="bi bi-cash-coin me-1"></i>Till <span class="badge text-bg-light ms-1"><?= count($reconciliations) ?></span></button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-sell" role="tabpanel">
    <?php if (!$canAdd): ?>
      <div class="alert alert-info">You can view products but you do not have permission to record a sale.</div>
    <?php elseif (!$activeProducts): ?>
      <div class="alert alert-warning">Add at least one active product before recording a sale.</div>
    <?php else: ?>
    <div class="row g-3">
      <div class="col-xl-8">
        <div class="card app-card">
          <div class="card-header">New sale</div>
          <div class="card-body">
            <form method="post" action="<?= url('canteen/sales/store') ?>" data-ajax id="sale-form">
              <?= csrf_field() ?>
              <div class="row g-2 mb-3">
                <div class="col-md-4">
                  <label class="form-label">Buyer</label>
                  <select class="form-select" name="buyer_type" id="buyer-type">
                    <option value="student">Student</option>
                    <option value="staff">Staff</option>
                    <option value="visitor">Visitor</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div class="col-md-4 student-field">
                  <label class="form-label">Student</label>
                  <select class="form-select" name="student_id" id="sale-student">
                    <option value="">-- Choose student --</option>
                    <?php foreach ($students as $student): ?>
                    <option value="<?= (int)$student['id'] ?>" data-balance="<?= $student['balance'] !== null ? e((string)$student['balance']) : '' ?>" data-wallet="<?= e($student['wallet_status'] ?? '') ?>">
                      <?= e($student['admission_number'] . ' — ' . $student['first_name'] . ' ' . $student['last_name']) ?><?= $student['class_name'] ? ' (' . e($student['class_name']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text" id="wallet-hint">Choose a student to see the wallet balance.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Payment</label>
                  <select class="form-select" name="payment_method" id="payment-method">
                    <option value="cash">Cash</option>
                    <option value="wallet">Student wallet</option>
                  </select>
                </div>
              </div>

              <div class="table-responsive mb-2">
                <table class="table align-middle mb-0" id="sale-lines">
                  <thead><tr><th style="min-width:220px">Product</th><th style="width:110px">Quantity</th><th style="width:110px">Price</th><th style="width:120px">Line total</th><th style="width:48px"></th></tr></thead>
                  <tbody>
                    <tr>
                      <td>
                        <select class="form-select product-select" name="product_id[]">
                          <option value="">-- Choose product --</option>
                          <?php foreach ($activeProducts as $product): ?>
                          <option value="<?= (int)$product['id'] ?>"><?= e($product['name']) ?><?= $product['current_stock'] <= 0 && (int)$product['track_stock'] === 1 ? ' (out of stock)' : '' ?></option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td><input class="form-control quantity-input" type="number" name="quantity[]" min="0.01" step="0.01" value="1"></td>
                      <td class="line-price text-muted">—</td>
                      <td class="line-total fw-semibold">—</td>
                      <td><button class="btn btn-sm btn-outline-danger remove-line" type="button" aria-label="Remove line"><i class="bi bi-x-lg"></i></button></td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button class="btn btn-sm btn-outline-secondary mb-3" type="button" id="add-line"><i class="bi bi-plus-lg me-1"></i>Add product line</button>

              <div class="row g-2 mb-3">
                <div class="col-sm-4">
                  <label class="form-label">Discount (UGX)</label>
                  <input class="form-control" type="number" name="discount" id="sale-discount" min="0" step="100" value="0">
                </div>
                <div class="col-sm-4 cash-field">
                  <label class="form-label">Cash received</label>
                  <input class="form-control" type="number" name="amount_paid" id="amount-paid" min="0" step="100" value="0">
                </div>
                <div class="col-sm-4">
                  <label class="form-label">Notes</label>
                  <input class="form-control" name="notes" maxlength="255" placeholder="Optional">
                </div>
              </div>

              <div class="d-flex flex-wrap align-items-center gap-3 border-top pt-3">
                <div class="fs-5">Total: <strong id="sale-total">0</strong> UGX</div>
                <div class="text-muted small cash-field">Change: <span id="sale-change">0</span> UGX</div>
                <div class="text-muted small wallet-field d-none">Wallet balance: <span id="wallet-balance">—</span> UGX</div>
                <button class="btn btn-primary ms-auto" type="submit"><i class="bi bi-check2-circle me-1"></i>Complete sale</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Till notes</div>
          <div class="card-body small text-muted">
            <p class="mb-2"><i class="bi bi-cash-coin me-1"></i>Cash sales show the change due before you submit.</p>
            <p class="mb-2"><i class="bi bi-wallet2 me-1"></i>Wallet sales check the active wallet, the daily limit and the available balance before posting.</p>
            <p class="mb-2"><i class="bi bi-box-seam me-1"></i>Stock is reduced as soon as a sale completes, with a stock movement recorded against the sale.</p>
            <p class="mb-0"><i class="bi bi-arrow-counterclockwise me-1"></i>A manager can void a completed sale; stock is restored and wallet payments are refunded.</p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="tab-pane fade" id="tab-products" role="tabpanel">
    <div class="row g-3">
    <?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Add product</div>
          <div class="card-body">
            <form method="post" action="<?= url('canteen/products/store') ?>" data-ajax>
              <?= csrf_field() ?>
              <div class="form-section"><i class="bi bi-box-seam"></i> Product details</div>
              <div class="mb-2">
                <label class="form-label">Product name <span class="text-danger">*</span></label>
                <input class="form-control" name="name" maxlength="160" required>
                <span class="invalid-feedback d-block" data-error-for="name"></span>
              </div>
              <div class="row g-2 mb-2">
                <div class="col">
                  <label class="form-label">Category</label>
                  <select class="form-select" name="category">
                    <?php foreach ($categoryLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col">
                  <label class="form-label">Unit</label>
                  <input class="form-control" name="unit" maxlength="40" placeholder="piece / bottle…">
                </div>
              </div>
              <div class="form-section"><i class="bi bi-cash-stack"></i> Pricing</div>
              <div class="row g-2 mb-2">
                <div class="col">
                  <label class="form-label">Cost price</label>
                  <input class="form-control" name="cost_price" type="number" step="100" min="0" value="0">
                  <span class="invalid-feedback d-block" data-error-for="cost_price"></span>
                </div>
                <div class="col">
                  <label class="form-label">Selling price</label>
                  <input class="form-control" name="selling_price" type="number" step="100" min="0" value="0">
                  <span class="invalid-feedback d-block" data-error-for="selling_price"></span>
                </div>
              </div>
              <div class="form-section"><i class="bi bi-box-seam"></i> Stock levels</div>
              <div class="row g-2 mb-3">
                <div class="col">
                  <label class="form-label">Opening stock</label>
                  <input class="form-control" name="current_stock" type="number" min="0" value="0">
                  <span class="invalid-feedback d-block" data-error-for="current_stock"></span>
                </div>
                <div class="col">
                  <label class="form-label">Reorder level</label>
                  <input class="form-control" name="reorder_level" type="number" min="0" value="0">
                </div>
              </div>
              <button class="btn btn-primary" type="submit">Add product</button>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Products <span class="badge text-bg-light"><?= count($products) ?></span></div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-table>
              <thead><tr><th>Name</th><th>Category</th><th>Unit</th><th>Selling price</th><th>Stock</th><th>Status</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
              <tbody>
                <?php if (!$products): ?><tr><td colspan="<?= $canEdit ? 7 : 6 ?>" class="text-center text-muted py-4">No products added yet.</td></tr><?php endif; ?>
                <?php foreach ($products as $p): ?>
                <?php $low = (int)$p['track_stock'] === 1 && (float)$p['current_stock'] <= (float)$p['reorder_level']; ?>
                <tr>
                  <td><strong><?= e($p['name']) ?></strong><div class="small text-muted"><?= e($p['product_code']) ?></div></td>
                  <td><?= e($categoryLabels[$p['category']] ?? ucfirst($p['category'])) ?></td>
                  <td><?= e($p['unit']) ?></td>
                  <td><?= number_format((float)$p['selling_price']) ?></td>
                  <td><?= rtrim(rtrim(number_format((float)$p['current_stock'], 2, '.', ''), '0'), '.') ?><?= $low ? ' <span class="badge text-bg-warning">Low</span>' : '' ?></td>
                  <td><span class="badge text-bg-<?= $p['status'] === 'active' ? 'success' : ($p['status'] === 'out_of_stock' ? 'warning' : 'secondary') ?>"><?= e(str_replace('_', ' ', $p['status'])) ?></span></td>
                  <?php if ($canEdit): ?>
                  <td>
                    <form method="post" action="<?= url('canteen/products/toggle') ?>" data-ajax>
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                        <option value="active"       <?= $p['status'] === 'active'       ? 'selected' : '' ?>>Active</option>
                        <option value="inactive"     <?= $p['status'] === 'inactive'     ? 'selected' : '' ?>>Inactive</option>
                        <option value="out_of_stock" <?= $p['status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
                      </select>
                    </form>
                  </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-sales" role="tabpanel">
    <div class="card app-card">
      <div class="card-header">Recent sales <span class="badge text-bg-light"><?= count($sales) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table data-order-column="1" data-order-dir="desc">
          <thead><tr><th>Sale</th><th>When</th><th>Buyer</th><th>Lines</th><th>Total</th><th>Payment</th><th>Status</th><?php if ($canApprove): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$sales): ?><tr><td colspan="<?= $canApprove ? 8 : 7 ?>" class="text-center text-muted py-4">No sales recorded yet.</td></tr><?php endif; ?>
            <?php foreach ($sales as $sale): ?>
            <tr>
              <td><strong><?= e($sale['sale_number']) ?></strong><?= $sale['void_reason'] ? '<div class="small text-muted">' . e($sale['void_reason']) . '</div>' : '' ?></td>
              <td class="small"><?= e(date('d M Y', strtotime($sale['sale_date']))) ?><div class="text-muted"><?= e(substr((string)$sale['sale_time'], 0, 5)) ?></div></td>
              <td>
                <?php if ($sale['first_name']): ?>
                  <?= e($sale['first_name'] . ' ' . $sale['last_name']) ?><div class="small text-muted"><?= e($sale['admission_number']) ?></div>
                <?php else: ?>
                  <?= e(ucfirst($sale['buyer_type'])) ?>
                <?php endif; ?>
              </td>
              <td><?= (int)$sale['line_count'] ?></td>
              <td><?= number_format((float)$sale['total_amount']) ?></td>
              <td><?= e(str_replace('_', ' ', ucfirst($sale['payment_method']))) ?></td>
              <td><span class="badge text-bg-<?= $sale['status'] === 'completed' ? 'success' : ($sale['status'] === 'void' ? 'secondary' : 'warning') ?>"><?= e($sale['status']) ?></span></td>
              <?php if ($canApprove): ?>
              <td>
                <?php if ($sale['status'] === 'completed'): ?>
                <form method="post" action="<?= url('canteen/sales/void') ?>" data-ajax class="d-flex gap-1" onsubmit="return confirm('Void this sale and restore stock?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$sale['id'] ?>">
                  <input class="form-control form-control-sm" name="void_reason" maxlength="255" placeholder="Reason" required style="min-width:130px">
                  <button class="btn btn-sm btn-outline-danger" type="submit">Void</button>
                </form>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-till" role="tabpanel">
    <div class="row g-3">
      <?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Count the till</div>
          <div class="card-body">
            <form method="post" action="<?= url('canteen/till/count') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label" for="recon_date">Business date</label><input class="form-control" id="recon_date" type="date" name="recon_date" value="<?= date('Y-m-d') ?>" required></div>
              <div class="mb-2"><label class="form-label" for="recon_seller">Seller</label><select class="form-select" id="recon_seller" name="seller_staff_id"><option value="0">All sellers</option>
                <?php foreach ($sellers as $seller): ?><option value="<?= (int)$seller['id'] ?>"><?= e($seller['last_name'] . ' ' . $seller['first_name']) ?></option><?php endforeach; ?>
              </select></div>
              <div class="mb-2"><label class="form-label" for="counted_cash">Cash counted</label><input class="form-control" id="counted_cash" type="number" min="0" step="0.01" name="counted_cash" required></div>
              <div class="mb-2"><label class="form-label" for="recon_notes">Remarks</label><input class="form-control" id="recon_notes" name="remarks" maxlength="255"></div>
              <button class="btn btn-primary btn-sm w-100">Save count</button>
            </form>
            <div class="small text-muted mt-2">Expected cash is the total of completed cash sales for the day and seller. Wallet, mobile money and card sales are shown separately.</div>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Till reconciliations <span class="badge text-bg-light"><?= count($reconciliations) ?></span></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Date</th><th>Seller</th><th class="text-end">Sales</th><th class="text-end">Expected cash</th><th class="text-end">Counted</th><th class="text-end">Difference</th><th class="text-end">Digital</th><th>Status</th><?php if ($canApprove): ?><th></th><?php endif; ?></tr></thead>
              <tbody>
                <?php if (!$reconciliations): ?><tr><td colspan="<?= $canApprove ? 9 : 8 ?>" class="text-center text-muted py-4">No till counts recorded.</td></tr><?php endif; ?>
                <?php foreach ($reconciliations as $row): ?>
                <tr>
                  <td><?= e(date('d M Y', strtotime($row['recon_date']))) ?></td>
                  <td><?= $row['first_name'] ? e($row['first_name'] . ' ' . $row['last_name']) : 'All sellers' ?></td>
                  <td class="text-end"><?= (int)$row['sales_count'] ?></td>
                  <td class="text-end"><?= e(number_format((float)$row['expected_cash'], 2)) ?></td>
                  <td class="text-end"><?= e(number_format((float)$row['counted_cash'], 2)) ?></td>
                  <td class="text-end <?= abs((float)$row['difference']) >= 0.01 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= e(number_format((float)$row['difference'], 2)) ?></td>
                  <td class="text-end"><?= e(number_format((float)$row['digital_total'], 2)) ?></td>
                  <td><span class="badge text-bg-<?= $row['status'] === 'approved' ? 'primary' : ($row['status'] === 'matched' ? 'success' : ($row['status'] === 'variance' ? 'warning' : 'secondary')) ?>"><?= e($row['status']) ?></span></td>
                  <?php if ($canApprove): ?>
                  <td class="text-end">
                    <?php if (in_array($row['status'], ['matched', 'variance'], true)): ?>
                    <form method="post" action="<?= url('canteen/till/approve') ?>">
                      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit">Approve</button>
                    </form>
                    <?php endif; ?>
                  </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.APP.canteenProducts = <?= json_encode($productMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.addEventListener('DOMContentLoaded', function () {
  var products = window.APP.canteenProducts || {};
  var form = document.getElementById('sale-form');
  if (!form) { return; }
  var linesBody = document.querySelector('#sale-lines tbody');
  var addButton = document.getElementById('add-line');
  var discountInput = document.getElementById('sale-discount');
  var paidInput = document.getElementById('amount-paid');
  var totalOutput = document.getElementById('sale-total');
  var changeOutput = document.getElementById('sale-change');
  var walletOutput = document.getElementById('wallet-balance');
  var walletHint = document.getElementById('wallet-hint');
  var studentSelect = document.getElementById('sale-student');
  var paymentSelect = document.getElementById('payment-method');
  var buyerSelect = document.getElementById('buyer-type');

  function money(value) {
    return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
  }

  function recalc() {
    var subtotal = 0;
    linesBody.querySelectorAll('tr').forEach(function (row) {
      var select = row.querySelector('.product-select');
      var qtyInput = row.querySelector('.quantity-input');
      var priceCell = row.querySelector('.line-price');
      var totalCell = row.querySelector('.line-total');
      var product = products[select.value];
      var quantity = parseFloat(qtyInput.value || '0');
      if (!product || quantity <= 0) {
        priceCell.textContent = '—';
        totalCell.textContent = '—';
        return;
      }
      var lineTotal = product.price * quantity;
      subtotal += lineTotal;
      priceCell.textContent = money(product.price);
      totalCell.textContent = money(lineTotal);
      if (product.track && quantity > product.stock) {
        totalCell.classList.add('text-danger');
        totalCell.title = 'Only ' + product.stock + ' ' + product.unit + ' in stock';
      } else {
        totalCell.classList.remove('text-danger');
        totalCell.removeAttribute('title');
      }
    });
    var discount = Math.min(parseFloat(discountInput.value || '0'), subtotal);
    var total = Math.max(subtotal - discount, 0);
    totalOutput.textContent = money(total);
    var paid = parseFloat(paidInput.value || '0');
    changeOutput.textContent = money(Math.max(paid - total, 0));
    if (paymentSelect.value === 'wallet') {
      var option = studentSelect.options[studentSelect.selectedIndex];
      var balance = option ? parseFloat(option.getAttribute('data-balance') || '0') : 0;
      walletOutput.textContent = money(balance);
      if (total > balance) {
        walletOutput.classList.add('text-danger');
      } else {
        walletOutput.classList.remove('text-danger');
      }
    }
  }

  function togglePaymentFields() {
    var isWallet = paymentSelect.value === 'wallet';
    document.querySelectorAll('.cash-field').forEach(function (el) { el.classList.toggle('d-none', isWallet); });
    document.querySelectorAll('.wallet-field').forEach(function (el) { el.classList.toggle('d-none', !isWallet); });
    var isStudent = buyerSelect.value === 'student';
    document.querySelectorAll('.student-field').forEach(function (el) { el.classList.toggle('d-none', !isStudent); });
    paidInput.required = !isWallet;
    if (isWallet) { buyerSelect.value = 'student'; }
    recalc();
  }

  function newRow() {
    var row = linesBody.querySelector('tr').cloneNode(true);
    row.querySelector('.product-select').value = '';
    row.querySelector('.quantity-input').value = '1';
    row.querySelector('.line-price').textContent = '—';
    row.querySelector('.line-total').textContent = '—';
    row.querySelector('.line-total').classList.remove('text-danger');
    return row;
  }

  addButton.addEventListener('click', function () {
    if (linesBody.querySelectorAll('tr').length >= 50) { return; }
    linesBody.appendChild(newRow());
    recalc();
  });
  linesBody.addEventListener('click', function (event) {
    var button = event.target.closest('.remove-line');
    if (!button) { return; }
    if (linesBody.querySelectorAll('tr').length === 1) {
      var row = button.closest('tr');
      row.querySelector('.product-select').value = '';
      row.querySelector('.quantity-input').value = '1';
      row.querySelector('.line-price').textContent = '—';
      row.querySelector('.line-total').textContent = '—';
    } else {
      button.closest('tr').remove();
    }
    recalc();
  });
  linesBody.addEventListener('change', recalc);
  linesBody.addEventListener('input', recalc);
  discountInput.addEventListener('input', recalc);
  paidInput.addEventListener('input', recalc);
  studentSelect.addEventListener('change', recalc);
  paymentSelect.addEventListener('change', togglePaymentFields);
  buyerSelect.addEventListener('change', togglePaymentFields);

  var hintForStudent = function () {
    var option = studentSelect.options[studentSelect.selectedIndex];
    if (!option || !option.value) {
      walletHint.textContent = 'Choose a student to see the wallet balance.';
      return;
    }
    var wallet = option.getAttribute('data-wallet');
    var balance = option.getAttribute('data-balance');
    walletHint.textContent = wallet === 'active'
      ? 'Wallet balance: ' + money(balance) + ' UGX'
      : 'No active wallet — record a deposit before a wallet sale.';
  };
  studentSelect.addEventListener('change', hintForStudent);
  hintForStudent();
  togglePaymentFields();
});
</script>

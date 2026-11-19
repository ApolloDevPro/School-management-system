<div class="page-head">
  <div><h1 class="h4 mb-0">Canteen &amp; shop</h1><p class="text-muted small mb-0">Manage canteen products and school shop inventory.</p></div>
</div>

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
                <option value="food">Food</option>
                <option value="stationery">Stationery</option>
                <option value="uniform">Uniform</option>
                <option value="toiletries">Toiletries</option>
                <option value="other">Other</option>
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
            <?php $low = (int)$p['current_stock'] <= 5; ?>
            <tr>
              <td><strong><?= e($p['name']) ?></strong><div class="small text-muted"><?= e($p['product_code']) ?></div></td>
              <td><?= e(ucfirst($p['category'])) ?></td>
              <td><?= e($p['unit']) ?></td>
              <td><?= number_format((float)$p['selling_price']) ?></td>
              <td><?= (int)$p['current_stock'] ?><?= $low ? ' <span class="badge text-bg-warning">Low</span>' : '' ?></td>
              <td><span class="badge text-bg-<?= $p['status'] === 'active' ? 'success' : ($p['status'] === 'out_of_stock' ? 'warning' : 'secondary') ?>"><?= e(str_replace('_',' ',$p['status'])) ?></span></td>
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

<div class="page-head">
  <div>
    <h1 class="h4 mb-0">School profile</h1>
    <p class="text-muted small mb-0">Identity details printed on report cards, receipts, letters and reports</p>
  </div>
</div>

<div class="card app-card">
  <div class="card-body">
<?php if (!$canEdit): ?>
    <div class="alert alert-info">You have view-only access to the school profile.</div>
<?php endif; ?>
    <form method="post" action="<?= url('setup/profile') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-lg-8">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label" for="p_name">School name</label>
              <input class="form-control" id="p_name" name="name" maxlength="160" value="<?= e($school['name'] ?? '') ?>" <?= $canEdit ? 'required' : 'disabled' ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="p_ownership">Ownership</label>
              <select class="form-select" id="p_ownership" name="ownership_type" <?= $canEdit ? '' : 'disabled' ?>>
<?php foreach (['government' => 'Government', 'private' => 'Private', 'church' => 'Church', 'community' => 'Community', 'other' => 'Other'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= ($school['ownership_type'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="p_motto">Motto</label>
              <input class="form-control" id="p_motto" name="motto" maxlength="200" value="<?= e($school['motto'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="p_phone">Telephone</label>
              <input class="form-control" id="p_phone" name="phone" maxlength="40" value="<?= e($school['phone'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="p_alt_phone">Alternate phone</label>
              <input class="form-control" id="p_alt_phone" name="alternate_phone" maxlength="40" value="<?= e($school['alternate_phone'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="p_email">Email</label>
              <input class="form-control" id="p_email" name="email" type="email" maxlength="120" value="<?= e($school['email'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="p_website">Website</label>
              <input class="form-control" id="p_website" name="website" maxlength="160" value="<?= e($school['website'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-12">
              <label class="form-label" for="p_physical">Physical address</label>
              <input class="form-control" id="p_physical" name="physical_address" maxlength="255" value="<?= e($school['physical_address'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="p_postal">Postal address</label>
              <input class="form-control" id="p_postal" name="postal_address" maxlength="255" value="<?= e($school['postal_address'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="p_district">District</label>
              <input class="form-control" id="p_district" name="district" maxlength="80" value="<?= e($school['district'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="p_subcounty">Sub-county</label>
              <input class="form-control" id="p_subcounty" name="sub_county" maxlength="80" value="<?= e($school['sub_county'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="row g-3">
            <div class="col-md-6 col-lg-12">
              <label class="form-label" for="p_reg">Registration number</label>
              <input class="form-control" id="p_reg" name="registration_number" maxlength="60" value="<?= e($school['registration_number'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6 col-lg-12">
              <label class="form-label" for="p_emis">EMIS code</label>
              <input class="form-control" id="p_emis" name="emis_code" maxlength="40" value="<?= e($school['emis_code'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6 col-lg-12">
              <label class="form-label" for="p_uneb">UNEB index number</label>
              <input class="form-control" id="p_uneb" name="uneb_index_number" maxlength="40" value="<?= e($school['uneb_index_number'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6 col-lg-12">
              <label class="form-label" for="p_established">Date established</label>
              <input class="form-control" id="p_established" name="date_established" type="date" value="<?= e($school['date_established'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-md-6 col-lg-12">
              <label class="form-label" for="p_currency">Currency</label>
              <select class="form-select" id="p_currency" name="currency" <?= $canEdit ? '' : 'disabled' ?>>
<?php foreach (['UGX', 'USD', 'EUR', 'GBP', 'KES', 'TZS', 'RWF', 'NGN', 'GHS'] as $code): ?>
                <option value="<?= $code ?>" <?= ($school['currency'] ?? 'UGX') === $code ? 'selected' : '' ?>><?= $code ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">School profile picture / logo</label>
              <div class="d-flex align-items-center gap-3 mb-2">
                <?php if (!empty($school['logo_path'])): ?>
                  <img src="<?= e(asset($school['logo_path'])) ?>" alt="School logo" class="rounded border bg-white" style="width:96px;height:96px;object-fit:contain;padding:6px;">
                <?php else: ?>
                  <div class="rounded border bg-light d-inline-flex align-items-center justify-content-center text-muted" style="width:96px;height:96px;">
                    <i class="bi bi-building" style="font-size:2rem;"></i>
                  </div>
                <?php endif; ?>
                <div class="flex-grow-1">
                  <input class="form-control" id="p_logo" name="logo" type="file" accept="image/png,image/jpeg,image/gif,image/webp" <?= $canEdit ? '' : 'disabled' ?>>
                  <div class="form-text">PNG, JPG, GIF or WEBP. Used on official documents and as the school profile picture.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

<?php if ($canEdit): ?>
      <div class="mt-4">
        <button class="btn btn-primary" type="submit">Save profile</button>
      </div>
<?php endif; ?>
    </form>
  </div>
</div>

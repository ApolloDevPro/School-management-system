<?php
declare(strict_types=1);

final class CanteenController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('canteen/index', [
            'pageTitle' => 'Canteen & shop',
            'activeNav' => 'canteen',
            'products'  => CanteenModule::products($schoolId),
            'canAdd'    => Auth::can('canteen', 'add'),
            'canEdit'   => Auth::can('canteen', 'edit'),
        ]);
    }

    public function storeProduct(): void
    {
        $name     = trim((string)($_POST['name'] ?? ''));
        $category = (string)($_POST['category'] ?? 'food');
        $unit     = trim((string)($_POST['unit'] ?? ''));
        $price    = (float)($_POST['selling_price'] ?? 0);
        $cost     = (float)($_POST['cost_price'] ?? 0);
        $stock    = (int)($_POST['current_stock'] ?? 0);
        $reorder  = (int)($_POST['reorder_level'] ?? 0);

        $errors = [];
        if ($name === '' || mb_strlen($name) > 160) { $errors['name']  = 'Enter a product name (max 160 chars).'; }
        if ($price < 0)  { $errors['selling_price'] = 'Price must be 0 or more.'; }
        if ($stock < 0)  { $errors['current_stock'] = 'Stock must be 0 or more.'; }
        if (!in_array($category, ['food', 'stationery', 'uniform', 'toiletries', 'other'], true)) { $category = 'food'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'canteen', [], $errors);
        }

        $code = 'PRD-' . strtoupper(bin2hex(random_bytes(2)));
        $id = CanteenModule::createProduct([
            'school_id' => school_id(),
            'code'      => $code,
            'name'      => $name,
            'category'  => $category,
            'unit'      => $unit ?: 'piece',
            'cost'      => $cost,
            'price'     => $price,
            'stock'     => $stock,
            'reorder'   => $reorder,
        ]);

        audit('canteen', 'product_created', 'products', $id, 'Added product: ' . $name);
        $this->respond(true, 'Product "' . $name . '" added.', 'canteen');
    }

    public function toggleProduct(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'inactive', 'out_of_stock'], true)) {
            $this->respond(false, 'Invalid status.', 'canteen');
        }
        $ok = CanteenModule::changeStatus($id, school_id(), $status);
        $this->respond($ok, $ok ? 'Product status updated.' : 'Could not update product.', 'canteen');
    }
}

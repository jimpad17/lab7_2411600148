# JimTrack Fitness Inventory — Laboratory Exercise 7

Laravel inventory system extended with **automated inventory replenishment and supplier management**. This project continues the Lab 6 application and keeps its real-time stock tracking, transaction observer, low-stock alerts, AJAX dashboard, activity log and API resources.

## Requirements

- PHP 8.2+
- Composer 2+
- MySQL (XAMPP recommended)
- Node/npm only if you want to rebuild front-end assets

## Local setup

1. Copy the environment file and generate an application key:

   ```powershell
   copy .env.example .env
   php artisan key:generate
   ```

2. Create an empty MySQL database named `lab7_2411600148` (or change `DB_DATABASE` in `.env`).

3. Install dependencies and seed the demo data:

   ```powershell
   composer install
   php artisan migrate:fresh --seed
   ```

   Use `php artisan migrate --seed` instead if you want to keep existing Lab 6 data; the migrations back-fill suppliers from the old free-text supplier column.

4. Start the application:

   ```powershell
   php artisan serve
   ```

   Open <http://127.0.0.1:8000>.

### Seeded accounts

| Role | Email | Password |
| --- | --- | --- |
| Admin | `jimpadillo@gmail.com` | `admin123` |
| Manager | `manager@jimtrack.local` | `MANAGER123` |
| Warehouse | `warehouse@jimtrack.local` | `WAREHOUSE123` |
| Staff | `juan@jimtrack.local` | `STAFF123` |
| Staff | `maria@jimtrack.local` | `STAFF123` |

## Lab 7 features

### 1. Supplier management

- Supplier CRUD with validation, search, active-status filtering and sorting.
- `Supplier` uses `SoftDeletes`; deleted suppliers stay attached to historical purchase orders and can be restored.
- Suppliers track contact details, address, payment terms, lead time, minimum order amount and categories.
- Supplier show page includes supplied products, recent purchase orders and calculated performance metrics.

### 2. Purchase orders

- `PurchaseOrder`, `PurchaseOrderItem` and polymorphic approval-history models.
- Draft/pending/approved/sent/received/rejected/cancelled workflow with partial receipts.
- Dynamic line-item form, reference-number generation, totals, tax and unit prices captured at order time.
- `PurchaseOrderPolicy` + Gates: admins bypass checks, managers approve, warehouse staff receive goods, and high-value orders require admin approval.
- Receiving a PO creates inventory transactions through the existing observer, so product quantities stay in sync.

### 3. Automated replenishment

- `ReorderService` calculates:

  ```text
  Reorder point = (average daily usage × supplier lead time) + safety stock
  ```

  Average usage comes from the last 30 days of outbound stock movements. Suggested quantities cover lead time plus a 7-day review period, respect supplier minimum order amounts and round up to package size.

- `php artisan inventory:check-reorder-points` reports candidates and creates one draft PO per supplier. Use `--dry-run` to preview without creating orders.
- Scheduled daily at 02:00 in `App\Console\Kernel` / `routes/console.php`.

  To activate scheduling on Windows/XAMPP, add a scheduled task running:

  ```text
  php C:\xampp\htdocs\lab7_2411600148\artisan schedule:run
  ```

  every minute.

### 4. Notifications and mail

- Mailable classes: `PurchaseOrderCreated`, `PurchaseOrderApproved`, `PurchaseOrderSent`, `LowStockAlert`.
- Matching Blade templates are in `resources/views/emails`. The new-PO mail includes a printable PDF attachment.
- Database notifications appear in the bell/inbox immediately.
- With `MAIL_MAILER=log`, mail is written to `storage/logs/laravel.log`. With SMTP, mail is queued; run `php artisan queue:work` to process it.

### 5. Procurement dashboard

- View composers in `ViewServiceProvider` bind data to the pending-orders, recent-orders, supplier-performance and reorder-alert widgets.
- All four widgets poll their JSON endpoints every 60 seconds so counts and lists stay consistent:

  ```text
  /api/dashboard/pending-orders
  /api/dashboard/recent-orders
  /api/dashboard/supplier-performance
  /api/dashboard/reorder-alerts
  ```

- Existing Lab 6 live inventory API endpoints are unchanged.

### 6. Supplier performance

- Tracks total orders, on-time deliveries, average actual/quoted delivery days, delivery variance, order accuracy and total order value.
- Report page: `/suppliers/performance`, with top/bottom performers and delivery charts.
- Aggregated calculations are cached and invalidated when purchase orders change.

### 7. Search, filters and exports

- `SearchService` powers products, purchase orders and inventory transactions.
- Reusable `<x-filter-card>` Blade component keeps filter toolbars consistent.
- Export classes produce filtered CSV and PDF files for products, purchase orders, inventory transactions and supplier performance (`barryvdh/laravel-dompdf` is used for PDF).
- Export buttons appear on each corresponding index/report page and preserve the active filters.

## Useful commands

```powershell
php artisan test
php artisan inventory:check-reorder-points --dry-run
php artisan inventory:check-reorder-points
php artisan schedule:list
php artisan route:list --except-vendor
php artisan queue:work
php artisan optimize:clear
```

The test suite uses an in-memory SQLite database, so it does not alter the MySQL demo database.

## Notes

- `.env` is intentionally not committed. Update `DB_*`, `MAIL_*` and `APP_URL` for your machine.
- Lab 7 screenshots, questionnaire answers and the report PDF are prepared separately.

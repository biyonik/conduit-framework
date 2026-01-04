# Queue Sistemi

## İçindekiler
1. [Giriş](#giriş)
2. [Konfigürasyon](#konfigürasyon)
3. [Job Oluşturma](#job-oluşturma)
4. [Job Dispatching](#job-dispatching)
5. [Queue Worker](#queue-worker)
6. [Failed Jobs](#failed-jobs)
7. [Gerçek Örnekler](#gerçek-örnekler)

---

## Giriş

Queue sistemi, uzun süren işlemleri arka planda asenkron olarak çalıştırmanıza olanak sağlar.

### Avantajlar
- ⚡ **Hızlı Response**: Kullanıcı beklemez
- 🔄 **Retry Logic**: Başarısız joblar yeniden denenebilir
- 📊 **Scalability**: Birden fazla worker çalıştırabilirsiniz
- 🎯 **Priority**: Önemli işler önce çalışabilir
- 🛡️ **Resilience**: Hata durumunda sistem etkilenmez

### Ne Zaman Kullanılır?
- Email gönderme
- Resim işleme (thumbnail, resize)
- PDF oluşturma
- Third-party API çağrıları
- Raporlama
- Toplu veri işleme
- Bildirim gönderme

---

## Konfigürasyon

### Database Setup

```bash
# Migration çalıştır (jobs tablosu oluşturulur)
php conduit migrate
```

Queue sistemi database kullanır (Redis gerekmez - shared hosting uyumlu!)

### Jobs Table Schema

```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) DEFAULT 'default',
    payload LONGTEXT,
    attempts TINYINT UNSIGNED DEFAULT 0,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED,
    created_at INT UNSIGNED
);

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255),
    payload LONGTEXT,
    exception LONGTEXT,
    failed_at INT UNSIGNED
);
```

---

## Job Oluşturma

### Basit Job

```php
// app/Jobs/SendWelcomeEmail.php
namespace App\Jobs;

use Conduit\Queue\Job;

class SendWelcomeEmail extends Job {
    public function __construct(
        private int $userId,
        private string $email
    ) {}

    public function handle(): void {
        // Email gönder
        mail_send(
            $this->email,
            'Hoş Geldiniz!',
            "Merhaba! Aramıza hoş geldiniz."
        );

        logger()->info('Welcome email sent', [
            'user_id' => $this->userId,
            'email' => $this->email,
        ]);
    }
}
```

### Job with Dependencies

```php
// app/Jobs/GenerateInvoicePDF.php
namespace App\Jobs;

use Conduit\Queue\Job;
use App\Services\InvoiceService;
use App\Models\Order;

class GenerateInvoicePDF extends Job {
    public function __construct(
        private int $orderId
    ) {}

    public function handle(): void {
        // Container'dan service al
        $invoiceService = app(InvoiceService::class);

        $order = Order::find($this->orderId);

        if (!$order) {
            logger()->error('Order not found for invoice', ['order_id' => $this->orderId]);
            return;
        }

        // PDF oluştur
        $pdfPath = $invoiceService->generatePDF($order);

        // Storage'a kaydet
        storage()->put("invoices/{$order->order_number}.pdf", file_get_contents($pdfPath));

        logger()->info('Invoice PDF generated', [
            'order_id' => $this->orderId,
            'path' => $pdfPath,
        ]);
    }
}
```

### Job with Retry Logic

```php
// app/Jobs/SendSMS.php
namespace App\Jobs;

use Conduit\Queue\Job;

class SendSMS extends Job {
    protected int $maxAttempts = 3; // Maksimum 3 deneme
    protected int $retryDelay = 60; // Başarısız olursa 60 saniye sonra tekrar dene

    public function __construct(
        private string $phone,
        private string $message
    ) {}

    public function handle(): void {
        $apiKey = env('SMS_API_KEY');
        $url = 'https://api.smsprovider.com/send';

        $response = file_get_contents($url . '?' . http_build_query([
            'api_key' => $apiKey,
            'phone' => $this->phone,
            'message' => $this->message,
        ]));

        $result = json_decode($response, true);

        if (!$result['success']) {
            throw new \Exception("SMS send failed: {$result['error']}");
        }

        logger()->info('SMS sent', ['phone' => $this->phone]);
    }

    public function failed(\Throwable $exception): void {
        // Tüm denemeler başarısız oldu
        logger()->error('SMS send failed permanently', [
            'phone' => $this->phone,
            'error' => $exception->getMessage(),
        ]);

        // Alternatif bildirim gönder
        mail_queue('admin@example.com', 'SMS Failed', "SMS to {$this->phone} failed");
    }
}
```

---

## Job Dispatching

### Immediate Dispatch

```php
use App\Jobs\SendWelcomeEmail;

// Queue'ya ekle (hemen çalışmaz, worker çalıştırır)
SendWelcomeEmail::dispatch($userId, $email);

// Alternatif syntax
$job = new SendWelcomeEmail($userId, $email);
app(QueueManager::class)->push($job);
```

### Delayed Dispatch

```php
use App\Jobs\SendReminderEmail;

// 1 saat sonra çalışsın
SendReminderEmail::dispatch($userId, $email)->delay(3600);

// 1 gün sonra
SendReminderEmail::dispatch($userId, $email)->delay(86400);

// QueueManager ile
$job = new SendReminderEmail($userId, $email);
app(QueueManager::class)->later($job, 3600);
```

### Queue Selection

```php
// Farklı queue'lara gönder (priority için)
SendEmailJob::dispatch($data)->onQueue('emails');
ProcessImageJob::dispatch($data)->onQueue('media');
GenerateReportJob::dispatch($data)->onQueue('reports');

// Worker çalıştırırken queue seç
// php conduit queue:work --queue=emails
```

---

## Queue Worker

### Worker Başlatma

```bash
# Default queue'yu işle
php conduit queue:work

# Belirli queue'yu işle
php conduit queue:work --queue=emails

# Verbose output
php conduit queue:work -v

# Background'da çalıştır (Linux)
php conduit queue:work > /dev/null 2>&1 &

# Nohup ile (terminal kapansa bile çalışsın)
nohup php conduit queue:work > storage/logs/queue.log 2>&1 &
```

### Worker Yönetimi

```bash
# Çalışan worker'ları listele
ps aux | grep "queue:work"

# Worker'ı durdur (gracefully)
kill -TERM <PID>

# Hemen durdur
kill -KILL <PID>

# Queue'yu temizle (tüm bekleyen jobları sil)
php conduit queue:clear

# Başarısız jobları listele
php conduit queue:failed

# Başarısız job'u yeniden dene
php conduit queue:retry <job-id>

# Tüm başarısız jobları yeniden dene
php conduit queue:retry --all
```

### Supervisor ile Production Setup (Linux)

```ini
; /etc/supervisor/conf.d/queue-worker.conf
[program:conduit-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/app/conduit queue:work
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/app/storage/logs/queue-worker.log
stopwaitsecs=3600
```

```bash
# Supervisor yeniden yükle
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start conduit-queue-worker:*

# Status kontrol
sudo supervisorctl status
```

### Cron ile Çalıştırma (Shared Hosting)

```bash
# crontab -e
* * * * * cd /path/to/app && php conduit queue:work --max-jobs=10 >> /dev/null 2>&1
```

Bu her dakika çalışır, 10 job işler ve durur. Shared hosting için ideal.

---

## Failed Jobs

### Failed Job Yönetimi

```bash
# Başarısız jobları listele
php conduit queue:failed

# Output:
# ID  | Queue   | Failed At           | Exception
# ----|---------|---------------------|------------------
# 1   | default | 2026-01-04 10:30:00 | Connection timeout
# 2   | emails  | 2026-01-04 11:15:00 | SMTP error

# Belirli job'u yeniden dene
php conduit queue:retry 1

# Tümünü yeniden dene
php conduit queue:retry --all

# Başarısız jobları temizle
php conduit queue:flush
```

### Failed Job Callback

```php
class SendPaymentNotification extends Job {
    protected int $maxAttempts = 3;

    public function handle(): void {
        // Payment gateway'e istek at
        $response = $this->callPaymentGateway();

        if (!$response['success']) {
            throw new \Exception('Payment notification failed');
        }
    }

    public function failed(\Throwable $exception): void {
        // Tüm denemeler başarısız oldu
        logger()->critical('Payment notification failed permanently', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Admin'e email gönder
        mail_send(
            'admin@example.com',
            'CRITICAL: Payment Notification Failed',
            "Order #{$this->orderId} payment notification failed after 3 attempts.\n\nError: {$exception->getMessage()}"
        );

        // Order'ı flag'le
        $order = Order::find($this->orderId);
        $order->update(['needs_manual_review' => true]);
    }
}
```

---

## Gerçek Örnekler

### Örnek 1: Email Gönderme

```php
// app/Jobs/SendOrderConfirmationEmail.php
namespace App\Jobs;

use Conduit\Queue\Job;
use App\Models\Order;

class SendOrderConfirmationEmail extends Job {
    public function __construct(
        private int $orderId
    ) {}

    public function handle(): void {
        $order = Order::with(['user', 'items'])->find($this->orderId);

        if (!$order) {
            return;
        }

        $html = $this->renderEmail($order);

        mail_send(
            $order->user->email,
            "Sipariş Onayı - #{$order->order_number}",
            $html
        );

        logger()->info('Order confirmation email sent', [
            'order_id' => $this->orderId,
            'user_id' => $order->user_id,
        ]);
    }

    private function renderEmail(Order $order): string {
        $itemsHtml = '';
        foreach ($order->items as $item) {
            $itemsHtml .= "
                <tr>
                    <td>{$item->product_name}</td>
                    <td>{$item->quantity}</td>
                    <td>{$item->price} TL</td>
                </tr>
            ";
        }

        return "
            <h1>Siparişiniz Alındı</h1>
            <p>Sipariş No: <strong>{$order->order_number}</strong></p>
            <table>
                <thead>
                    <tr><th>Ürün</th><th>Adet</th><th>Fiyat</th></tr>
                </thead>
                <tbody>{$itemsHtml}</tbody>
            </table>
            <p><strong>Toplam: {$order->total} TL</strong></p>
        ";
    }
}

// Controller'da kullanım
class OrderController {
    public function store(Request $request): JsonResponse {
        $order = $this->orderService->createOrder($request->all());

        // Email'i queue'ya ekle (kullanıcı beklemez)
        SendOrderConfirmationEmail::dispatch($order->id);

        return new JsonResponse(['order' => $order], 201);
    }
}
```

### Örnek 2: Resim İşleme

```php
// app/Jobs/ProcessUploadedImage.php
namespace App\Jobs;

use Conduit\Queue\Job;

class ProcessUploadedImage extends Job {
    public function __construct(
        private string $imagePath,
        private int $mediaId
    ) {}

    public function handle(): void {
        $fullPath = storage_path($this->imagePath);

        // Orijinal resmi yükle
        $image = imagecreatefromjpeg($fullPath);

        if (!$image) {
            throw new \Exception("Failed to load image: {$fullPath}");
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Thumbnail oluştur (200x200)
        $this->createThumbnail($image, $originalWidth, $originalHeight, 200, 200);

        // Medium boyut (800x600)
        $this->createResized($image, $originalWidth, $originalHeight, 800, 600);

        // Large boyut (1920x1080)
        $this->createResized($image, $originalWidth, $originalHeight, 1920, 1080);

        imagedestroy($image);

        logger()->info('Image processed', [
            'media_id' => $this->mediaId,
            'path' => $this->imagePath,
        ]);
    }

    private function createThumbnail($source, $srcW, $srcH, $dstW, $dstH): void {
        $thumbnail = imagecreatetruecolor($dstW, $dstH);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $outputPath = storage_path('thumbnails/' . basename($this->imagePath));
        imagejpeg($thumbnail, $outputPath, 85);
        imagedestroy($thumbnail);
    }

    private function createResized($source, $srcW, $srcH, $maxW, $maxH): void {
        // Aspect ratio koru
        $ratio = min($maxW / $srcW, $maxH / $srcH);
        $dstW = (int) ($srcW * $ratio);
        $dstH = (int) ($srcH * $ratio);

        $resized = imagecreatetruecolor($dstW, $dstH);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $outputPath = storage_path("resized/{$maxW}x{$maxH}/" . basename($this->imagePath));
        imagejpeg($resized, $outputPath, 90);
        imagedestroy($resized);
    }
}

// Controller'da kullanım
class MediaController {
    public function upload(Request $request): JsonResponse {
        $file = $request->file('image');
        $path = 'uploads/' . uniqid() . '.jpg';

        storage()->put($path, file_get_contents($file['tmp_name']));

        $media = Media::create([
            'path' => $path,
            'size' => $file['size'],
        ]);

        // Resim işlemeyi queue'ya at (kullanıcı beklemez)
        ProcessUploadedImage::dispatch($path, $media->id);

        return new JsonResponse(['media' => $media], 201);
    }
}
```

### Örnek 3: Rapor Oluşturma

```php
// app/Jobs/GenerateMonthlySalesReport.php
namespace App\Jobs;

use Conduit\Queue\Job;
use App\Services\ReportService;

class GenerateMonthlySalesReport extends Job {
    protected int $maxAttempts = 1; // Rapor tek seferde oluşmalı

    public function __construct(
        private int $year,
        private int $month,
        private int $userId
    ) {}

    public function handle(): void {
        $reportService = app(ReportService::class);

        logger()->info('Generating monthly report', [
            'year' => $this->year,
            'month' => $this->month,
        ]);

        // Rapor verilerini topla (uzun sürebilir)
        $data = $reportService->getMonthlySalesData($this->year, $this->month);

        // Excel/CSV oluştur
        $csvContent = $this->generateCSV($data);

        // Storage'a kaydet
        $filename = "sales-report-{$this->year}-{$this->month}.csv";
        storage()->put("reports/{$filename}", $csvContent);

        // Kullanıcıya bildirim gönder
        $user = User::find($this->userId);

        mail_send(
            $user->email,
            'Rapor Hazır',
            "Merhaba,\n\n{$this->year}/{$this->month} satış raporunuz hazır.\n\nİndirme linki: /reports/{$filename}"
        );

        logger()->info('Monthly report generated', [
            'year' => $this->year,
            'month' => $this->month,
            'filename' => $filename,
        ]);
    }

    private function generateCSV(array $data): string {
        $csv = "Date,Orders,Revenue\n";

        foreach ($data as $row) {
            $csv .= "{$row['date']},{$row['orders']},{$row['revenue']}\n";
        }

        return $csv;
    }
}

// Controller'da kullanım
class ReportController {
    public function requestReport(Request $request): JsonResponse {
        $year = (int) $request->input('year');
        $month = (int) $request->input('month');
        $user = $request->getAttribute('user');

        // Rapor oluşturmayı queue'ya at
        GenerateMonthlySalesReport::dispatch($year, $month, $user->id);

        return new JsonResponse([
            'message' => 'Rapor oluşturuluyor. Hazır olduğunda email alacaksınız.',
        ], 202); // 202 Accepted
    }
}
```

### Örnek 4: Toplu İşlem (Bulk)

```php
// app/Jobs/SendNewsletterToSubscribers.php
namespace App\Jobs;

use Conduit\Queue\Job;
use App\Models\Subscriber;

class SendNewsletterToSubscribers extends Job {
    public function __construct(
        private string $subject,
        private string $content
    ) {}

    public function handle(): void {
        // Tüm aktif aboneleri al
        $subscribers = Subscriber::where('status', '=', 'active')->get();

        logger()->info('Sending newsletter', [
            'subject' => $this->subject,
            'subscriber_count' => count($subscribers),
        ]);

        // Her abone için ayrı email job'u oluştur (paralel işlenebilir)
        foreach ($subscribers as $subscriber) {
            SendNewsletterEmail::dispatch($subscriber->email, $this->subject, $this->content);
        }

        logger()->info('Newsletter jobs queued', [
            'count' => count($subscribers),
        ]);
    }
}

// app/Jobs/SendNewsletterEmail.php
class SendNewsletterEmail extends Job {
    protected int $maxAttempts = 2;

    public function __construct(
        private string $email,
        private string $subject,
        private string $content
    ) {}

    public function handle(): void {
        mail_send($this->email, $this->subject, $this->content);
    }
}

// Controller'da kullanım
class NewsletterController {
    public function send(Request $request): JsonResponse {
        $subject = $request->input('subject');
        $content = $request->input('content');

        // Ana job'u queue'ya at
        SendNewsletterToSubscribers::dispatch($subject, $content);

        return new JsonResponse([
            'message' => 'Newsletter gönderiliyor...',
        ], 202);
    }
}
```

### Örnek 5: Third-Party API Entegrasyonu

```php
// app/Jobs/SyncProductsFromSupplier.php
namespace App\Jobs;

use Conduit\Queue\Job;
use App\Services\SupplierAPIService;
use App\Models\Product;

class SyncProductsFromSupplier extends Job {
    protected int $maxAttempts = 3;
    protected int $retryDelay = 300; // 5 dakika

    public function __construct(
        private int $supplierId
    ) {}

    public function handle(): void {
        $apiService = app(SupplierAPIService::class);

        logger()->info('Syncing products from supplier', [
            'supplier_id' => $this->supplierId,
        ]);

        // Supplier API'den ürünleri çek (uzun sürebilir)
        $products = $apiService->getProducts($this->supplierId);

        $created = 0;
        $updated = 0;

        foreach ($products as $productData) {
            $existing = Product::where('supplier_sku', '=', $productData['sku'])->first();

            if ($existing) {
                $existing->update([
                    'price' => $productData['price'],
                    'stock' => $productData['stock'],
                ]);
                $updated++;
            } else {
                Product::create([
                    'supplier_id' => $this->supplierId,
                    'supplier_sku' => $productData['sku'],
                    'name' => $productData['name'],
                    'price' => $productData['price'],
                    'stock' => $productData['stock'],
                ]);
                $created++;
            }
        }

        logger()->info('Product sync completed', [
            'supplier_id' => $this->supplierId,
            'created' => $created,
            'updated' => $updated,
        ]);

        // Cache'i invalidate et
        cache()->delete('products_list');
    }

    public function failed(\Throwable $exception): void {
        logger()->error('Product sync failed', [
            'supplier_id' => $this->supplierId,
            'error' => $exception->getMessage(),
        ]);

        // Admin'e bildir
        mail_send(
            'admin@example.com',
            'Supplier Sync Failed',
            "Supplier #{$this->supplierId} senkronizasyonu başarısız oldu.\n\nHata: {$exception->getMessage()}"
        );
    }
}

// Cron ile otomatik çalıştır
// crontab: 0 2 * * * php /path/to/app/conduit queue:dispatch SyncProductsFromSupplier 1
```

---

## Best Practices

### ✅ YAP

```php
// 1. Küçük, tek amaçlı joblar yap
class SendEmail extends Job {} // ✅ İyi
class ProcessEverything extends Job {} // ❌ Kötü

// 2. Retry logic kullan
protected int $maxAttempts = 3;
protected int $retryDelay = 60;

// 3. Failed callback ekle
public function failed(\Throwable $e): void {
    logger()->error('Job failed', ['error' => $e->getMessage()]);
}

// 4. Logging yap
logger()->info('Job started', ['job_id' => $this->id]);

// 5. Timeout belirle
protected int $timeout = 300; // 5 dakika

// 6. Queue seç (priority)
SendEmailJob::dispatch($data)->onQueue('high-priority');
```

### ❌ YAPMA

```php
// 1. Sync işlemleri job'da yapma
SendEmail::dispatch($email)->wait(); // ❌ Sync olur, queue'nun anlamı kalmaz

// 2. Çok fazla veri pass etme
new ProcessJob($hugeArray); // ❌ Serialize edilir, yavaş olur
new ProcessJob($id); // ✅ Sadece ID gönder, job içinde çek

// 3. External dependency'leri constructor'da kullanma
public function __construct(PDO $db) {} // ❌ Serialize edilemez
public function handle() {
    $db = app(PDO::class); // ✅ handle() içinde al
}

// 4. Queue worker'ı unutma
// Job'ları dispatch ettiysen mutlaka worker çalıştır!
```

---

## Özet

- ✅ **Asenkron**: Uzun işlemleri arka planda çalıştır
- ✅ **Retry Logic**: Başarısız jobları otomatik tekrarla
- ✅ **Database-backed**: Redis gerekmez (shared hosting OK!)
- ✅ **Failed Jobs**: Başarısız jobları yönet
- ✅ **Scalable**: Birden fazla worker çalıştır
- ✅ **Delayed Jobs**: İstediğin zaman çalıştır
- ✅ **Priority Queues**: Önemli işleri önce çalıştır

**Altın Kural:** Kullanıcı beklemesi gereken her işlem için queue kullan!

**Worker'ı Unutma:** Job'ları dispatch ettiysen mutlaka `php conduit queue:work` çalıştır!

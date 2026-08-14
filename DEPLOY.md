# Deploy ke Railway.app

## Langkah-langkah:

### 1. Buat Akun Railway
- Buka https://railway.app
- Login dengan GitHub/Email

### 2. Buat Project Baru
- Klik "New Project"
- Pilih "Empty Project"

### 3. Tambah MySQL Database
- Klick "+ New"
- Pilih "Database" → "MySQL"
- Tunggu selesai provisioning

### 4. Deploy Laravel
- Klick "+ New" → "GitHub Repo" atau "Upload Files"
- Upload semua file di folder `backend/`
- Atau push ke GitHub lalu connect ke Railway

### 5. Setup Environment Variables
Di Railway dashboard, klik tab "Variables" lalu tambah:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:generate_dulu
APP_URL=https://namaproject.up.railway.app
DB_CONNECTION=mysql
DB_HOST=dari railway mysql
DB_PORT=3306
DB_DATABASE=dari railway mysql
DB_USERNAME=dari railway mysql
DB_PASSWORD=dari railway mysql
SESSION_DRIVER=database
BCRYPT_ROUNDS=12
```

### 6. Generate App Key
Buka terminal di Railway (tab "Deployments" → "View Logs")
Jalankan:
```bash
php artisan key:generate
```

### 7. Jalankan Migrations
```bash
php artisan migrate --force
```

### 8. Import Data
```bash
php artisan import:teachers
```

### 9. Buka Aplikasi
URL akan ada di tab "Settings" → "Networking" → "Public Domain"

## Catatan:
- Setiap kali deploy, Railway otomatis jalankan `migrate`
- Database MySQL persist (data tidak hilang)
- Gratis $5/bulan (cukup untuk app ini)

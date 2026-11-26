# Catatan Deployment Harmonis Dashboard

Catatan ringkas deployment **frontend** (React + Nginx/IIS) dan **backend** (Laravel PHP 8.3 + MariaDB/MySQL) menggunakan **Docker Compose** maupun **langsung di Linux dan Windows**.

> [!NOTE]
> Proyek ini disarankan menggunakan **MariaDB**, namun **MySQL** (tidak diuji) juga dapat digunakan karena template `.env` backend memakai adapter `mysql` (`DB_CONNECTION=mysql`).

---

## Metode 1: Deployment dengan Docker Compose

### 1. Prasyarat Server

- **Docker Engine** & **Docker Compose** (v2+)
- Port host `15001` tersedia (port dapat diubah bila diperlukan)

### 2. Persiapan Konfigurasi

Lakukan penyesuaian berikut **sebelum** menjalankan build kontainer.

#### A. Konfigurasi Frontend (Build Args & Nginx)

- **Build Arguments (`services.frontend.build.args` di `docker-compose.yml`)**: `VITE_BACKEND_PROTOCOL`, `VITE_BACKEND_HOSTNAME`, dan `VITE_BACKEND_PORT` bernilai default `current` (otomatis mengikuti origin Nginx saat ini). Ubah hanya jika API backend di-host di domain/port terpisah (gunakan `default` pada port untuk standar 80/443).
- **Batas Upload Nginx**: Nilai `client_max_body_size` pada `./frontend/nginx/config/default.conf` dapat disesuaikan dengan kebutuhan unggahan Anda.

#### B. Konfigurasi Backend & Kredensial Database

1. **Atur Environment Variables Backend (`docker-compose.yml`)**

   Atur variabel environment Laravel pada `services.backend` di `docker-compose.yml` (melalui `environment:` atau `env_file:`, mengacu ke daftar variabel pada `backend/.env.prod.example`). Pastikan variabel inti berikut sesuai:

   ```yaml
   services:
     backend:
       environment:
         - APP_ENV=production
         - APP_DEBUG=false
         - APP_KEY=<APP_KEY_ANDA>
         - APP_URL=http://<IP_ATAU_DOMAIN_ANDA>:15001
         - DB_CONNECTION=mysql
         - DB_HOST=database
         - DB_DATABASE=dashboard_harmonis
         - DB_USERNAME=<USERNAME_DB_PRODUKSI>
         - DB_PASSWORD=<PASSWORD_DB_PRODUKSI>
   ```

2. **Batas Upload PHP-FPM**: Pastikan `./backend/php-fpm/config/custom.ini` tersedia dan batas unggahan (`upload_max_filesize`, `post_max_size`) disesuaikan dengan kebutuhan serta selaras dengan batas Nginx.

3. **Pengamanan Kredensial Database (`docker-compose.yml`)**

   Ganti kredensial contoh (`admin` / `laravel`) pada `services.database.environment` di `docker-compose.yml`:

   ```yaml
   services:
     database:
       environment:
         - MARIADB_ROOT_PASSWORD=<PASSWORD_ROOT_DB_KUAT>
         - MARIADB_USER=<USERNAME_DB_PRODUKSI>
         - MARIADB_PASSWORD=<PASSWORD_DB_PRODUKSI>
   ```

   > [!WARNING]
   > - Jangan gunakan password default di produksi dan jangan commit perubahan `docker-compose.yml` (atau file env bila memakai `env_file`) yang berisi kredensial produksi ke Git.
   > - `MARIADB_*` hanya berlaku saat volume `dashboard_harmonis_db` pertama kali dibuat. Jika database sudah berjalan dengan kredensial lama, ubah langsung lewat SQL (`ALTER USER`) di MariaDB. **Menghapus volume lama sangat tidak disarankan karena akan menghapus seluruh data aplikasi secara permanen.**

#### C. Pengaturan Port Host (`docker-compose.yml` — Opsional)

Port `15001` adalah port bawaan. Jika perlu mengganti (misalnya bentrok dengan layanan lain), ubah mapping port host pada `services.frontend.ports` di `docker-compose.yml`:

```yaml
services:
  frontend:
    ports:
      - <PORT_HOST_ANDA>:8080
```

### 3. Langkah Deployment (Docker Compose)

1. **Build dan Jalankan Kontainer** dari root proyek:

   ```bash
   docker compose up -d --build
   docker compose ps
   ```

2. **Generate `APP_KEY` (jika belum diisi di `docker-compose.yml`)**:

   ```bash
   docker compose exec backend php artisan key:generate --show
   ```

   Masukkan output `base64:...` tersebut ke `APP_KEY` pada `services.backend` di `docker-compose.yml`, lalu terapkan perubahan dengan:

   ```bash
   docker compose up -d
   ```

3. **Jalankan Migrasi & Seeder Database**:

   ```bash
   docker compose exec backend php artisan migrate
   docker compose exec backend php artisan db:seed
   ```

   > [!WARNING]
   > `db:seed` membuat akun default `it_staff` dengan kredensial `admin` / `admin`. Segera amankan akun ini setelah seeding (lihat bagian [Keamanan Akun Default Setelah Seeding](#keamanan-akun-default-setelah-seeding)).

---

## Metode 2: Deployment Langsung (Tanpa Docker)

Web server yang digunakan pada metode ini (**Nginx** dan **IIS**) hanyalah contoh; Anda bebas menggunakan web server lain selama mendukung SPA React dan dapat menjalankan/meneruskan request ke PHP.

### Prasyarat Umum (Linux & Windows)

- **PHP 8.3** (varian **PHP-FPM**, **Non-Thread Safe / NTS**, atau **Thread Safe** disesuaikan dengan web server dan sistem operasi yang dipilih) dengan ekstensi aktif: `pdo_mysql`, `mysqli`, `gd`, `zip`, `intl`, `mbstring`, `xml`, `bcmath`, `curl`, `opcache`
- Pada `php.ini`, sesuaikan `upload_max_filesize` dan `post_max_size` sesuai kebutuhan ukuran unggahan
- **Composer 2** dan **Node.js 24**
- **MariaDB** (disarankan) atau **MySQL** (tidak diuji) dengan database serta akun pengguna yang sudah disiapkan
- Web server yang mendukung SPA React dan dapat menjalankan/meneruskan request ke PHP

### A. Deployment Langsung di Linux (Nginx + PHP-FPM)

#### 1. Setup Backend & Build Frontend

1. **Siapkan Backend (`backend/`)**:

   ```bash
   cd backend
   composer install --no-dev --optimize-autoloader
   cp .env.prod.example .env
   ```

   Atur `.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, serta `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), lalu jalankan inisialisasi dan atur izin direktori:

   ```bash
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   sudo chown -R www-data:www-data storage bootstrap/cache
   ```

   > [!WARNING]
   > `db:seed` membuat akun default `it_staff` dengan kredensial `admin` / `admin`. Segera amankan akun ini setelah deployment selesai (lihat [Keamanan Akun Default Setelah Seeding](#keamanan-akun-default-setelah-seeding)).

   *(Sesuaikan `upload_max_filesize` dan `post_max_size` di `php.ini` PHP-FPM sesuai kebutuhan unggahan, lalu restart layanan `php8.3-fpm`. Jika memilih **Opsi 2**, arahkan `APP_URL` ke URL/port backend dan pastikan konfigurasi CORS Laravel mengizinkan origin frontend beserta header `Authorization` serta method `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`.)*

2. **Build Frontend (`frontend/`)**:

   - **Opsi 1 (Satu Origin & Port)**: Biarkan `VITE_BACKEND_PROTOCOL`, `VITE_BACKEND_HOSTNAME`, dan `VITE_BACKEND_PORT` bernilai default `current`.
   - **Opsi 2 (Origin dan/atau Port Terpisah)**: Atur variabel berikut sebelum `npm run build` (misalnya melalui `frontend/.env.production` atau environment shell; sertakan titik dua pada protokol):

     ```env
     VITE_BACKEND_PROTOCOL=http:
     VITE_BACKEND_HOSTNAME=<DOMAIN_ATAU_IP_BACKEND>
     VITE_BACKEND_PORT=<PORT_BACKEND_ATAU_default>
     ```

   Jalankan build frontend:

   ```bash
   cd ../frontend
   npm ci
   npm run build
   ```

#### 2. Contoh Konfigurasi Nginx Linux

Contoh di bawah menggunakan **PHP 8.3 FPM** (`php8.3-fpm`); pastikan layanan tersebut sudah terpasang dan aktif (restart `php8.3-fpm` jika mengubah `php.ini`). Konfigurasi ini **hanyalah contoh** server block Nginx (misalnya di `/etc/nginx/sites-available/harmonis`). Sesuaikan path proyek, socket PHP-FPM, port, dan pengaturan lainnya sesuai kebutuhan server Anda, lalu uji dan reload Nginx (`sudo nginx -t && sudo systemctl reload nginx`).

##### Opsi 1: Satu Origin & Port (Frontend + Backend Bersama)

```nginx
server {
    listen 80;
    server_name _;

    root /path/ke/proyek/frontend/dist;
    index index.html;

    location = /api/upload {
        client_max_body_size 30M; # Sesuaikan dengan kebutuhan ukuran unggahan
        try_files /__laravel_front_controller__ @laravel;
    }

    location ^~ /api/ {
        try_files /__laravel_front_controller__ @laravel;
    }

    location @laravel {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /path/ke/proyek/backend/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT /path/ke/proyek/backend/public;
    }

    location / {
        try_files $uri /index.html;
    }
}
```

##### Opsi 2: Origin dan/atau Port Terpisah (Frontend & Backend Terpisah)

- **Contoh Server Block Frontend** (hanya menyajikan aset statis SPA):

  ```nginx
  server {
      listen 80;
      server_name app.domain-anda.com;

      root /path/ke/proyek/frontend/dist;
      index index.html;

      location / {
          try_files $uri /index.html;
      }
  }
  ```

- **Contoh Server Block Backend** (di domain dan/atau port terpisah):

  ```nginx
  server {
      listen 8000;
      server_name api.domain-anda.com;

      root /path/ke/proyek/backend/public;
      index index.php;

      client_max_body_size 30M; # Sesuaikan dengan kebutuhan ukuran unggahan

      location / {
          try_files $uri $uri/ /index.php?$query_string;
      }

      location ~ \.php$ {
          try_files $uri =404;
          include fastcgi_params;
          fastcgi_pass unix:/run/php/php8.3-fpm.sock;
          fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
      }
  }
  ```

### B. Deployment Langsung di Windows (Nginx atau IIS)

#### 1. Setup Backend & Build Frontend

1. **Siapkan Backend (`backend\`)**:

   ```powershell
   cd backend
   composer install --no-dev --optimize-autoloader
   copy .env.prod.example .env
   ```

   Atur `backend\.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_CONNECTION=mysql`, serta `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`; jika memakai **Opsi 2: Origin dan/atau Port Terpisah**, arahkan `APP_URL` ke URL/port backend dan atur CORS Laravel seperti pada bagian Linux), lalu jalankan inisialisasi:

   ```powershell
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   ```

   > [!WARNING]
   > `db:seed` membuat akun default `it_staff` dengan kredensial `admin` / `admin`. Segera amankan akun ini setelah deployment selesai (lihat [Keamanan Akun Default Setelah Seeding](#keamanan-akun-default-setelah-seeding)).

2. **Build Frontend (`frontend\`)**:

   *(Jika memilih **Opsi 2**, atur variabel `VITE_BACKEND_PROTOCOL`, `VITE_BACKEND_HOSTNAME`, dan `VITE_BACKEND_PORT` terlebih dahulu sebelum `npm run build` seperti pada bagian Linux di atas.)*

   ```powershell
   cd ..\frontend
   npm ci
   npm run build
   ```

#### 2. Pilihan Web Server

Kedua contoh web server di bawah (**Nginx for Windows** dan **IIS**) berkomunikasi via FastCGI, sehingga memerlukan **PHP 8.3 Non-Thread Safe (NTS)** (`php-cgi.exe`).

##### Opsi A: Menggunakan Nginx for Windows

1. Jalankan PHP FastCGI di port `9000` (disarankan didaftarkan sebagai service background menggunakan NSSM atau Task Scheduler):

   ```powershell
   C:\php\php-cgi.exe -b 127.0.0.1:9000
   ```

2. Sesuaikan contoh konfigurasi Nginx Linux sesuai mode yang dipilih:
   - **Opsi 1 (Satu Origin & Port)**: Gunakan contoh Opsi 1 Linux dengan menyesuaikan path Windows dan `fastcgi_pass`:

     ```nginx
     root C:/apps/harmonis/frontend/dist;
     # ...
     location @laravel {
         include fastcgi_params;
         fastcgi_pass 127.0.0.1:9000;
         fastcgi_param SCRIPT_FILENAME C:/apps/harmonis/backend/public/index.php;
         fastcgi_param SCRIPT_NAME /index.php;
         fastcgi_param DOCUMENT_ROOT C:/apps/harmonis/backend/public;
     }
     ```

   - **Opsi 2 (Origin dan/atau Port Terpisah)**: Gunakan dua contoh blok `server` dari Opsi 2 Linux dengan mengganti `root` ke `C:/apps/harmonis/frontend/dist` (frontend) dan `C:/apps/harmonis/backend/public` (backend), serta mengganti `fastcgi_pass` menjadi `127.0.0.1:9000`.

##### Opsi B: Menggunakan IIS

1. Aktifkan fitur **CGI** (pada *Turn Windows features on or off* → *Internet Information Services* → *Application Development Features*) dan pasang modul **IIS URL Rewrite** (serta **Application Request Routing / ARR** bila memakai Opsi 1).
2. Pada **Handler Mappings** IIS, daftarkan `*.php` menggunakan `FastCgiModule` yang mengarah ke `C:\php\php-cgi.exe`.
3. Atur Site sesuai mode yang dipilih:
   - **Opsi 1 (Satu Origin & Port)**:
     - Buat **Site Backend** lokal (misalnya pada port internal `127.0.0.1:8000`) mengarah ke `backend\public` dengan Handler Mapping `*.php` dan aturan **URL Rewrite** Laravel ke `index.php`.
     - Buat **Site Frontend** utama mengarah ke `frontend\dist`, lalu gunakan **URL Rewrite + ARR (Reverse Proxy)** untuk meneruskan request `/api/*` ke `http://127.0.0.1:8000` (agar prefix `/api` tetap utuh diterima Laravel), dan arahkan request non-file/non-direktori lainnya ke `/index.html`.
     - Sesuaikan **Request Filtering** (`maxAllowedContentLength`) sesuai kebutuhan ukuran unggahan.
   - **Opsi 2 (Origin dan/atau Port Terpisah)**:
     - Buat **Site Frontend** mengarah ke `frontend\dist` pada port/binding frontend, lalu pasang aturan **URL Rewrite** SPA fallback ke `/index.html`.
     - Buat **Site Backend** terpisah pada port/binding backend mengarah ke `backend\public`, aktifkan Handler Mapping `*.php` ke `php-cgi.exe`, pasang aturan **URL Rewrite** Laravel ke `index.php`, dan sesuaikan `maxAllowedContentLength` sesuai kebutuhan.

---

## Keamanan Akun Default Setelah Seeding

Perintah `php artisan db:seed` (`DatabaseSeeder`) akan membuat akun bawaan dengan role **Staf IT** (`it_staff`), `username` = `admin`, dan `password` = `admin` apabila belum ada pengguna ber-role `it_staff` di database. **Jangan biarkan kredensial bawaan ini aktif di lingkungan produksi.**

Untuk memulai, login ke aplikasi menggunakan akun `admin` / `admin`, lalu buka menu **Kelola Pengguna** di sidebar dan pilih salah satu dari dua opsi pengamanan berikut:

### Opsi A: Ubah Kata Sandi (dan Opsional Username) Akun `admin`

1. Pada baris pengguna `admin`, klik tombol ubah (ikon edit) untuk membuka dialog **Ubah Informasi Pengguna**.
2. Isi kolom **Password** dengan kata sandi baru yang kuat (minimal 4 karakter; disarankan kombinasi huruf besar, huruf kecil, angka, dan simbol).
3. *(Opsional)* Ubah **Username** sesuai kebutuhan (hanya menerima huruf, angka, `-`, dan `_`).
4. Pastikan checkbox **Pengguna Aktif** tetap tercentang dan **Role** tetap **Staf IT** *(sistem mencegah akun yang sedang login menonaktifkan atau menurunkan role dirinya sendiri)*.
5. Klik **Ubah Pengguna**, lalu logout dan uji login ulang menggunakan kredensial baru tersebut.

### Opsi B (Alternatif): Buat Akun Staf IT Baru lalu Nonaktifkan atau Hapus Akun `admin`

1. Pada halaman **Kelola Pengguna**, klik tombol **Tambahkan Pengguna Baru**.
2. Isi **Username** (huruf, angka, `-`, atau `_`) dan **Password** yang kuat (minimal 4 karakter), pilih **Role** = **Staf IT**, pastikan checkbox **Pengguna Aktif** tercentang, lalu klik **Tambahkan Pengguna**.
3. **Logout** dari akun `admin`, lalu **login ulang menggunakan akun Staf IT baru** tersebut *(wajib dilakukan karena pengguna yang sedang aktif tidak dapat menonaktifkan atau menghapus akunnya sendiri)*.
4. Buka kembali menu **Kelola Pengguna**, cari baris `admin`, lalu pilih salah satu tindakan berikut:
   - **Nonaktifkan akun `admin`**: Klik tombol ubah (ikon edit), hapus centang **Pengguna Aktif**, lalu klik **Ubah Pengguna**.
   - **Hapus akun `admin`**: Klik tombol hapus (ikon tempat sampah), lalu konfirmasi dengan menekan **Hapus Pengguna**.

> [!NOTE]
> **Catatan Khusus Seed Ulang (`php artisan db:seed`)**: Seeder memeriksa keberadaan pengguna dengan role `it_staff` di database, bukan hanya username `admin`. Selama masih ada minimal satu akun dengan role `it_staff` (misalnya akun Staf IT baru dari Opsi B, atau akun `admin` yang hanya dinonaktifkan), menjalankan `php artisan db:seed` ulang **tidak** akan membuat akun `admin` lagi. Namun, jika tidak ada satu pun akun ber-role `it_staff` yang tersisa di database (misalnya seluruh akun `it_staff` terhapus atau database di-reset), menjalankan `php artisan db:seed` ulang akan otomatis membuat kembali akun `admin` dengan kata sandi default `admin`.

---

## Troubleshooting Singkat

- **Bentrok port (Docker)**: Ganti port host pada `services.frontend.ports` di `docker-compose.yml`, lalu jalankan `docker compose up -d`.
- **Error koneksi database**: Pastikan `DB_HOST` (`database` untuk Docker, `127.0.0.1` untuk instalasi langsung) dan kredensial database pada environment (`docker-compose.yml` atau `.env`) cocok; jika mengubah `.env` secara langsung, jalankan `php artisan config:clear`.
- **Error `413 Request Entity Too Large` / batas unggahan**: Samakan batas ukuran request pada Nginx (`client_max_body_size`) atau IIS (`maxAllowedContentLength`) dengan `upload_max_filesize` dan `post_max_size` di konfigurasi PHP.
- **Error permission / FastCGI tidak merespons (Linux & Windows)**: Di Linux pastikan `chown -R www-data:www-data storage bootstrap/cache` dan service `php8.3-fpm` aktif; di Windows pastikan proses `php-cgi.exe -b 127.0.0.1:9000` (Nginx) atau Handler Mapping FastCGI (IIS) berjalan.
- **Error CORS atau request mengarah ke URL salah (Opsi 2 Terpisah)**: Pastikan CORS Laravel mengizinkan origin frontend beserta header `Authorization` dan method `OPTIONS`, pastikan `VITE_BACKEND_PROTOCOL` menyertakan titik dua (`http:` / `https:`), dan jalankan ulang `npm run build` setelah mengubah variabel `VITE_BACKEND_*`.

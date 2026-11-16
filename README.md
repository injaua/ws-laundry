<div align="center">
  <br>
  <h1>🧺 WS Clean & Laundry Serviços, Lda</h1>
  <p><strong>Modern Laundry Management System</strong> — a Laravel-based laundry management app designed to keep small and medium laundry businesses running efficiently.</p>
  <p>
    <img src="https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel" alt="Laravel 11">
    <img src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php" alt="PHP 8.3">
    <img src="https://img.shields.io/badge/SQLite-003B57?logo=sqlite" alt="SQLite">
    <img src="https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap" alt="Bootstrap 5">
    <img src="https://img.shields.io/badge/license-MIT-blue" alt="MIT License">
  </p>
  <br>
</div>

## 📖 About WS Clean & Laundry Serviços, Lda
WS Clean & Laundry Serviços, Lda is an integrated digital solution for laundry businesses. The system covers full operational management, real-time status tracking, a loyalty points system, and accurate financial reporting to support business decisions.

## 📋 Table of Contents

- [Key Features](#-features)
- [Prerequisites](#-prerequisites)
- [Installation](#-installation)
- [Default Logins](#-default-logins)
- [Database Structure](#-database-structure)
- [License](#-license)

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| 👥 **Customer Management** | Customer CRUD, order history details, and a **loyalty points** system (earn & redeem) |
| 🛠️ **Service Management** | Manage by-weight / by-piece services and **laundry packages** |
| 📋 **Orders** | Status tracking (8-step forward-only flow), **auto-lock on completion**, and **barcode label printing** |
| 🔍 **Public Status Check** | Self-service tracking page for customers via order number |
| 💰 **Finance & Reports** | Profit-and-loss reports (Chart.js), Excel export (.xlsx), and expense management |
| 🛡️ **Stability & Safety** | Audit **activity log**, multi-role (Admin/Cashier/Owner), automatic backups, and **double-submit prevention** |
| 🌗 **Modern UI/UX** | Bootstrap 5.3, persistent **dark mode**, and a responsive design |

---

## 🚀 Prerequisites

- **PHP** 8.1+
- **Composer** 2.x
- **Database** SQLite (dev) / MySQL 5.7+ (prod)
- **PHP extensions**: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `xml`

---

## 🛠️ Installation

```bash
git clone https://github.com/afdhalpower/laravel-laundry.git
cd laravel-laundry
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

---

## 🔐 Default Logins

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@ws-cleanlaundry.example` | `password` |
| Cashier | `cashier@ws-cleanlaundry.example` | `password` |
| Owner | `owner@ws-cleanlaundry.example` | `password` |

---

## 🗂️ Database Structure
`users`, `customers`, `orders`, `order_items`, `services`, `payments`, `expenses`, `packages`, `loyalty_points`, `activity_logs`

---

## 📄 License
**MIT License** — © 2026 [afdhalpower](https://github.com/afdhalpower)

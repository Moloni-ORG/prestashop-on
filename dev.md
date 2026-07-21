# PrestaShop Module – Docker, Build & Run Instructions

This document explains how to install dependencies, compile assets, and run the PrestaShop module using Docker.

---

## Prerequisites

Make sure you have the following installed on your system:

* PHP & Composer
* Node.js & npm
* Docker & Docker Compose

---

## 1. Install PHP Dependencies

From the **root of the project**, run:

```bash
composer install
```

This will install all required PHP dependencies for the PrestaShop module.

---

## 2. Install Frontend Dependencies

Navigate to the `.dev` folder:

```bash
cd .dev
```

Then install the Node.js dependencies:

```bash
npm install
```

---

## 3. Compile Assets (CSS & JavaScript)

Still inside the `.dev` folder, run:

```bash
npm run build-prod
```

⚠️ **Important:**

* This command must be run **every time you make changes to CSS or JavaScript files**.
* The compiled assets are required for the module to work correctly.

---

## 4. Start the Store Using Docker

From the **root of the project**, run:

```bash
docker compose up -d
```

This will start the PrestaShop store, database, and all required services in the background. The
project directory is bind-mounted into the container at `/var/www/html/modules/molonion`, so changes
you make locally are reflected inside the store.

---

## 5. Access the Back Office

Once Docker is running, open your browser and navigate to:

```
http://localhost:8080/administration
```

⏳ **First startup notice:**

* The first time you run Docker, it may take a few minutes.
* During this time, the store and database are being configured.

---

## Summary

1. `composer install` (project root)
2. `npm install` (inside `.dev`)
3. `npm run build-prod` (inside `.dev`, required after JS/CSS changes)
4. `docker compose up -d` (project root)
5. Open `http://localhost:8080/administration`

---

You are now ready to develop and run the PrestaShop module locally using Docker 🚀

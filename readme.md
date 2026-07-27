# Moloni ON – PrestaShop Integration

Innovative billing software that fits your business.
Designed for professionals, micro, small, and medium-sized enterprises who use PrestaShop and want seamless integration with Moloni ON for invoicing and product synchronization.

---

## ✨ Features

With the Moloni ON module, you can:

- 🔄 Synchronize products and stock between PrestaShop and Moloni ON
- 🧾 Issue documents automatically or manually from orders
- ⚙️ Choose the status of issued documents (draft or closed)
- 📄 Select from a wide range of document types (invoices, receipts, etc.)
- 🏬 Define the outbound warehouse for items
- 📬 Automatically send documents to customers by email
- 👤 Automatically create customers and articles
- 🧩 Customize billing settings to fit your workflow
- 🔗 Access issued documents directly from the PrestaShop Back Office
- 🧰 View logs and use tools provided by the module to troubleshoot and manage syncs

All technical and commercial support is provided free of charge by the Moloni Customer Support team.

---

## 🛠 Requirements

- PrestaShop: 1.7.6 — 8.2.1 (module declares compatibility)
- PHP: 7.2 or higher (7.4+ recommended)
- PHP extensions: curl, json
- A Moloni ON account with API access

Note: Version constraints are also defined in the module’s composer configuration.

---

## 📥 Installation

### Install via Back Office (recommended)
1. Download the latest release of the module ZIP from the GitHub releases page.
2. In your PrestaShop Back Office, go to Modules > Module Manager > Upload a module.
3. Upload the ZIP and follow the on-screen steps to install.

### Install via FTP/SFTP
1. Extract the module and upload the folder to:
   /modules/molonion
2. In your Back Office, go to Modules > Module Manager, locate “Moloni ON” and click Install.

---

## ⚙️ Configuration

After installation, open the module settings in the Back Office:

1. Connect to Moloni ON
   - Go to Sell > Moloni ON > Settings.
   - Enter your Moloni ON API Client ID and Client Secret.
   - Authorize the app and select your company.

2. Configure your preferences (organized by tabs)

   Documents
   - Document set and type used when creating documents
   - Document reference and status (draft or closed)
   - Product details source (use PrestaShop or Moloni ON names/descriptions)
   - Exemption reasons for products and shipping (if applicable)
   - Measurement unit and document warehouse
   - Customer options: code prefix, auto-update, default language
   - Optional: create Bill of Lading, include shipping information, send documents by email

   Orders
   - “Orders since” filter for listings
   - Order statuses that make an order eligible for document creation

   Automation
   - Sync stock from PrestaShop → Moloni ON and choose the warehouse
   - Sync stock from Moloni ON → PrestaShop and choose the warehouse
   - Automatically create/update products in both directions
   - Choose which product fields are synchronized

   Advanced
   - Alert email for error notifications

3. Issuing documents
   - Automatically: when an order enters one of the selected statuses (if “Auto create documents” is enabled)
   - Manually: from each order page (Moloni ON action buttons) or from the Moloni ON > Orders list

---

## ❓ Frequently Asked Questions

### Is there a paid version of this module?
No. The module is fully free and open-source.

### Do I need to pay for support?
No. Support is completely free and provided by the Moloni Customer Support team.

### Who can I contact for questions or suggestions?
You can reach us at suporte@molonion.pt.

---

## 🧰 Troubleshooting

- Ensure your API credentials are correct and the company is selected after authorization.
- Verify that the configured document set, type, and warehouse exist in your Moloni ON account.
- For stock sync from Moloni ON → PrestaShop, ensure your Moloni ON plan supports webhooks and the option is enabled in the module.
- Use the Moloni ON > Logs area to inspect recent actions and errors.

---

## 🤝 Support
- Email: suporte@molonion.pt
- Please include relevant screenshots, order IDs, and a brief description of what you were doing when the problem occurred.

---

## 🌐 Repository
- GitHub: https://github.com/Moloni-ORG/prestashop-on

---

## 🧑‍💻 Development (optional)

From source:
- Requires PHP and Composer.
- Install dependencies and run the build script:

```powershell
composer install
composer run build
```

This will run coding standards tools and build the distributable package using the project’s builder.

---

## 📄 License
See the LICENSE file included in this repository for licensing details.

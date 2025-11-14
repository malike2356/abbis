# ABBIS Onboarding Wizard

The onboarding wizard helps new deployments import their existing records for **clients**, **rigs**, **workers**, and **catalog items** without touching SQL scripts. It offers a guided workflow with CSV upload, column mapping, preview, and import summary.

---

## 🚀 Launching the Wizard

1. Sign in as an administrator.
2. Navigate to `System → Onboarding Wizard` or open `modules/onboarding-wizard.php`.
3. Choose the dataset you want to import (clients, rigs, workers, catalog items).
4. Upload your CSV file (headers required in the first row).
5. Map your CSV columns to ABBIS fields.
6. Review the summary and run the import.

The wizard shows:

- Detected rows and sample preview.
- Required field indicators.
- Suggested mappings based on header names.
- Import results (inserted, updated, skipped, and errors).

---

## 📋 Supported Datasets & Required Columns

| Dataset | Required Fields | Notes |
| ------- | --------------- | ----- |
| Clients | Client Name | Optional: Contact Person, Phone, Email, Address |
| Rigs | Rig Name, Rig Code | Optional: Truck Model, Registration, Status |
| Workers | Worker Name, Role | Optional: Default Rate, Phone, Status |
| Catalog Items | Item Name | Optional: SKU, Item Type, Category, Prices, Flags |

> **Tip:** Match your CSV headers to the field names for automatic mapping. You can still adjust mappings manually.

---

## ⚙️ Advanced Options

- **Update existing records:** Enabled by default. The importer updates rows when it finds a match (e.g., by rig code or client name + phone). Disable to insert only.
- **Skip blank values:** Enabled by default. Blank cells will not overwrite existing data. Disable to allow blanks to clear existing values.
- **CSV delimiters:** Choose comma, semicolon, tab, or pipe.

---

## 🧾 Import Results

After running the import the wizard displays:

- Count of inserted, updated, and skipped rows.
- Total rows processed.
- First 10 errors (if any), including row number and reason (e.g., missing required fields or invalid values).

Errors do not stop the whole import—remaining rows continue processing.

---

## 🖥️ Command Line Imports

Administrators can run imports on the server via CLI, useful for large files or automated deployments.

```bash
php scripts/import-dataset.php clients storage/import/clients.csv
php scripts/import-dataset.php rigs data/rigs.csv --delimiter=";" --no-update
```

- `--delimiter` sets the CSV separator (defaults to comma).
- `--no-update` prevents updating existing rows.
- `--allow-blank-overwrite` lets blank cells overwrite existing values.

The CLI uses the same dataset definitions and validations as the wizard.

---

## 🔐 Security & Cleanup

- Uploaded CSVs are stored temporarily in `storage/temp` and deleted after import completion.
- Only admins can access the wizard and API endpoints.
- CSRF tokens guard all requests and uploads.

---

## 📚 Related Resources

- `modules/data-management.php` → Export/Import/Purge tools.
- `scripts/import-dataset.php` → CLI automation script.
- `docs/DIRECTORY_STRUCTURE.md` → Project layout.

For further assistance, visit the in-app Help (`Help → Data Management → Onboarding Wizard`) or contact your ABBIS support representative.



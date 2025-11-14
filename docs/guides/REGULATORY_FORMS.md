# Regulatory Forms Automation Guide

## Overview
The Regulatory Forms module helps teams compile official documents that must be filed with regulators, municipal agencies, and compliance authorities. Templates are written in HTML with merge fields referencing ABBIS data (field reports, rigs, clients, and company profile). The module renders completed forms instantly and logs the output for future download or auditing.

## Setup
1. **Run database migration**: `php scripts/setup-regulatory-forms.php`
2. **Permission**: Users require the `resources.access` permission (or admin role) to access the module.
3. **Storage path**: Generated HTML files are stored in `storage/regulatory/`. Ensure the directory is writable by the web server.

## Creating Templates
1. Navigate to `Resources → Regulatory Forms`.
2. Fill out the template form:
   - **Form Name** – internal label for the form.
   - **Jurisdiction** – optional tag (e.g., “GWCL / Ghana”).
   - **Reference Type** – choose the data source (`Field Report`, `Rig`, `Client`, or `Custom`).
   - **Template HTML** – author your form layout using HTML and merge tags.
   - **Instructions** – internal guidance for when/how to use the template.
3. Save. The template now appears in the list and can be edited, duplicated, or deleted.

### Merge Tags
Use `{{ placeholder }}` syntax. Some examples:

| Placeholder | Description |
|-------------|-------------|
| `{{ company.company_name }}` | Company name from `system_config` |
| `{{ company.company_phone }}` | Company phone number |
| `{{ generated_at }}` | Generation timestamp |
| `{{ field_report.report_id }}` | Field report ID (when reference type is `field_report`) |
| `{{ field_report.site_name }}` | Field report site name |
| `{{ field_report.report_date_formatted }}` | Formatted field report date |
| `{{ field_report.rig_name }}` | Associated rig |
| `{{ field_report.client_name }}` | Client name |
| `{{ rig.rig_name }}` | Rig name (for rig reference templates) |
| `{{ client.address }}` | Client address |

You can also reference custom context values supplied at generation time via `{{ context.variable }}`.

## Generating Forms
1. Open the **Generate Form** tab.
2. Select a template (only active templates appear).
3. Provide the reference record ID (e.g., Field Report ID) that the template expects.
4. Optional: paste JSON context (e.g., `{"inspector_name": "A. Boateng"}`) to inject ad-hoc values.
5. Click **Generate Form**.
6. The preview panel displays the rendered form. Download the HTML from the same view or via the **Generation Log** tab.

Every generation is logged in `regulatory_form_exports`, storing the template used, reference data, user, and downloadable output path.

## Tips
- Use `<table>` elements to approximate PDF-style layouts.
- Combine basic CSS inline styles (e.g., `<strong>`, `<u>`, `<p style="margin-bottom:8px;">`) for readability.
- Embed conditional wording by supplying context JSON and referencing it in the template.
- Duplicating templates is useful when regional variations share a baseline layout.

## Troubleshooting
- **Missing data**: Unresolved merge tags render as em-dashes (`—`). Confirm that the reference data (e.g., field report) exists and includes the expected field.
- **Permissions**: Ensure the user has `resources.access`.
- **File permissions**: If downloads fail, give write access to `storage/regulatory`.
- **Custom placeholders**: When using context JSON, ensure the string is valid JSON (double quotes).

## API Endpoint
`POST /api/regulatory-form-generate.php` accepts JSON payload:
```json
{
  "csrf_token": "...",
  "template_id": 5,
  "reference_id": 123,
  "context": {
    "inspector_name": "A. Boateng"
  }
}
```
Response includes rendered HTML, datasets, and a download URL for the stored artifact.

## Change Log
- **v1.0** – Initial release with template CRUD, generation, logging, and documentation (ABBIS v3.2).


# Environmental Sampling Workflow Guide

## Overview

Use the Environmental Sampling module to manage water, soil, air, or geological sampling projects from field collection through lab analysis. The workflow includes:

- Project planning and scheduling
- Sample metadata and field observations
- Chain-of-custody logging
- Laboratory results capture and QA/QC flags

## Setup

1. Run the database migration:  
   `php scripts/setup-environmental-sampling.php`
2. Ensure users have `resources.access` permission.
3. Access the module via `Resources → Environmental Sampling`.

## Creating a Project

1. Click **New Project**.
2. Enter project code/name (code autogenerates if left blank).
3. Select client, sampling type, status, and schedule dates.
4. Optional: link a field report ID, enter site details, coordinates, and notes.
5. Save. The project appears in the dashboard with summary stats.

## Managing Samples

1. Within a project, click **Add Sample**.
2. Capture metadata: sample type, matrix, collection/preservative methods, collector, time, temperature, and observations.
3. Update sample status as it moves from field to lab (`pending → in_cooler → in_transit → at_lab → analyzed → disposed`).

## Chain of Custody

1. Click **Log Transfer**.
2. Select the sample, action (collected, sealed, shipped, received, etc.), timestamp, handler, and notes.
3. Optionally record cooler temperature and whether the lab received the sample.
4. Entries appear in a chronological timeline to prove custody integrity.

## Lab Results

1. Click **Add Result**.
2. Choose a sample and parameter (e.g., pH, Iron, Total Coliform).
3. Record value, units, detection limit, method reference, analyst, and QA/QC flag (pass/review/fail).
4. Attach method notes, remarks, or a report path.
5. Results display in a tabular summary grouped by parameter.

## Status Tracking

- Set the overall project status (Draft → Scheduled → In Progress → Submitted → Completed → Archived).
- Sample status changes are independent but reflected in the project drill-down.

## Tips

- Use descriptive sample codes (e.g., `BH-01-2025-11-13`).
- Record transfer conditions (temperature, seal condition) for compliance audits.
- Attach scanned chain-of-custody forms or lab certificates via the `attachment_path` field.
- Filter the project list by status, client, or keyword (site name, project code).

## API Endpoints

- `POST /api/environmental-sampling.php` (actions: `save_project`, `save_sample`, `add_chain_entry`, `add_lab_result`, `update_status`)
- `POST /api/environmental-sampling-view.php?id={projectId}` – detailed JSON for dashboards or integrations.

Ensure each request includes a valid `csrf_token` if invoked from browser sessions.


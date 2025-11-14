# Geology Estimator Guide

The Geology Estimator predicts drilling conditions (depth, aquifer type, yields, water level) at any location by analysing nearby wells in your historical dataset.

---

## 📦 Prerequisites

1. **Run the migration:**
   ```bash
   php scripts/setup-geology-estimator.php
   ```
2. **Import well data** via the onboarding wizard (choose **Geology Wells**) or CLI:
   ```bash
   php scripts/import-dataset.php geology_wells storage/import/geology_wells.csv
   ```

The CSV should include latitude, longitude, drilled depth, and any optional hydrogeology attributes (aquifer type, lithology, yield, TDS, etc.).

---

## 🗺️ Using the Estimator

1. Open `Resources → Geology Estimator`.
2. Drop a pin on the map or enter the coordinates manually.
3. Adjust the search radius (default 15 km) and click **Generate Estimate**.
4. Review the prediction cards, lithology/aquifer summary, and the list of nearby wells.
5. Use **Download Quote/Invoice** buttons in the client portal to share estimation data with customers.

Each prediction is logged to `geology_prediction_logs` so you can audit activity or report on estimator usage.

---

## 🔧 API

`POST /api/geology-estimate.php`

```jsonc
{
  "csrf_token": "...",
  "latitude": 5.6037,
  "longitude": -0.1870,
  "radius_km": 15
}
```

Response excerpt:

```jsonc
{
  "success": true,
  "data": {
    "prediction": {
      "depth_avg_m": 62.4,
      "depth_min_m": 54.0,
      "depth_max_m": 71.2,
      "recommended_casing_depth_m": 46.8,
      "confidence_score": 0.78
    },
    "lithology_summary": "weathered granite, clay, fractured quartz",
    "neighbors": [
      {
        "reference_code": "GW-045",
        "depth_m": 65.0,
        "distance_km": 4.2,
        "aquifer_type": "Fractured Basement"
      }
    ]
  }
}
```

Use the `neighbor_count` and `confidence_score` values to decide when to seek all-new geology data.

---

## 📝 Tips

- Import as many wells as possible within the areas you serve. The estimator’s confidence increases with local data density.
- Include lithology, aquifer type, and water quality notes—the system builds meaningful insights from recurring patterns.
- Leverage the prediction logs for dashboards or to pre-fill proposal templates for customers.


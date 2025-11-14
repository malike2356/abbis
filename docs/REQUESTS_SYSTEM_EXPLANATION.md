# Requests System - How It Works

## Overview

The Requests System handles two distinct types of client requests, each serving different customer segments and business needs.

---

## 📋 Request a Quote (Quote Requests)

### Who Uses This?
**Direct clients and homeowners** who need a complete borehole service from start to finish.

### What's Included?
When a client requests a quote, they can select from these services:

1. **Drilling** - Borehole drilling with your drilling machine
2. **Construction** - Installation of materials:
   - Screen pipe
   - Plain pipe  
   - Gravels
3. **Mechanization** - Pump installation and accessories:
   - Can select preferred pumps from your catalog
   - Pump accessories included
4. **Yield Test** - Water yield testing with all technical details
5. **Chemical Test** - Laboratory water quality testing
6. **Polytank Stand Construction** - Optional construction of polytank stand

### How It Works

1. **Client Submits Form** (`/cms/quote`)
   - Fills out personal details (name, email, phone, location)
   - Selects which services they need (checkboxes)
   - If mechanization is selected, can choose preferred pumps from catalog
   - Optionally provides estimated budget
   - Submits request

2. **System Processing**
   - Request saved to `cms_quote_requests` table
   - Client record created/updated in `clients` table (if not existing)
   - CRM follow-up automatically created in `client_followups` table
   - Request appears in CRM Dashboard and Requests Management

3. **Staff Management**
   - View all quote requests in `?module=requests&type=quote`
   - Update status: New → Contacted → Quoted → Converted/Rejected
   - View full details in modal
   - Track in CRM for follow-up

4. **Integration Points**
   - **CRM:** Auto-creates follow-ups for staff to contact client
   - **Clients:** Creates/updates client records
   - **Finances:** When converted, links to field reports and financial records

---

## 🚛 Request Rig (Rig Requests)

### Who Uses This?
**Agents and contractors** who don't have their own drilling rigs and want to rent one for their projects.

### What's Collected?
- Requester details (name, email, phone, company name)
- Requester type (Agent, Contractor, or Existing Client)
- Location with Google Maps integration:
  - Search for location or click on map
  - Drag marker to adjust
  - Coordinates automatically saved
- Number of boreholes to drill
- Estimated budget (optional)
- Preferred start date
- Urgency level (Low, Medium, High, Urgent)
- Additional notes

### How It Works

1. **Contractor/Agent Submits Form** (`/cms/rig-request`)
   - Fills out requester details
   - Selects location using Google Maps
   - Specifies number of boreholes
   - Optionally provides budget and preferred start date
   - Sets urgency level
   - Submits request

2. **System Processing**
   - Request saved to `rig_requests` table
   - Auto-generated request number: `RR-YYYYMMDD-####` (e.g., RR-20241215-0001)
   - If requester is existing client, links to client record
   - If new, creates client record
   - CRM follow-up automatically created
   - Request appears in CRM Dashboard and Requests Management

3. **Staff Management**
   - View all rig requests in `?module=requests&type=rig`
   - Update status: New → Under Review → Negotiating → Dispatched → Completed/Declined
   - Assign rig to the request
   - Assign user to handle the request
   - Add internal notes (not visible to requester)
   - View full details in modal

4. **Integration Points**
   - **CRM:** Auto-creates follow-ups
   - **Clients:** Links to existing clients or creates new ones
   - **Rigs:** Can assign specific rig to request
   - **Field Reports:** When completed, can link to field report
   - **Finances:** Links to financial records when job is done

---

## Unified Requests Management Page

### Access
Navigate to `?module=requests` in ABBIS

### Features
- **View Both Types:** See quote and rig requests in one place
- **Filtering:**
  - Filter by type (All, Quote Only, Rig Only)
  - Filter by status
- **CRUD Operations:**
  - **Create:** Done via CMS forms (public-facing)
  - **Read:** View all requests in table format
  - **Update:** Edit status, assign resources, add notes
  - **Delete:** Remove requests (use with caution)
- **Modal Details:** Click "View" to see full request details in a modal
- **Quick Actions:** Edit status, assign rigs/users directly from table

---

## CRM Integration

### Dashboard Display
The CRM Dashboard clearly distinguishes between the two request types:

- **📋 Request a Quote:**
  - Blue border and color (#0ea5e9)
  - Label: "REQUEST A QUOTE"
  - Description: "Complete borehole services"
  - Shows count of new quote requests

- **🚛 Request Rig:**
  - Green border and color (#059669)
  - Label: "REQUEST RIG"
  - Description: "Rig rental for contractors"
  - Shows count of new rig requests

### Automatic Actions
Both request types automatically:
1. Create/update client records
2. Create CRM follow-ups (scheduled for next day, high priority)
3. Record activities in client activity log
4. Appear in CRM Dashboard for quick access

### Follow-up Workflow
- Staff can see all requests in CRM Dashboard
- Click "View All" to go to Requests Management
- Use CRM follow-ups to track communication
- Update request status as you progress
- Link completed requests to field reports and finances

---

## Request Statuses

### Quote Request Statuses
- **New** - Just received, not yet contacted
- **Contacted** - Initial contact made
- **Quoted** - Quote has been sent to client
- **Converted** - Quote accepted, job in progress
- **Rejected** - Quote declined or request cancelled

### Rig Request Statuses
- **New** - Just received
- **Under Review** - Being evaluated
- **Negotiating** - Discussing terms and pricing
- **Dispatched** - Rig has been assigned and dispatched
- **Completed** - Job finished, can link to field report
- **Declined** - Request rejected
- **Cancelled** - Request cancelled by requester

---

## Database Structure

### Tables
1. **`cms_quote_requests`** - Stores quote requests
   - Includes service flags (drilling, construction, mechanization, etc.)
   - Pump preferences (JSON array of catalog item IDs)
   - Location coordinates
   - Estimated budget

2. **`rig_requests`** - Stores rig rental requests
   - Auto-generated request numbers
   - Location with coordinates
   - Links to clients, rigs, and field reports
   - Internal notes for staff

3. **`rig_request_followups`** - Tracks follow-ups for rig requests
   - Similar to `client_followups` but specific to rig requests

### Relationships
- Quote requests → `clients` (via `converted_to_client_id`)
- Rig requests → `clients` (via `client_id`)
- Rig requests → `rigs` (via `assigned_rig_id`)
- Rig requests → `field_reports` (via `field_report_id`)
- Both → `client_followups` (auto-created)

---

## Workflow Examples

### Example 1: Complete Quote Request Flow
1. Homeowner visits `/cms/quote`
2. Selects: Drilling, Construction, Mechanization, Yield Test
3. Chooses preferred pump from catalog dropdown
4. Submits form
5. System creates client record and follow-up
6. Staff sees in CRM Dashboard (blue card)
7. Staff contacts client, sends quote
8. Updates status to "Quoted"
9. Client accepts, status → "Converted"
10. Job proceeds, creates field report
11. Links to finances and CRM

### Example 2: Rig Rental Flow
1. Contractor visits `/cms/rig-request`
2. Fills details, selects location on map
3. Specifies 3 boreholes, high urgency
4. Submits form
5. System generates request number: RR-20241215-0001
6. Creates client record (if new) and follow-up
7. Staff sees in CRM Dashboard (green card)
8. Staff reviews, assigns rig "Rig-01"
9. Updates status to "Dispatched"
10. Rig goes to site, completes job
11. Staff creates field report, links to rig request
12. Updates status to "Completed"
13. Links to finances and CRM

---

## Key Features

### Google Maps Integration (Rig Requests)
- Search for location with autocomplete
- Click on map to set location
- Drag marker to adjust
- Automatically saves coordinates
- Falls back to manual entry if API unavailable

### Auto-Numbering (Rig Requests)
- Format: `RR-YYYYMMDD-####`
- Example: `RR-20241215-0001`
- Auto-increments per day
- Trigger-based generation

### Pump Selection (Quote Requests)
- Only shows when "Mechanization" is selected
- Loads pumps from catalog
- Multi-select dropdown
- Stores as JSON array of catalog item IDs

### Status Tracking
- All status changes tracked
- Timestamps for dispatched/completed
- Internal notes for team communication
- Activity logs in CRM

---

## Best Practices

1. **Quick Response:** Review new requests within 24 hours
2. **Status Updates:** Keep status current as you progress
3. **Internal Notes:** Use internal notes for team communication
4. **Resource Assignment:** Assign rigs and users early
5. **Link Reports:** Link completed rig requests to field reports
6. **CRM Follow-up:** Use CRM follow-ups to track all communication
7. **Clear Distinction:** Always use the correct form type for clarity

---

## Access Points

### Public Forms (CMS)
- Quote Request: `/cms/quote`
- Rig Request: `/cms/rig-request`

### Management (ABBIS)
- Unified Requests: `?module=requests`
- Quote Only: `?module=requests&type=quote`
- Rig Only: `?module=requests&type=rig`
- CRM Dashboard: `?action=crm&action=dashboard`

### API Endpoints
- Rig Requests API: `/api/rig-requests-api.php`
- CRM API: `/api/crm-api.php`

---

## Summary

The Requests System provides a complete workflow from initial client inquiry through job completion, with full integration into CRM, client management, and financial systems. The clear distinction between "Request a Quote" (complete services) and "Request Rig" (rental) ensures the right form is used for the right purpose, while unified management makes it easy to track and manage all requests in one place.


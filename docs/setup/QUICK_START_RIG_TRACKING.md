# Quick Start: Rig Tracking

## 📍 Where to Find "Update Location" Button

### Location 1: Bottom of Map (When Rig Has Location)
1. Go to **System → Configuration → Rigs Management**
2. Click **📍 Track Rigs** (top button) or **📍 Track** (next to rig)
3. **Select a rig** from the left panel
4. Look at the **bottom-left corner of the map**
5. You'll see a white info box
6. **Scroll down** in that box to find: **📍 Update Location** button

### Location 2: Center of Map (When Rig Has NO Location)
1. Follow steps 1-3 above
2. If rig has no location, you'll see a message in the **center**
3. Click **📍 Add Location** button in the center

## 📝 Where to Enter Coordinates

After clicking "Update Location" or "Add Location":

1. **A popup modal appears** with a form
2. **Find these fields:**
   - **Latitude** (required) - Top field, enter like: `5.603717`
   - **Longitude** (required) - Second field, enter like: `-0.186964`
3. **Location Source dropdown** - Choose:
   - "Manual Entry" - for typing coordinates
   - "GPS Device" - shows extra GPS fields
   - "Third-Party API" - for automated tracking
4. **Click "Update Location"** at bottom of form

## 🗺️ Visual Guide

```
┌─────────────────────────────────────────────┐
│  System → Config → Rigs → Track Rigs       │
├──────────────┬─────────────────────────────┤
│              │                              │
│  [Rig 1]     │         MAP                 │
│  [Rig 2] ←───┼─── SELECT THIS              │
│  [Rig 3]     │                              │
│              │                              │
│              │                              │
│              │                              │
│              │  ┌────────────────────┐     │
│              │  │ Location Info Box  │     │
│              │  │                    │     │
│              │  │ [📍 Update] ←─── HERE! │
│              │  └────────────────────┘     │
└──────────────┴─────────────────────────────┘
              ↓ Click "Update Location"
┌─────────────────────────────────────────────┐
│  Update Rig Location                       │
├─────────────────────────────────────────────┤
│  Location Source: [Manual Entry ▼]         │
│                                            │
│  Latitude *:  [5.603717      ] ←── HERE!  │
│  Longitude *: [-0.186964     ] ←── HERE!  │
│                                            │
│  Address:     [Auto-filled...]            │
│  Notes:       [Optional...]                │
│                                            │
│  [Cancel]  [Update Location]               │
└─────────────────────────────────────────────┘
```

## ✅ Third-Party API Integration Status

### ✅ Framework is Complete!

**What's Ready:**
- ✅ Database tables (`rig_tracking_config`)
- ✅ API endpoint (`api/rig-tracking.php`)
- ✅ Integration function structure
- ✅ Example code for popular providers

**What You Need to Do:**
1. Open `api/third-party-tracking-examples.php`
2. Copy code for your provider (Fleet Complete, Samsara, Geotab, etc.)
3. Paste into `api/rig-tracking.php`
4. Configure API credentials in database

**Files:**
- **Examples**: `api/third-party-tracking-examples.php` ← Start here!
- **Setup Guide**: `THIRD_PARTY_TRACKING_SETUP.md`
- **User Guide**: `docs/RIG_TRACKING_USER_GUIDE.md`

## Quick Test

1. Go to: `http://localhost:8080/abbis3.2/modules/rig-tracking.php`
2. Select a rig
3. Click "📍 Update Location" (bottom of map)
4. Enter:
   - Latitude: `5.603717`
   - Longitude: `-0.186964`
5. Click "Update Location"
6. See location on map! 🎉

---

**Need Help?**
- User Guide: `docs/RIG_TRACKING_USER_GUIDE.md`
- API Setup: `THIRD_PARTY_TRACKING_SETUP.md`
- Examples: `api/third-party-tracking-examples.php`


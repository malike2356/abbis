# Rig Tracking - User Guide

## Where to Find "Update Location" Button

### Step 1: Navigate to Rig Tracking
1. Go to **System → Configuration → Rigs Management**
2. Click **📍 Track Rigs** button (top right), OR
3. Click **📍 Track** button next to any rig in the table

### Step 2: Select a Rig
- On the left side panel, you'll see a list of all active rigs
- **Click on any rig** to select it

### Step 3: Find "Update Location" Button

**If the rig has a location:**
- The button is at the **bottom of the map** (bottom-left corner)
- Look for a white info box with rig details
- At the bottom of that box, you'll see: **📍 Update Location** button

**If the rig has NO location yet:**
- You'll see a message in the center: "This rig has no location data yet"
- Click the **📍 Add Location** button in the center

## Where to Enter Coordinates

After clicking "Update Location" or "Add Location":

1. **A modal window will pop up** with a form

2. **Find the coordinate fields:**
   - **Latitude** field (required) - Enter latitude like: `5.603717`
   - **Longitude** field (required) - Enter longitude like: `-0.186964`

3. **Location Source dropdown:**
   - Select "Manual Entry" for manual coordinates
   - Select "GPS Device" to show additional GPS fields
   - Select "Third-Party API" to show API provider fields

4. **Optional fields:**
   - Address (auto-filled if coordinates are valid)
   - Notes
   - Accuracy, Speed, Heading, Altitude (if GPS Device selected)

5. **Click "Update Location"** button at the bottom of the form

## Visual Guide

```
┌─────────────────────────────────────────────────┐
│  Rig Location Tracking                          │
├──────────────┬──────────────────────────────────┤
│              │                                  │
│  Rig List    │         MAP DISPLAY              │
│  (Left)      │                                  │
│              │                                  │
│  [Rig 1]     │                                  │
│  [Rig 2] ←───┼─── SELECT THIS                   │
│  [Rig 3]     │                                  │
│              │                                  │
│              │                                  │
│              │                                  │
│              │                                  │
│              │                                  │
│              │                                  │
│              │  ┌──────────────────────────┐   │
│              │  │ Rig Name - Location Info │   │
│              │  │ Coordinates: 5.60, -0.18│   │
│              │  │ Last Updated: ...        │   │
│              │  │                          │   │
│              │  │ [📍 Update Location] ←──┼─── HERE!
│              │  └──────────────────────────┘   │
└──────────────┴──────────────────────────────────┘
```

## Quick Steps Summary

1. **Go to**: System → Configuration → Rigs Management
2. **Click**: "📍 Track Rigs" or "📍 Track" next to a rig
3. **Select**: A rig from the left panel
4. **Click**: "📍 Update Location" (bottom of map) or "📍 Add Location" (center if no location)
5. **Enter**: Latitude and Longitude in the modal form
6. **Click**: "Update Location" button

## Getting Coordinates

### Option 1: Use Your Device GPS
- When you click "Update Location", the form will try to auto-fill your current location
- Grant location permission when prompted

### Option 2: Get from Google Maps
1. Go to Google Maps
2. Right-click on the location
3. Click the coordinates at the top
4. Copy and paste into the form

### Option 3: Manual Entry
- Enter coordinates manually
- Format: Latitude (e.g., 5.603717), Longitude (e.g., -0.186964)

## Example Coordinates

**Accra, Ghana:**
- Latitude: `5.603717`
- Longitude: `-0.186964`

**Kumasi, Ghana:**
- Latitude: `6.6885`
- Longitude: `-1.6244`


# Rig Location Tracking System - Implementation Complete

## Overview

A comprehensive rig location tracking system has been implemented to monitor and track the real-time location of drilling rigs. The system supports both manual location updates and integration with third-party GPS tracking services.

## What Was Implemented

### 1. Database Schema
- **`rig_locations`** table: Stores location history with GPS telemetry data
- **`rig_tracking_config`** table: Stores tracking configuration per rig
- Enhanced **`rigs`** table: Added current location fields and tracking status

**Migration File**: `database/rig_tracking_migration.sql`

### 2. Tracking Interface
- **Main Tracking Page**: `modules/rig-tracking.php`
  - Interactive map display (Google Maps or Leaflet/OpenStreetMap)
  - Rig selector panel
  - Real-time location display
  - Location history toggle
  - Directions integration
  - Manual location update modal

### 3. API Endpoints
- **`api/rig-tracking.php`**: Complete API for location management
  - `GET get_location`: Get current location
  - `GET get_history`: Get location history
  - `POST update_location`: Update location (manual or API)
  - `POST sync_third_party`: Sync from third-party tracking services

### 4. Integration Points
- **Rig Management** (`modules/config.php`):
  - Added "📍 Track" button in rigs table
  - Added "📍 Track Rigs" button in rigs management header
- **Navigation** (`includes/header.php`):
  - Added rig-tracking.php to System navigation active pages

### 5. Features
- ✅ Real-time location tracking
- ✅ Location history (up to 100 points)
- ✅ Multiple location sources (manual, GPS device, third-party API, field report)
- ✅ GPS telemetry (accuracy, speed, heading, altitude)
- ✅ Reverse geocoding (address lookup)
- ✅ Map integration (Google Maps & Leaflet/OpenStreetMap)
- ✅ Directions to rig location
- ✅ Location update modal with GPS support
- ✅ Third-party API integration framework

## Setup Instructions

### Step 1: Run Database Migration

```bash
# Option 1: Using PHP migration runner
php database/run_migration.php database/rig_tracking_migration.sql

# Option 2: Direct MySQL import
mysql -u your_username -p abbis_3_2 < database/rig_tracking_migration.sql
```

### Step 2: Configure Map Provider

1. Navigate to **System → Integrations → Map Providers**
2. Choose your map provider:
   - **Google Maps**: Requires API key (Maps JavaScript API + Geocoding API)
   - **Leaflet (OpenStreetMap)**: Free, no API key needed
3. Save settings

### Step 3: Start Tracking

1. Go to **System → Configuration → Rigs Management**
2. Click **📍 Track** next to any rig, or
3. Click **📍 Track Rigs** to see all rigs
4. Select a rig to view its location on the map

## Usage Guide

### Manual Location Update

1. Select a rig from the list
2. Click **📍 Update Location**
3. Enter coordinates (or use device GPS)
4. Optionally add:
   - Accuracy (meters)
   - Speed (km/h)
   - Heading (degrees)
   - Altitude (meters)
   - Address
   - Notes
5. Click **Update Location**

### Viewing Locations

- **Current Location**: Most recent location is displayed by default
- **History**: Click **📜 History** to view past locations (up to 100)
- **Directions**: Click **🧭 Directions** to get Google Maps directions
- **Refresh**: Click **🔄 Refresh** to update location data

### Location Sources

- **Manual Entry**: Enter coordinates manually
- **From Field Report**: Link location from a field report
- **GPS Device**: Direct GPS device input with full telemetry
- **Third-Party API**: Automated updates from tracking providers

## Third-Party API Integration

The system is designed to integrate with:
- Fleet Complete
- Samsara
- Geotab
- Custom GPS tracking services

### Implementation Steps

1. **Configure Provider**:
   - Edit rig tracking config in database
   - Enter provider name, device ID, API credentials

2. **Implement API Integration**:
   - Edit `api/rig-tracking.php`
   - Implement `fetchFromThirdPartyAPI()` function
   - See `docs/RIG_TRACKING_GUIDE.md` for details

3. **Set Up Automated Sync**:
   - Create cron job to sync locations periodically
   - See `docs/RIG_TRACKING_GUIDE.md` for cron setup

## Files Created/Modified

### New Files
- `database/rig_tracking_migration.sql` - Database migration
- `modules/rig-tracking.php` - Main tracking interface
- `api/rig-tracking.php` - API endpoints
- `docs/RIG_TRACKING_GUIDE.md` - Comprehensive documentation
- `RIG_TRACKING_IMPLEMENTATION.md` - This file

### Modified Files
- `modules/config.php` - Added tracking buttons
- `includes/header.php` - Added navigation link

## Technical Details

### Database Tables

**rig_locations**
- Stores all location records with timestamps
- Supports GPS telemetry (accuracy, speed, heading, altitude)
- Tracks location source and provider information

**rig_tracking_config**
- Stores per-rig tracking configuration
- Manages API credentials (should be encrypted)
- Tracks update frequency and status

**rigs** (enhanced)
- `current_latitude`, `current_longitude`: Last known location
- `current_location_updated_at`: Last update timestamp
- `tracking_enabled`: Whether tracking is active

### API Structure

All API endpoints require authentication and CSRF tokens (except cron jobs with secure tokens).

### Map Integration

- Supports Google Maps (requires API key)
- Supports Leaflet/OpenStreetMap (free)
- Automatic provider selection based on system configuration

## Security Considerations

- ✅ CSRF protection on all POST requests
- ✅ Authentication required for all endpoints
- ⚠️ API keys should be encrypted in database
- ⚠️ Implement rate limiting for API endpoints
- ⚠️ Use HTTPS for all communications

## Future Enhancements

Potential improvements:
- Real-time WebSocket updates
- Geofencing alerts
- Route optimization
- Historical route playback
- Mobile app integration
- Offline location tracking
- Batch location updates
- Location-based notifications

## Support

For detailed documentation, see:
- `docs/RIG_TRACKING_GUIDE.md` - Complete user and developer guide

For issues or questions:
- Check database migration completed successfully
- Verify map provider configuration
- Check browser console for JavaScript errors
- Review API error messages in `rig_tracking_config.error_message`

---

**Implementation Date**: 2025-01-27
**Status**: ✅ Complete and Ready for Use


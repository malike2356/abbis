#!/bin/bash
# Create GREEN RIG import script from template

TEMPLATE="import-green-rig-data-template.php"
OUTPUT="import-green-rig-data.php"

# Copy template
cp "$TEMPLATE" "$OUTPUT"

# Replace RED RIG references with GREEN RIG
sed -i 's/rig_id = 4/rig_id = \$greenRigId/g' "$OUTPUT"
sed -i 's/rig_id != 4/rig_id != \$greenRigId/g' "$OUTPUT"

# Find and replace the fieldReports array section
# This will be done by PHP script that generates the reports array

echo "GREEN RIG import script structure created"
echo "Now need to replace fieldReports array with GREEN RIG data"

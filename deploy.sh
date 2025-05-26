#!/bin/bash

echo "🚀 Deploying MyEventLane..."

# Pull latest changes
git pull origin main

# Start DDEV if not running
ddev start

# Import config
echo "📥 Importing config..."
ddev drush cim -y

# Run DB updates
echo "🧰 Running DB updates..."
ddev drush updb -y

# Clear cache
echo "🧼 Clearing cache..."
ddev drush cr

echo "✅ Deployment complete!"

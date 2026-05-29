#!/bin/bash

# CareerConnect Setup Script
# This script sets up the CareerConnect application for local development

echo "========================================="
echo "CareerConnect Setup"
echo "========================================="
echo

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Step 1: Install PHP dependencies
echo -e "${YELLOW}Step 1: Installing PHP dependencies...${NC}"
if ! composer install; then
    echo -e "${RED}Failed to install dependencies${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Dependencies installed${NC}"
echo

# Step 2: Generate app key
echo -e "${YELLOW}Step 2: Generating application key...${NC}"
php artisan key:generate
echo -e "${GREEN}✓ App key generated${NC}"
echo

# Step 3: Create database
echo -e "${YELLOW}Step 3: Setting up database...${NC}"
php artisan migrate:fresh --force
echo -e "${GREEN}✓ Database migrations completed${NC}"
echo

# Step 4: Seed database
echo -e "${YELLOW}Step 4: Seeding database with sample data...${NC}"
php artisan db:seed
echo -e "${GREEN}✓ Database seeded${NC}"
echo

# Step 5: Install Node.js dependencies
echo -e "${YELLOW}Step 5: Installing Node.js dependencies...${NC}"
if command -v npm &> /dev/null; then
    npm install
    echo -e "${GREEN}✓ Node dependencies installed${NC}"
else
    echo -e "${YELLOW}⚠ npm not found, skipping Node.js setup${NC}"
fi
echo

# Step 6: Build frontend assets
echo -e "${YELLOW}Step 6: Building frontend assets...${NC}"
npm run build 2>/dev/null || echo -e "${YELLOW}⚠ Frontend build skipped${NC}"
echo

# Step 7: Clear cache
echo -e "${YELLOW}Step 7: Clearing application cache...${NC}"
php artisan cache:clear
php artisan config:clear
php artisan view:clear
echo -e "${GREEN}✓ Cache cleared${NC}"
echo

echo -e "${GREEN}========================================="
echo "CareerConnect Setup Complete!"
echo "=========================================${NC}"
echo
echo "Next steps:"
echo "1. Copy .env.example to .env and update configuration"
echo "2. Start your web server: php artisan serve"
echo "3. Start queue worker: php artisan queue:work redis"
echo "4. (Optional) Start WebSocket server: php artisan reverb:start"
echo
echo "Admin user created:"
echo "  Email: admin@university.edu"
echo "  SSO ID: admin-001"
echo

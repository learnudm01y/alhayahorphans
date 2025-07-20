#!/bin/bash

# Fix Enhanced Attachments Migration Conflict
# Created: July 20, 2025
# Problem: Multiple migrations trying to create the same table

echo "🔧 Enhanced Attachments Migration Conflict Fix"
echo "============================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

echo -e "${YELLOW}📋 Current situation:${NC}"
echo -e "   • Migration 2025_01_16_000001_create_enhanced_attachments_table is PENDING"
echo -e "   • Migration 2025_07_12_052417_create_enhanced_attachments_table already RAN"
echo -e "   • Both try to create the same table 'enhanced_attachments'"
echo ""

echo -e "${GREEN}🎯 Solution: Mark the conflicting migration as completed${NC}"
echo ""

# Method 1: Mark migration as run without executing it
echo -e "${CYAN}⚡ Method 1: Mark migration as completed (RECOMMENDED)${NC}"
echo -e "   Running: php artisan migrate:mark-as-run"

if php artisan migrate:mark-as-run --path=database/migrations/2025_01_16_000001_create_enhanced_attachments_table.php; then
    echo -e "   ${GREEN}✅ Migration marked as completed successfully!${NC}"
    echo ""
    
    # Now try running all pending migrations
    echo -e "${CYAN}🚀 Running remaining pending migrations...${NC}"
    if php artisan migrate --force; then
        echo -e "   ${GREEN}✅ All migrations completed successfully!${NC}"
    else
        echo -e "   ${YELLOW}⚠️  Some migrations had issues${NC}"
    fi
else
    echo -e "   ${YELLOW}⚠️  Method 1 failed, trying alternative approach...${NC}"
    echo ""
    
    # Method 2: Manual database entry
    echo -e "${CYAN}⚡ Method 2: Manual database fix${NC}"
    echo -e "   Please run this SQL command manually:"
    echo ""
    echo -e "   ${GREEN}INSERT INTO migrations (migration, batch) VALUES${NC}"
    echo -e "   ${GREEN}('2025_01_16_000001_create_enhanced_attachments_table', ${NC}"
    echo -e "   ${GREEN}(SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations m));${NC}"
    echo ""
    echo -e "   ${GREEN}Then run: php artisan migrate --force${NC}"
fi

echo ""
echo -e "${CYAN}📊 Checking final migration status...${NC}"
php artisan migrate:status

echo ""
echo -e "${GREEN}✨ Fix completed! Check the output above for results.${NC}"
echo -e "${GREEN}🌐 If successful, your file access should now work properly.${NC}"

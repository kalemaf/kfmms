# Equipment Spare Association Feature Guide

## Overview
This feature allows you to attach specific parts/spares to equipment items in your inventory system. When you assign a part to equipment, it automatically creates a spare record in the equipment spares inventory, making it easy to assess and monitor which spares are maintained for each piece of equipment.

## What Was Added

### 1. **New Backend Functions** (inventory_manager.php)
Four new helper functions were added to support equipment-spare associations:

#### `get_equipment_list($connection, $active_only = true)`
- Retrieves all active equipment from the database
- Used to populate the equipment dropdown in the parts form
- Returns array of equipment with ID, description, manufacturer, model, and serial number

#### `get_part_equipment_spares($part_id, $connection)`
- Gets all equipment currently associated with a specific part
- Returns detailed equipment information for each association
- Used to pre-select checkboxes when editing a part

#### `attach_part_to_equipment($part_id, $equipment_id, $quantity = 0, $connection)`
- Creates or updates a spare record in equipment_spares table
- Automatically pulls part name and number from parts_master
- Links part to equipment for future tracking
- Returns the spare ID on success

#### `save_part_equipment_associations($part_id, $equipment_ids, $connection)`
- Handles bulk synchronization of equipment associations
- Adds equipment that's selected but not yet associated
- Removes equipment that's deselected
- Called automatically when saving a part with selected equipment

---

## 2. **Enhanced Parts Master Form** (inventory/parts_master.php)

### New Form Section: "Equipment Compatibility & Spares"

**Location:** Between "Inventory Control" and "Additional Information" sections

**Features:**

#### Information Banner
- Explains the purpose of equipment associations
- Encourages users to attach spares to equipment for better tracking

#### Equipment Selector
- **Scrollable checkbox list** of all available equipment
- Shows equipment details:
  - Equipment description
  - Manufacturer and model
  - Serial number (if available)
- **Pre-selects** currently associated equipment when editing
- Indicates if no equipment is available

#### Currently Associated Equipment Display
- **Only shown when editing a part** that has existing associations
- Green-themed section showing all linked equipment
- Displays equipment details in card format
- Helps users verify associations at a glance

---

## 3. **Database Integration**

The feature uses existing `equipment_spares` table:
```
equipment_spares
├── id (primary key)
├── equipment_id (links to equipment.id)
├── part_id (links to parts_master.id)
├── part_name (from parts_master)
├── part_number (from parts_master)
└── quantity (spare quantity for equipment)
```

---

## How to Use

### Adding a New Part with Equipment Association

1. **Navigate to** Inventory > Parts Master > New Part
2. **Fill in part details** as normal:
   - Part Code (required)
   - Part Name (required)
   - Category, manufacturer, unit cost, etc.

3. **Scroll to "Equipment Compatibility & Spares" section**

4. **Select equipment** the part is suitable for:
   - Check the checkbox next to each equipment
   - You can select multiple equipment items
   - Use the scrollbar if many equipment items exist

5. **Save the part**
   - Click "Save Part" button
   - The system automatically:
     - Creates the part record in parts_master
     - Creates equipment_spares entries for each selected equipment
     - Links the part to the selected equipment

### Editing an Existing Part

1. **Navigate to** Inventory > Parts Master > [search/find part]
2. **Click Edit** on the part you want to modify
3. **Update equipment associations** as needed:
   - Check/uncheck equipment associations
   - Already-associated equipment will be pre-checked
   - **Checked** = will be associated
   - **Unchecked** = will be removed if currently associated

4. **Save changes**
   - The system updates equipment_spares automatically
   - Adds new associations
   - Removes old associations

### Viewing Equipment Spares

Once parts are attached to equipment:

1. **Equipment Spares View:**
   - Each equipment item will show its associated spares
   - Quantities can be tracked per equipment
   - Easy assessment of spare availability

2. **Inventory Dashboard:**
   - Parts show stock levels including spares
   - Equipment spares integrate into overall inventory counts
   - Low-stock alerts account for spare quantities

---

## Benefits

✅ **Centralized Spare Tracking**
- Know exactly which spares are maintained for each equipment piece
- No more forgotten or unmaintained spares

✅ **Easy Assessment**
- See all equipment that uses a specific spare
- Monitor spare inventory by equipment
- Plan spare stock levels per equipment

✅ **Efficient Monitoring**
- Check spare status when equipment is maintained
- Verify spares availability before work orders
- Track spare usage patterns per equipment

✅ **Integrated with Inventory System**
- Parts automatically show in inventory system once associated
- Stock levels automatically updated
- Spare consumption tracked in transactions

---

## Technical Details

### Data Flow

```
Add/Edit Part
    ↓
Select Equipment Checkboxes
    ↓
Save Part
    ↓
save_part() saves part record
    ↓
save_part_equipment_associations() processes associations:
  - Identifies equipment to add (new selections)
  - Identifies equipment to remove (deselections)
  - Calls attach_part_to_equipment() for each
    ↓
equipment_spares table updated with new/removed associations
    ↓
Equipment spare is now in inventory system
```

### Database Operations

- **Insert:** When equipment is selected, a new equipment_spares record is created
- **Update:** If association exists, quantity field can be updated
- **Delete:** When equipment is deselected, the association is removed
- **Query:** Select queries join equipment_spares with parts_master for reporting

---

## Examples

### Example 1: Pump Equipment Spare
**Part:** 6208ZZ Roller Bearing
**Associated Equipment:**
- Pump-101 (Centrifugal Pump)
- Motor-200 (Electric Motor)
- Compressor-15 (Air Compressor)

**Result:** When you search inventory, the bearing shows stock from all three equipment types combined.

### Example 2: Motor Maintenance
**Equipment:** Motor-200
**Associated Spares:**
- 6208ZZ Roller Bearing (qty: 2 available)
- Motor Coupling (qty: 1 available)
- Insulation Tape (qty: 5 available)

**Result:** Technician can quickly see what spares are available for Motor-200 maintenance.

---

## Key Features Summary

| Feature | Description |
|---------|-------------|
| **Multi-select** | Attach one part to multiple equipment items |
| **Pre-selection** | Currently associated equipment auto-checks |
| **Equipment Details** | Shows manufacturer, model, serial number |
| **Visual Feedback** | Green section showing current associations |
| **Bulk Operations** | Add/remove multiple equipment in one save |
| **Auto-sync** | Database automatically syncs all associations |
| **Non-destructive** | Deselecting equipment removes association cleanly |

---

## Notes for CMMS Administrators

1. **Equipment Must Exist First**
   - Ensure equipment is added to the system before attaching spares
   - Equipment list is pulled from `equipment` table

2. **Part Code Must Be Unique**
   - Part codes ensure no duplicate parts in system
   - Equipment spares link via part_id (not part code)

3. **Inventory Integration**
   - Equipment spares feed into overall inventory totals
   - Stock levels reflect spare quantities
   - Consumption is tracked via inventory transactions

4. **Visibility**
   - Part must be marked as "Active" to appear in forms
   - Equipment must not be marked as "Inactive"

---

## Future Enhancements

Potential extensions to this feature:
- Assign **specific quantities** per equipment (e.g., always maintain 3 bearings for this pump)
- Automatic **reorder point alerts** per equipment
- Spare **rotation/usage tracking** by equipment
- Equipment-specific **maintenance schedules** with spare checklists
- Spare **cost analysis** by equipment type

---

## Support & Troubleshooting

### Equipment Not Appearing in List
- Check that equipment exists in the system
- Verify equipment status is not "Inactive"
- Try refreshing the page

### Parts Not Saving
- Verify Part Code is unique
- Ensure Part Name is filled in
- Check that selected equipment exists
- Look for error messages at top of form

### Associations Not Keeping
- Verify database permissions
- Check equipment_spares table exists
- Ensure part_id references valid parts_master record

---

**Feature Added:** April 2026
**Version:** CMMS SQLite Edition with Equipment Spare Integration

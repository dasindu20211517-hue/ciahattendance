# Gender Chart Implementation

This document explains the new Male vs Female employee distribution chart feature added to the daily attendance report.

## Features Added

1. **Gender Chart**: A visual pie chart showing the distribution of Male vs Female employees
2. **Gender Column**: Added M/F column to the daily attendance table
3. **Gender Statistics API**: Backend support for gender data
4. **Sample Data Templates**: CSV template and import scripts

## Database Changes

The `users` table now includes a `gender` column:
- Accepts values: 'M', 'F', 'Male', 'Female', or empty string
- Automatically normalized to 'M' or 'F' for display

## Files Modified

### 1. `daily.php`
- Added gender chart HTML structure
- Added Gender column to attendance table header
- Updated table colspan from 10 to 11 columns

### 2. `assets/style.css`
- Added CSS styles for the gender chart
- Chart container with responsive design
- Legend styling with color indicators

### 3. `assets/app.js`
- Added gender chart initialization and drawing functions
- Updated `loadDailyData()` to fetch and display gender data
- Added chart update functionality

### 4. `database.php`
- Added `gender` column to users table schema
- Updated `getDailySummary()` to include gender data
- Added `getGenderStats()` function for chart data

### 5. `api/attendance.php`
- Added gender field to daily attendance API responses
- Added new `gender_stats` endpoint
- Updated all employee data arrays to include gender

## Usage Instructions

### 1. Initial Setup

Run the sample data script to populate test data:
```bash
php sample_employee_data.php
```

### 2. Import Employee Data with Gender

Use the CSV template (`employee_template.csv`) and import script:
```bash
php import_employees_with_gender.php employee_template.csv
```

### 3. CSV Format

Your employee data CSV should have these columns:
```csv
Employee ID,Badge ID,Name,M/F,Department,Company,Phone
EMP001,00001,John Doe,M,IT,TechCorp,+1234567890
EMP002,00002,Jane Smith,F,HR,TechCorp,+1234567891
```

**M/F Column Values:**
- `M`, `Male`, `1` → Displays as "M"
- `F`, `Female`, `2` → Displays as "F"  
- Empty or other values → Displays as "-"

### 4. Manual Database Updates

To update existing employee gender data manually:
```sql
UPDATE users SET gender = 'M' WHERE badge_id = '00001';
UPDATE users SET gender = 'F' WHERE badge_id = '00002';
```

## Chart Features

- **Real-time Updates**: Chart refreshes when date or filters change
- **Responsive Design**: Adapts to mobile devices
- **Percentage Display**: Shows percentage breakdown in chart slices
- **Legend**: Color-coded legend with actual counts
- **No Data Handling**: Displays message when no gender data available

## API Endpoints

### Gender Statistics
```
GET /api/attendance.php?action=gender_stats
```
Returns:
```json
{
  "success": true,
  "gender_stats": {
    "male_count": 5,
    "female_count": 3,
    "total_count": 8
  }
}
```

### Daily Attendance (Updated)
```
GET /api/attendance.php?action=daily&date=2024-01-01
```
Now includes `gender` field in employee records.

## Troubleshooting

### Chart Not Displaying
1. Check browser console for JavaScript errors
2. Ensure `gender_stats` API endpoint returns data
3. Verify employees have gender values in database

### Gender Data Missing
1. Import data using provided CSV template
2. Update existing records with gender values
3. Check database schema includes `gender` column

### Import Issues
1. Ensure CSV file has correct format and headers
2. Check file permissions and path
3. Review import script error messages

## Customization

### Chart Colors
Edit in `assets/app.js`:
```javascript
ctx.fillStyle = '#3B82F6'; // Blue for male
ctx.fillStyle = '#EC4899'; // Pink for female
```

### Chart Size
Edit in `assets/style.css`:
```css
#genderChart {
    width: 300px;
    height: 300px;
}
```

### Gender Values
Modify normalization logic in `import_employees_with_gender.php`:
```php
if (in_array($gender, ['M', 'MALE', '1'])) {
    $gender = 'M';
}
```

## Next Steps

1. Import your actual employee data using the CSV template
2. Update existing employee records with gender information
3. Test the chart functionality on the daily report page
4. Customize colors and styling as needed

The gender chart will automatically update based on your employee database and provides a quick visual overview of your workforce composition.
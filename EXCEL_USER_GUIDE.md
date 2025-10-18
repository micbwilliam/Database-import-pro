# Excel Support - User Guide

**Database Import Pro v1.1.0**

---

## 📊 What is Excel Support?

Database Import Pro now supports importing data from Excel files (.xlsx and .xls) in addition to CSV files. This feature makes it easier to import data directly from spreadsheets without manual conversion.

---

## ✅ Checking If Excel Support Is Available

### Step 1: Check the Admin Notice
When you visit the Database Import Pro page, you'll see one of these notices:

**✅ Green Notice - Excel Enabled:**
```
Excel support is enabled. You can import CSV, XLSX, XLS files.
```
**You're all set! You can import Excel files.**

**⚠️ Yellow Notice - Missing Extensions:**
```
Excel support is unavailable. Missing required PHP extensions: ZIP, XML.
Only CSV files are currently supported.
```
**Contact your hosting provider to enable these extensions.**

**ℹ️ Blue Notice - Not Installed:**
```
Excel support is not installed. Only CSV files are currently supported.
To enable Excel (.xlsx, .xls) support, run composer install in the plugin directory.
```
**Follow the installation guide below.**

---

## 📋 System Status Page

### Navigate to System Status
1. Go to **WordPress Admin**
2. Click **Database Import Pro** in the sidebar
3. Click **System Status** submenu

### What You'll See
The System Status page shows:

- ✅ **Supported File Formats** - Which formats work on your server
- 🔧 **PHP Extensions** - Which extensions are installed
- 💻 **System Information** - PHP version, memory limits, upload size
- 📖 **How to Enable Excel** - Step-by-step instructions
- 🐛 **Debug Information** - Technical details for support

---

## 🚀 How to Enable Excel Support

### Option 1: For WordPress Site Owners

**If you have SSH/terminal access:**

1. Connect to your server via SSH
2. Navigate to the plugin directory:
   ```bash
   cd /path/to/wordpress/wp-content/plugins/database-import-pro
   ```
3. Run Composer:
   ```bash
   composer install --no-dev
   ```
4. Refresh the Database Import Pro page
5. You should see the green "Excel support enabled" notice

**If you use FTP/cPanel:**

Contact your hosting provider and ask them to:
1. Install Composer on your server (if not already available)
2. Run `composer install --no-dev` in the plugin directory

### Option 2: For Shared Hosting Users

**If you don't have terminal access:**

1. Check the System Status page
2. If PHP extensions are missing (ZIP, XML, XMLReader):
   - Contact your hosting provider
   - Ask them to enable: **ZIP, XML, XMLReader extensions**
   - Most hosts can enable these extensions easily

3. If extensions are available but library is missing:
   - Ask your hosting provider to run Composer
   - Or, upgrade to a hosting plan with Composer support

### Option 3: For Developers

**Local development:**

```bash
cd database-import-pro
composer install
```

**For production deployment:**

Include the `vendor` directory when deploying, or ensure Composer runs during deployment.

---

## 📂 Using Excel Files

### Once Excel Support is Enabled

1. **Go to Import Wizard:**
   - Database Import Pro > Import Wizard

2. **Upload Your File:**
   - Drag and drop your Excel file (.xlsx or .xls)
   - Or click "Select File" to browse

3. **Supported Formats:**
   - ✅ .xlsx (Excel 2007+)
   - ✅ .xls (Excel 97-2003)
   - ✅ .csv (Always supported)

4. **File Requirements:**
   - Maximum file size: 50MB
   - First row should contain column headers
   - UTF-8 encoding recommended

### Import Process
The rest of the import process is the same:
- Select target database table
- Map Excel columns to database fields
- Preview and validate data
- Monitor import progress
- View results

---

## ❓ Frequently Asked Questions

### Q: Why don't I see Excel support?
**A:** Your server needs:
1. PHP extensions (ZIP, XML, XMLReader)
2. PHPSpreadsheet library installed via Composer

Check the System Status page for details.

### Q: Can I still use CSV files?
**A:** Yes! CSV support is always available regardless of Excel support status.

### Q: Does Excel support cost extra?
**A:** No! Excel support is included free in v1.1.0. You just need to enable it on your server.

### Q: What if my host doesn't support it?
**A:** You can always convert Excel files to CSV and import them. Excel support is optional.

### Q: Are there any security risks?
**A:** No. The PHPSpreadsheet library is a well-maintained, secure library used by thousands of applications.

### Q: Will my imports be slower with Excel?
**A:** Excel files may take slightly longer to parse than CSV, but the import speed is similar once data is extracted.

### Q: Can I import multiple sheets?
**A:** Currently, only the first/active sheet is imported. Multi-sheet selection is planned for v1.2.0.

### Q: What Excel features are supported?
**A:** The plugin extracts text and numbers from cells. Formulas are evaluated and their results are imported. Images, charts, and formatting are ignored.

---

## 🆘 Troubleshooting

### Error: "Invalid file type. Supported formats: CSV"
**Cause:** Excel support is not enabled on your server  
**Solution:** Follow the "How to Enable Excel Support" guide above

### Error: "Missing required PHP extensions: ZIP"
**Cause:** Required PHP extension is not installed  
**Solution:** Contact your hosting provider to enable ZIP extension

### Error: "Class PhpOffice\PhpSpreadsheet\IOFactory not found"
**Cause:** PHPSpreadsheet library not installed  
**Solution:** Run `composer install` in the plugin directory

### Excel file uploads but shows error
**Cause:** File may be corrupted or use unsupported features  
**Solution:** Try re-saving the Excel file, or convert to CSV

### File size too large
**Cause:** Excel files can be large, exceeding 50MB limit  
**Solution:** Split into multiple files, or increase PHP upload limit

---

## 📞 Getting Help

### Check System Status First
Before asking for help:
1. Visit **Database Import Pro > System Status**
2. Copy the Debug Information
3. Include it when asking for support

### Support Channels
- **Documentation:** [Read the full README](README.md)
- **GitHub Issues:** Report bugs or request features
- **Hosting Provider:** For PHP extension questions
- **WordPress Forums:** Community support

---

## 🎉 Tips for Best Results

### Preparing Excel Files

1. **Clean Your Data:**
   - Remove empty rows and columns
   - Ensure consistent data types per column
   - Fix any formula errors (#REF!, #DIV/0!, etc.)

2. **Use Headers:**
   - First row should contain clear column names
   - Avoid special characters in headers
   - Keep names short and descriptive

3. **Keep It Simple:**
   - One sheet per file (for now)
   - Plain data (avoid complex formatting)
   - Remove merged cells if possible

4. **Test First:**
   - Import a small sample (10-20 rows)
   - Verify mapping and results
   - Then import the full dataset

### Performance Tips

- Close other tabs/programs before large imports
- Import during low-traffic times
- Split very large files (>10,000 rows) into batches
- Use CSV for very large datasets (faster parsing)

---

## 🔄 Fallback to CSV

### When to Use CSV Instead

Even with Excel support enabled, CSV is still the best choice for:

- ✅ Very large datasets (100,000+ rows)
- ✅ Automated/scripted imports
- ✅ Maximum compatibility
- ✅ Fastest parsing speed
- ✅ Smallest file size

### Converting Excel to CSV

**In Excel:**
1. File > Save As
2. Choose "CSV (Comma delimited) (*.csv)"
3. Click Save

**In Google Sheets:**
1. File > Download
2. Choose "Comma-separated values (.csv)"

---

**Remember:** CSV support is always available, Excel is an optional convenience feature that enhances your import capabilities when available on your server.

---

**Need more help?** Visit the System Status page for detailed information about your server's capabilities.

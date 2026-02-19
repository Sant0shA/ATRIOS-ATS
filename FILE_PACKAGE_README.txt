ATRIOS ATS - FILE INSTALLATION GUIDE
=====================================

STEP 1: Copy These Files to Your GitHub Folder
------------------------------------------------
Copy ALL files from CORE_FILES folder to:
C:\Users\YourName\Documents\GitHub\ATRIOS-ATS\

The structure should look like:
ATRIOS-ATS/
├── config.php
├── index.php
├── login.php
├── logout.php
├── dashboard.php (coming next)
├── includes/
│   ├── database.php
│   ├── auth.php
│   ├── functions.php
│   ├── header.php (coming next)
│   └── footer.php (coming next)

STEP 2: Create Folders
-----------------------
Create these empty folders:
- uploads/
- uploads/cvs/
- uploads/logos/

STEP 3: Commit to Git
----------------------
1. Open GitHub Desktop
2. You'll see all new files listed
3. Summary: "Initial ATS system setup"
4. Description: "Core files, login system, database config"
5. Click "Commit to main"
6. Click "Publish repository" (or "Push origin")

STEP 4: Upload to Server
-------------------------
1. Open FileZilla
2. Connect to Hostinger
3. Navigate to: /public_html/recruitment-ats/
4. Upload ALL files from your local ATRIOS-ATS folder
5. Set folder permissions:
   - uploads/ → 755
   - uploads/cvs/ → 755
   - uploads/logos/ → 755

STEP 5: Test
------------
Visit: https://atrios.in/recruitment-ats/
Login: admin / admin123

DONE!

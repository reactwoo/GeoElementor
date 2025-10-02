# Fix: "Unable to Execute File in Temp Folder" Error in Cursor

## Problem
Windows is blocking Cursor from executing files in the temp directory, usually due to:
- Antivirus software
- Windows Defender
- Folder permissions

## Solutions (Try in Order)

### Solution 1: Run Cursor as Administrator

1. **Close Cursor completely**
2. Right-click on **Cursor icon**
3. Select **"Run as administrator"**
4. Try your operations again

### Solution 2: Add Exclusion to Windows Defender

1. Press `Win + I` to open Settings
2. Go to **Update & Security** → **Windows Security** → **Virus & threat protection**
3. Click **Manage settings** under "Virus & threat protection settings"
4. Scroll down to **Exclusions**
5. Click **Add or remove exclusions**
6. Click **Add an exclusion** → **Folder**
7. Add these folders:
   ```
   C:\Users\PaulMoore\AppData\Local\Temp
   C:\Users\PaulMoore\AppData\Local\Programs\cursor
   ```

### Solution 3: Check Your Antivirus

If you have antivirus software (McAfee, Norton, Avast, etc.):
1. Open your antivirus software
2. Find **Exclusions** or **Exceptions** settings
3. Add Cursor to the exclusion list:
   ```
   C:\Users\PaulMoore\AppData\Local\Programs\cursor\Cursor.exe
   ```

### Solution 4: Fix Temp Folder Permissions

1. Press `Win + R`
2. Type: `%TEMP%` and press Enter
3. Right-click in the folder → **Properties**
4. Go to **Security** tab
5. Click **Edit**
6. Select your username
7. Check **Full control** under "Allow"
8. Click **OK** → **Apply**

### Solution 5: Clear Cursor Cache

1. Close Cursor
2. Press `Win + R`
3. Type: `%APPDATA%` and press Enter
4. Delete these folders if they exist:
   - `Cursor`
5. Press `Win + R` again
6. Type: `%LOCALAPPDATA%` and press Enter
7. Delete these folders:
   - `Cursor`
   - `cursor-updater`
8. Restart Cursor

### Solution 6: Reinstall Cursor

If none of the above work:
1. Uninstall Cursor completely
2. Delete remaining folders:
   - `C:\Users\PaulMoore\AppData\Local\Programs\cursor`
   - `C:\Users\PaulMoore\AppData\Roaming\Cursor`
   - `C:\Users\PaulMoore\AppData\Local\cursor-updater`
3. Download latest Cursor from https://cursor.sh
4. Install as Administrator (right-click installer → Run as administrator)

## Quick Test

After trying a solution, test if it worked:
1. Open Cursor
2. Try to use a tool (like file editing)
3. Check if the error still appears

## Most Common Solution

**For most users**: Solution 2 (Windows Defender exclusion) + Solution 1 (Run as admin) fixes it.

---

**Note**: This is a Windows/Cursor issue, not related to the geo-elementor plugin we're working on.

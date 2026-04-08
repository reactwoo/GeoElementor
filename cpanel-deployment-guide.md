# GitHub to cPanel Deployment Guide

This guide shows you how to merge code from GitHub to two cPanel accounts using Cursor.

## Method 1: Direct Git Integration (Recommended)

### Prerequisites
- SSH access enabled on both cPanel accounts
- Terminal access in cPanel
- GitHub repository with your code

### Step 1: Generate SSH Keys for Each cPanel Account

For each cPanel account, you need to generate SSH keys:

1. **Log into cPanel Account 1:**
   - Go to Terminal (Advanced → Terminal)
   - Run: `ssh-keygen -t rsa -b 4096 -C "account1@example.com"`
   - Press Enter for default location
   - Leave passphrase empty
   - Run: `cat ~/.ssh/id_rsa.pub` and copy the output

2. **Log into cPanel Account 2:**
   - Repeat the same process
   - Run: `cat ~/.ssh/id_rsa.pub` and copy the output

### Step 2: Add SSH Keys to GitHub

1. Go to your GitHub repository
2. Navigate to Settings → Deploy keys
3. Add deploy key for Account 1 (paste the public key from Account 1)
4. Add deploy key for Account 2 (paste the public key from Account 2)

### Step 3: Clone Repository to Both cPanel Accounts

**For Account 1:**
```bash
cd /home/your_cpanel_username1/public_html
git clone git@github.com:your_username/your_repo.git
```

**For Account 2:**
```bash
cd /home/your_cpanel_username2/public_html
git clone git@github.com:your_username/your_repo.git
```

### Step 4: Set up Multiple Remotes in Cursor

In your local Cursor workspace, add both cPanel accounts as remotes:

```bash
git remote add cpanel1 ssh://your_cpanel_username1@server1:/home/your_cpanel_username1/public_html/your_repo
git remote add cpanel2 ssh://your_cpanel_username2@server2:/home/your_cpanel_username2/public_html/your_repo
```

## Method 2: GitHub Actions (Automated)

This method automatically deploys to both cPanel accounts when you push to GitHub.

### Step 1: Create GitHub Actions Workflow

Create `.github/workflows/deploy.yml` in your repository:

```yaml
name: Deploy to cPanel Accounts

on:
  push:
    branches: [ main, master ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
      
    - name: Deploy to cPanel Account 1
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.CPANEL_HOST_1 }}
        username: ${{ secrets.CPANEL_USER_1 }}
        key: ${{ secrets.CPANEL_SSH_KEY_1 }}
        script: |
          cd /home/${{ secrets.CPANEL_USER_1 }}/public_html/your_repo
          git pull origin main
          
    - name: Deploy to cPanel Account 2
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.CPANEL_HOST_2 }}
        username: ${{ secrets.CPANEL_USER_2 }}
        key: ${{ secrets.CPANEL_SSH_KEY_2 }}
        script: |
          cd /home/${{ secrets.CPANEL_USER_2 }}/public_html/your_repo
          git pull origin main
```

### Step 2: Add GitHub Secrets

In your GitHub repository, go to Settings → Secrets and variables → Actions, and add:

- `CPANEL_HOST_1`: Server hostname/IP for Account 1
- `CPANEL_USER_1`: Username for Account 1
- `CPANEL_SSH_KEY_1`: Private SSH key for Account 1
- `CPANEL_HOST_2`: Server hostname/IP for Account 2
- `CPANEL_USER_2`: Username for Account 2
- `CPANEL_SSH_KEY_2`: Private SSH key for Account 2

## Method 3: Manual Deployment Script

Create a deployment script that you can run from Cursor to push to both accounts.

### Deployment Script

Create `deploy.sh`:

```bash
#!/bin/bash

# Configuration
REPO_NAME="your_repo"
CPANEL1_USER="your_cpanel_username1"
CPANEL1_HOST="server1.yourhost.com"
CPANEL2_USER="your_cpanel_username2"
CPANEL2_HOST="server2.yourhost.com"

echo "Deploying to cPanel Account 1..."
ssh $CPANEL1_USER@$CPANEL1_HOST "cd /home/$CPANEL1_USER/public_html/$REPO_NAME && git pull origin main"

echo "Deploying to cPanel Account 2..."
ssh $CPANEL2_USER@$CPANEL2_HOST "cd /home/$CPANEL2_USER/public_html/$REPO_NAME && git pull origin main"

echo "Deployment complete!"
```

## Using Cursor for Development

1. **Clone your GitHub repository locally:**
   ```bash
   git clone https://github.com/your_username/your_repo.git
   cd your_repo
   ```

2. **Open in Cursor:**
   ```bash
   cursor .
   ```

3. **Make your changes in Cursor**

4. **Commit and push:**
   ```bash
   git add .
   git commit -m "Your changes"
   git push origin main
   ```

5. **Deploy to cPanel accounts** (choose one method above)

## Troubleshooting

### SSH Connection Issues
- Ensure SSH is enabled on both cPanel accounts
- Check that SSH keys are properly added to GitHub
- Verify server hostnames and usernames

### Permission Issues
- Make sure your cPanel user has write permissions to the public_html directory
- Check that the repository directory exists and is accessible

### Git Issues
- Ensure Git is installed on the cPanel server
- Check that the repository was cloned correctly
- Verify remote URLs are correct

## Best Practices

1. **Use branches:** Create feature branches for development
2. **Test locally:** Always test changes locally before deploying
3. **Backup:** Keep backups of your cPanel files before major deployments
4. **Monitor:** Check both sites after deployment to ensure everything works
5. **Security:** Keep SSH keys secure and rotate them regularly
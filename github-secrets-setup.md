# GitHub Secrets Setup for Aplenty Deployment

## Required GitHub Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions

Add these secrets:

### Aplenty Staging Account:
- **Name:** `APLENTY_HOST`
- **Value:** aplenty.co.uk

- **Name:** `APLENTY_USER`
- **Value:** aplenty

- **Name:** `APLENTY_SSH_KEY`
- **Value:** [Private SSH key from Aplenty account]

## Getting SSH Keys

### For Aplenty Account:
```bash
ssh aplenty@aplenty.co.uk "cat ~/.ssh/id_rsa"
```

Copy the output and add it as the `APLENTY_SSH_KEY` secret.

## Testing the Setup

After adding all secrets, push a small change to trigger the deployment:

```bash
git add .
git commit -m "Test deployment"
git push origin main
```

Check the Actions tab in GitHub to see the deployment progress.

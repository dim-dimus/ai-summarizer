# AI Summarizer — AWS Free Tier Deployment Guide

**Target:** Deploy to AWS with **$0–$1/month cost** using only free tier services.

**Estimated time:** 2–3 hours (first time setup).

---

## Prerequisites

1. **AWS Account** — Sign up at https://aws.amazon.com/free (free tier eligible for 12 months)
2. **AWS CLI** — Install from https://aws.amazon.com/cli/
3. **Docker** — Your local Docker (with Colima context)
4. **Git & GitHub** — Repository for code + Amplify frontend
5. **Your project** — Fully working locally as described in `README.md`

Verify your setup:
```bash
aws --version
docker --version
git --version
```

**Shell note:** If using zsh or bash, always **quote AWS CLI `--query` parameters** with single quotes to prevent shell expansion:
```bash
# ✓ Correct
aws ec2 describe-addresses --query 'Addresses[0].PublicIp'

# ✗ Wrong (zsh/bash will fail)
aws ec2 describe-addresses --query Addresses[0].PublicIp
```

---

## Architecture (Free Tier Only)

```
┌──────────────────────────────────────────────────────────────┐
│ Amplify (Next.js frontend)                      — $0/month   │
│ Static hosting + SSR, 15GB data transfer free                │
└────────────────────────┬─────────────────────────────────────┘
                         │ HTTPS (Bearer token)
                         ▼
┌──────────────────────────────────────────────────────────────┐
│ EC2 t2.micro (API + Worker)                     — $0/month   │
│ 750 hours/month free, elastic IP free when associated        │
│ Runs: Laravel API + queue worker                             │
└────────────────────────┬─────────────────────────────────────┘
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
    ┌──────────┐  ┌──────────┐  ┌──────────────┐
    │ RDS      │  │ SQS      │  │ Anthropic    │
    │ $0/mo    │  │ $0/mo    │  │ API (paid)   │
    │ 20GB, 1y │  │ 1M msgs  │  │ Your credits │
    └──────────┘  └──────────┘  └──────────────┘

Total: $0–$1/month (Year 1 free tier)
```

---

## Step 1: Prepare Docker Image for AWS

### 1.1 Create production Dockerfile

Create `api/Dockerfile.prod`:

```dockerfile
FROM php:8.3-cli-alpine
RUN apk add --no-cache postgresql-client zip unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader
RUN php artisan config:cache && php artisan route:cache
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

### 1.2 Build and test locally

Build for **linux/amd64** (required for EC2, even on Mac with Apple Silicon):

```bash
cd /Users/dmytroodulo/testing/ai-summarizer
docker context use colima

# Build for linux/amd64 (EC2 architecture)
docker build \
  -f api/Dockerfile.prod \
  -t summarizer-api:latest \
  --platform linux/amd64 \
  api/

# Test it
docker run -it summarizer-api:latest php artisan --version
# Should output: Laravel Framework 11.x.x
```

**Important:** Always include `--platform linux/amd64` when building on Mac, even if you're on Apple Silicon. EC2 instances use x86_64 architecture.

---

## Step 2: Create AWS Account & Set Up CLI

### 2.1 Create AWS Account

1. Go to https://aws.amazon.com/free
2. Click **"Create a Free Account"**
3. Enter email, set password
4. Verify your identity (credit card required, but won't be charged in free tier)
5. **Note:** Save your **AWS Account ID** (12-digit number) — you'll need it later

### 2.2 Create IAM User for CLI

1. Go to AWS Console → **IAM** → **Users** → **Create user**
2. Name: `deployment`
3. Check **"Provide user access to AWS Management Console"** (optional)
4. Click **"Create user"**
5. Attach policy: **AdministratorAccess** (for development; use least privilege in production)
6. Go to **Security credentials** tab
7. Click **"Create access key"** → select "CLI"
8. **Download CSV** — keep this safe!

### 2.3 Configure AWS CLI

```bash
aws configure

# Paste from the downloaded CSV:
# AWS Access Key ID: AKIA...
# AWS Secret Access Key: xxxxxxx
# Default region: us-east-1
# Default output format: json
```

Verify:
```bash
aws sts get-caller-identity
# Should output your account info
```

---

## Step 3: Push Docker Image to ECR (Elastic Container Registry)

### 3.1 Create ECR Repository

```bash
aws ecr create-repository \
  --repository-name summarizer-api \
  --region us-east-1
```

Note the **Repository URI** from the output (looks like `123456789.dkr.ecr.us-east-1.amazonaws.com/summarizer-api`).

### 3.2 Log in to ECR and Push Image

```bash
# Get login token
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin \
  123456789.dkr.ecr.us-east-1.amazonaws.com

# Tag your image (already tagged during build, but you can re-tag if needed)
docker tag summarizer-api:latest \
  123456789.dkr.ecr.us-east-1.amazonaws.com/summarizer-api:latest

# Push to ECR
docker push 123456789.dkr.ecr.us-east-1.amazonaws.com/summarizer-api:latest

# Verify push succeeded
aws ecr list-images --repository-name summarizer-api --region us-east-1 --query 'imageIds[*].imageTag' --output text
```

**Troubleshooting:** If you get "no matching manifest for linux/amd64", rebuild the image with `--platform linux/amd64` flag (see Step 1.2).

---

## Step 4: Create RDS PostgreSQL Database

### 4.1 Via AWS Console (easier first time)

1. Go to **AWS Console** → **RDS** → **Databases** → **Create database**
2. **Engine:** PostgreSQL
3. **Version:** 15.4 (15.18-R2)
4. **Templates:** Free tier
5. **DB instance identifier:** `summarizer-prod`
6. **Master username:** `summarizer`
7. **Master password:** `YourStrongPassword123!` (save this!)
8. **Allocated storage:** 20 GB
9. **Connectivity:** Default VPC, publicly accessible: **No**
10. Click **Create database**

Wait ~10 minutes for the database to be created.

### 4.2 Get Database Endpoint

1. Go to **RDS** → **Databases** → **summarizer-prod**
2. Copy the **Endpoint** (looks like `summarizer-prod.xxxxx.us-east-1.rds.amazonaws.com`)
3. **Save:** You'll need this in EC2 env vars

---

## Step 5: Create SQS Queue

### 5.1 Create Dead Letter Queue (DLQ)

```bash
aws sqs create-queue \
  --queue-name summaries-prod-dlq \
  --region us-east-1
```

Get the DLQ ARN (save it):
```bash
aws sqs get-queue-attributes \
  --queue-url https://sqs.us-east-1.amazonaws.com/123456789/summaries-prod-dlq \
  --attribute-names QueueArn \
  --region us-east-1 \
  --query 'Attributes.QueueArn' \
  --output text
```

### 5.2 Create Main Queue with DLQ Policy

Replace `YOUR_DLQ_ARN` with the ARN from above:

```bash
aws sqs create-queue \
  --queue-name summaries-prod \
  --attributes '{
    "MessageRetentionPeriod": "1209600",
    "RedrivePolicy": "{\"deadLetterTargetArn\":\"YOUR_DLQ_ARN\",\"maxReceiveCount\":\"3\"}"
  }' \
  --region us-east-1
```

Get the queue URL (save it):
```bash
aws sqs get-queue-url --queue-name summaries-prod --region us-east-1
# Copy the QueueUrl value
```

---

## Step 6: Launch EC2 Instance

### 6.1 Create Key Pair

```bash
aws ec2 create-key-pair \
  --key-name summarizer-key \
  --region us-east-1 \
  --query 'KeyMaterial' \
  --output text > ~/.aws/summarizer-key.pem

chmod 400 ~/.aws/summarizer-key.pem
```

### 6.2 Launch t2.micro Instance

```bash
aws ec2 run-instances \
  --image-id ami-0517aaaee33d8b971 \
  --instance-type t3.micro \
  --key-name summarizer-key \
  --region us-east-1 \
  --tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=summarizer-api}]'
```

Save the **InstanceId** from the output (looks like `i-0xxxxx`).

### 6.3 Allocate & Associate Elastic IP

```bash
# Allocate
ALLOC=$(aws ec2 allocate-address \
  --domain vpc \
  --region us-east-1 \
  --query AllocationId \
  --output text)

# Associate to instance (replace i-xxxxx)
aws ec2 associate-address \
  --instance-id i-xxxxx \
  --allocation-id $ALLOC \
  --region us-east-1

# Get the public IP
aws ec2 describe-addresses \
  --allocation-ids $ALLOC \
  --region us-east-1 \
  --query 'Addresses[0].PublicIp' \
  --output text
```

**Save this IP address** — you'll SSH to it next.

### 6.4 Configure Security Group

Get your security group ID:
```bash
SG=$(aws ec2 describe-instances \
  --instance-ids i-xxxxx \
  --region us-east-1 \
  --query 'Reservations[0].Instances[0].SecurityGroups[0].GroupId' \
  --output text)

# Test it
echo "Security Group: $SG"
```

Allow HTTP & HTTPS:
```bash
# HTTP
aws ec2 authorize-security-group-ingress \
  --group-id $SG \
  --protocol tcp \
  --port 80 \
  --cidr 0.0.0.0/0 \
  --region us-east-1

# HTTPS
aws ec2 authorize-security-group-ingress \
  --group-id $SG \
  --protocol tcp \
  --port 443 \
  --cidr 0.0.0.0/0 \
  --region us-east-1
```

---

## Step 7: Set Up EC2 Instance

### 7.1 SSH into Instance

```bash
# Wait 1 min for instance to fully start
ssh -i ~/.aws/summarizer-key.pem ec2-user@YOUR_ELASTIC_IP

# Verify you're in
echo "Connected!"
```

### 7.2 Install Docker & Dependencies

```bash
# Update system
sudo yum update -y

# Install Docker
sudo yum install -y docker git

# Start Docker
sudo systemctl start docker
sudo systemctl enable docker

# Add user to docker group
sudo usermod -a -G docker ec2-user

# Log out and back in for docker group to apply
exit
ssh -i ~/.aws/summarizer-key.pem ec2-user@YOUR_ELASTIC_IP
```

### 7.3 Configure AWS Credentials on EC2

The EC2 instance needs AWS credentials to pull from ECR. Configure them:

```bash
aws configure

# Enter when prompted:
# AWS Access Key ID: AKIA... (from your downloaded CSV)
# AWS Secret Access Key: xxxx... (from your downloaded CSV)
# Default region: us-east-1
# Default output format: json
```

**Security note:** For production, use an **IAM role** attached to the EC2 instance instead of storing credentials. But for this dev setup, storing credentials is acceptable.

### 7.4 Log in to ECR

```bash
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin \
  123456789.dkr.ecr.us-east-1.amazonaws.com
```

### 7.5 Pull Docker Image

```bash
docker pull 123456789.dkr.ecr.us-east-1.amazonaws.com/summarizer-api:latest
```

---

## Step 9: Configure Environment & Run Containers

### 9.1 Create .env for Containers

Create a file `/home/ec2-user/.env.prod` on the EC2 instance:

```bash
cat > ~/.env.prod << 'EOF'
APP_NAME="AI Summarizer"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_APP_KEY_FROM_LOCAL_ENV
APP_URL=http://YOUR_ELASTIC_IP

DB_CONNECTION=pgsql
DB_HOST=summarizer-prod.xxxxx.us-east-1.rds.amazonaws.com
DB_PORT=5432
DB_DATABASE=summarizer_prod
DB_USERNAME=summarizer
DB_PASSWORD=YourStrongPassword123!

QUEUE_CONNECTION=sqs
AWS_DEFAULT_REGION=us-east-1
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=xxxx...
SQS_PREFIX=https://sqs.us-east-1.amazonaws.com/123456789
SQS_QUEUE=summaries-prod

LLM_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-haiku-4-5-20251001

MAX_INPUT_TOKENS=12000
RATE_LIMIT_PER_HOUR=20
FETCH_TIMEOUT_SECONDS=10
FETCH_MAX_BYTES=2000000

FRONTEND_URL=http://localhost:3000
EOF
```

**Important:** Replace `FRONTEND_URL` with your actual frontend URL after Step 11 (when you deploy to Amplify and get a free domain like `https://dxxxxx.amplifyapp.com`). For now, use `localhost:3000` as a placeholder.

**Get your `APP_KEY`** from your local `.env`:
```bash
# On your local machine
grep "APP_KEY=" /Users/dmytroodulo/testing/ai-summarizer/api/.env
```

### 9.2 Run API Container

On EC2:
```bash
docker run -d \
  --name api \
  -p 8000:8000 \
  --restart unless-stopped \
  --env-file ~/.env.prod \
  123456789.dkr.ecr.us-east-1.amazonaws.com/summarizer-api:latest

# Check logs
docker logs api
```

### 9.3 Run Worker Container

```bash
docker run -d \
  --name worker \
  --restart unless-stopped \
  --env-file ~/.env.prod \
  123456789.dkr.ecr.us-east-1.amazonaws.com/summarizer-api:latest \
  php artisan queue:work sqs --tries=3 --backoff=10 --timeout=120

# Check logs
docker logs worker
```

### 9.4 Verify Containers Running

```bash
docker ps
# Should show both 'api' and 'worker' containers
```

---

## Step 10: Create Database and Run Migrations

### 10.1 Create the Database

On EC2, create the database on your RDS instance:

```bash
PGPASSWORD="YourStrongPassword123!" psql \
  -h summarizer-prod.ca3isq066nlq.us-east-1.rds.amazonaws.com \
  -U summarizer \
  -c "CREATE DATABASE summarizer_prod;"
```

Replace:
- `YourStrongPassword123!` with your actual RDS master password
- `summarizer-prod.ca3isq066nlq.us-east-1.rds.amazonaws.com` with your actual RDS endpoint

### 10.2 Run Migrations and Seed Data

```bash
# Run migrations to create tables
docker exec api php artisan migrate --force

# Seed initial data (admin + test users)
docker exec api php artisan db:seed --force
```

### 10.3 Verify

```bash
# Check user count
docker exec api php artisan tinker --execute "echo User::count();"
# Should output: 2 (admin + test users)
```

---

## Step 11: Deploy Frontend to Amplify

### 10.1 Push Code to GitHub

```bash
# On your local machine
cd /Users/dmytroodulo/testing/ai-summarizer
git init
git add .
git commit -m "Initial commit: AI Summarizer"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/ai-summarizer.git
git push -u origin main
```

### 10.2 Deploy Frontend via Amplify

1. Go to **AWS Console** → **Amplify** → **Get started** → **Host web app**
2. Select **GitHub** → authorize
3. Choose your repository: `ai-summarizer`
4. Branch: `main`
5. **Build settings:**
   - **Build command:** `cd web && npm ci && npm run build`
   - **Base directory:** `web`
6. Environment variables:
   - `NEXT_PUBLIC_API_BASE_URL=http://YOUR_ELASTIC_IP:8000`
7. Click **Save and deploy**

Wait ~5 minutes for Amplify to build and deploy.

### 10.3 Get Your Amplify URL

Go to **Amplify** → **Deployments** → Copy the domain (looks like `dxxxxx.amplifyapp.com`).

---

## Step 12: Test End-to-End

### 11.1 Test API directly

```bash
# Register a new user
curl -X POST http://YOUR_ELASTIC_IP:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123"
  }'

# Should return: token + user
```

### 11.2 Test via Amplify UI

1. Go to `https://dxxxxx.amplifyapp.com`
2. Register & log in
3. Submit a text summary
4. Wait ~5 seconds
5. Check the detail page — should show "completed" with model/tokens

### 11.3 Check Worker Logs

```bash
ssh -i ~/.aws/summarizer-key.pem ec2-user@YOUR_ELASTIC_IP
docker logs worker --tail 20
# Should see: "Processing job..." → "Summary stored"
```

---

## Step 13: Set Up Domain (Optional)

If you want a custom domain instead of elastic IP:

1. Buy domain from **Route 53** or **Namecheap**
2. In Route 53, create A record pointing to your elastic IP
3. Update `FRONTEND_URL` and `APP_URL` in EC2 `.env.prod`
4. Restart containers: `docker restart api worker`

---

## Cost Tracking

### Monitor Free Tier Usage

1. Go to **AWS Billing Dashboard**
2. Check **Free Tier** tab
3. Verify you're under limits:
   - EC2: < 750 hours/month ✓
   - RDS: < 750 hours/month ✓
   - SQS: < 1M requests/month ✓
   - Amplify: < 15GB/month ✓

### Set Billing Alarm

```bash
aws cloudwatch put-metric-alarm \
  --alarm-name free-tier-alert \
  --alarm-description "Alert if charges exceed $5" \
  --metric-name EstimatedCharges \
  --namespace AWS/Billing \
  --statistic Maximum \
  --period 86400 \
  --threshold 5 \
  --comparison-operator GreaterThanThreshold \
  --dimensions Name=Currency,Value=USD
```

---

## Troubleshooting

### SSH connection timeout

**Error:**
```
ssh: connect to host 34.198.172.110 port 22: Operation timed out
```

**Solution:**
1. **Wait longer** — EC2 instances take 1–2 minutes to fully boot. Wait 2–3 minutes and try again:
```bash
sleep 120
ssh -i ~/.aws/summarizer-key.pem ec2-user@YOUR_ELASTIC_IP
```

2. **Allow SSH in security group** — If timeout persists, SSH may be blocked:
```bash
# Get security group ID
SG=$(aws ec2 describe-instances \
  --instance-ids i-xxxxx \
  --region us-east-1 \
  --query 'Reservations[0].Instances[0].SecurityGroups[0].GroupId' \
  --output text)

# Allow SSH (port 22)
aws ec2 authorize-security-group-ingress \
  --group-id $SG \
  --protocol tcp \
  --port 22 \
  --cidr 0.0.0.0/0 \
  --region us-east-1

# Try SSH again
ssh -i ~/.aws/summarizer-key.pem ec2-user@YOUR_ELASTIC_IP
```

3. **Verify key permissions** — The key file must have restrictive permissions:
```bash
chmod 400 ~/.aws/summarizer-key.pem
```

---

### API not responding (port 8000)

```bash
# SSH to EC2
docker ps -a
docker logs api

# Common issues:
# 1. Database not accessible — check RDS security group
# 2. App key mismatch — verify APP_KEY in .env.prod
# 3. Out of memory — t2.micro has 1GB RAM; restart: docker restart api
```

### Worker not processing messages

```bash
docker logs worker

# Check SQS queue depth
aws sqs get-queue-attributes \
  --queue-url https://sqs.us-east-1.amazonaws.com/123456789/summaries-prod \
  --attribute-names ApproximateNumberOfMessages
```

### RDS connection fails

```bash
# Verify EC2 security group allows port 5432 from itself
aws ec2 describe-security-groups \
  --group-ids YOUR_SG_ID \
  --region us-east-1
```

### Frontend shows "API unreachable"

```bash
# Check Amplify env var is correct
# Go to Amplify console → App settings → Environment variables
# Verify NEXT_PUBLIC_API_BASE_URL matches your EC2 IP
```

---

## Cleanup (Avoid Unexpected Charges After Year 1)

When free tier expires or you want to stop:

```bash
# Stop EC2
aws ec2 stop-instances --instance-ids i-xxxxx --region us-east-1

# Stop RDS
aws rds stop-db-instance --db-instance-identifier summarizer-prod --region us-east-1

# Delete Amplify app (if not needed)
# (Use console, no CLI command)

# You're still charged for:
# - EBS volume (20GB) — ~$2/month
# - RDS storage — ~$2/month
# To fully stop charges, delete resources:
# aws ec2 terminate-instances ... (deletes instance + EBS)
# aws rds delete-db-instance ... (deletes RDS)
```

---

## Summary

You now have:
- ✓ Laravel API + Queue Worker on EC2 t2.micro ($0/mo free tier)
- ✓ PostgreSQL on RDS ($0/mo free tier)
- ✓ SQS queue for async jobs ($0/mo free tier)
- ✓ Next.js frontend on Amplify ($0/mo free tier)
- ✓ **Total cost: $0–$1/month** (year 1)

**Next steps:**
- Monitor billing dashboard
- Set custom domain (optional)
- Add HTTPS via ACM (optional, free)
- Plan for post-free-tier cost reduction

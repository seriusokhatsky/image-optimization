# SSL Certificate Setup Guide

This guide covers how to set up production SSL certificates for your Laravel application using Let's Encrypt.

## 🚀 Quick Start (Recommended)

### Prerequisites

1. **Domain Setup**: Ensure `img-optim.xtemos.com` points to your server's IP address
2. **Port Access**: Ports 80 and 443 must be accessible from the internet
3. **Valid Email**: Update the email address in the scripts

### Option 1: Docker-based Setup (Easiest)

```bash
# 1. Update email in the script
nano scripts/generate-ssl-docker.sh  # Change EMAIL variable

# 2. Run the setup script
./scripts/generate-ssl-docker.sh
```

### Option 2: Traditional Setup (Direct on server)

```bash
# 1. Update email in the script
nano scripts/setup-ssl.sh  # Change EMAIL variable

# 2. Run the setup script (requires sudo)
sudo ./scripts/setup-ssl.sh
```

## 📁 Certificate Storage Structure

```
docker/nginx/ssl/
├── live/
│   └── img-optim.xtemos.com/
│       ├── fullchain.pem      # Full certificate chain (used by nginx)
│       ├── privkey.pem        # Private key (used by nginx)
│       ├── cert.pem           # Certificate only
│       └── chain.pem          # Chain only
└── ...other Let's Encrypt files
```

## 🔧 Manual Setup

If you prefer to set up certificates manually:

### Step 1: Stop Production Containers

```bash
docker compose -f docker-compose.prod.yml down
```

### Step 2: Install Certbot (if not using Docker)

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install snapd
sudo snap install core
sudo snap refresh core
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot
```

### Step 3: Generate Certificate

```bash
# Using standalone mode (server must be stopped)
sudo certbot certonly --standalone -d img-optim.xtemos.com --email your-email@example.com --agree-tos --non-interactive
```

### Step 4: Copy Certificates

```bash
# Create target directory
mkdir -p docker/nginx/ssl/live/img-optim.xtemos.com

# Copy certificates
sudo cp /etc/letsencrypt/live/img-optim.xtemos.com/fullchain.pem docker/nginx/ssl/live/img-optim.xtemos.com/
sudo cp /etc/letsencrypt/live/img-optim.xtemos.com/privkey.pem docker/nginx/ssl/live/img-optim.xtemos.com/

# Set permissions
sudo chmod 644 docker/nginx/ssl/live/img-optim.xtemos.com/fullchain.pem
sudo chmod 600 docker/nginx/ssl/live/img-optim.xtemos.com/privkey.pem
```

### Step 5: Update Nginx Configuration

```bash
# The script does this automatically, but manually:
sed -i 's|local-cert\.pem|fullchain.pem|g' docker/nginx/nginx.conf
sed -i 's|local-key\.pem|privkey.pem|g' docker/nginx/nginx.conf
```

### Step 6: Start Production

```bash
docker compose -f docker-compose.prod.yml up -d
```

## 🔄 Certificate Renewal

### Automatic Renewal (Recommended)

The setup scripts create a renewal script at `scripts/renew-ssl.sh`. Set up a cron job:

```bash
# Edit crontab
crontab -e

# Add this line to renew certificates daily at 3 AM
0 3 * * * /path/to/your/project/scripts/renew-ssl.sh >> /var/log/ssl-renewal.log 2>&1
```

### Manual Renewal

```bash
# Using the renewal script
./scripts/renew-ssl.sh

# Or manually with Docker
docker compose -f docker-compose.prod.yml down
docker compose -f docker-compose.ssl.yml up -d nginx-certbot
docker compose -f docker-compose.ssl.yml run --rm certbot renew
docker compose -f docker-compose.ssl.yml down
docker compose -f docker-compose.prod.yml up -d
```

## 🔍 Testing & Verification

### Test HTTPS Connection

```bash
# Test basic connectivity
curl -I https://img-optim.xtemos.com

# Test SSL certificate
openssl s_client -connect img-optim.xtemos.com:443 -servername img-optim.xtemos.com
```

### Online SSL Tests

- **SSL Labs Test**: https://www.ssllabs.com/ssltest/
- **Certificate Details**: https://crt.sh/?q=img-optim.xtemos.com

### Check Certificate Expiry

```bash
# Check expiry date
openssl x509 -in docker/nginx/ssl/live/img-optim.xtemos.com/fullchain.pem -noout -dates

# Or using certbot
sudo certbot certificates
```

## 🚨 Troubleshooting

### Common Issues

1. **Port 80/443 not accessible**
   ```bash
   # Check if ports are open
   sudo ufw status
   sudo iptables -L
   
   # Open ports if needed
   sudo ufw allow 80
   sudo ufw allow 443
   ```

2. **Domain not resolving to server**
   ```bash
   # Check DNS resolution
   dig img-optim.xtemos.com
   nslookup img-optim.xtemos.com
   ```

3. **Permission issues**
   ```bash
   # Fix certificate permissions
   sudo chown -R $USER:$USER docker/nginx/ssl
   chmod 600 docker/nginx/ssl/live/img-optim.xtemos.com/privkey.pem
   chmod 644 docker/nginx/ssl/live/img-optim.xtemos.com/fullchain.pem
   ```

4. **Certificate validation failed**
   ```bash
   # Check if nginx is blocking verification
   curl -I http://img-optim.xtemos.com/.well-known/acme-challenge/test
   
   # Verify webroot is accessible
   echo "test" > docker/nginx/webroot/test.txt
   curl http://img-optim.xtemos.com/test.txt
   ```

### Debug Mode

Run certbot with debug output:

```bash
docker compose -f docker-compose.ssl.yml run --rm certbot certonly --webroot -w /var/www/html -d img-optim.xtemos.com --email your-email@example.com --agree-tos --non-interactive --expand --dry-run --verbose
```

## 📋 Certificate Information

### Let's Encrypt Limits

- **Rate Limits**: 50 certificates per registered domain per week
- **Certificate Lifetime**: 90 days
- **Renewal Window**: Recommended to renew when 30 days remaining

### Backup Recommendations

```bash
# Backup entire SSL directory
tar -czf ssl-backup-$(date +%Y%m%d).tar.gz docker/nginx/ssl/

# Backup to remote location
rsync -av docker/nginx/ssl/ user@backup-server:/backups/ssl/
```

## 🔐 Security Best Practices

1. **Keep private keys secure** (600 permissions)
2. **Regular renewal** (automated cron job)
3. **Monitor expiry dates**
4. **Use strong ciphers** (already configured in nginx.conf)
5. **Enable HSTS** (already configured)
6. **Test regularly** with SSL Labs

## 📞 Support

If you encounter issues:

1. Check the logs: `docker compose -f docker-compose.ssl.yml logs certbot`
2. Verify DNS: `dig img-optim.xtemos.com`
3. Test port access: `telnet your-server-ip 80`
4. Check Let's Encrypt status: https://letsencrypt.status.io/

---

## Files Created by This Setup

- `scripts/setup-ssl.sh` - Traditional SSL setup script
- `scripts/generate-ssl-docker.sh` - Docker-based SSL setup script  
- `scripts/renew-ssl.sh` - Certificate renewal script
- `docker-compose.ssl.yml` - Docker compose for certificate generation
- `docker/nginx/nginx-certbot.conf` - Nginx config for certificate verification
- `README-SSL.md` - This documentation

All scripts are designed to be safe and include validation steps before making changes. 
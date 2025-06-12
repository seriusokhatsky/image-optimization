SERVER_IP="157.180.83.204"
CERT_PATH="/etc/nginx/ssl/live/img-optim.xtemos.com"

# Create the directory structure
ssh root@$SERVER_IP "mkdir -p $CERT_PATH"

# Upload and rename certificate files to match your nginx config
scp ./certs/fullchain.pem root@$SERVER_IP:$CERT_PATH/local-cert.pem
scp ./certs/privkey.pem root@$SERVER_IP:$CERT_PATH/local-key.pem

# Set proper permissions
ssh root@$SERVER_IP "
    chmod 644 $CERT_PATH/local-cert.pem &&
    chmod 600 $CERT_PATH/local-key.pem &&
    chown root:root $CERT_PATH/* &&
    echo '✅ Certificates uploaded to nginx path!'
"
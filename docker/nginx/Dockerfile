FROM nginx:alpine

# Remove default nginx config
RUN rm -rf /etc/nginx/conf.d/*

# Copy nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf
COPY conf.d/ /etc/nginx/conf.d/

# Create SSL directory and copy certificates if they exist
RUN mkdir -p /etc/nginx/ssl
COPY ssl/ /etc/nginx/ssl/
# Remove .gitkeep if present
RUN rm -f /etc/nginx/ssl/.gitkeep 2>/dev/null || true

# Create log directory
RUN mkdir -p /var/log/nginx

# Validate nginx configuration
RUN nginx -t

EXPOSE 80 443 8001 8002 8003

CMD ["nginx", "-g", "daemon off;"]

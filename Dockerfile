FROM richarvey/nginx-php-fpm:latest

ARG VERSION

#Install Bash Utilities
RUN curl -L https://github.com/a8m/envsubst/releases/download/v1.2.0/envsubst-`uname -s`-`uname -m` -o envsubst
RUN chmod +x envsubst
RUN mv envsubst /usr/local/bin

#Install Necessary PHP Extensions and Composer
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions sockets mysqli @composer

# Copy wadapi entrypoint
COPY ./bin/wadapi_entrypoint.sh /wadapi_entrypoint.sh

# Copy custom nGinx config
COPY ./conf/nginx.conf /var/www/html/conf/nginx/nginx-site.conf

# Create wadapi project structure
RUN composer create-project --no-scripts dadlian/wadapi wadapi

WORKDIR /var/www/html/wadapi

# Update wadapi framework to latest version
RUN composer require dadlian/wadapi-framework:$VERSION

# Define Healthcheck
HEALTHCHECK --interval=5s --timeout=5s --retries=6 \
  CMD ps auxf | grep "[w]adapi_entrypoint.sh" | wc -l

# Initialise log file
RUN touch /wadapi.log

ENTRYPOINT ["/wadapi_entrypoint.sh"]

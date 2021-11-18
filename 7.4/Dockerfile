FROM registry.access.redhat.com/ubi8/php-74

USER root

# Update the image with the latest packages (recommended), then
# Install php-xmlrpc module from RHSCL repo, remove override_install_langs so all locales can be installed, install glibc-common for all locales (locale -a)
RUN yum update -y && \
    yum clean all && \
    yum install -y php-xmlrpc && \
    yum install -y php-zip && \
    yum install -y glibc-all-langpacks && \
    yum reinstall -y glibc-common && \
    yum clean all

# Might want to refine this (something more specific than $APP_DATA)
RUN chgrp -Rf root $APP_DATA && \
    chmod -Rf g+w $APP_DATA && \
    cd $APP_DATA && \
    umask 002

# CIODRCOE-308 if the build is not a s2i build, these values are not being properly set and the container fails to start
ENV HTTPD_MAX_REQUESTS_PER_CHILD=${HTTPD_MAX_REQUESTS_PER_CHILD:-4000}
ENV HTTPD_MAX_KEEPALIVE_REQUESTS=${HTTPD_MAX_KEEPALIVE_REQUESTS:-100}

USER 1001

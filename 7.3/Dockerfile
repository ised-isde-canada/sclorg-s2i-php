FROM registry.redhat.io/rhscl/php-73-rhel7

USER root

# Update the image with the latest packages (recommended)
RUN yum update -y; yum clean all

# Install php-xmlrpc module from RHSCL repo, remove override_install_langs so all locales can be installed, install glibc-common for all locales (locale -a)
RUN yum-config-manager --enable rhel-server-rhscl-7-rpms; yum install -y rh-php72-php-xmlrpc; yum install -y rh-php73-php-zip; yum clean all; sed -i s/override_install_langs=en_US/#override_install_langs=en_US/ /etc/yum.conf; yum reinstall -y glibc-common;

# Might want to refine this (something more specific than $APP_DATA)
RUN chgrp -Rf root $APP_DATA && \
    chmod -Rf g+w $APP_DATA && \
    cd $APP_DATA && \
    umask 002

USER 1001

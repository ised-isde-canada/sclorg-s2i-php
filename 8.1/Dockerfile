FROM registry.redhat.io/ubi9/php-81

USER root

# Update the image with the latest packages (recommended), then
# Install php-xmlrpc module from RHSCL repo, remove override_install_langs so all locales can be installed, install glibc-common for all locales (locale -a)
RUN yum update -y && \
    yum clean all && \
    yum install -y php-zip && \
    yum install -y glibc-all-langpacks && \
    yum reinstall -y glibc-common && \
    yum clean all

# Might want to refine this (something more specific than $APP_DATA)
RUN chgrp -R 0 /var/run/httpd && \
    chmod -R g=u /var/run/httpd && \
    chgrp -Rf root $APP_DATA && \
    chmod -Rf g+w $APP_DATA && \
    cd $APP_DATA && \
    umask 002

#Resolve the following error httpd service startup: AH02282: No slotmem from mod_heartmonitor
RUN sed -i 's/mod_lbmethod_heartbeat.so\/mod_lbmethod_heartbeat.so/# mod_lbmethod_heartbeat.so\/mod_lbmethod_heartbeat.so/g' /etc/httpd/conf.modules.d/00-proxy.conf
# chgrp and chmod on /var/run/httpd was previously not necessary in the php-7.4 build, however knocked myself out when using this build and had to modify perms of /var/run/httpd
# the chgrp and chmod command was taken from Red Hat support document called 13)Permission denied: AH000058: Error retrieving pid file /run/httpd/httpd.pid - Red Hat Customer Portal
# the solution was in a comment from 25 November 2019 10:06 PM
#Gustavo Vieira Oliveira
#On my tests i noticed that the folder /var/run/httpd didn't have group permissions for
#Write.
#So i changed it on the dockerfile i was using, which as based on php7.2 s2i image.
#RUN chgrp -R 0 /var/run/httpd \ 
#&& chmod -R g+rwX /var/run/httpd

# CIODRCOE-308 if the build is not a s2i build, these values are not being properly set and the container fails to start
ENV HTTPD_MAX_REQUESTS_PER_CHILD=${HTTPD_MAX_REQUESTS_PER_CHILD:-4000}
ENV HTTPD_MAX_KEEPALIVE_REQUESTS=${HTTPD_MAX_KEEPALIVE_REQUESTS:-100}

USER 1001

FROM registry.access.redhat.com/ubi8/php-73

USER root

# Update the image with the latest packages (recommended), then
# Install php-xmlrpc module from RHSCL repo, remove override_install_langs so all locales can be installed, install glibc-common for all locales (locale -a)
RUN yum update -y && \
    yum clean all && \
    yum install -y php-xmlrpc && \
    yum install -y php-zip && \
    yum install -y langpacks-fr && \
    yum reinstall -y glibc-common && \
    yum install -y https://download.postgresql.org/pub/repos/yum/reporpms/EL-8-x86_64/pgdg-redhat-repo-latest.noarch.rpm && \
    yum --disablerepo=rhel-8-for-x86_64-appstream-rpms install -y postgresql12 && \
    yum clean all

RUN yum install -y python27 && \
    pip2 install python-dateutil && \
    cd /usr/local && \
    git clone https://github.com/s3tools/s3cmd.git && \
    ln -s /usr/local/s3cmd/s3cmd /usr/bin/s3cmd && \
    ln -s /usr/bin/python2 /usr/bin/python && \
    mkdir /opt/backup && \
    chmod -R 775 /opt/backup

COPY backup-restore/backup.php backup-restore/restore.php backup-restore/api /opt/backup/
RUN chown -R 1001 /opt/backup

# Might want to refine this (something more specific than $APP_DATA)
RUN chgrp -Rf root $APP_DATA && \
    chmod -Rf g+w $APP_DATA && \
    cd $APP_DATA && \
    umask 002

USER 1001

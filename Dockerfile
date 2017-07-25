FROM composer

EXPOSE 8888

ADD ./ /opt/project
WORKDIR /opt/project

#RUN composer install

CMD ["php", "-S", "0.0.0.0:8888", "-t", "/opt/project"]
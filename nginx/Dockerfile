FROM nginx

ARG WEB_USER_ID=33
ARG WEB_USER_NAME=www-data
RUN useradd -m -u ${WEB_USER_ID} ${WEB_USER_NAME} || echo "User exists, it's ok." \
    && sed -i -- "s/user nginx;/user ${WEB_USER_NAME};/" /etc/nginx/nginx.conf

COPY site.conf /etc/nginx/conf.d/default.conf

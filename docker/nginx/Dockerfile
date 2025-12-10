FROM node:24.5.0-alpine3.21 AS frontend-builder

WORKDIR /app

COPY . .

RUN npm ci

RUN npm run build

FROM nginx:1.28.0

COPY ./docker/nginx/php.conf /etc/nginx/conf.d/default.conf

COPY ./public /var/www/html/public
COPY --from=frontend-builder app/public/build /var/www/html/public/build

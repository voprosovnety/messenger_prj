FROM node:24-alpine AS frontend-build
WORKDIR /app
COPY frontend/package*.json ./
RUN npm install --frozen-lockfile
COPY frontend/ .
COPY VERSION /VERSION
RUN npm run build

FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend-build /app/dist /var/www/frontend/dist

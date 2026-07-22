# Multi-stage build: compile the Vite app, then serve the static output with
# nginx. No custom entrypoint script here either - nginx's official image
# starts nginx directly (its own default CMD), and the config below is just a
# file we COPY in, not something that needs an executable bit.

FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
ARG VITE_API_URL=http://localhost:8000/api/v1
ENV VITE_API_URL=${VITE_API_URL}
RUN npm run build

FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80

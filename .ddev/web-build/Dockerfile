ARG BASE_IMAGE
FROM $BASE_IMAGE

# Install Node.js (18.x LTS) and npm
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
  && apt-get update \
  && apt-get install -y nodejs \
  && npm install -g vite sass

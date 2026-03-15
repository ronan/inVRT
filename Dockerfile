FROM mcr.microsoft.com/playwright:v1.58.2-noble

WORKDIR /app

COPY . .

RUN npm install
RUN composer install --no-dev --optimize-autoloader

WORKDIR /dir

CMD [ "/app/bin/invrt" ]

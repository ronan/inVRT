FROM mcr.microsoft.com/playwright:v1.58.2-noble

WORKDIR /app

COPY . .

RUN npm install

WORKDIR /dir

CMD [ "/app/bin/invrt" ]

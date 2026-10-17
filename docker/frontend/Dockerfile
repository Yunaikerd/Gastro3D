FROM node:20-alpine

WORKDIR /app

COPY frontend/package*.json ./

RUN npm install

COPY frontend ./

EXPOSE 3000

ENV PORT=3000
ENV HOSTNAME="0.0.0.0"

CMD ["npm", "run", "dev", "--", "-H", "0.0.0.0"]

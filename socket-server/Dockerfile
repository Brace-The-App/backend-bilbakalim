FROM node:18-alpine

WORKDIR /app

# Package dosyalarını kopyala
COPY package*.json ./

# Bağımlılıkları yükle
RUN npm install

# Uygulama dosyalarını kopyala
COPY . .

# Port'u aç
EXPOSE 3001

# Uygulamayı başlat
CMD ["node", "server.js"]

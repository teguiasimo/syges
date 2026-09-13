# SYGES

Système de Gestion scolaire / académique (SYGES) développé avec Laravel, Inertia.js et React.

## Prérequis

- PHP >= 8.3
- Composer
- Node.js & npm (ou pnpm)
- Base de données (MySQL / PostgreSQL / SQLite)

## Installation

1. **Cloner le projet :**
   ```bash
   git clone https://github.com/teguiasimo/syges.git
   cd syges
   ```

2. **Installer les dépendances PHP et JavaScript :**
   ```bash
   composer install
   npm install
   ```

3. **Configuration de l'environnement :**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configurer la base de données :**
   Mettre à jour les variables `DB_*` dans le fichier `.env`, puis lancer les migrations :
   ```bash
   php artisan migrate
   ```

5. **Lancer le serveur de développement :**
   ```bash
   # Terminal 1 : Backend Laravel
   php artisan serve

   # Terminal 2 : Frontend Vite
   npm run dev
   ```

## Licence

Ce projet est sous licence MIT.

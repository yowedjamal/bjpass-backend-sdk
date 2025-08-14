# Installation

## Prérequis

- PHP 8.1 ou supérieur
- Laravel 9.x, 10.x ou 11.x
- Composer

## Installation via Composer

```bash
composer require yowedjamal/bjpass-backend-sdk
```

## Publication des assets

Publiez la configuration et les assets du package :

```bash
php artisan vendor:publish --tag=bjpass-config
php artisan vendor:publish --tag=bjpass-routes
php artisan vendor:publish --tag=bjpass-views
php artisan vendor:publish --tag=bjpass-migrations
```

## Configuration de l'environnement

Ajoutez les variables d'environnement suivantes dans votre fichier `.env` :

```env
# Configuration OIDC
BJPASS_CLIENT_ID=your_client_id
BJPASS_CLIENT_SECRET=your_client_secret
BJPASS_REDIRECT_URI=https://your-app.com/auth/callback
BJPASS_PROVIDER_URL=https://your-provider.com
BJPASS_SCOPE=openid profile email

# Configuration de sécurité
BJPASS_SESSION_MAX_AGE=3600
BJPASS_COOKIE_SECURE=true
BJPASS_COOKIE_SAMESITE=lax

# Configuration des routes
BJPASS_ROUTE_PREFIX=auth
BJPASS_FRONTEND_ORIGIN=https://your-frontend.com
```

## Vérification de l'installation

Vérifiez que le package est correctement installé :

```bash
php artisan route:list | grep bjpass
```

Vous devriez voir les routes suivantes :
- `GET /auth/start`
- `GET /auth/callback`
- `GET /auth/status`
- `POST /auth/logout`
- `GET /user`

## Prochaines étapes

- [Configuration](configuration.md)
- [Utilisation](usage.md)

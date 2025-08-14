# BjPass Backend SDK - Documentation

Bienvenue dans la documentation officielle du BjPass Backend SDK pour Laravel.

## 📚 Table des matières

- [Installation](installation.md)
- [Configuration](configuration.md)
- [Utilisation](usage.md)
- [API Reference](api-reference.md)
- [Sécurité](security.md)
- [Intégration Frontend](frontend-integration.md)
- [Exemples](examples.md)
- [Troubleshooting](troubleshooting.md)
- [Changelog](changelog.md)

## 🚀 Démarrage rapide

```bash
composer require yowedjamal/bjpass-backend-sdk
php artisan vendor:publish --tag=bjpass-config
```

## 🔧 Configuration minimale

```env
BJPASS_CLIENT_ID=your_client_id
BJPASS_CLIENT_SECRET=your_client_secret
BJPASS_REDIRECT_URI=https://your-app.com/auth/callback
BJPASS_PROVIDER_URL=https://your-provider.com
```

## 📖 Documentation complète

Consultez les sections ci-dessus pour une documentation détaillée de toutes les fonctionnalités du SDK.

## 🏗️ Construction de la documentation

Cette documentation est construite avec [Honkit](https://github.com/honkit/honkit), une version maintenue de GitBook.

### Prérequis

- Node.js 18+
- npm

### Installation

```bash
cd docs
npm install
```

### Construction

```bash
# Construire la documentation
npm run build

# Servir en local pour le développement
npm run serve
```

### Structure des fichiers

```
docs/
├── README.md              # Page d'accueil
├── SUMMARY.md             # Table des matières
├── book.json             # Configuration Honkit
├── package.json          # Dépendances npm
├── installation.md       # Guide d'installation
├── configuration.md      # Configuration
├── usage.md             # Guide d'utilisation
├── api-reference.md     # Référence API
├── security.md          # Sécurité
├── frontend-integration.md # Intégration frontend
├── examples.md          # Exemples
├── troubleshooting.md   # Dépannage
└── changelog.md         # Historique des versions
```

## 🚀 Déploiement automatique

La documentation est automatiquement déployée sur Vercel via GitHub Actions :

- **Déclencheur** : Push sur `main` ou `develop`
- **Build** : Honkit génère les fichiers HTML
- **Déploiement** : Vercel déploie automatiquement
- **URL** : Configurée dans Vercel

### Configuration Vercel

Le fichier `vercel.json` à la racine configure le déploiement :

```json
{
  "buildCommand": "npm install -g honkit && cd docs && honkit build . ../_book",
  "outputDirectory": "_book",
  "framework": null
}
```

## 🔄 Workflows GitHub Actions

### Déploiement de la documentation

```yaml
name: Deploy Documentation
on:
  push:
    branches: [main, develop]
    paths: ['docs/**']
```

### Publication du package

```yaml
name: Publish to Packagist
on:
  release:
    types: [published]
```

## 📝 Contribution à la documentation

1. **Modifier les fichiers Markdown** dans le répertoire `docs/`
2. **Tester localement** avec `npm run serve`
3. **Pousser les changements** - le déploiement est automatique

### Standards de documentation

- **Format** : Markdown avec syntaxe Honkit
- **Langue** : Français
- **Structure** : Hiérarchique avec navigation claire
- **Exemples** : Code fonctionnel et commenté

## 🤝 Support

- [Issues GitHub](https://github.com/yowedjamal/bjpass-backend-sdk/issues)
- [Documentation API](https://your-docs-site.com)
- [Discussions GitHub](https://github.com/yowedjamal/bjpass-backend-sdk/discussions)

## 📄 Licence

Cette documentation est sous licence MIT, comme le package principal.

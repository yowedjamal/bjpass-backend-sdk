# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Documentation complète avec Honkit
- Workflows GitHub Actions pour le déploiement automatique
- Configuration Vercel pour la documentation

## [1.0.0] - 2024-01-XX

### Added
- Package Laravel complet pour l'authentification OIDC/OAuth2
- Service Provider et Facade Laravel
- Middleware d'authentification
- Contrôleur d'authentification avec endpoints HTTP
- Services pour la gestion des JWKS, tokens et authentification
- Gestion complète des exceptions avec contexte
- Configuration flexible via variables d'environnement
- Support PKCE (Proof Key for Code Exchange)
- Validation automatique des tokens JWT
- Gestion des sessions et cookies sécurisés
- Tests unitaires avec PHPUnit
- Documentation complète avec exemples
- Guide d'intégration frontend

### Features
- **Authentification OIDC complète** : Flux d'autorisation, échange de code, validation de tokens
- **Sécurité renforcée** : Protection CSRF, validation des origines, cookies HTTPOnly
- **Performance** : Cache JWKS, gestion optimisée des sessions
- **Flexibilité** : Configuration via .env, personnalisation des endpoints
- **Intégration Laravel** : Service Provider, Facade, Middleware, Routes automatiques

### Technical Details
- **PHP 8.1+** : Support des versions récentes de PHP
- **Laravel 9.x, 10.x, 11.x** : Compatibilité avec les versions LTS et récentes
- **PSR-4** : Autoloading standard
- **PSR-12** : Standards de codage
- **Composer** : Gestion des dépendances
- **PHPUnit** : Tests unitaires
- **Orchestra Testbench** : Tests Laravel

## [0.1.0] - 2024-01-XX

### Added
- Structure initiale du package
- Classes de base et exceptions
- Configuration de base

---

## Notes de version

### Version 1.0.0
- **Première version stable** du package BjPass Backend SDK
- **Fonctionnalités complètes** d'authentification OIDC/OAuth2
- **Intégration Laravel native** avec tous les composants nécessaires
- **Documentation exhaustive** pour les développeurs
- **Tests unitaires** pour garantir la qualité du code
- **Sécurité renforcée** avec toutes les bonnes pratiques OIDC

### Migration depuis les versions précédentes
- Cette version est la première version stable
- Aucune migration requise depuis les versions alpha/beta
- Compatible avec Laravel 9.x, 10.x et 11.x

### Dépendances
- **Laravel Framework** : ^9.0|^10.0|^11.0
- **Guzzle HTTP** : ^7.0
- **Firebase JWT** : ^6.0
- **LCobucci JWT** : ^4.0
- **Ramsey UUID** : ^4.0

### Support
- **PHP** : 8.1+
- **Laravel** : 9.x, 10.x, 11.x
- **Environnements** : Local, Staging, Production

---

## Politique de version

Ce projet suit le [Semantic Versioning](https://semver.org/):

- **MAJOR** : Changements incompatibles avec l'API
- **MINOR** : Nouvelles fonctionnalités compatibles
- **PATCH** : Corrections de bugs compatibles

### Cycle de publication

- **Versions majeures** : Tous les 6-12 mois
- **Versions mineures** : Tous les 1-3 mois
- **Versions patch** : Selon les besoins (bugs critiques)

### Support des versions

- **Version actuelle** : Support complet
- **Version précédente** : Support de sécurité uniquement
- **Versions antérieures** : Aucun support

---

## Contribution

Pour contribuer à ce projet :

1. Fork le repository
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

### Standards de code

- Suivre les standards PSR-12
- Ajouter des tests pour les nouvelles fonctionnalités
- Mettre à jour la documentation
- Respecter le format de commit conventionnel

---

## Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](../LICENSE) pour plus de détails.

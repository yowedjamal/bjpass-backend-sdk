# Intégration Frontend

## Vue d'ensemble

Cette section détaille les modifications nécessaires dans votre SDK frontend existant pour l'intégrer parfaitement avec le BjPass Backend SDK.

## Modifications requises

### 1. Configuration

#### Nouveaux paramètres

```javascript
const config = {
    // Configuration existante
    clientId: 'your_client_id',
    redirectUri: 'https://your-app.com/auth/callback',
    
    // Nouveaux paramètres pour le backend
    backendUrl: 'https://your-api.com',
    backendEndpoints: {
        start: '/auth/start',
        status: '/auth/status',
        user: '/auth/user',
        logout: '/auth/logout',
        refresh: '/auth/refresh',
        introspect: '/auth/introspect'
    },
    frontendOrigin: 'https://your-frontend.com',
    backendOrigin: 'https://your-api.com',
    useBackend: true,
    popupMode: true,
    autoClosePopup: true
};
```

### 2. Méthode `startAuthFlow`

#### Avant (SDK frontend uniquement)

```javascript
startAuthFlow() {
    const state = this.generateState();
    const nonce = this.generateNonce();
    const codeVerifier = this.generateCodeVerifier();
    
    // Stockage local
    this.state = state;
    this.nonce = nonce;
    this.codeVerifier = codeVerifier;
    
    // Construction de l'URL
    const authUrl = this.buildAuthorizationUrl({
        state,
        nonce,
        code_challenge: this.generateCodeChallenge(codeVerifier)
    });
    
    // Redirection
    if (this.popupMode) {
        this.openPopup(authUrl);
    } else {
        window.location.href = authUrl;
    }
}
```

#### Après (avec backend)

```javascript
async startAuthFlow() {
    try {
        // Délégation au backend
        const response = await fetch(`${this.config.backendUrl}${this.config.backendEndpoints.start}`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (response.ok) {
            const { authUrl } = await response.json();
            
            if (this.config.popupMode) {
                this.openPopup(authUrl);
            } else {
                window.location.href = authUrl;
            }
        } else {
            throw new Error('Failed to get authorization URL');
        }
    } catch (error) {
        this.handleError(error);
    }
}
```

### 3. Écouteur de messages

#### Validation des origines

```javascript
setupMessageListener() {
    window.addEventListener('message', (event) => {
        // Validation stricte des origines
        if (event.origin !== this.config.backendOrigin) {
            console.warn('Message from unauthorized origin:', event.origin);
            return;
        }
        
        if (event.data.type === 'bjpass-auth-response') {
            this.handleAuthResponse(event.data);
        }
    });
}
```

#### Gestion des réponses

```javascript
handleAuthResponse(data) {
    if (data.status === 'success') {
        this.userInfo = data.user;
        this.isAuthenticated = true;
        
        if (this.config.autoClosePopup && this.popup) {
            this.popup.close();
        }
        
        this.emit('authenticated', data.user);
    } else {
        this.handleError(new Error(data.message));
    }
}
```

### 4. Nouvelles méthodes

#### Vérification du statut

```javascript
async verifyBackendStatus() {
    try {
        const response = await fetch(`${this.config.backendUrl}${this.config.backendEndpoints.status}`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (response.ok) {
            const { authenticated, user } = await response.json();
            this.isAuthenticated = authenticated;
            this.userInfo = user;
            return { authenticated, user };
        }
        
        return { authenticated: false, user: null };
    } catch (error) {
        console.error('Status check failed:', error);
        return { authenticated: false, user: null };
    }
}
```

#### Récupération des informations utilisateur

```javascript
async getUserInfoFromBackend() {
    try {
        const response = await fetch(`${this.config.backendUrl}${this.config.backendEndpoints.user}`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (response.ok) {
            const user = await response.json();
            this.userInfo = user;
            return user;
        }
        
        throw new Error('Failed to get user info');
    } catch (error) {
        this.handleError(error);
        return null;
    }
}
```

#### Déconnexion

```javascript
async logoutFromBackend() {
    try {
        const response = await fetch(`${this.config.backendUrl}${this.config.backendEndpoints.logout}`, {
            method: 'POST',
            credentials: 'include'
        });
        
        if (response.ok) {
            this.isAuthenticated = false;
            this.userInfo = null;
            this.emit('loggedOut');
        }
    } catch (error) {
        this.handleError(error);
    }
}
```

#### Rafraîchissement de token

```javascript
async refreshToken() {
    try {
        const response = await fetch(`${this.config.backendUrl}${this.config.backendEndpoints.refresh}`, {
            method: 'POST',
            credentials: 'include'
        });
        
        if (response.ok) {
            const { user } = await response.json();
            this.userInfo = user;
            return user;
        }
        
        throw new Error('Token refresh failed');
    } catch (error) {
        this.handleError(error);
        return null;
    }
}
```

## Configuration recommandée

### Configuration minimale

```javascript
const bjpass = new BjPassAuth({
    clientId: 'your_client_id',
    backendUrl: 'https://your-api.com',
    useBackend: true
});
```

### Configuration avancée

```javascript
const bjpass = new BjPassAuth({
    clientId: 'your_client_id',
    backendUrl: 'https://your-api.com',
    backendEndpoints: {
        start: '/auth/start',
        status: '/auth/status',
        user: '/auth/user',
        logout: '/auth/logout',
        refresh: '/auth/refresh',
        introspect: '/auth/introspect'
    },
    frontendOrigin: 'https://your-frontend.com',
    backendOrigin: 'https://your-api.com',
    useBackend: true,
    popupMode: true,
    autoClosePopup: true,
    onAuthenticated: (user) => {
        console.log('User authenticated:', user);
    },
    onError: (error) => {
        console.error('Authentication error:', error);
    }
});
```

## Flux complet

### 1. Initialisation

```javascript
// L'utilisateur clique sur "Se connecter"
bjpass.startAuthFlow();
```

### 2. Redirection

```javascript
// Le backend génère l'URL d'autorisation
// Redirection vers le provider OIDC
```

### 3. Authentification

```javascript
// L'utilisateur s'authentifie sur le provider
// Redirection vers le callback du backend
```

### 4. Traitement

```javascript
// Le backend traite le callback
// Stockage des tokens en session
// Affichage de la page de succès
```

### 5. Communication

```javascript
// La page de succès envoie un message au frontend
// Le frontend reçoit les informations utilisateur
// Fermeture automatique de la popup
```

### 6. Vérification

```javascript
// Le frontend peut vérifier le statut
const { authenticated, user } = await bjpass.verifyBackendStatus();

if (authenticated) {
    // Utilisateur connecté
    console.log('Welcome,', user.name);
}
```

## Sécurité

### Validation des origines

```javascript
// Toujours valider l'origine des messages
if (event.origin !== this.config.backendOrigin) {
    return;
}
```

### Cookies sécurisés

```javascript
// Utilisation de credentials: 'include' pour les cookies
fetch(url, {
    credentials: 'include',
    // ...
});
```

### Gestion des erreurs

```javascript
// Ne jamais exposer d'informations sensibles
catch (error) {
    console.error('Authentication failed');
    this.emit('error', { message: 'Authentication failed' });
}
```

## Migration depuis l'ancien SDK

### Étapes de migration

1. **Mise à jour de la configuration**
   - Ajouter `backendUrl` et `useBackend: true`
   - Configurer les endpoints backend

2. **Modification des méthodes**
   - Remplacer `startAuthFlow` par la version backend
   - Ajouter les nouvelles méthodes de vérification

3. **Tests**
   - Vérifier l'authentification complète
   - Tester la déconnexion
   - Valider la gestion des erreurs

### Code de migration

```javascript
// Ancien code
const authUrl = bjpass.buildAuthorizationUrl();

// Nouveau code
const { authUrl } = await bjpass.getBackendAuthUrl();
```

## Prochaines étapes

- [Exemples](examples.md)
- [Troubleshooting](troubleshooting.md)
- [API Reference](api-reference.md)

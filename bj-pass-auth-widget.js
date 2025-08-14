/**
 * bj-pass Authentication Widget
 * Widget d'authentification OAuth 2.0/OpenID Connect moderne et sécurisé
 * @version 1.0.0
 * @author yowedjamal
 * @license MIT
 */


// =======================
// Configuration Manager
// =======================
class ConfigManager {
  constructor(userConfig = {}) {
    this.environments = {
      test: {
        baseUrl: "https://test-tx-pki.gouv.bj",
        defaultAuthServer: "main-as",
      },
      production: {
        baseUrl: "https://tx-pki.gouv.bj",
        defaultAuthServer: "main-as",
      },
    };
    

    this.defaultConfig = {
      environment: "test",
      clientId: "",
      authServer: "",
      scope: "openid profile",
      redirectUri: "http://127.0.0.1:5500/examples/redirect.html",
      // redirectUri: "https://bj-pass.vercel.app/redirect.html",
      pkce: true,
      verifyAccessToken: false,
      tokenVerificationScopes: ["urn:safelayer:eidas:oauth:token:introspect"],
      beUrl: "",
      beBearer: "",
      header:{
        
      },
      ui: {
        showEnvSelector: true,
        container: "#bjpass-auth-container",
        language: "fr",
        primaryColor: "#0066cc",
        theme: "default"
      },
    };

    this.config = { ...this.defaultConfig, ...userConfig };
    this.resolvedConfig = this.applyEnvironmentConfig();
  }

  applyEnvironmentConfig() {
    const env = this.config.environment || "test";
    const envConfig = this.environments[env] || this.environments.test;

    return {
      ...this.config,
      baseUrl: envConfig.baseUrl,
      authServer: this.config.authServer || envConfig.defaultAuthServer,
    };
  }

  updateConfig(newConfig) {
    this.config = { ...this.config, ...newConfig };
    this.resolvedConfig = this.applyEnvironmentConfig();
  }

  get() {
    return this.resolvedConfig;
  }

  getEnvironments() {
    return Object.keys(this.environments);
  }
}

// =======================
// Crypto Utils
// =======================
class CryptoUtils {
  static generateRandomString(length) {
    const array = new Uint8Array(length);
    window.crypto.getRandomValues(array);
    return Array.from(array, (b) => b.toString(16).padStart(2, "0")).join("");
  }

  static async generateCodeChallenge(codeVerifier) {
    const encoder = new TextEncoder();
    const data = encoder.encode(codeVerifier);
    const digest = await window.crypto.subtle.digest("SHA-256", data);

    return btoa(String.fromCharCode(...new Uint8Array(digest)))
      .replace(/\+/g, "-")
      .replace(/\//g, "_")
      .replace(/=+$/, "");
  }

  static base64UrlToArrayBuffer(base64Url) {
    const padding = "=".repeat((4 - (base64Url.length % 4)) % 4);
    const base64 = base64Url.replace(/-/g, "+").replace(/_/g, "/") + padding;
    const rawData = atob(base64);
    const output = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      output[i] = rawData.charCodeAt(i);
    }
    return output;
  }

  static checkBrowserCompatibility() {
    return !!(window.crypto && window.crypto.subtle);
  }
}

// =======================
// Session Manager
// =======================
class SessionManager {
  static setItem(key, value) {
    sessionStorage.setItem(`tx_auth_${key}`, value);
  }

  static getItem(key) {
    return sessionStorage.getItem(`tx_auth_${key}`);
  }

  static removeItem(key) {
    sessionStorage.removeItem(`tx_auth_${key}`);
  }

  static clear() {
    const keys = ["state", "code_verifier", "nonce", "success"];
    keys.forEach(key => this.removeItem(key));
  }

  static generateAndStoreAuthData(scope) {
    const state = CryptoUtils.generateRandomString(32);
    const nonce = CryptoUtils.generateRandomString(32);
    const codeVerifier = CryptoUtils.generateRandomString(64);

    this.setItem("state", state);
    this.setItem("code_verifier", codeVerifier);
    
    if (scope.includes("openid")) {
      this.setItem("nonce", nonce);
    }

    return { state, nonce, codeVerifier };
  }
}

// =======================
// OAuth URL Builder
// =======================
class OAuthUrlBuilder {
  constructor(config) {
    this.config = config;
  }

  async buildAuthorizationUrl(authData) {
    const { state, nonce, codeVerifier } = authData;
    const codeChallenge = await CryptoUtils.generateCodeChallenge(codeVerifier);

    const authUrl = new URL(
      `${this.config.baseUrl}/trustedx-authserver/oauth`
    );

    if (this.config.authServer) {
      authUrl.pathname += `/${encodeURIComponent(this.config.authServer)}`;
    }

    const params = new URLSearchParams({
      response_type: "code",
      client_id: this.config.clientId,
      redirect_uri: this.config.redirectUri,
      scope: this.config.scope.split("+").join(" "),
      state,
      code_challenge: codeChallenge,
      code_challenge_method: "S256",
      prompt: "login",
    });

    if (this.config.scope.includes("openid") && nonce) {
      params.set("nonce", nonce);
    }

    authUrl.search = params.toString();
    return authUrl.toString();
  }

  buildTokenUrl() {
    return `${this.config.baseUrl}/trustedx-authserver/oauth/${encodeURIComponent(
      this.config.authServer
    )}/token`;
  }

  buildJWKSUrl() {
    return `${this.config.baseUrl}/trustedx-authserver/oauth/keys`;
  }

  buildIntrospectionUrl() {
    return `${this.config.baseUrl}/trustedx-authserver/oauth/token/verify`;
  }
}

// =======================
// Token Validator
// =======================
class TokenValidator {
  constructor(config, urlBuilder) {
    this.config = config;
    this.urlBuilder = urlBuilder;
  }

  async validateIdToken(idToken) {
    try {
      const [header, payload] = idToken.split(".");
      const decodedHeader = JSON.parse(atob(header));
      const decodedPayload = JSON.parse(atob(payload));

      // Basic validations
      const now = Math.floor(Date.now() / 1000);
      const savedNonce = SessionManager.getItem("nonce");

      if (decodedPayload.exp < now) {
        throw new Error("Token expired");
      }

      if (decodedPayload.nonce !== savedNonce) {
        throw new Error("Invalid nonce");
      }

      if (decodedPayload.aud !== this.config.clientId) {
        throw new Error("Invalid audience");
      }

      // Signature validation
      const jwks = await this.fetchJWKS();
      const publicKey = jwks.keys.find((key) => key.kid === decodedHeader.kid);

      if (!publicKey) {
        throw new Error("No matching public key found");
      }

      const isValid = await this.verifySignature(idToken, publicKey);
      if (!isValid) {
        throw new Error("Invalid signature");
      }

      return decodedPayload;
    } catch (error) {
      console.error("ID Token validation failed:", error);
      throw new Error("Invalid ID Token");
    }
  }

  async verifyAccessToken(accessToken) {
    try {
      const response = await fetch(this.urlBuilder.buildIntrospectionUrl(), {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          Authorization: `Bearer ${accessToken}`,
        },
        body: new URLSearchParams({ token: accessToken }),
      });

      const data = await response.json();

      if (!response.ok || !data.active) {
        throw new Error("Token verification failed");
      }

      return data;
    } catch (error) {
      console.error("Token verification error:", error);
      throw error;
    }
  }

  async fetchJWKS() {
    const response = await fetch(this.urlBuilder.buildJWKSUrl());
    if (!response.ok) {
      throw new Error("Failed to fetch JWKS");
    }
    return await response.json();
  }

  async verifySignature(token, jwk) {
    try {
      const key = await window.crypto.subtle.importKey(
        "jwk",
        jwk,
        { name: "RSASSA-PKCS1-v1_5", hash: { name: "SHA-256" } },
        false,
        ["verify"]
      );

      const [header, payload, signature] = token.split(".");
      const signatureData = CryptoUtils.base64UrlToArrayBuffer(signature);
      const data = new TextEncoder().encode(`${header}.${payload}`);

      return await window.crypto.subtle.verify(
        "RSASSA-PKCS1-v1_5",
        key,
        signatureData,
        data
      );
    } catch (error) {
      console.error("Signature verification error:", error);
      return false;
    }
  }
}

// =======================
// UI Component Base Class
// =======================
class UIComponent {
  constructor(container) {
    this.container = container;
    this.element = null;
    this.eventListeners = new Map();
  }

  createElement(tag, className = '', innerHTML = '') {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (innerHTML) element.innerHTML = innerHTML;
    return element;
  }

  addEventListeners(element, events) {
    Object.entries(events).forEach(([event, handler]) => {
      element.addEventListener(event, handler);
      
      // Store for cleanup
      if (!this.eventListeners.has(element)) {
        this.eventListeners.set(element, []);
      }
      this.eventListeners.get(element).push({ event, handler });
    });
  }

  removeEventListeners() {
    this.eventListeners.forEach((listeners, element) => {
      listeners.forEach(({ event, handler }) => {
        element.removeEventListener(event, handler);
      });
    });
    this.eventListeners.clear();
  }

  destroy() {
    this.removeEventListeners();
    if (this.element && this.element.parentNode) {
      this.element.parentNode.removeChild(this.element);
    }
  }

  render() {
    throw new Error('render() must be implemented by subclass');
  }

  show() {
    if (this.element) this.element.style.display = 'block';
  }

  hide() {
    if (this.element) this.element.style.display = 'none';
  }
}

// =======================
// UI Components
// =======================
class EnvironmentSelector extends UIComponent {
  constructor(container, config, onEnvironmentChange) {
    super(container);
    this.config = config;
    this.onEnvironmentChange = onEnvironmentChange;
    this.render();
  }

  render() {
    if (!this.config.ui.showEnvSelector) return;

    const envOptions = this.config.getEnvironments()
      .map(env => `
        <label>
          <input type="radio" name="env" value="${env}" ${env === this.config.get().environment ? 'checked' : ''}>
          ${env.charAt(0).toUpperCase() + env.slice(1)}
        </label>
      `).join('');

    this.element = this.createElement('div', 'bjpass-env-selector', `
      <div class="env-selector-label">Environment:</div>
      ${envOptions}
    `);

    const radioInputs = this.element.querySelectorAll('input[name="env"]');
    radioInputs.forEach(radio => {
      this.addEventListeners(radio, {
        change: (e) => this.onEnvironmentChange(e.target.value)
      });
    });

    this.container.appendChild(this.element);
  }
}

class LoginButton extends UIComponent {
  constructor(container, config, onLogin) {
    super(container);
    this.config = config;
    this.onLogin = onLogin;
    this.render();
  }

  render() {
    this.element = this.createElement('button', 'bjpass-login-btn', 'Se connecter');
    this.element.type = 'button';
    this.element.style.cssText = `
      width: 100%;
      padding: 12px 24px;
      background-color: ${this.config.ui.primaryColor};
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.2s;
    `;

    this.addEventListeners(this.element, {
      click: this.onLogin,
      mouseover: () => {
        this.element.style.opacity = '0.9';
      },
      mouseout: () => {
        this.element.style.opacity = '1';
      }
    });

    this.container.appendChild(this.element);
  }

  setLoading(isLoading) {
    this.element.disabled = isLoading;
    this.element.innerHTML = isLoading ? 'Connexion...' : 'Se connecter';
  }
}

class LoadingSpinner extends UIComponent {
  constructor(container) {
    super(container);
    this.render();
  }

  render() {
    this.element = this.createElement('div', 'bjpass-loading', `
      <div class="spinner"></div>
      <div class="loading-text">Authentification en cours...</div>
    `);
    
    this.element.style.cssText = `
      display: none;
      text-align: center;
      padding: 20px;
    `;

    const spinner = this.element.querySelector('.spinner');
    spinner.style.cssText = `
      border: 3px solid #f3f3f3;
      border-radius: 50%;
      border-top: 3px solid #0066cc;
      width: 30px;
      height: 30px;
      animation: spin 1s linear infinite;
      margin: 0 auto 10px;
    `;

    // Add CSS animation
    if (!document.getElementById('bjpass-spinner-styles')) {
      const style = document.createElement('style');
      style.id = 'bjpass-spinner-styles';
      style.textContent = `
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
      `;
      document.head.appendChild(style);
    }

    this.container.appendChild(this.element);
  }
}

class ErrorDisplay extends UIComponent {
  constructor(container, config) {
    super(container);
    this.config = config;
    this.render();
  }

  render() {
    this.element = this.createElement('div', 'bjpass-error');
    this.element.style.cssText = `
      display: none;
      padding: 12px;
      margin: 10px 0;
      background-color: #fee;
      border: 1px solid #fcc;
      border-radius: 4px;
      color: #c33;
      font-size: 14px;
    `;

    this.container.appendChild(this.element);
  }

  showError(message) {
    this.element.textContent = message;
    this.show();
  }

  clearError() {
    this.element.textContent = '';
    this.hide();
  }
}

// =======================
// UI Manager
// =======================
class UIManager {
  constructor(config) {
    this.config = config;
    this.container = null;
    this.components = {};
    this.state = {
      isLoading: false,
      error: null
    };
  }

  initialize() {
    this.container = document.querySelector(this.config.ui.container);
    if (!this.container) {
      throw new Error(`Container ${this.config.ui.container} not found`);
    }

    this.createMainContainer();
    this.initializeComponents();
  }

  createMainContainer() {
    this.mainElement = document.createElement('div');
    this.mainElement.className = 'bjpass-widget';
    this.mainElement.style.cssText = `
      max-width: 400px;
      margin: 0 auto;
      padding: 20px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    `;

    const title = document.createElement('h3');
    title.textContent = 'Authentification BjPass';
    title.style.cssText = `
      color: ${this.config.ui.primaryColor};
      text-align: center;
      margin: 0 0 20px 0;
    `;

    this.mainElement.appendChild(title);
    this.container.appendChild(this.mainElement);
  }

  initializeComponents() {
    // Environment selector
    this.components.envSelector = new EnvironmentSelector(
      this.mainElement, 
      this.config, 
      this.onEnvironmentChange.bind(this)
    );

    // Login button
    this.components.loginButton = new LoginButton(
      this.mainElement, 
      this.config, 
      this.onLoginClick.bind(this)
    );

    // Loading spinner
    this.components.loadingSpinner = new LoadingSpinner(this.mainElement);

    // Error display
    this.components.errorDisplay = new ErrorDisplay(this.mainElement, this.config);

    // Help text
    const helpText = document.createElement('p');
    helpText.textContent = 'Vous serez redirigé vers le service d\'authentification sécurisée';
    helpText.style.cssText = `
      text-align: center;
      margin: 15px 0 0 0;
      font-size: 0.9em;
      color: #666;
    `;
    this.mainElement.appendChild(helpText);
  }

  onEnvironmentChange(environment) {
    if (this.onEnvironmentChangeCallback) {
      this.onEnvironmentChangeCallback(environment);
    }
  }

  onLoginClick() {
    if (this.onLoginCallback) {
      this.onLoginCallback();
    }
  }

  setState(newState) {
    this.state = { ...this.state, ...newState };
    this.updateUI();
  }

  updateUI() {
    if (this.state.isLoading) {
      this.components.loginButton.setLoading(true);
      this.components.loadingSpinner.show();
      this.components.errorDisplay.clearError();
    } else {
      this.components.loginButton.setLoading(false);
      this.components.loadingSpinner.hide();
      
      if (this.state.error) {
        this.components.errorDisplay.showError(this.state.error);
      } else {
        this.components.errorDisplay.clearError();
      }
    }
  }

  destroy() {
    Object.values(this.components).forEach(component => {
      if (component.destroy) component.destroy();
    });
    
    if (this.mainElement && this.mainElement.parentNode) {
      this.mainElement.parentNode.removeChild(this.mainElement);
    }
  }

  // Event handler setters
  setOnEnvironmentChange(callback) {
    this.onEnvironmentChangeCallback = callback;
  }

  setOnLogin(callback) {
    this.onLoginCallback = callback;
  }
}

// =======================
// Backend Client
// =======================
class BackendClient {
  constructor(config) {
    this.config = config;
  }

  async exchangeCode(code, state) {    
    const response = await fetch(this.config.beUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...this.config.header
      },
      body: JSON.stringify({
        code,
        state,
        redirect_uri: this.config.redirectUri,
        code_verifier: SessionManager.getItem("code_verifier"),
      }),
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.message || "Échec de l'authentification");
    }

    return await response.json();
  }

  async exchangeCodeDirect(code, state, urlBuilder) {
    const codeVerifier = SessionManager.getItem("code_verifier");
    if (!codeVerifier) {
      throw new Error("Missing code verifier");
    }

    const response = await fetch(urlBuilder.buildTokenUrl(), {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        grant_type: "authorization_code",
        code,
        redirect_uri: this.config.redirectUri,
        client_id: this.config.clientId,
        code_verifier: codeVerifier,
      }),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error_description || "Failed to obtain token");
    }

    return data;
  }
}

// =======================
// Popup Manager
// =======================
class PopupManager {
  constructor() {
    this.popup = null;
    this.checkInterval = null;
  }

  open(url, onClose) {
    const width = 500;
    const height = 600;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;

    this.popup = window.open(
      url,
      "BjPassAuth",
      `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes`
    );

    // Check if popup is closed
    this.checkInterval = setInterval(() => {
      if (this.popup && this.popup.closed) {
        this.close();
        if (onClose) onClose();
      }
    }, 500);
  }

  close() {
    if (this.checkInterval) {
      clearInterval(this.checkInterval);
      this.checkInterval = null;
    }
    
    if (this.popup && !this.popup.closed) {
      this.popup.close();
    }
    
    this.popup = null;
  }

  isOpen() {
    return this.popup && !this.popup.closed;
  }
}

// =======================
// Error Handler
// =======================
class ErrorHandler {
  static getErrorMessage(error, description) {
    let errorMessage = "Erreur d'authentification";

    const errorMessages = {
      invalid_token: "Token invalide ou expiré",
      unsuported_grant_type_error: "Type de subvention OAuth invalide",
      insufficient_scope: "Permissions insuffisantes",
      invalid_grant: "Code d'autorisation invalide ou expiré",
      access_denied: "Authentification annulée",
      invalid_request: "Requête invalide",
      invalid_scope: "Permissions invalides",
      server_error: "Erreur du serveur",
      popup_closed: "La fenêtre d'authentification a été fermée",
      browser_error: "Navigateur non supporté",
      backend_error: "Erreur du backend",
      token_error: "Erreur de validation du token",
      token_exchange_error:"Erreur lors de l'échange"
    };

    if (errorMessages[error]) {
      errorMessage = errorMessages[error];
    }

    if (description) {
      errorMessage += `: ${description}`;
    }

    return errorMessage;
  }
}

// =======================
// Main Widget Class
// =======================
class BjPassAuthWidget {
  constructor(config = {}) {
    // Initialize modules
    this.configManager = new ConfigManager(config);
    this.uiManager = new UIManager(this.configManager.get());
    this.urlBuilder = new OAuthUrlBuilder(this.configManager.get());
    this.tokenValidator = new TokenValidator(this.configManager.get(), this.urlBuilder);
    this.backendClient = new BackendClient(this.configManager.get());
    this.popupManager = new PopupManager();

    // Check browser compatibility
    if (!CryptoUtils.checkBrowserCompatibility()) {
      throw new Error("Browser not supported. Please use a modern browser.");
    }

    // Initialize UI
    this.initialize();

    // Setup global message listener
    this.setupMessageListener();
  }

  initialize() {
    this.uiManager.initialize();
    this.setupEventHandlers();
  }

  setupEventHandlers() {
    this.uiManager.setOnEnvironmentChange((environment) => {
      this.configManager.updateConfig({ environment });
      this.urlBuilder = new OAuthUrlBuilder(this.configManager.get());
      this.tokenValidator = new TokenValidator(this.configManager.get(), this.urlBuilder);
      this.backendClient = new BackendClient(this.configManager.get());
    });

    this.uiManager.setOnLogin(() => {
      this.startAuthFlow();
    });
  }

  setupMessageListener() {
    window.addEventListener("message", (event) => {
      if (event.data.type === "bjpass-auth-response") {
        this.handlePopupResponse(event.data.query);
      }
    });
  }

  async startAuthFlow() {
    try {
      this.uiManager.setState({ isLoading: true, error: null });

      const config = this.configManager.get();
      const authData = SessionManager.generateAndStoreAuthData(config.scope);
      const authUrl = await this.urlBuilder.buildAuthorizationUrl(authData);

      console.log("Authorization URL:", authUrl);

      this.popupManager.open(authUrl, () => {
        this.uiManager.setState({ isLoading: false });
        if (!SessionManager.getItem("success")) {
          this.handleError("popup_closed", "La fenêtre d'authentification a été fermée");
        }
      });

    } catch (error) {
      this.handleError("auth_flow_error", error.message);
    }
  }

  async handlePopupResponse(queryParams) {
    try {
      const params = new URLSearchParams(queryParams);
      const code = params.get("code");
      const state = params.get("state");
      const error = params.get("error");

      if (error) {
        this.handleError(error, params.get("error_description"));
        return;
      }

      if (!code || !state) {
        this.handleError("invalid_response", "Code ou state manquant");
        return;
      }

      // Validate state
      const savedState = SessionManager.getItem("state");
      if (state !== savedState) {
        this.handleError("invalid_state", "Paramètre state invalide");
        return;
      }

      SessionManager.setItem("success", "true");
      this.popupManager.close();

      // Exchange code for tokens
      await this.exchangeCodeForTokens(code, state);

    } catch (error) {
      this.handleError("callback_error", error.message);
    }
  }

  async exchangeCodeForTokens(code, state) {
    try {
      const config = this.configManager.get();
      let tokenData;

      // Use backend or direct exchange based on configuration
      if (config.beUrl) {
        tokenData = await this.backendClient.exchangeCode(code, state);
      } else {
        this.handleError("unsuported_grant_type_error", "Type de subvention OAuth non encore pris en charge.");
        return
      }

      // Validate tokens
      await this.validateTokens(tokenData.data);

      // Success
      SessionManager.clear();
      this.uiManager.setState({ isLoading: false, error: null });

      if (config.onSuccess) {
        config.onSuccess(tokenData);
      }

    } catch (error) {
      this.handleError("token_exchange_error", error.message);
    }
  }

  async validateTokens(tokenData) {
    const config = this.configManager.get();

    // Validate ID token if present
    if (tokenData.id_token && config.scope.includes("openid")) {
      await this.tokenValidator.validateIdToken(tokenData.id_token);
    }

    // Verify access token if requested
    if (config.verifyAccessToken && tokenData.access_token) {
      await this.tokenValidator.verifyAccessToken(tokenData.access_token);
    }
  }

  handleError(errorCode, description) {
    console.error("Auth error:", errorCode, description);
    
    const errorMessage = ErrorHandler.getErrorMessage(errorCode, description);
    
    this.uiManager.setState({
      isLoading: false,
      error: errorMessage
    });

    const config = this.configManager.get();
    if (config.onError) {
      config.onError({ error: errorCode, error_description: description });
    }
  }

  // Public API methods
  destroy() {
    this.popupManager.close();
    this.uiManager.destroy();
    SessionManager.clear();
  }

  refresh() {
    this.uiManager.destroy();
    this.initialize();
  }

  getConfig() {
    return this.configManager.get();
  }

  updateConfig(newConfig) {
    this.configManager.updateConfig(newConfig);
    // Refresh components that depend on config
    this.urlBuilder = new OAuthUrlBuilder(this.configManager.get());
    this.tokenValidator = new TokenValidator(this.configManager.get(), this.urlBuilder);
    this.backendClient = new BackendClient(this.configManager.get());
  }
}

// =======================
// Auto-initialization and Export
// =======================
if (typeof window !== "undefined") {
  window.BjPassAuthWidget = BjPassAuthWidget;
  window.BjPassComponents = {
    ConfigManager,
    CryptoUtils,
    SessionManager,
    UIManager,
    TokenValidator,
    BackendClient,
    PopupManager,
    ErrorHandler
  };
}

// Auto-initialization
document.addEventListener("DOMContentLoaded", () => {
  const containers = document.querySelectorAll("[data-bjpass-widget]");

  containers.forEach((container) => {
    try {
      const config = container.dataset.bjpassWidget
        ? JSON.parse(container.dataset.bjpassWidget)
        : {};

      config.ui = { ...config.ui, container: `#${container.id}` };
      new BjPassAuthWidget(config);
    } catch (error) {
      console.error("Failed to initialize BjPass widget:", error);
    }
  });
});

// =======================
// Widget Factory
// =======================
class BjPassWidgetFactory {
  static create(config = {}) {
    return new BjPassAuthWidget(config);
  }

  static createMultiple(configs) {
    return configs.map(config => new BjPassAuthWidget(config));
  }

  static createWithTheme(theme, config = {}) {
    const themes = {
      default: {
        primaryColor: "#0066cc",
        backgroundColor: "#ffffff",
        borderColor: "#ddd"
      },
      dark: {
        primaryColor: "#4da6ff",
        backgroundColor: "#2d2d2d",
        borderColor: "#555",
        textColor: "#ffffff"
      },
      modern: {
        primaryColor: "#6366f1",
        backgroundColor: "#fafafa",
        borderColor: "#e5e7eb"
      },
      minimal: {
        primaryColor: "#000000",
        backgroundColor: "#ffffff",
        borderColor: "transparent"
      }
    };

    const themeConfig = themes[theme] || themes.default;
    return new BjPassAuthWidget({
      ...config,
      ui: {
        ...config.ui,
        theme,
        ...themeConfig
      }
    });
  }
}

// =======================
// Plugin System
// =======================
class PluginManager {
  constructor(widget) {
    this.widget = widget;
    this.plugins = new Map();
    this.hooks = new Map();
  }

  register(name, plugin) {
    if (typeof plugin.init === 'function') {
      this.plugins.set(name, plugin);
      plugin.init(this.widget);
    }
  }

  unregister(name) {
    const plugin = this.plugins.get(name);
    if (plugin && typeof plugin.destroy === 'function') {
      plugin.destroy();
    }
    this.plugins.delete(name);
  }

  addHook(hookName, callback) {
    if (!this.hooks.has(hookName)) {
      this.hooks.set(hookName, []);
    }
    this.hooks.get(hookName).push(callback);
  }

  executeHook(hookName, ...args) {
    const callbacks = this.hooks.get(hookName) || [];
    callbacks.forEach(callback => {
      try {
        callback(...args);
      } catch (error) {
        console.error(`Hook ${hookName} error:`, error);
      }
    });
  }

  getPlugin(name) {
    return this.plugins.get(name);
  }

  listPlugins() {
    return Array.from(this.plugins.keys());
  }
}

// =======================
// Built-in Plugins
// =======================

// Analytics Plugin
class AnalyticsPlugin {
  init(widget) {
    this.widget = widget;
    this.setupAnalytics();
  }

  setupAnalytics() {
    const originalStartAuth = this.widget.startAuthFlow.bind(this.widget);
    const originalHandleSuccess = this.widget.exchangeCodeForTokens.bind(this.widget);
    // const originalHandleError = this.widget.handleError.bind(this.widget);

    this.widget.startAuthFlow = async () => {
      this.track('auth_started');
      return originalStartAuth();
    };

    this.widget.exchangeCodeForTokens = async (code, state) => {
      try {
        const result = await originalHandleSuccess(code, state);
        this.track('auth_success');
        return result;
      } catch (error) {
        this.track('auth_error', { error: error.message });
        throw error;
      }
    };
  }

  track(event, data = {}) {
    console.log('Analytics:', event, data);
    
    // Send to analytics service
    if (typeof gtag !== 'undefined') {
    //   gtag('event', event, {
    //     event_category: 'bjpass_auth',
    //     ...data
    //   });
    }

    if (typeof window.analytics !== 'undefined') {
      window.analytics.track(event, data);
    }
  }

  destroy() {
    // Cleanup if needed
  }
}

// Debug Plugin
class DebugPlugin {
  init(widget) {
    this.widget = widget;
    this.originalConsoleLog = console.log;
    this.setupDebugMode();
  }

  setupDebugMode() {
    if (this.widget.getConfig().debug) {
      this.addDebugPanel();
      this.interceptMethods();
    }
  }

  addDebugPanel() {
    const debugPanel = document.createElement('div');
    debugPanel.id = 'bjpass-debug-panel';
    debugPanel.style.cssText = `
      position: fixed;
      top: 10px;
      right: 10px;
      width: 300px;
      max-height: 400px;
      background: rgba(0,0,0,0.9);
      color: #00ff00;
      font-family: monospace;
      font-size: 12px;
      padding: 10px;
      border-radius: 5px;
      overflow-y: auto;
      z-index: 10000;
      display: none;
    `;

    const header = document.createElement('div');
    header.innerHTML = `
      <strong>BjPass Debug Panel</strong>
      <button onclick="this.parentElement.parentElement.style.display='none'" 
              style="float:right;background:none;border:none;color:#fff;cursor:pointer;">×</button>
    `;

    const logs = document.createElement('div');
    logs.id = 'bjpass-debug-logs';

    debugPanel.appendChild(header);
    debugPanel.appendChild(logs);
    document.body.appendChild(debugPanel);

    // Toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.textContent = '🐛';
    toggleBtn.style.cssText = `
      position: fixed;
      top: 10px;
      right: 10px;
      z-index: 10001;
      background: #000;
      color: #0f0;
      border: none;
      padding: 5px 10px;
      border-radius: 3px;
      cursor: pointer;
    `;
    toggleBtn.onclick = () => {
      debugPanel.style.display = debugPanel.style.display === 'none' ? 'block' : 'none';
    };
    document.body.appendChild(toggleBtn);

    this.debugPanel = debugPanel;
    this.debugLogs = logs;
  }

  interceptMethods() {
    const methods = ['startAuthFlow', 'handlePopupResponse', 'exchangeCodeForTokens', 'handleError'];
    
    methods.forEach(methodName => {
      const originalMethod = this.widget[methodName].bind(this.widget);
      this.widget[methodName] = (...args) => {
        this.log(`${methodName} called with:`, args);
        const result = originalMethod(...args);
        if (result instanceof Promise) {
          return result.then(res => {
            this.log(`${methodName} resolved:`, res);
            return res;
          }).catch(err => {
            this.log(`${methodName} rejected:`, err);
            throw err;
          });
        }
        this.log(`${methodName} returned:`, result);
        return result;
      };
    });
  }

  log(message, data) {
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = document.createElement('div');
    logEntry.style.marginBottom = '5px';
    logEntry.innerHTML = `
      <span style="color:#888">[${timestamp}]</span> 
      ${message} 
      ${data ? `<pre style="margin:5px 0;color:#ff0;">${JSON.stringify(data, null, 2)}</pre>` : ''}
    `;
    
    if (this.debugLogs) {
      this.debugLogs.appendChild(logEntry);
      this.debugLogs.scrollTop = this.debugLogs.scrollHeight;
    }
    
    console.log(`[BjPass Debug] ${message}`, data);
  }

  destroy() {
    if (this.debugPanel) {
      document.body.removeChild(this.debugPanel);
    }
  }
}

// Retry Plugin
class RetryPlugin {
  init(widget) {
    this.widget = widget;
    this.maxRetries = widget.getConfig().maxRetries || 3;
    this.retryDelay = widget.getConfig().retryDelay || 1000;
    this.setupRetryLogic();
  }

  setupRetryLogic() {
    const originalExchangeCode = this.widget.exchangeCodeForTokens.bind(this.widget);
    
    this.widget.exchangeCodeForTokens = async (code, state, attempt = 1) => {
      try {
        return await originalExchangeCode(code, state);
      } catch (error) {
        if (attempt < this.maxRetries && this.shouldRetry(error)) {
          console.log(`Retry attempt ${attempt}/${this.maxRetries} after ${this.retryDelay}ms`);
          await this.delay(this.retryDelay * attempt);
          return this.widget.exchangeCodeForTokens(code, state, attempt + 1);
        }
        throw error;
      }
    };
  }

  shouldRetry(error) {
    const retryableErrors = ['network', 'timeout', 'server_error', '503', '502', '500'];
    return retryableErrors.some(errType => 
      error.message.toLowerCase().includes(errType) || 
      error.code === errType
    );
  }

  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  destroy() {
    // Cleanup if needed
  }
}

// Enhanced Widget with Plugin Support
class EnhancedBjPassAuthWidget extends BjPassAuthWidget {
  constructor(config = {}) {
    super(config);
    this.pluginManager = new PluginManager(this);
    this.setupBuiltinPlugins();
  }

  setupBuiltinPlugins() {
    const config = this.getConfig();
    
    // Auto-enable plugins based on config
    if (config.analytics) {
      this.pluginManager.register('analytics', new AnalyticsPlugin());
    }
    
    if (config.debug) {
      this.pluginManager.register('debug', new DebugPlugin());
    }
    
    if (config.maxRetries > 0) {
      this.pluginManager.register('retry', new RetryPlugin());
    }
  }

  // Plugin API
  use(name, plugin) {
    this.pluginManager.register(name, plugin);
    return this;
  }

  unuse(name) {
    this.pluginManager.unregister(name);
    return this;
  }

  getPlugin(name) {
    return this.pluginManager.getPlugin(name);
  }

  // Hook API
  addHook(hookName, callback) {
    this.pluginManager.addHook(hookName, callback);
    return this;
  }

  executeHook(hookName, ...args) {
    this.pluginManager.executeHook(hookName, ...args);
  }

  // Enhanced methods with hooks
  async startAuthFlow() {
    this.executeHook('beforeAuthStart');
    try {
      const result = await super.startAuthFlow();
      this.executeHook('afterAuthStart', result);
      return result;
    } catch (error) {
      this.executeHook('authStartError', error);
      throw error;
    }
  }

  async exchangeCodeForTokens(code, state) {
    this.executeHook('beforeTokenExchange', code, state);
    try {
      const result = await super.exchangeCodeForTokens(code, state);
      this.executeHook('afterTokenExchange', result);
      return result;
    } catch (error) {
      this.executeHook('tokenExchangeError', error);
      throw error;
    }
  }

  destroy() {
    this.executeHook('beforeDestroy');
    this.pluginManager.listPlugins().forEach(name => {
      this.pluginManager.unregister(name);
    });
    super.destroy();
    this.executeHook('afterDestroy');
  }
}

// =======================
// Exports and Global Setup
// =======================
if (typeof window !== "undefined") {
  // Main exports
  window.BjPassAuthWidget = BjPassAuthWidget;
  window.EnhancedBjPassAuthWidget = EnhancedBjPassAuthWidget;
  window.BjPassWidgetFactory = BjPassWidgetFactory;
  
  // Component exports
  window.BjPassComponents = {
    ConfigManager,
    CryptoUtils,
    SessionManager,
    UIManager,
    TokenValidator,
    BackendClient,
    PopupManager,
    ErrorHandler,
    PluginManager
  };

  // Plugin exports
  window.BjPassPlugins = {
    AnalyticsPlugin,
    DebugPlugin,
    RetryPlugin
  };

  // Utility function for quick setup
  window.createBjPassWidget = (config) => {
    return BjPassWidgetFactory.create(config);
  };
}

// Export par défaut pour ES modules et UMD
if (typeof module !== 'undefined' && module.exports) {
  module.exports = BjPassAuthWidget;
} else if (typeof define === 'function' && define.amd) {
  define(() => BjPassAuthWidget);
} else if (typeof exports !== 'undefined') {
  exports.BjPassAuthWidget = BjPassAuthWidget;
}

// Export par défaut pour ES modules
export default BjPassAuthWidget;
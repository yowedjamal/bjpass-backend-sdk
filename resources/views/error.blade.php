<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur d'authentification - BjPass</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: white;
        }
        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 1rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 500px;
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .error-code {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.9rem;
        }
        .error-message {
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            text-align: left;
        }
        .retry-button {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1rem;
            margin: 1rem 0.5rem;
            transition: all 0.3s ease;
        }
        .retry-button:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }
        .close-button {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1rem;
            margin: 1rem 0.5rem;
            transition: all 0.3s ease;
        }
        .close-button:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">❌</div>
        <h1>Erreur d'authentification</h1>
        
        <div class="error-code">
            Code d'erreur: {{ $error }}
        </div>
        
        <div class="error-message">
            <strong>Description :</strong><br>
            {{ $message }}
        </div>

        <div>
            <button class="retry-button" onclick="retryAuthentication()">
                Réessayer
            </button>
            <button class="close-button" onclick="closeWindow()">
                Fermer
            </button>
        </div>
    </div>

    <script>
        (function() {
            'use strict';

            // Configuration
            const frontendOrigin = '{{ config("bjpass.frontend_origin", "*") }}';
            const errorData = {
                type: 'bjpass-auth-response',
                status: 'error',
                error: '{{ $error }}',
                message: '{{ $message }}',
                timestamp: new Date().toISOString()
            };

            // Function to send error message to parent window
            function sendErrorToParent() {
                try {
                    if (window.opener && !window.opener.closed) {
                        window.opener.postMessage(errorData, frontendOrigin);
                        console.log('Error message sent to opener:', errorData);
                    } else if (window.parent && window.parent !== window) {
                        window.parent.postMessage(errorData, frontendOrigin);
                        console.log('Error message sent to parent:', errorData);
                    } else {
                        console.warn('No parent window or opener found');
                    }
                } catch (error) {
                    console.error('Failed to send error message:', error);
                }
            }

            // Send error message immediately
            sendErrorToParent();

            // Function to retry authentication
            window.retryAuthentication = function() {
                try {
                    // Redirect to start authentication
                    window.location.href = '{{ route("bjpass.start") }}';
                } catch (error) {
                    console.error('Failed to retry authentication:', error);
                    // Fallback: reload page
                    window.location.reload();
                }
            };

            // Function to close window
            window.closeWindow = function() {
                try {
                    if (window.opener && !window.opener.closed) {
                        window.close();
                    } else {
                        // Redirect to home if we can't close
                        window.location.href = '/';
                    }
                } catch (error) {
                    console.error('Failed to close window:', error);
                    window.location.href = '/';
                }
            };

            // Auto-close after 10 seconds if no action taken
            setTimeout(() => {
                if (window.location.href.includes('error')) {
                    closeWindow();
                }
            }, 10000);

        })();
    </script>
</body>
</html>

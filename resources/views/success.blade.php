<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentification réussie - BjPass</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }
        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .message {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .user-info {
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            text-align: left;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>Authentification réussie !</h1>
        <p class="message">Vous êtes maintenant connecté avec BjPass.</p>
        
        <div class="user-info">
            <strong>Informations utilisateur :</strong><br>
            <small>ID: {{ $user['sub'] ?? 'N/A' }}</small><br>
            <small>Nom: {{ $user['name'] ?? 'N/A' }}</small><br>
            <small>Email: {{ $user['email'] ?? 'N/A' }}</small>
        </div>

        <p>
            <span class="loading"></span>
            Communication avec l'application en cours...
        </p>
    </div>

    <script>
        (function() {
            'use strict';

            // Configuration
            const frontendOrigin = '{{ $frontend_origin }}';
            const userData = @json($user);
            const tokens = @json($tokens);
            
            const returnUrl = '{{ $return_url }}';

            // Function to send message to parent window
            function sendMessageToParent(data) {
                try {
                    if (window.opener && !window.opener.closed) {
                        // Send to popup opener
                        window.opener.postMessage(data, frontendOrigin);
                        console.log('Message sent to opener:', data);
                    } else if (window.parent && window.parent !== window) {
                        // Send to parent frame
                        window.parent.postMessage(data, frontendOrigin);
                        console.log('Message sent to parent:', data);
                    } else {
                        console.warn('No parent window or opener found');
                    }
                } catch (error) {
                    console.error('Failed to send message:', error);
                }
            }

            // Function to close window after successful communication
            function closeWindow() {
                setTimeout(() => {
                    try {
                        if (window.opener && !window.opener.closed) {
                            window.close();
                        } else {
                            // Redirect if we can't close
                            window.location.href = returnUrl;
                        }
                    } catch (error) {
                        console.error('Failed to close window:', error);
                        window.location.href = returnUrl;
                    }
                }, 5000);
            }

            // Send success message
            const successMessage = {
                type: 'bjpass-auth-response',
                status: 'success',
                user: userData,
                tokens,
                returnUrl: returnUrl,
                timestamp: new Date().toISOString()
            };

            sendMessageToParent(successMessage);

            // Close window after communication
            // closeWindow();

            // Fallback: redirect after timeout
            setTimeout(() => {
                if (window.location.href.includes('callback')) {
                    window.location.href = returnUrl;
                }
            }, 50000);

        })();
    </script>
</body>
</html>

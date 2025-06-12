document.addEventListener('DOMContentLoaded', function () {
    const appleSignInButton = document.getElementById('cls-apple-signin-button');

    if (appleSignInButton) {
        appleSignInButton.addEventListener('click', function (e) {
            e.preventDefault();

            if (typeof AppleID === 'undefined') {
                console.error('AppleID JS SDK not loaded.');
                alert('Sign in with Apple is currently unavailable. Please try again later.');
                return;
            }

            if (!cls_apple_auth_params || !cls_apple_auth_params.client_id || !cls_apple_auth_params.redirect_uri) {
                console.error('Apple Auth parameters not defined. Make sure cls_apple_auth_params is localized.');
                alert('Sign in with Apple configuration error. Please contact support.');
                return;
            }

            if (cls_apple_auth_params.client_id === 'YOUR_APPLE_SERVICE_ID') {
                console.warn('Apple Client ID is not configured.');
                alert('Sign in with Apple is not yet configured by the site administrator.');
                return;
            }

            AppleID.auth.init({
                clientId: cls_apple_auth_params.client_id, // Your Service ID
                scope: 'name email', // Request name and email
                redirectURI: cls_apple_auth_params.redirect_uri, // Your redirect URI
                state: cls_apple_auth_params.state || 'default_state', // CSRF protection, should be unique per request
                usePopup: cls_apple_auth_params.use_popup !== 'false', // true by default, or use 'false' for redirect
            });

            AppleID.auth.signIn()
                .then(response => {
                    // This block is usually not hit if usePopup is false and redirectURI is set,
                    // as Apple directly POSTs to the redirectURI.
                    // If usePopup is true, you might handle the response here,
                    // then send it to your server.
                    // For this implementation, we rely on the POST to CLS_APPLE_REDIRECT_URI.
                    console.log('Apple Sign In success (client-side, if usePopup=true):', response);
                })
                .catch(error => {
                    console.error('Apple Sign In error (client-side):', error);
                    // error: "popup_closed_by_user" or "access_denied" or other client-side errors
                    let userMessage = 'Sign in with Apple failed.';
                    if (error && error.error === 'popup_closed_by_user') {
                        userMessage = 'Sign in with Apple was cancelled.';
                    } else if (error && error.error === 'access_denied') {
                        userMessage = 'Access to Sign in with Apple was denied.';
                    }
                    alert(userMessage);
                });
        });
    }
});

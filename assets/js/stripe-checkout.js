document.addEventListener('DOMContentLoaded', function () {
    // Ensure Stripe.js is loaded and cls_stripe_params are available
    if (typeof Stripe === 'undefined' || typeof cls_stripe_params === 'undefined') {
        console.error('Stripe.js or cls_stripe_params not loaded. Stripe checkout cannot proceed.');
        // Optionally disable all subscribe buttons or show a general error message
        document.querySelectorAll('.cls-subscribe-button[data-stripe-price-id]').forEach(button => {
            // button.disabled = true;
            // button.textContent = 'Payment Error';
        });
        return;
    }

    if (cls_stripe_params.publishable_key === 'YOUR_STRIPE_PUBLISHABLE_KEY') {
        console.warn('Stripe Publishable Key is not configured. Stripe checkout will not work.');
        // Optionally disable buttons
        return;
    }

    const stripe = Stripe(cls_stripe_params.publishable_key);
    const subscribeButtons = document.querySelectorAll('.cls-subscribe-button[data-stripe-price-id]');

    subscribeButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();

            const stripePriceId = this.dataset.stripePriceId;
            const packageId = this.dataset.packageId;

            if (!stripePriceId) {
                alert('Stripe Price ID is missing for this package.');
                return;
            }

            // Show a loading state on the button
            this.textContent = 'Processing...';
            this.disabled = true;

            fetch(cls_stripe_params.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'cls_create_stripe_checkout_session',
                    nonce: cls_stripe_params.nonce,
                    stripe_price_id: stripePriceId,
                    package_id: packageId,
                })
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    // Redirect to Stripe Checkout
                    stripe.redirectToCheckout({ sessionId: response.data.sessionId })
                        .then(function (result) {
                            // If `redirectToCheckout` fails due to a browser policy or an error,
                            // display the localized error message to your customer.
                            if (result.error) {
                                alert(result.error.message);
                                // Restore button state
                                button.textContent = 'Subscribe';
                                button.disabled = false;
                            }
                        });
                } else {
                    // Handle errors (e.g., display error message)
                    alert('Error: ' + (response.data.message || 'Could not initiate Stripe checkout.'));
                    console.error('Stripe Checkout Error:', response);
                    // Restore button state
                    button.textContent = 'Subscribe';
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Request failed:', error);
                alert('An unexpected error occurred. Please try again.');
                // Restore button state
                button.textContent = 'Subscribe';
                button.disabled = false;
            });
        });
    });
});

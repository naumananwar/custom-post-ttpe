document.addEventListener('DOMContentLoaded', function () {
    if (typeof cls_paypal_params === 'undefined') {
        console.error('cls_paypal_params not loaded. PayPal checkout cannot proceed.');
        return;
    }

    const paypalSubscribeButtons = document.querySelectorAll('.cls-paypal-subscribe-button[data-paypal-plan-id]');

    paypalSubscribeButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();

            const paypalPlanId = this.dataset.paypalPlanId;
            const packageId = this.dataset.packageId;

            if (!paypalPlanId) {
                alert('PayPal Plan ID is missing for this package.');
                return;
            }

            // Show a loading state on the button
            this.textContent = 'Processing...';
            this.disabled = true;

            fetch(cls_paypal_params.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'cls_create_paypal_subscription',
                    nonce: cls_paypal_params.nonce,
                    paypal_plan_id: paypalPlanId,
                    package_id: packageId,
                })
            })
            .then(response => response.json())
            .then(response => {
                if (response.success && response.data.approve_url) {
                    // Redirect to PayPal for approval
                    window.location.href = response.data.approve_url;
                } else {
                    // Handle errors (e.g., display error message)
                    let errorMessage = 'Error: Could not initiate PayPal subscription.';
                    if (response.data && response.data.message) {
                        errorMessage = 'Error: ' + response.data.message;
                    } else if (response.data && response.data.raw_response) {
                        // For debugging, not ideal for production display
                        // errorMessage += ' Raw: ' + JSON.stringify(response.data.raw_response);
                        console.error('PayPal Checkout Error:', response.data.raw_response);
                    } else {
                        console.error('PayPal Checkout Error:', response);
                    }
                    alert(errorMessage);
                    // Restore button state
                    this.textContent = 'Subscribe with PayPal';
                    this.disabled = false;
                }
            })
            .catch(error => {
                console.error('Request failed:', error);
                alert('An unexpected error occurred. Please try again.');
                // Restore button state
                this.textContent = 'Subscribe with PayPal';
                this.disabled = false;
            });
        });
    });
});

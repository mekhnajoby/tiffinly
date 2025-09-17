
function selectPlan(plan) {
    // Check if the user is logged in
    // This is a simplified check. In a real application, you'd use a more robust method.
    const isLoggedIn = document.body.classList.contains('logged-in');

    if (isLoggedIn) {
        // If logged in, proceed to the subscription page
        window.location.href = `user/subscription.php?plan=${plan}`;
    } else {
        // If not logged in, redirect to the login page
        window.location.href = `login.php?plan=${plan}`;
    }
}

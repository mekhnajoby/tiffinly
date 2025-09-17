// forgot_password.js

// Toggle password visibility
const toggleButtons = document.querySelectorAll('.toggle-password');
toggleButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    const targetInput = document.getElementById(btn.dataset.target);
    if (targetInput.type === "password") {
      targetInput.type = "text";
      btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
      targetInput.type = "password";
      btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
  });
});

// Animate step transitions
const stepSections = document.querySelectorAll('.step-section');
function showStep(stepIndex) {
  stepSections.forEach((section, i) => {
    if (i === stepIndex) {
      section.classList.add('active');
    } else {
      section.classList.remove('active');
    }
  });
}

// Form validations
document.addEventListener('DOMContentLoaded', () => {
  const emailForm = document.getElementById('email-form');
  const securityForm = document.getElementById('security-form');
  const resetForm = document.getElementById('reset-form');

  // Step 1: Email validation
  emailForm?.addEventListener('submit', function (e) {
    const emailInput = document.getElementById('email');
    const email = emailInput.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (email === '' || !emailRegex.test(email)) {
      e.preventDefault();
      alert('Please enter a valid email address.');
    }
  });

  // Step 2: Security answer validation
  securityForm?.addEventListener('submit', function (e) {
    const answer = document.getElementById('security_answer').value.trim();
    if (answer === '') {
      e.preventDefault();
      alert('Please answer the security question.');
    }
  });

  // Step 3: Password reset validation
  resetForm?.addEventListener('submit', function (e) {
    const newPassword = document.getElementById('new_password').value.trim();
    const confirmPassword = document.getElementById('confirm_password').value.trim();

    if (newPassword.length < 6) {
      e.preventDefault();
      alert('Password must be at least 6 characters long.');
      return;
    }

    if (newPassword !== confirmPassword) {
      e.preventDefault();
      alert('Passwords do not match.');
    }
  });
});

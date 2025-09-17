// Password visibility toggle
function togglePassword() {
  const passwordInput = document.getElementById("password");
  const toggleIcon = document.getElementById("togglePasswordIcon");

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    toggleIcon.classList.remove("bi-eye");
    toggleIcon.classList.add("bi-eye-slash");
  } else {
    passwordInput.type = "password";
    toggleIcon.classList.remove("bi-eye-slash");
    toggleIcon.classList.add("bi-eye");
  }
}


// Combined validation
document.getElementById('loginForm').addEventListener('submit', function (e) {
  let valid = true;
  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value.trim();
  const role = document.getElementById("role").value;

  const error = document.getElementById("clientError");
  error.style.display = "none";
  error.innerHTML = "";

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  const disallowedDomains = ["tempmail.com", "mailinator.com"];

  if (!email || !emailRegex.test(email)) {
    error.innerHTML += "❌ Please enter a valid email.<br>";
    valid = false;
  } else {
    const domain = email.split('@')[1];
    if (disallowedDomains.includes(domain)) {
      error.innerHTML += "⚠️ Temporary email domains are not allowed.<br>";
      valid = false;
    }
  }

  if (!password || password.length < 8) {
    error.innerHTML += "🔒 Password must be at least 8 characters long.<br>";
    valid = false;
  } else {
    if (!/[A-Z]/.test(password)) {
      error.innerHTML += "🔠 Password must contain an uppercase letter.<br>";
      valid = false;
    }
    if (!/[0-9]/.test(password)) {
      error.innerHTML += "🔢 Password must contain a number.<br>";
      valid = false;
    }
  }

  if (!role || !["user", "admin", "delivery"].includes(role)) {
    error.innerHTML += "🧑 Please select a valid role.<br>";
    valid = false;
  }

  if (!valid) {
    error.style.display = "block";
    window.scrollTo({ top: 0, behavior: "smooth" });
    e.preventDefault();
  }
});

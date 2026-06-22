const form = document.getElementById("loginForm");
const btn = document.getElementById("loginBtn");

form.addEventListener("submit", async function(e) {
    e.preventDefault();

    btn.disabled = true;
    btn.textContent = "Logging in...";

    //API CALL
    try {

        document.getElementById("messageBox").innerHTML = "";
      const res = await fetch("../api/login.php", {
    method: "POST",

    headers: {
        "Content-Type": "application/json"
    },

    body: JSON.stringify({
        username: form.username.value,
        password: form.password.value
    })
});
        if (!res.ok) throw new Error("Server error");

        const data = await res.json();

        console.log("LOGIN RESPONSE:", data);

        if (data.success && data.access_token) {
            setAuthToken(data.access_token);
            if (data.role === "admin") {
                window.location.href = "admin.html";
            } else {
                window.location.href = "connections.html";
            }
        } else {
            showMessage(data.error || "Login failed", "danger");
        }

    } catch (err) {
        console.error(err);
        showMessage("Server error", "danger");
    }

    btn.disabled = false;
    btn.textContent = "Log In";
});


function showMessage(message, type = "danger") {
    const box = document.getElementById("messageBox");

    box.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    setTimeout(() => {
        box.innerHTML = "";
    }, 3000);
}



// TOGGLE PASSWORD 
const passwordInput = document.getElementById("password");
const toggleIcon = document.getElementById("togglePassword");

if (passwordInput && toggleIcon) {

    toggleIcon.addEventListener("click", () => {

        const isHidden = passwordInput.type === "password";
        passwordInput.type = isHidden ? "text" : "password";

        // icon
        toggleIcon.classList.toggle("bi-eye");
        toggleIcon.classList.toggle("bi-eye-slash");
    });
}


 // TOOLTIP //
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });
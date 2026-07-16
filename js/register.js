const form = document.getElementById("registerForm");
const btn = document.getElementById("registerBtn");

form.addEventListener("submit", async function(e) {
    e.preventDefault();

    const password = form.password.value;
    const password2 = form.password_2.value;

    // client validation
    if (password !== password2) {
        showMessage("Passwords do not match!", "danger");
        form.password_2.focus();
        return;
    }

    btn.disabled = true;
    btn.textContent = "Registering...";


    try {     // API FETCH //
       const res = await fetch("../api/register.php", {
    method: "POST",

    headers: {
        "Content-Type": "application/json"
    },

    body: JSON.stringify({
        username: form.username.value,
        email: form.email.value,
        password: form.password.value,
        password_2: form.password_2.value
    })
});

        const text = await res.text();

console.log(text);

if (!res.ok) {
    showMessage(text, "danger");
    return;
}

const data = JSON.parse(text);

        if (data.success) {

            // ΜΗΝΥΜΑ
            showMessage(
                "Registration successful! Check your email to verify your account.",
                "success"
            );

            form.reset();

            // redirect μετά από λίγο
            setTimeout(() => {
                window.location.href = "login.html";
            }, 4000);

        } else {
            showMessage(data.error || "Registration failed!", "danger");
        }

    } catch (err) {
        console.error(err);
        showMessage("Server error!", "danger");
    }

    btn.disabled = false;
    btn.textContent = "Register";
});


 // MESSAGE //
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
    }, 4000);
}


 // EYE ICON TOGGLE //
document.querySelectorAll(".toggle-password").forEach(icon => {
    icon.addEventListener("click", () => {
        const input = icon.parentElement.querySelector(".password-field");

        if (!input) return;

        const isHidden = input.type === "password";

        input.type = isHidden ? "text" : "password";

        icon.classList.toggle("bi-eye");
        icon.classList.toggle("bi-eye-slash");
    });
});





document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });

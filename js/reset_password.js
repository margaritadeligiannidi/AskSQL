/*  GET TOKEN FROM URL */
const token = new URLSearchParams(window.location.search).get("token");

if (!token) {
    document.getElementById("resetForm").innerHTML = "";
    showMessage("Invalid or missing token", "danger");
}

/*  SUBMIT  */
/* SUBMIT */
document.getElementById("resetForm").addEventListener("submit", async function(e){

    e.preventDefault();

    const p1 = document.getElementById("password1").value;
    const p2 = document.getElementById("password2").value;

    if (p1 !== p2) {

        showMessage(
            "Passwords do not match",
            "danger"
        );

        return;
    }

    if (p1.length < 8) {

        showMessage(
            "Password must be at least 8 characters",
            "danger"
        );

        return;
    }

    try {

        // API FETCH //
        const data = await apiFetch(
            "../api/reset_password.php",
            {
                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

                body: {
                    token: token,
                    password: p1
                }
            }
        );

        if (data.success) {

            showMessage(
                "Password updated! Redirecting...",
                "success"
            );

            setTimeout(() => {

                window.location.href = "login.html";

            }, 2000);

        } else {

            showMessage(
                data.error || "Something went wrong",
                "danger"
            );
        }

    } catch (err) {

        showMessage(
            "Server connection failed",
            "danger"
        );
    }
});

/*  MESSAGE UI  */
function showMessage(message, type) {

    const box = document.getElementById("messageBox");

    box.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}


 // EYE ICON TOGGLE //
document.querySelectorAll(".toggle-password").forEach(icon => {

    icon.addEventListener("click", () => {

        const wrapper = icon.closest(".position-relative");
        const input = wrapper.querySelector(".password-field");

        if (!input) return;

        const isHidden = input.type === "password";

        input.type = isHidden ? "text" : "password";

        icon.classList.toggle("bi-eye");
        icon.classList.toggle("bi-eye-slash");
    });

});


 // TOOLTIP //
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });

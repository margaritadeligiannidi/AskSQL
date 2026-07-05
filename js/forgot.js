
document.getElementById("forgotForm").addEventListener("submit", async function(e){

    e.preventDefault();

    const email = document.getElementById("email").value;

    try {

        // API FETCH //
        const data = await apiFetch("../api/forgot_password.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: {
                email: email
            }
        });

        if (data.success) {

            showMessage(
                data.message || "If an account with this email exists, a password reset link has been sent.",
                "success"
            );

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

/*  MESSAGE UI */
function showMessage(message, type) {

    const box = document.getElementById("messageBox");
    let icon = "bi-info-circle";

    if (type === "success") icon = "bi-check-circle";
    if (type === "danger") icon = "bi-x-circle";

    box.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show d-flex align-items-center">
            <i class="bi ${icon} me-2"></i>
            <div>${message}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    `;
}



 // TOOLTIP //
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });

// LOAD PROFILE
async function loadProfile() {
            // API FETCH //
    const data = await apiFetch("../api/profile.php", {
        method: "GET",
        headers: { "Content-Type": "application/json" }
    });

    if (!data || data.error) return;

    document.getElementById("Username").innerText = data.username || "-";
    document.getElementById("Email").innerText = data.email || "-";
    document.getElementById("Created").innerText = data.created_at || "-";
    document.getElementById("Role").innerText = (data.role || "-").toUpperCase();
}

loadProfile();


// LOAD USERNAME FOR NAVBAR
async function loadUsername() {
    const data = await apiFetch("../api/profile.php", {
        method: "GET",
        headers: { "Content-Type": "application/json" }
    });

    if (data && data.username) {
        document.getElementById("navbarUsername").innerText = data.username;
    }
}

loadUsername();


// CHANGE PASSWORD
async function change_password() {
    let password1 = document.getElementById("password1").value;
    let password2 = document.getElementById("password2").value;

    if (password1 !== password2) {
        showMessage("Passwords do not match!", "danger");
        return;
    }

    if (password1.length < 8) {
        showMessage("Password must be at least 8 characters", "danger");
        return;
    }
               // API FETCH //
    const data = await apiFetch("../api/change_password.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
       body: {
    password: password1
}
    });

    if (!data || data.error) {
        showMessage(data?.error || "Something went wrong", "danger");
    } else {
        showMessage("Password changed!", "success");

        // clear inputs
        document.getElementById("password1").value = "";
        document.getElementById("password2").value = "";
    }
}


// MESSAGE UI
function showMessage(message, type = "success") {
    const box = document.getElementById("messageBox");

    if (!box) return;

    box.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    window.scrollTo({ top: 0, behavior: "smooth" });

    setTimeout(() => {
        box.innerHTML = "";
    }, 3000);
}


//  NAVIGATION
function back() {
    window.location.href = "../html/dashboard.html";
}

async function logout() {
    // call server logout endpoint (stateless) then clear client state
    try {
        await apiFetch("../api/logout.php", { method: 'GET' });
    } catch (e) {
        // ignore
    }

    try { clearAuthToken(); } catch (e) {}
    try { clearConnectionId(); } catch (e) {}

    window.location.href = 'login.html';
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


//  INIT
document.getElementById("settingsForm").addEventListener("submit", function(e){
    e.preventDefault();
    change_password();
});



 // TOOLTIP //
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });